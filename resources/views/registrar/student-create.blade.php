@extends('layouts.app')

@section('title', 'Add Student')

@section('content')
    <div class="card col-lg-8">
        <div class="card-body">
            <form method="POST" action="{{ route('registrar.students.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="student_no" class="form-label">Student no.</label>
                        <input type="text" id="student_no" name="student_no" class="form-control" value="{{ old('student_no') }}" placeholder="2026-00123" required>
                    </div>
                    <div class="col-md-8">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="last_name" class="form-label">Last name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="first_name" class="form-label">First name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="middle_name" class="form-label">Middle name</label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control" value="{{ old('middle_name') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="program_id" class="form-label">Program</label>
                        <select id="program_id" name="program_id" class="form-select" required>
                            @foreach ($programs as $program)
                                <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>{{ $program->code }} · {{ $program->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="year_level" class="form-label">Year level</label>
                        <input type="number" id="year_level" name="year_level" class="form-control" min="1" max="5" value="{{ old('year_level', 1) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="sex" class="form-label">Sex</label>
                        <select id="sex" name="sex" class="form-select">
                            <option value="">—</option>
                            <option value="M" @selected(old('sex') === 'M')>Male</option>
                            <option value="F" @selected(old('sex') === 'F')>Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="birth_date" class="form-label">Birth date</label>
                        <input type="date" id="birth_date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label for="contact_no" class="form-label">Contact no.</label>
                        <input type="text" id="contact_no" name="contact_no" class="form-control" value="{{ old('contact_no') }}">
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value="{{ old('address') }}">
                    </div>
                </div>
                <p class="form-text mt-3">A login account is created with the student number as username and a temporary password shown once after saving.</p>
                <div class="d-flex gap-2">
                    <a href="{{ route('registrar.students') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save student</button>
                </div>
            </form>
        </div>
    </div>
@endsection
