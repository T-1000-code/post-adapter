<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register — Post Idea Rewriter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, sans-serif;
            max-width: 420px;
            margin: 4rem auto;
            padding: 0 1.5rem;
            color: #1f2937;
        }
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; }
        label { display: block; margin-top: 1rem; font-size: 0.9rem; font-weight: 600; }
        input[type="email"], input[type="password"], input[type="text"] {
            width: 100%;
            padding: 0.6rem 0.75rem;
            margin-top: 0.35rem;
            font: inherit;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-sizing: border-box;
        }
        button {
            margin-top: 1.5rem;
            padding: 0.6rem 1.25rem;
            background: #1d9bf0;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
        }
        button:hover { background: #1a8cd8; }
        .errors { margin-top: 1rem; color: #b91c1c; }
        .switch { margin-top: 1.5rem; font-size: 0.9rem; }
        .switch a { color: #1d9bf0; }
    </style>
</head>
<body>
    <h1>Create an account</h1>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label for="name">Name</label>
        <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus>

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required>

        <label for="password">Password</label>
        <input type="password" name="password" id="password" required>

        <label for="password_confirmation">Confirm password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" required>

        <button type="submit">Register</button>
    </form>

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <p class="switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</body>
</html>
