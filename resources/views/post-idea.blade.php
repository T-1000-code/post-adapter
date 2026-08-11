<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Post Idea Rewriter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 640px;
            margin: 3rem auto;
            padding: 0 1.5rem;
            color: #1f2937;
        }
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; }
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 0.75rem;
            font: inherit;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            resize: vertical;
        }
        button {
            margin-top: 0.75rem;
            padding: 0.6rem 1.25rem;
            background: #1d9bf0;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover { background: #1a8cd8; }
        .result {
            margin-top: 2rem;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 8px;
            white-space: pre-wrap;
        }
        .result + .result {
            margin-top: 0.75rem;
        }
        .result .thread-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.35rem;
        }
        .errors {
            margin-top: 1rem;
            color: #b91c1c;
        }
        .success {
            margin-top: 1rem;
            color: #15803d;
        }
        .buffer-btn {
            background: #2c4bff;
            margin-top: 1rem;
        }
        .buffer-btn:hover { background: #1f39cc; }
        .media-field {
            margin-top: 1rem;
        }
        .schedule-field {
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
        }
        .schedule-field label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .buffer-result {
            margin-top: 1rem;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 8px;
        }
        .media-note {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #4b5563;
        }
        .account-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        .account-bar a { color: #1d9bf0; }
        .account-bar button {
            margin: 0;
            padding: 0;
            background: none;
            border: none;
            color: #6b7280;
            font-size: 0.85rem;
            text-decoration: underline;
            cursor: pointer;
        }
        .account-bar .connect-warn { color: #b91c1c; }
    </style>
</head>
<body>
    <div class="account-bar">
        <span>
            {{ auth()->user()->email }} &middot;
            @if ($bufferConnection && $bufferConnection->isConnected())
                <a href="{{ route('buffer.show') }}">X: {{ $bufferConnection->channel_name }}</a>
            @else
                <a href="{{ route('buffer.show') }}" class="connect-warn">Connect your X account</a>
            @endif
        </span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </div>

    <h1>Rewrite a post idea for X</h1>

    <form method="POST" action="{{ route('post-idea.store') }}" enctype="multipart/form-data">
        @csrf
        <textarea name="idea" placeholder="Type your rough post idea here...">{{ $idea }}</textarea>

        <div class="media-field">
            <label for="media-input">Image or video (optional):</label><br>
            <input type="file" name="media" id="media-input" accept="image/jpeg,image/png,video/mp4">
        </div>

        <div>
            <button type="submit">Rewrite idea</button>
        </div>
    </form>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (! empty($posts))
        @foreach ($posts as $index => $post)
            <div class="result">
                @if (count($posts) > 1)
                    <span class="thread-label">Post {{ $index + 1 }} of {{ count($posts) }}</span>
                @else
                    <strong>Rewritten post:</strong>
                @endif
                <p>{{ $post }}</p>
            </div>
        @endforeach

        @if (! empty($mediaPath))
            <p class="media-note">Attached {{ $mediaType }} (first post only): {{ basename($mediaPath) }}</p>
        @endif

        <form method="POST" action="{{ route('post-idea.buffer') }}">
            @csrf
            @foreach ($posts as $post)
                <input type="hidden" name="posts[]" value="{{ $post }}">
            @endforeach
            @if (! empty($mediaPath))
                <input type="hidden" name="media_path" value="{{ $mediaPath }}">
                <input type="hidden" name="media_type" value="{{ $mediaType }}">
            @endif

            <div class="schedule-field">
                <label>
                    <input type="radio" name="schedule_choice" value="now" id="schedule-now" checked>
                    Post now
                </label>
                <label>
                    <input type="radio" name="schedule_choice" value="later" id="schedule-later">
                    Schedule for later
                </label>
                <input type="datetime-local" name="scheduled_at" id="scheduled-at" disabled>
            </div>

            @error('scheduled_at')
                <div class="errors"><p>{{ $message }}</p></div>
            @enderror

            <button type="submit" class="buffer-btn">{{ count($posts) > 1 ? 'Post thread to X via Buffer' : 'Post to X via Buffer' }}</button>
        </form>

        @if (! empty($bufferResult))
            <div class="buffer-result">
                <p>{{ $bufferResult }}</p>
            </div>
        @endif
    @endif

    <script>
        (function () {
            var scheduledAtInput = document.getElementById('scheduled-at');
            var nowRadio = document.getElementById('schedule-now');
            var laterRadio = document.getElementById('schedule-later');

            if (!scheduledAtInput) {
                return;
            }

            var minLocal = new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
                .toISOString()
                .slice(0, 16);
            scheduledAtInput.min = minLocal;

            function refresh() {
                scheduledAtInput.disabled = !laterRadio.checked;
                scheduledAtInput.required = laterRadio.checked;
            }

            nowRadio.addEventListener('change', refresh);
            laterRadio.addEventListener('change', refresh);
            refresh();
        })();
    </script>
</body>
</html>
