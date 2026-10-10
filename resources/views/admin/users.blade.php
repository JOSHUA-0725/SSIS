@extends('layouts.app')

@section('title', 'Users')

@section('actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add user</a>
@endsection

@section('content')
    @include('partials.search', ['placeholder' => 'Search username or name'])

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Username</th><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Last login</th><th scope="col"></th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->username }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->role->name }}</td>
                            <td>
                                @include('partials.status', ['status' => $user->status])
                                @if ($user->locked_until?->isFuture()) <span class="badge text-bg-warning">Locked</span> @endif
                            </td>
                            <td>{{ $user->last_login?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.users.reset', $user) }}" data-confirm="Reset the password of {{ $user->username }}? Any lockout is also removed.">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Reset password</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $users->links() }}
    <p class="small text-secondary">Every change on this page is written to the audit log.</p>
@endsection
