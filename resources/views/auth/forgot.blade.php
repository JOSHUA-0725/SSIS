<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'Forgot password'])
</head>
<body>
<main class="guest">
    <div class="guest-box">
        <h1>Forgot password</h1>
        <p class="muted mb-4">Enter your username and the email on your school record. The MIS office will check the request and give you a temporary password.</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.send') }}">
            @csrf
            <div class="mb-4">
                <label for="username" class="form-label">Student number or username</label>
                <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" maxlength="30" required autofocus>
            </div>
            <div class="mb-4">
                <label for="email" class="form-label">Email on record</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" maxlength="120" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">Request a password reset</button>
        </form>

        <p class="mt-4"><a href="{{ route('login') }}" class="link-arrow">@include('partials.icon', ['name' => 'arrow-left']) Back to sign in</a></p>
    </div>
</main>
</body>
</html>
