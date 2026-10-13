@extends('layouts.app')

@section('eyebrow', $student->program->code.' · '.$student->program->name)
@section('title', 'Curriculum')
@section('lead', 'Every subject of your program from first year to fourth year, and which ones you have already taken.')

@section('actions')
    @if ($years->isNotEmpty())
        <button type="button" class="btn btn-outline-secondary" data-print>@include('partials.icon', ['name' => 'printer']) Print</button>
    @endif
@endsection

@section('content')
    @if ($years->isEmpty())
        <div class="panel"><p class="muted mb-0">The curriculum of {{ $student->program->code }} has not been encoded yet.</p></div>
    @else
        <div class="card mb-4">
            <div class="summary">
                <div>
                    <div class="stat-label">Subjects passed</div>
                    <div class="value big">{{ $summary['passed'] }} <span class="muted fs-6">of {{ $summary['subjects'] }}</span></div>
                </div>
                <div>
                    <div class="stat-label">Units earned</div>
                    <div class="value">{{ $summary['unitsEarned'] }} <span class="muted fs-6">of {{ $summary['units'] }}</span></div>
                </div>
                <div>
                    <div class="stat-label">Taking this term</div>
                    <div class="value">{{ $summary['enrolled'] }}</div>
                </div>
            </div>
        </div>

        @foreach ($years as $year => $semesters)
            <h2 class="h5 mt-4 mb-3">
                {{ $yearNames[$year] ?? "Year {$year}" }}
                @if ((int) $student->year_level === (int) $year) <span class="pill pill-success ms-2">Your year level</span> @endif
            </h2>
            <div class="row g-3">
                @foreach ($semesters as $semester => $rows)
                    <div class="col-12 col-xl-6">
                        <div class="card h-100">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <caption class="caption-top px-3 fw-semibold">{{ $semester === 'Summer' ? 'Summer' : "{$semester} Semester" }} · {{ $rows->sum('subject.units') }} units</caption>
                                    <thead>
                                        <tr><th scope="col">Code</th><th scope="col">Subject</th><th scope="col" class="num">Units</th><th scope="col" class="num">Grade</th><th scope="col">Status</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            @php $taken = $progress[$row->subject_id] ?? null; @endphp
                                            <tr>
                                                <td class="mono text-nowrap">{{ $row->subject->code }}</td>
                                                <td>{{ $row->subject->title }}</td>
                                                <td class="num">{{ $row->subject->units }}</td>
                                                <td class="num fw-semibold">{{ isset($taken['rating']) ? number_format($taken['rating'], 2) : '—' }}</td>
                                                <td>
                                                    @if ($taken)
                                                        @include('partials.status', ['status' => $taken['status']])
                                                    @else
                                                        <span class="muted small">Not yet taken</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
@endsection
