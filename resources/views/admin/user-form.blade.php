@extends('layouts.app')

@section('title', $user->exists ? 'Edit User' : 'Add User')

@section('content')
    @php $isStudent = $user->exists && $user->hasRole('Student'); @endphp

    <div class="card col-lg-8">
        <div class="card-body">
            <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
                @csrf
                @if ($user->exists) @method('PUT') @endif

                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="username" class="form-label">Username (employee ID)</label>
                        <input type="text" id="username" name="username" class="form-control" maxlength="30" value="{{ old('username', $user->username) }}" @if ($user->exists) disabled @else required @endif>
                    </div>
                    <div class="col-md-7">
                        <label for="name" class="form-label">Full name</label>
                        <input type="text" id="name" name="name" class="form-control" maxlength="120" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="col-md-7">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" maxlength="120" value="{{ old('email', $user->email) }}">
                    </div>
                    @if ($user->exists)
                        <div class="col-md-5">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                @foreach (['Active', 'Inactive'] as $option)
                                    <option @selected(old('status', $user->status) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($isStudent)
                        <div class="col-12"><p class="form-text mb-0">Role: Student. Student accounts are created and managed with the student record in the Registrar Module.</p></div>
                    @else
                        <div class="col-md-4">
                            <label for="role_id" class="form-label">Role</label>
                            <select id="role_id" name="role_id" class="form-select" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="office_id" class="form-label">Clearance office</label>
                            <select id="office_id" name="office_id" class="form-select" aria-describedby="officeHelp">
                                <option value="">None</option>
                                @foreach ($offices as $office)
                                    <option value="{{ $office->id }}" @selected(old('office_id', $user->office_id) == $office->id)>{{ $office->name }}</option>
                                @endforeach
                            </select>
                            <div id="officeHelp" class="form-text">Required for the Signatory role.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="department_id" class="form-label">Department</label>
                            <select id="department_id" name="department_id" class="form-select" aria-describedby="deptHelp">
                                <option value="">None</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->code }}</option>
                                @endforeach
                            </select>
                            <div id="deptHelp" class="form-text">Required for Faculty and Department Head.</div>
                        </div>
                    @endif
                </div>

                @unless ($user->exists)
                    <p class="form-text mt-3">A temporary password is generated and shown once after saving.</p>
                @endunless

                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('admin.users', $user->exists ? \App\Http\Controllers\AdminController::usersTab($user) : request()->only('group')) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">{{ $user->exists ? 'Save changes' : 'Create user' }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
