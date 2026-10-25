@extends('layouts.app')

@section('title', 'Activity log')
@section('lead', 'Sign-ins, failed attempts, and everything done with your account and records. If you see something you did not do, change your password and tell the Registrar.')

@section('content')
    <div class="card mb-4">
        <div class="summary">
            <div>
                <div class="stat-label">Last sign-in</div>
                <div class="value">{{ $lastLogin ? $lastLogin->created_at->format('M j, Y g:i A') : '—' }}</div>
                @if ($lastLogin)
                    <div class="muted small">from {{ $lastLogin->ip_address ?? 'unknown address' }}</div>
                @endif
            </div>
            <div>
                <div class="stat-label">Wrong passwords, last 30 days</div>
                <div class="value">{{ $failed }}</div>
            </div>
            <div>
                <div class="stat-label">Times locked, last 30 days</div>
                <div class="value">{{ $locked }}</div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach (['all' => 'All activity', 'security' => 'Sign-in and security', 'records' => 'Requests and enrollment'] as $value => $label)
            <li class="nav-item">
                <a class="nav-link {{ $filter === $value ? 'active' : '' }}" href="{{ route('student.activity', ['show' => $value]) }}" @if ($filter === $value) aria-current="page" @endif>{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">When</th><th scope="col">Activity</th><th scope="col">Done by</th><th scope="col">IP address</th></tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap muted">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                            <td>
                                <span class="{{ in_array($log->action, ['LOGIN_FAILED', 'LOGIN_LOCKED', 'ACCOUNT_LOCKED', 'ACCESS_DENIED'], true) ? 'text-danger fw-medium' : '' }}">{{ $log->describe() }}</span>
                            </td>
                            <td>{{ $log->user_id === $user->id ? 'You' : ($log->user?->name ?? 'System') }}</td>
                            <td class="muted text-nowrap">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $logs->links() }}
@endsection
