<?php

namespace App\Http\Controllers;

use App\Models\BufferConnection;
use App\Services\BufferClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BufferConnectionController extends Controller
{
    private const SERVICES = ['twitter' => 'X', 'facebook' => 'Facebook'];

    public function show(Request $request): View
    {
        return view('buffer.connect', [
            'connection' => $request->user()->bufferConnection,
            'services' => self::SERVICES,
        ]);
    }

    public function saveToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $channels = (new BufferClient($validated['access_token']))->findChannels();

        $connection = BufferConnection::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['access_token' => $validated['access_token']]
        );

        $this->syncChannels($connection, $channels);

        return redirect()->route('buffer.show')->with('status', $this->statusMessage($channels));
    }

    public function refresh(Request $request): RedirectResponse
    {
        $connection = $request->user()->bufferConnection;

        if (! $connection) {
            return redirect()->route('buffer.show')->with('status', '❌ Save your Buffer token first.');
        }

        $channels = (new BufferClient($connection->access_token))->findChannels();
        $this->syncChannels($connection, $channels);

        return redirect()->route('buffer.show')->with('status', $this->statusMessage($channels));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->bufferConnection?->delete();

        return redirect()->route('buffer.show')
            ->with('status', "Disconnected. Your token was removed from this app — revoke it in Buffer's own dashboard too if you want it fully invalidated.");
    }

    /**
     * @param  array<string, array{id: string, name: string}>  $channels
     */
    private function syncChannels(BufferConnection $connection, array $channels): void
    {
        foreach (array_keys(self::SERVICES) as $service) {
            if (isset($channels[$service])) {
                $connection->channels()->updateOrCreate(
                    ['service' => $service],
                    ['channel_id' => $channels[$service]['id'], 'channel_name' => $channels[$service]['name']]
                );
            } else {
                $connection->channels()->where('service', $service)->delete();
            }
        }
    }

    /**
     * @param  array<string, array{id: string, name: string}>  $channels
     */
    private function statusMessage(array $channels): string
    {
        if (empty($channels)) {
            return '⚠️ Token saved, but no connected channels found yet — connect X and/or Facebook on Buffer, then refresh below.';
        }

        $found = [];
        foreach (self::SERVICES as $service => $label) {
            if (isset($channels[$service])) {
                $found[] = "{$label} \"{$channels[$service]['name']}\"";
            }
        }

        return '✅ Connected to '.implode(' and ', $found).'.';
    }
}
