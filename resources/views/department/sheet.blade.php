@extends('layouts.app')

@section('title', 'Review grade sheet')

@section('actions')
    <a href="{{ route('head.sheets') }}" class="btn btn-outline-secondary">All sheets</a>
@endsection

@section('content')
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div>
            <div class="fw-semibold">{{ $section->subject->code }} · {{ $section->subject->title }} · {{ $section->section_code }}</div>
            <div class="text-secondary">{{ $section->term->label }} · Faculty: {{ $section->faculty->name }}</div>
        </div>
        <div>Sheet status: @include('partials.status', ['status' => $section->sheet_status])</div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Student no.</th><th scope="col">Name</th><th scope="col" class="text-end">Prelim</th><th scope="col" class="text-end">Midterm</th><th scope="col" class="text-end">Final</th><th scope="col" class="text-end">Rating</th><th scope="col">Remarks</th></tr></thead>
                <tbody>
                    @foreach ($enrollments as $enrollment)
                        <tr>
                            <td>{{ $enrollment->student->student_no }}</td>
                            <td>{{ $enrollment->student->full_name }}</td>
                            <td class="text-end">{{ $enrollment->grade?->prelim_grade !== null ? number_format($enrollment->grade->prelim_grade, 2) : '—' }}</td>
                            <td class="text-end">{{ $enrollment->grade?->midterm_grade !== null ? number_format($enrollment->grade->midterm_grade, 2) : '—' }}</td>
                            <td class="text-end">{{ $enrollment->grade?->final_grade !== null ? number_format($enrollment->grade->final_grade, 2) : '—' }}</td>
                            <td class="text-end fw-semibold">{{ $enrollment->grade?->final_rating !== null ? number_format($enrollment->grade->final_rating, 2) : '—' }}</td>
                            <td>@if ($enrollment->grade?->remarks) @include('partials.status', ['status' => $enrollment->grade->remarks]) @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($section->sheet_status === 'Submitted')
        <div class="row g-3">
            <div class="col-lg-4">
                <form method="POST" action="{{ route('head.sheet.decide', $section) }}" data-confirm="Approve this grade sheet? It will be locked.">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-primary">Approve and lock</button>
                </form>
            </div>
            <div class="col-lg-8">
                <form method="POST" action="{{ route('head.sheet.decide', $section) }}" class="row g-2 align-items-end">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="action" value="return">
                    <div class="col">
                        <label for="remarks" class="form-label">Remarks for the faculty member</label>
                        <input type="text" id="remarks" name="remarks" class="form-control" maxlength="255" required>
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-outline-danger">Return for correction</button></div>
                </form>
            </div>
        </div>
    @elseif ($section->sheet_status === 'Approved')
        <p class="text-secondary">Approved {{ $section->approved_at?->format('M j, Y g:i A') }}. This sheet is locked.</p>
    @endif
@endsection
