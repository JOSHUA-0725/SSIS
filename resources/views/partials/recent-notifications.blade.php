<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent notifications</span>
        <a href="{{ route('notifications') }}" class="link-arrow small">View all @include('partials.icon', ['name' => 'arrow-right'])</a>
    </div>
    <ul class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            <li class="list-group-item">
                <div class="fw-semibold">{{ $notification->title }}</div>
                <div>{{ $notification->message }}</div>
                <div class="small text-secondary">{{ $notification->created_at->diffForHumans() }}</div>
            </li>
        @empty
            <li class="list-group-item text-secondary">No notifications yet.</li>
        @endforelse
    </ul>
</div>
