@extends('layouts.app')

@section('title', 'Terms and grade release')
@section('lead', 'Open or close enrollment and grade encoding, and release approved grades to students.')

@section('content')
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Term</th><th scope="col">Active</th><th scope="col">Enrollment</th><th scope="col">Grade encoding</th><th scope="col">Grades</th><th scope="col">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach ($terms as $term)
                        @php
                            $buttons = [];
                            if (! $term->is_active) { $buttons[] = ['activate', 'Set as active', 'btn-outline-primary', 'Make this the active term? The current active term will be closed.']; }
                            if ($term->is_active) {
                                $buttons[] = $term->enrollment_open
                                    ? ['close_enrollment', 'Close enrollment', 'btn-outline-danger', 'Close enrollment? Regular students will no longer be able to enroll.']
                                    : ['open_enrollment', 'Open enrollment', 'btn-primary', 'Open enrollment? Regular students can then enroll in the subjects of their year level.'];
                            }
                            if (! $term->grades_released) {
                                $buttons[] = $term->encoding_open
                                    ? ['close_encoding', 'Close encoding', 'btn-outline-secondary', null]
                                    : ['open_encoding', 'Open encoding', 'btn-outline-secondary', null];
                                $buttons[] = ['release', 'Release grades', 'btn-outline-primary', 'Release grades to students? Encoding will be closed.'];
                            } else {
                                $buttons[] = ['unrelease', 'Withdraw release', 'btn-outline-danger', 'Hide the grades of this term from students?'];
                            }
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $term->label }}</td>
                            <td>{{ $term->is_active ? 'Yes' : 'No' }}</td>
                            <td>@include('partials.status', ['status' => $term->enrollment_open ? 'Open' : 'Closed'])</td>
                            <td>{{ $term->encoding_open ? 'Open' : 'Closed' }}</td>
                            <td>{{ $term->grades_released ? 'Released' : 'Not released' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($buttons as [$action, $label, $class, $confirm])
                                        <form method="POST" action="{{ route('registrar.terms.update', $term) }}" @if ($confirm) data-confirm="{{ $confirm }}" @endif>
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="action" value="{{ $action }}">
                                            <button type="submit" class="btn btn-sm {{ $class }}">{{ $label }}</button>
                                        </form>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card col-lg-6">
        <div class="card-header">Add a term</div>
        <div class="card-body">
            <form method="POST" action="{{ route('registrar.terms.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-sm-5">
                    <label for="school_year" class="form-label">School year</label>
                    <input type="text" id="school_year" name="school_year" class="form-control" placeholder="2027-2028" value="{{ old('school_year') }}" required>
                </div>
                <div class="col-sm-4">
                    <label for="semester" class="form-label">Semester</label>
                    <select id="semester" name="semester" class="form-select">
                        <option>1st</option><option>2nd</option><option>Summer</option>
                    </select>
                </div>
                <div class="col-sm-3"><button type="submit" class="btn btn-primary w-100">Add</button></div>
            </form>
        </div>
    </div>
@endsection
