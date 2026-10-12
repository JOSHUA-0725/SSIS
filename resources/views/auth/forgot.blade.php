<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'Forgot password'])
</head>
<body>
<main class="auth">
    <div class="auth-card">
        <img class="auth-logo" src="{{ asset('images/ssis-logo.png') }}" alt="Student Services Information System seal" width="96" height="96">
        <p class="school mb-4">Student Services Information System</p>

        <h1>Forgot password</h1>
        <p class="hint mb-3">Enter your username and the email on your school record. The MIS office will check the request and give you a temporary password.</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.send') }}">
            @csrf
            <div class="mb-3">
                <label for="username" class="form-label">Student number or username</label>
                <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" maxlength="30" required autofocus>
            </div>
            <div class="mb-4">
                <label for="email" class="form-label">Email on record</label>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" maxlength="120" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">Request a password reset</button>
        </form>

        <hr class="rule">
        <a href="{{ route('login') }}" class="doc">@include('partials.icon', ['name' => 'arrow-left']) <span class="fw-semibold">Back to sign in</span></a>
    </div>
</main>
</body>
</html>
