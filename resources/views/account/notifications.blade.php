@extends('layouts.app')

@section('title', 'Notifications')

@section('actions')
    <form method="POST" action="{{ route('notifications.read') }}">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm">Mark all as read</button>
    </form>
@endsection

@section('content')
    <div class="list-group mb-3">
        @forelse ($notifications as $notification)
            <div class="list-group-item {{ $notification->is_read ? '' : 'list-group-item-primary' }}">
                <div class="d-flex justify-content-between gap-3">
                    <span class="fw-semibold">{{ $notification->title }}</span>
                    <span class="small text-secondary text-nowrap">{{ $notification->created_at->format('M j, g:i A') }}</span>
                </div>
                <div>{{ $notification->message }}</div>
            </div>
        @empty
            <div class="list-group-item text-secondary">No notifications yet.</div>
        @endforelse
    </div>
    {{ $notifications->links() }}
@endsection
