<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Connect Accounts — Post Idea Rewriter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 640px;
            margin: 3rem auto;
            padding: 0 1.5rem;
            color: #1f2937;
        }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .back { font-size: 0.9rem; }
        .back a { color: #1d9bf0; }
        .status {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 8px;
        }
        .connected {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #ecfdf5;
            border-radius: 8px;
            color: #065f46;
        }
        label { display: block; margin-top: 1rem; font-size: 0.9rem; font-weight: 600; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.6rem 0.75rem;
            margin-top: 0.35rem;
            font: inherit;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-sizing: border-box;
        }
        .help {
            margin-top: 0.35rem;
            font-size: 0.85rem;
            color: #6b7280;
        }
        .help a { color: #1d9bf0; }
        button {
            margin-top: 1rem;
            margin-right: 0.5rem;
            padding: 0.6rem 1.25rem;
            background: #1d9bf0;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover { background: #1a8cd8; }
        button.secondary { background: #6b7280; }
        button.secondary:hover { background: #4b5563; }
        button.danger { background: #b91c1c; }
        button.danger:hover { background: #991b1b; }
        form { display: inline-block; }
        .errors { margin-top: 1rem; color: #b91c1c; }
    </style>
</head>
<body>
    <p class="back"><a href="{{ route('post-idea.create') }}">&larr; Back to post idea rewriter</a></p>
    <h1>Connect your Buffer accounts</h1>

    @if (session('status'))
        <div class="status"><p>{{ session('status') }}</p></div>
    @endif

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($connection)
        <div class="connected">
            @foreach ($services as $service => $label)
                @if ($connection->isConnected($service))
                    <p>✅ {{ $label }}: <strong>{{ $connection->channelFor($service)->channel_name }}</strong></p>
                @else
                    <p>⚠️ {{ $label }}: not connected yet — connect it on Buffer, then refresh.</p>
                @endif
            @endforeach
        </div>

        <a href="https://buffer.com" target="_blank" rel="noopener">
            <button type="button" class="secondary">Open Buffer</button>
        </a>
        <form method="POST" action="{{ route('buffer.refresh') }}">
            @csrf
            <button type="submit">Refresh</button>
        </form>
        <form method="POST" action="{{ route('buffer.disconnect') }}">
            @csrf
            <button type="submit" class="danger">Disconnect</button>
        </form>
    @else
        <p>Paste a personal Buffer API token to connect. Buffer doesn't yet support signing in directly from
        other apps, so this is the closest thing available today.</p>

        <form method="POST" action="{{ route('buffer.save-token') }}">
            @csrf
            <label for="access_token">Buffer API token</label>
            <input type="password" name="access_token" id="access_token" required autofocus>
            <p class="help">Find yours at <a href="https://buffer.com/app/settings/api" target="_blank" rel="noopener">Buffer &rarr; Settings &rarr; API</a>. Connect X and/or Facebook to your Buffer account first (or come back and refresh after you do).</p>

            <button type="submit">Save token</button>
        </form>
    @endif
</body>
</html>
