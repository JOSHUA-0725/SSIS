@extends('layouts.app')

@section('title', 'Grades')
@section('lead', 'Final grades appear here as soon as your department approves them and the Registrar releases the term.')

@section('actions')
    @if ($term)
        <button type="button" class="btn btn-outline-secondary" data-print>@include('partials.icon', ['name' => 'printer']) Unofficial copy</button>
    @endif
@endsection

@section('content')
    @if ($unreleased)
        <div class="alert alert-info">Grades for {{ $unreleased->label }} have not been released by the Registrar yet.</div>
    @endif

    @if (! $term)
        <div class="panel"><p class="muted mb-0">No released grades yet.</p></div>
    @else
        <ul class="nav nav-tabs mb-4 d-print-none">
            @foreach ($terms as $option)
                <li class="nav-item">
                    <a class="nav-link {{ $option->id === $term->id ? 'active' : '' }}" href="{{ route('student.grades', ['term' => $option->id]) }}" @if ($option->id === $term->id) aria-current="page" @endif>
                        {{ $option->semester }} Sem {{ $option->school_year }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="card">
            <div class="summary">
                <div>
                    <div class="stat-label">General weighted average</div>
                    <div class="value big">{{ $grades['gwa'] !== null ? number_format($grades['gwa'], 2) : '—' }}</div>
                </div>
                <div>
                    <div class="stat-label">Units earned</div>
                    <div class="value">{{ $grades['units'] }}</div>
                </div>
                <div>
                    <div class="stat-label">Subjects</div>
                    <div class="value">{{ $grades['rows']->count() }}</div>
                </div>
                <div class="ms-auto muted small d-none d-print-block">{{ $term->label }} · Unofficial copy</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Code</th><th scope="col">Subject</th><th scope="col" class="num">Units</th>
                            <th scope="col" class="num">Midterm</th><th scope="col" class="num">Final</th>
                            <th scope="col" class="num">Rating</th><th scope="col">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grades['rows'] as $row)
                            <tr>
                                <td class="mono text-nowrap">{{ $row->section->subject->code }}</td>
                                <td>
                                    <div class="fw-medium">{{ $row->section->subject->title }}</div>
                                    <div class="small muted">{{ $row->section->faculty->name }}</div>
                                </td>
                                <td class="num">{{ $row->section->subject->units }}</td>
                                <td class="num muted">{{ $row->grade?->midterm_grade !== null ? number_format($row->grade->midterm_grade, 2) : '—' }}</td>
                                <td class="num muted">{{ $row->grade?->final_grade !== null ? number_format($row->grade->final_grade, 2) : '—' }}</td>
                                <td class="num fw-semibold">{{ $row->grade?->final_rating !== null ? number_format($row->grade->final_rating, 2) : '—' }}</td>
                                <td>@if ($row->grade?->remarks) @include('partials.status', ['status' => $row->grade->remarks]) @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No approved grade sheets for this term yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($certificate)
            <p class="mt-3 d-print-none">Need an official copy? <a href="{{ route('student.requests', ['type' => $certificate->id]) }}" class="fw-medium">Request a Certificate of Grades</a></p>
        @endif
    @endif
@endsection
