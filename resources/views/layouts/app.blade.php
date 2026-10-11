<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => trim(html_entity_decode(strip_tags($__env->yieldContent('title', 'Overview')), ENT_QUOTES))])
    {{-- Applies the saved open or closed sidebar before the page paints. --}}
    <script src="{{ asset('js/boot.js') }}"></script>
</head>
<body data-live-url="{{ route('live') }}">
@php
    $user = auth()->user();
    $role = $user->roleName();
    $student = $role === 'Student' ? $user->student : null;

    // [label, route, icon, badge]
    $menus = [
        'Student' => ['Student Portal', [
            ['Overview', 'dashboard', 'grid'], ['Clearance', 'student.clearance', 'clipboard'], ['Grades', 'student.grades', 'book-open'],
            ['Document requests', 'student.requests', 'file'],
            ['Payments', 'student.payments', 'wallet', $student ? \App\Services\LedgerService::payables($student)->where('payable', '>', 0)->count() : 0],
        ]],
        'Registrar' => ['Registrar Module', [
            ['Overview', 'dashboard', 'grid'], ['Students', 'registrar.students', 'users'], ['Terms and grade release', 'registrar.terms', 'calendar'],
            ['Document requests', 'registrar.requests', 'file', \App\Models\DocumentRequest::where('status', 'Paid')->count()], ['Clearance', 'clearance.index', 'clipboard'],
        ]],
        'Cashier' => ['Cashier Module', [
            ['Overview', 'dashboard', 'grid'], ['Post payment', 'cashier.payments', 'wallet'],
            ['Verify payments', 'cashier.proofs', 'receipt', \App\Models\Payment::where('status', 'For Verification')->count()],
            ['Clearance', 'clearance.index', 'clipboard'], ['Collection report', 'cashier.report', 'chart'],
        ]],
        'Faculty' => ['Department Module', [['Overview', 'dashboard', 'grid'], ['My classes', 'faculty.classes', 'book-open']]],
        'Department Head' => ['Department Module', [
            ['Overview', 'dashboard', 'grid'], ['Grade sheets', 'head.sheets', 'book-open'], ['Clearance', 'clearance.index', 'clipboard'],
        ]],
        'Signatory' => ['Department Module', [['Overview', 'dashboard', 'grid'], ['Clearance', 'clearance.index', 'clipboard']]],
        'Administrator' => ['Admin Module', [
            ['Overview', 'dashboard', 'grid'], ['Users', 'admin.users', 'users'], ['Document types and fees', 'admin.doctypes', 'tag'], ['Audit log', 'admin.audit', 'list'],
        ]],
    ];
    [$module, $menu] = $menus[$role] ?? ['SSIS', []];

    $unread = \App\Models\Notification::where('user_id', $user->id)->where('is_read', false)->count();
    $initials = collect(preg_split('/\s+/', preg_replace('/^(Prof|Dr|Engr|Atty|Mr|Ms|Mrs)\.?\s+/i', '', $user->name)))
        ->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)));
    $initials = $initials->first().($initials->count() > 1 ? $initials->last() : '');
    $sub = $student ? "{$student->program->code} {$student->year_level}" : ($user->office?->name ?? $user->department?->code ?? $role);
    $link = fn (string $name) => request()->routeIs($name.'*') ? 'active' : '';
@endphp

<header class="topbar d-print-none">
    <button type="button" class="icon-btn" data-sidebar-toggle aria-controls="sidebar" aria-label="Open or close the menu">@include('partials.icon', ['name' => 'menu'])</button>
    <a class="topbar-brand" href="{{ route('dashboard') }}">
        <img class="brand-logo" src="{{ asset('images/pnc-logo.png') }}" alt="" width="40" height="40">
        <span>
            <span class="brand-name d-block">Pamantasan ng Cabuyao</span>
            <span class="brand-sub">University of Cabuyao · Student Services Information System</span>
        </span>
    </a>
    <div class="topbar-id">
        <span class="label">{{ $student ? 'Student ID' : 'User ID' }}</span>
        <span class="value">{{ $student?->student_no ?? $user->username }}</span>
    </div>
</header>

<aside class="side d-print-none" id="sidebar">
    <div class="side-module">{{ $module }}</div>

    <nav aria-label="Main">
        <ul class="side-nav" data-live="nav">
            @foreach ($menu as $item)
                <li>
                    <a class="{{ $link($item[1]) }}" href="{{ route($item[1]) }}" @if ($link($item[1])) aria-current="page" @endif>
                        @include('partials.icon', ['name' => $item[2]])
                        {{ $item[0] }}
                        @if (! empty($item[3])) <span class="nav-badge">{{ $item[3] }}</span> @endif
                    </a>
                </li>
            @endforeach
            <li><hr class="side-rule"></li>
            <li>
                <a class="{{ $link('notifications') }}" href="{{ route('notifications') }}">
                    @include('partials.icon', ['name' => 'bell'])
                    Notifications
                    @if ($unread) <span class="nav-badge">{{ $unread }}</span> @endif
                </a>
            </li>
        </ul>
    </nav>

    <div class="side-user">
        <span class="avatar" aria-hidden="true">{{ $initials }}</span>
        <a href="{{ route('profile') }}" title="Profile and password">
            <div class="name">{{ $user->name }}</div>
            <div class="sub">{{ $sub }}</div>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="icon-btn" aria-label="Log out" title="Log out">@include('partials.icon', ['name' => 'logout'])</button>
        </form>
    </div>
</aside>
<div class="scrim" data-sidebar-toggle></div>

<main class="main">
    <div class="page" data-live="page">
        <div class="page-head">
            <div>
                @hasSection('eyebrow') <div class="eyebrow">@yield('eyebrow')</div> @endif
                <h1>@yield('title', 'Overview')</h1>
                @hasSection('lead') <p class="lead-text">@yield('lead')</p> @endif
            </div>
            @hasSection('actions') <div class="page-actions">@yield('actions')</div> @endif
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status" data-flash>{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert" data-errors>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </div>
</main>

<div class="live-toast d-print-none" data-live-toast hidden>
    <span>New updates are available.</span>
    <button type="button" class="btn btn-sm btn-light" data-live-refresh>Show updates</button>
</div>

<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
