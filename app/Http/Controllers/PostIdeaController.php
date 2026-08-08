<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PostIdeaController extends Controller
{
    /**
     * Platforms whose posts are rejected by Buffer if no media is attached.
     */
    private const MEDIA_REQUIRED_PLATFORMS = ['instagram', 'tiktok'];

    private const PLATFORM_LABELS = [
        'x' => 'X',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
    ];

    public function create(): View
    {
        return view('post-idea', [
            'idea' => old('idea', ''),
            'result' => session('result'),
            'platforms' => session('platforms', []),
            'mediaPath' => session('mediaPath'),
            'mediaType' => session('mediaType'),
            'bufferResults' => session('bufferResults', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idea' => ['required', 'string', 'max:2000'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['in:facebook,instagram,tiktok,x'],
            'media' => ['nullable', 'file', 'max:51200'],
        ]);

        $platforms = $validated['platforms'];
        $needsImage = count(array_intersect($platforms, ['facebook', 'instagram'])) > 0;
        $needsVideo = in_array('tiktok', $platforms, true);

        if ($needsImage && $needsVideo) {
            return back()
                ->withInput()
                ->withErrors(['media' => 'TikTok requires a video, but Facebook/Instagram require an image — a single upload can\'t satisfy both. Uncheck one of these platforms, or publish them separately.']);
        }

        if ($request->hasFile('media')) {
            $mimeRule = match (true) {
                $needsVideo => 'mimes:mp4',
                $needsImage => 'mimes:jpg,jpeg,png',
                default => 'mimes:jpg,jpeg,png,mp4',
            };

            $request->validate([
                'media' => ['file', $mimeRule],
            ]);
        }

        $response = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini.key'),
        ])->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model()}:generateContent",
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->prompt($validated['idea'])],
                        ],
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            return back()
                ->withInput()
                ->withErrors(['idea' => 'Gemini request failed: '.$response->json('error.message', $response->body())]);
        }

        $rewritten = data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        $mediaPath = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaPath = $file->store('media', 'public');
            $mediaType = strtolower($file->getClientOriginalExtension()) === 'mp4' ? 'video' : 'image';
        }

        return back()
            ->withInput()
            ->with('result', trim($rewritten))
            ->with('platforms', $platforms)
            ->with('mediaPath', $mediaPath)
            ->with('mediaType', $mediaType);
    }

    public function postToBuffer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:280'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['in:facebook,instagram,tiktok,x'],
            'media_path' => ['nullable', 'string'],
            'media_type' => ['nullable', 'in:image,video'],
        ]);

        $mediaPath = $validated['media_path'] ?? null;
        $mediaType = $validated['media_type'] ?? null;

        $assetUrl = null;
        if ($mediaPath && config('services.buffer.public_media_url')) {
            $assetUrl = rtrim(config('services.buffer.public_media_url'), '/').Storage::url($mediaPath);
        }

        $results = [];

        foreach ($validated['platforms'] as $platform) {
            $label = self::PLATFORM_LABELS[$platform];

            if (in_array($platform, self::MEDIA_REQUIRED_PLATFORMS, true) && ! $mediaPath) {
                $results[] = "❌ {$label} requires an image or video — none uploaded.";

                continue;
            }

            $channelId = config("services.buffer.channels.{$platform}");

            if (! $channelId) {
                $results[] = "❌ {$label} channel not connected in Buffer.";

                continue;
            }

            $assets = [];
            if ($assetUrl) {
                $assets[] = $mediaType === 'video'
                    ? ['video' => ['url' => $assetUrl]]
                    : ['image' => ['url' => $assetUrl]];
            }

            $mutation = <<<'GQL'
            mutation CreatePost($text: String!, $channelId: ChannelId!, $assets: [AssetInput!]!) {
              createPost(input: {
                text: $text
                channelId: $channelId
                schedulingType: automatic
                mode: shareNow
                needsApproval: false
                assets: $assets
              }) {
                ... on PostActionSuccess {
                  post { id status }
                }
                ... on MutationError {
                  message
                }
              }
            }
            GQL;

            $response = Http::withToken(config('services.buffer.token'))
                ->post('https://api.buffer.com', [
                    'query' => $mutation,
                    'variables' => [
                        'text' => $validated['text'],
                        'channelId' => $channelId,
                        'assets' => $assets,
                    ],
                ]);

            $errorMessage = $response->json('errors.0.message')
                ?? data_get($response->json(), 'data.createPost.message');

            if ($response->failed() || $errorMessage) {
                $results[] = "❌ {$label}: ".($errorMessage ?? $response->body());

                continue;
            }

            $results[] = "✅ Posted to {$label}.";
        }

        return back()
            ->with('result', $validated['text'])
            ->with('platforms', $validated['platforms'])
            ->with('mediaPath', $mediaPath)
            ->with('mediaType', $mediaType)
            ->with('bufferResults', $results);
    }

    private function prompt(string $idea): string
    {
        return <<<PROMPT
        Rewrite the following rough idea as a single punchy, engaging post for X (Twitter).
        Keep it under 280 characters. No hashtags unless essential. No surrounding quotation marks.
        Return only the rewritten post text, nothing else.

        Idea: {$idea}
        PROMPT;
    }

    private function model(): string
    {
        return config('services.gemini.model');
    }
}
