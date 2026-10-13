@extends('layouts.app')

@section('title', 'Grade encoding')

@section('actions')
    <a href="{{ route('faculty.classes') }}" class="btn btn-outline-secondary">My classes</a>
@endsection

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <div class="fw-semibold">{{ $section->subject->code }} · {{ $section->subject->title }} · {{ $section->section_code }}</div>
            <div class="text-secondary">{{ $section->term->label }}</div>
        </div>
        <div>Sheet status: @include('partials.status', ['status' => $section->sheet_status])</div>
    </div>

    @if ($section->return_remarks)
        <div class="alert alert-warning">Returned by the Department Head: {{ $section->return_remarks }}</div>
    @endif
    @unless ($editable)
        <div class="alert alert-info">
            {{ $section->term->encoding_open ? "This sheet is {$section->sheet_status} and is locked." : 'The encoding period is closed. Grades are read-only.' }}
        </div>
    @endunless

    <form method="POST" action="{{ route('faculty.encode.save', $section) }}">
        @csrf
        @method('PUT')
        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th scope="col">Student no.</th><th scope="col">Name</th><th scope="col">Prelim</th><th scope="col">Midterm</th><th scope="col">Final</th><th scope="col" class="text-end">Rating</th><th scope="col">Remarks</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($enrollments as $enrollment)
                            <tr>
                                <td>{{ $enrollment->student->student_no }}</td>
                                <td>{{ $enrollment->student->full_name }}</td>
                                @foreach (['prelim' => 'prelim_grade', 'midterm' => 'midterm_grade', 'final' => 'final_grade'] as $field => $column)
                                    <td>
                                        <label class="visually-hidden" for="{{ $field }}_{{ $enrollment->id }}">{{ ucfirst($field) }} grade of {{ $enrollment->student->student_no }}</label>
                                        <input type="number" class="form-control grade-input" id="{{ $field }}_{{ $enrollment->id }}"
                                               name="grades[{{ $enrollment->id }}][{{ $field }}]" step="0.25" min="1" max="5"
                                               value="{{ old("grades.{$enrollment->id}.{$field}", $enrollment->grade?->$column !== null ? number_format($enrollment->grade->$column, 2) : '') }}"
                                               @disabled(! $editable)>
                                    </td>
                                @endforeach
                                <td class="text-end fw-semibold">{{ $enrollment->grade?->final_rating !== null ? number_format($enrollment->grade->final_rating, 2) : '—' }}</td>
                                <td>@if ($enrollment->grade?->remarks) @include('partials.status', ['status' => $enrollment->grade->remarks]) @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-secondary">No students are enrolled in this section.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="form-text">Grades accept 1.00 to 5.00 only. The rating is the average of the prelim, midterm, and final grades, computed when you save. The sheet locks after the Department Head approves it.</p>

        @if ($editable && $enrollments->isNotEmpty())
            <div class="d-flex gap-2">
                <button type="submit" name="action" value="save" class="btn btn-outline-primary">Save draft</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary">Submit for approval</button>
            </div>
        @endif
    </form>
@endsection
