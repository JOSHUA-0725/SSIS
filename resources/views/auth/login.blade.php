<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => $portal['module']])
</head>
<body>
<main class="auth">
    <div class="auth-card">
        <a href="{{ route('login') }}" class="auth-back">@include('partials.icon', ['name' => 'arrow-left']) All portals</a>

        <span class="portal-icon portal-icon-lg">@include('partials.icon', ['name' => $portal['icon']])</span>
        <h1 class="portal-title">{{ $portal['module'] }}</h1>
        <p class="system">{{ $portal['about'] }}</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <input type="hidden" name="portal" value="{{ $key }}">

            <div class="mb-3">
                <label for="username" class="form-label">{{ $portal['field'] }}</label>
                <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" placeholder="{{ $portal['hint'] }}" maxlength="30" required autofocus autocomplete="username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100">
                Sign in
                @include('partials.icon', ['name' => 'arrow-right'])
            </button>
            <p class="text-center mt-3 mb-0"><a href="{{ route('password.request') }}" class="fw-medium">Forgot password?</a></p>
        </form>

        <p class="hint d-flex align-items-center gap-2 mt-3 mb-0">
            @include('partials.icon', ['name' => 'lock'])
            Accounts lock for {{ \App\Http\Controllers\AuthController::LOCK_MINUTES }} minutes after {{ \App\Http\Controllers\AuthController::MAX_ATTEMPTS }} failed attempts.
        </p>
    </div>
</main>
</body>
</html>
