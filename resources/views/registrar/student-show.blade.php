@extends('layouts.app')

@section('title', $student->full_name)

@section('actions')
    <a href="{{ route('registrar.students') }}" class="btn btn-outline-secondary">All students</a>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">Student record</div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-5">Student no.</dt><dd class="col-7">{{ $student->student_no }}</dd>
                        <dt class="col-5">Program</dt><dd class="col-7">{{ $student->program->code }} ({{ $student->program->department->code }})</dd>
                        <dt class="col-5">Email</dt><dd class="col-7">{{ $student->user->email ?? '—' }}</dd>
                        <dt class="col-5">Birth date</dt><dd class="col-7">{{ $student->birth_date?->format('M j, Y') ?? '—' }}</dd>
                    </dl>
                    <form method="POST" action="{{ route('registrar.students.update', $student) }}">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-7">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-select">
                                    @foreach (['Enrolled', 'Not Enrolled', 'LOA', 'Graduated', 'Dropped'] as $option)
                                        <option @selected($student->status === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="year_level" class="form-label">Year level</label>
                                <input type="number" id="year_level" name="year_level" class="form-control" min="1" max="5" value="{{ old('year_level', $student->year_level) }}">
                            </div>
                            <div class="col-7">
                                <label for="student_type" class="form-label">Student type</label>
                                <select id="student_type" name="student_type" class="form-select" aria-describedby="typeHelp">
                                    @foreach (['Regular', 'Irregular'] as $option)
                                        <option @selected(old('student_type', $student->student_type) === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="block_section" class="form-label">Block section</label>
                                <input type="text" id="block_section" name="block_section" class="form-control" maxlength="15" value="{{ old('block_section', $student->block_section) }}" placeholder="BSIT-3A">
                            </div>
                            <div class="col-12"><div id="typeHelp" class="form-text mt-0">Regular students enroll by block section. Irregular and first-year students line up within the approved slots.</div></div>
                            <div class="col-12">
                                <label for="contact_no" class="form-label">Contact no.</label>
                                <input type="text" id="contact_no" name="contact_no" class="form-control" value="{{ old('contact_no', $student->contact_no) }}">
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" id="address" name="address" class="form-control" value="{{ old('address', $student->address) }}">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Save changes</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if ($term)
                <div class="card mb-4">
                    <div class="card-header">Enroll in a class section · {{ $term->label }}</div>
                    <div class="card-body">
                        @if ($sections->isEmpty())
                            <p class="text-secondary mb-0">No more class sections to add this term.</p>
                        @else
                            <form method="POST" action="{{ route('registrar.students.enroll', $student) }}" class="row g-2">
                                @csrf
                                <div class="col">
                                    <label for="section_id" class="visually-hidden">Class section</label>
                                    <select id="section_id" name="section_id" class="form-select">
                                        @foreach ($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->subject->code }} · {{ $section->subject->title }} ({{ $section->section_code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-auto"><button type="submit" class="btn btn-primary">Enroll</button></div>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">Subjects and grades</div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead><tr><th scope="col">Term</th><th scope="col">Subject</th><th scope="col">Section</th><th scope="col" class="text-end">Rating</th><th scope="col">Sheet</th></tr></thead>
                        <tbody>
                            @forelse ($enrollments as $enrollment)
                                <tr>
                                    <td>{{ $enrollment->section->term->label }}</td>
                                    <td>{{ $enrollment->section->subject->code }} · {{ $enrollment->section->subject->title }}</td>
                                    <td>{{ $enrollment->section->section_code }}</td>
                                    <td class="text-end">{{ $enrollment->grade?->final_rating !== null ? number_format($enrollment->grade->final_rating, 2) : '—' }}</td>
                                    <td>@include('partials.status', ['status' => $enrollment->section->sheet_status])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-secondary">Not enrolled in any class section.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
