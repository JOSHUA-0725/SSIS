@extends('layouts.app')

@section('title', 'Students')

@section('actions')
    <a href="{{ route('registrar.students.create') }}" class="btn btn-primary">Add student</a>
@endsection

@section('content')
    @include('partials.search', ['placeholder' => 'Search student no. or name'])

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Student no.</th><th scope="col">Name</th><th scope="col">Program</th><th scope="col">Year</th><th scope="col">Status</th><th scope="col"></th></tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td>{{ $student->student_no }}</td>
                            <td>{{ $student->full_name }}</td>
                            <td>{{ $student->program->code }}</td>
                            <td>{{ $student->year_level }}</td>
                            <td>@include('partials.status', ['status' => $student->status])</td>
                            <td class="text-end"><a href="{{ route('registrar.students.show', $student) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No students found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $students->links() }}
@endsection
