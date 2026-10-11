<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => 'Sign in'])
</head>
<body>
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

        <h1>Choose your portal</h1>

        <nav class="portal-list" aria-label="Portals">
            @foreach ($portals as $key => $portal)
                <a href="{{ route('login.portal', $key) }}" class="portal-tile">
                    <span class="portal-icon">@include('partials.icon', ['name' => $portal['icon']])</span>
                    <span class="flex-grow-1">
                        <span class="portal-name">{{ $portal['label'] }}</span>
                        <span class="hint d-block">{{ $portal['about'] }}</span>
                    </span>
                    @include('partials.icon', ['name' => 'arrow-right'])
                </a>
            @endforeach
        </nav>
    </div>
</main>
</body>
</html>
