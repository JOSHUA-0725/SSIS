<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'Sign in'])
</head>
<body>
@php $chosen = old('portal', 'student'); @endphp
<main class="auth">
    <div class="auth-card">
        <img class="auth-logo" src="{{ asset('images/pnc-logo.png') }}" alt="Pamantasan ng Cabuyao seal" width="96" height="96">
        <p class="school">Pamantasan ng Cabuyao</p>
        <p class="system">Student Services Information System</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <h1>Sign in</h1>

            <div class="mb-3">
                <label for="portal" class="form-label">I am signing in as</label>
                <select class="form-select" id="portal" name="portal">
                    @foreach ($portals as $key => $portal)
                        <option value="{{ $key }}" @selected($chosen === $key)
                                data-field="{{ $portal['field'] }}" data-hint="{{ $portal['hint'] }}" data-module="{{ $portal['module'] }}">{{ $portal['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label" data-portal-label>{{ $portals[$chosen]['field'] }}</label>
                <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" placeholder="{{ $portals[$chosen]['hint'] }}" maxlength="30" required autofocus autocomplete="username">
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100">
                <span data-portal-button>Sign in to {{ $portals[$chosen]['module'] }}</span>
                @include('partials.icon', ['name' => 'arrow-right'])
            </button>
            <p class="text-center mt-3 mb-0"><a href="{{ route('password.request') }}" class="fw-medium">Forgot password?</a></p>
        </form>

        <p class="hint d-flex align-items-center gap-2 mt-3 mb-0">
            @include('partials.icon', ['name' => 'lock'])
            Accounts lock for {{ \App\Http\Controllers\AuthController::LOCK_MINUTES }} minutes after {{ \App\Http\Controllers\AuthController::MAX_ATTEMPTS }} failed attempts.
        </p>

        <hr class="rule">

        <a href="{{ route('documentation') }}" class="doc">
            @include('partials.icon', ['name' => 'book'])
            <span class="flex-grow-1">
                <span class="doc-title fw-semibold d-block">System documentation</span>
                <span class="hint">SDLC plan, modeling, storyboard, database, and test plan</span>
            </span>
            @include('partials.icon', ['name' => 'arrow-right'])
        </a>
    </div>
</main>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
