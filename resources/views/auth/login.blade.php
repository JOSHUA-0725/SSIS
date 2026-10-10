<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'Sign in'])
</head>
<body>
@php $chosen = old('portal', 'student'); @endphp
<main class="guest">
    <div class="guest-box">
        <h1>Sign in</h1>
        <p class="muted mb-4">Choose how you use SSIS, then sign in with your school credentials.</p>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <fieldset class="mb-4">
                <legend class="form-label">I am signing in as</legend>
                <div class="portals">
                    @foreach ($portals as $key => $portal)
                        <label class="choice">
                            <input type="radio" name="portal" value="{{ $key }}" @checked($chosen === $key)
                                   data-field="{{ $portal['field'] }}" data-hint="{{ $portal['hint'] }}" data-module="{{ $portal['module'] }}">
                            <span class="box">@include('partials.icon', ['name' => $portal['icon']]) {{ $portal['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="mb-4">
                <label for="username" class="form-label" data-portal-label>{{ $portals[$chosen]['field'] }}</label>
                <input type="text" class="form-control" id="username" name="username" value="{{ old('username') }}" placeholder="{{ $portals[$chosen]['hint'] }}" maxlength="30" required autofocus autocomplete="username">
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-baseline">
                    <label for="password" class="form-label">Password</label>
                    <a href="{{ route('password.request') }}" class="small fw-medium text-decoration-none">Forgot password?</a>
                </div>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100">
                <span data-portal-button>Sign in to {{ $portals[$chosen]['module'] }}</span>
                @include('partials.icon', ['name' => 'arrow-right'])
            </button>
        </form>

        <p class="small muted d-flex align-items-center gap-2 mt-4 mb-5">
            @include('partials.icon', ['name' => 'lock'])
            Accounts lock for {{ \App\Http\Controllers\AuthController::LOCK_MINUTES }} minutes after {{ \App\Http\Controllers\AuthController::MAX_ATTEMPTS }} failed attempts.
        </p>

        <a href="{{ route('documentation') }}" class="panel doc-link">
            @include('partials.icon', ['name' => 'book'])
            <span class="flex-grow-1">
                <span class="fw-semibold d-block">System documentation</span>
                <span class="small muted">SDLC plan, modeling, storyboard, database, and test plan</span>
            </span>
            @include('partials.icon', ['name' => 'arrow-right'])
        </a>
    </div>
</main>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
