<?php

namespace App\Http\Controllers;

use App\Services\BufferClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PostIdeaController extends Controller
{
    public function create(Request $request): View
    {
        return view('post-idea', [
            'idea' => old('idea', ''),
            'posts' => session('posts', []),
            'mediaPath' => session('mediaPath'),
            'mediaType' => session('mediaType'),
            'bufferResult' => session('bufferResult'),
            'bufferConnection' => $request->user()->bufferConnection,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idea' => ['required', 'string', 'max:2000'],
            'media' => ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,mp4'],
        ]);

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
        $posts = $this->parsePosts($rewritten);

        $mediaPath = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaPath = $file->store('media', 'public');
            $mediaType = strtolower($file->getClientOriginalExtension()) === 'mp4' ? 'video' : 'image';
        }

        return back()
            ->withInput()
            ->with('posts', $posts)
            ->with('mediaPath', $mediaPath)
            ->with('mediaType', $mediaType);
    }

    public function postToBuffer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'posts' => ['required', 'array', 'min:1', 'max:25'],
            'posts.*' => ['required', 'string', 'max:280'],
            'media_path' => ['nullable', 'string'],
            'media_type' => ['nullable', 'in:image,video'],
            'schedule_choice' => ['required', 'in:now,later'],
            'scheduled_at' => ['required_if:schedule_choice,later', 'nullable', 'date', 'after:now'],
        ]);

        $posts = array_values($validated['posts']);
        $mediaPath = $validated['media_path'] ?? null;
        $mediaType = $validated['media_type'] ?? null;
        $scheduledAt = $validated['schedule_choice'] === 'later' ? $validated['scheduled_at'] : null;

        $connection = $request->user()->bufferConnection;

        if (! $connection || ! $connection->isConnected()) {
            return redirect()
                ->route('buffer.show')
                ->with('bufferResult', '❌ Connect your X account before publishing.');
        }

        $assets = [];
        if ($mediaPath && config('services.buffer.public_media_url')) {
            $assetUrl = rtrim(config('services.buffer.public_media_url'), '/').Storage::url($mediaPath);
            $assets[] = $mediaType === 'video'
                ? ['video' => ['url' => $assetUrl]]
                : ['image' => ['url' => $assetUrl]];
        }

        $metadata = null;
        if (count($posts) > 1) {
            $metadata = [
                'twitter' => [
                    'thread' => array_map(
                        fn (string $text) => ['text' => $text, 'assets' => []],
                        array_slice($posts, 1)
                    ),
                ],
            ];
        }

        $result = (new BufferClient($connection->access_token))->createPost([
            'text' => $posts[0],
            'channelId' => $connection->channel_id,
            'assets' => $assets,
            'mode' => $scheduledAt ? 'customScheduled' : 'shareNow',
            'dueAt' => $scheduledAt ? Carbon::parse($scheduledAt)->toIso8601String() : null,
            'metadata' => $metadata,
        ]);

        if (! $result['success']) {
            $bufferResult = '❌ '.$result['error'];
        } else {
            $label = count($posts) > 1 ? 'thread ('.count($posts).' posts)' : 'post';
            $bufferResult = $scheduledAt
                ? "✅ Scheduled {$label} for ".Carbon::parse($scheduledAt)->format('M j, Y g:i A').'.'
                : "✅ Posted {$label} to X.";
        }

        return back()
            ->with('posts', $posts)
            ->with('mediaPath', $mediaPath)
            ->with('mediaType', $mediaType)
            ->with('bufferResult', $bufferResult);
    }

    private function prompt(string $idea): string
    {
        return <<<PROMPT
        Rewrite the following rough idea as one or more punchy, engaging posts for X (Twitter).
        Each post must stay under 280 characters. No hashtags unless essential. No surrounding quotation marks.

        If the idea fits naturally in a single post, return exactly one. If it's substantial enough to
        benefit from a thread, break it into multiple connected posts that each stand well on their own
        but flow into the next. When there is more than one post, prefix each with its position like "1/3".

        Respond with ONLY valid JSON in this exact shape, no markdown code fences, nothing else:
        {"posts": ["first post text", "second post text"]}

        Idea: {$idea}
        PROMPT;
    }

    private function parsePosts(string $raw): array
    {
        $cleaned = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($raw)));
        $decoded = json_decode($cleaned, true);

        if (is_array($decoded) && ! empty($decoded['posts']) && is_array($decoded['posts'])) {
            $posts = array_values(array_filter(array_map('trim', $decoded['posts'])));

            if (! empty($posts)) {
                return $posts;
            }
        }

        return [trim($raw)];
    }

    private function model(): string
    {
        return config('services.gemini.model');
    }
}
