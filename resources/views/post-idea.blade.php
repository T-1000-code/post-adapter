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
        .platforms {
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .platforms label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .warn {
            color: #b91c1c;
            font-size: 0.8rem;
        }
        .media-field {
            margin-top: 1rem;
        }
        .buffer-results {
            margin-top: 1rem;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 8px;
        }
        .buffer-results p { margin: 0.25rem 0; }
        .media-note {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <h1>Rewrite a post idea for X</h1>

    @php
        $selectedPlatforms = old('platforms', $platforms ?? []);
    @endphp

    <form method="POST" action="{{ route('post-idea.store') }}" enctype="multipart/form-data">
        @csrf
        <textarea name="idea" placeholder="Type your rough post idea here...">{{ $idea }}</textarea>

        <div class="platforms">
            <label>
                <input type="checkbox" name="platforms[]" value="x" id="platform-x" {{ in_array('x', $selectedPlatforms) ? 'checked' : '' }}>
                X
            </label>
            <label>
                <input type="checkbox" name="platforms[]" value="facebook" id="platform-facebook" {{ in_array('facebook', $selectedPlatforms) ? 'checked' : '' }}>
                Facebook
            </label>
            <label>
                <input type="checkbox" name="platforms[]" value="instagram" id="platform-instagram" {{ in_array('instagram', $selectedPlatforms) ? 'checked' : '' }}>
                Instagram
                <span class="warn" id="warn-instagram"></span>
            </label>
            <label>
                <input type="checkbox" name="platforms[]" value="tiktok" id="platform-tiktok" {{ in_array('tiktok', $selectedPlatforms) ? 'checked' : '' }}>
                TikTok
                <span class="warn" id="warn-tiktok"></span>
            </label>
        </div>

        <div class="media-field">
            <label for="media-input">Image or video (required for Instagram/TikTok):</label><br>
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

    @if ($result)
        <div class="result">
            <strong>Rewritten post:</strong>
            <p>{{ $result }}</p>
        </div>

        @if (! empty($mediaPath))
            <p class="media-note">Attached {{ $mediaType }}: {{ basename($mediaPath) }}</p>
        @endif

        <form method="POST" action="{{ route('post-idea.buffer') }}">
            @csrf
            <input type="hidden" name="text" value="{{ $result }}">
            @foreach ($platforms ?? [] as $platform)
                <input type="hidden" name="platforms[]" value="{{ $platform }}">
            @endforeach
            @if (! empty($mediaPath))
                <input type="hidden" name="media_path" value="{{ $mediaPath }}">
                <input type="hidden" name="media_type" value="{{ $mediaType }}">
            @endif
            <button type="submit" class="buffer-btn">Post to selected platforms via Buffer</button>
        </form>

        @if (! empty($bufferResults))
            <div class="buffer-results">
                @foreach ($bufferResults as $line)
                    <p>{{ $line }}</p>
                @endforeach
            </div>
        @endif
    @endif

    <script>
        (function () {
            var mediaInput = document.getElementById('media-input');
            var watched = ['instagram', 'tiktok'];

            function refreshWarnings() {
                var hasFile = mediaInput.files && mediaInput.files.length > 0;

                watched.forEach(function (platform) {
                    var checkbox = document.getElementById('platform-' + platform);
                    var warning = document.getElementById('warn-' + platform);

                    if (checkbox.checked && !hasFile) {
                        warning.textContent = '(requires image/video — none selected)';
                    } else {
                        warning.textContent = '';
                    }
                });
            }

            mediaInput.addEventListener('change', refreshWarnings);
            watched.forEach(function (platform) {
                document.getElementById('platform-' + platform).addEventListener('change', refreshWarnings);
            });

            refreshWarnings();
        })();
    </script>
</body>
</html>
