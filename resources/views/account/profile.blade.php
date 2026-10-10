@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Account</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Username</dt><dd class="col-sm-8">{{ $user->username }}</dd>
                        <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $user->name }}</dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $user->email ?? '—' }}</dd>
                        <dt class="col-sm-4">Role</dt><dd class="col-sm-8">{{ $user->roleName() }}</dd>
                        <dt class="col-sm-4">Last login</dt><dd class="col-sm-8 mb-0">{{ $user->last_login?->format('M j, Y g:i A') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Change password</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('profile.password') }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">New password</label>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password" aria-describedby="passwordHelp">
                            <div id="passwordHelp" class="form-text">At least 8 characters, with letters and numbers.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm new password</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary">Update password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
