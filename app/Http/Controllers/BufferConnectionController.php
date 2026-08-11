<?php

namespace App\Http\Controllers;

use App\Models\BufferConnection;
use App\Services\BufferClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BufferConnectionController extends Controller
{
    public function show(Request $request): View
    {
        return view('buffer.connect', [
            'connection' => $request->user()->bufferConnection,
        ]);
    }

    public function saveToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $channel = (new BufferClient($validated['access_token']))->findTwitterChannel();

        $connection = BufferConnection::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'access_token' => $validated['access_token'],
                'channel_id' => $channel['id'] ?? null,
                'channel_name' => $channel['name'] ?? null,
            ]
        );

        return redirect()->route('buffer.show')->with('status', $connection->isConnected()
            ? "✅ Connected to X channel \"{$connection->channel_name}\"."
            : '⚠️ Token saved, but no X channel found yet — connect X on Buffer, then refresh below.');
    }

    public function refresh(Request $request): RedirectResponse
    {
        $connection = $request->user()->bufferConnection;

        if (! $connection) {
            return redirect()->route('buffer.show')->with('status', '❌ Save your Buffer token first.');
        }

        $channel = (new BufferClient($connection->access_token))->findTwitterChannel();

        $connection->update([
            'channel_id' => $channel['id'] ?? null,
            'channel_name' => $channel['name'] ?? null,
        ]);

        return redirect()->route('buffer.show')->with('status', $connection->isConnected()
            ? "✅ Connected to X channel \"{$connection->channel_name}\"."
            : '⚠️ Still no X channel found — make sure you connected X on Buffer, then refresh again.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->bufferConnection?->delete();

        return redirect()->route('buffer.show')
            ->with('status', "Disconnected. Your token was removed from this app — revoke it in Buffer's own dashboard too if you want it fully invalidated.");
    }
}
