@extends('layouts.app')

@section('eyebrow', $department->code.' · '.($term?->label ?? 'No active term'))
@section('title', 'Enrollment')
@section('lead', 'Regular students enroll by themselves once the Registrar opens enrollment. Irregular students do not enroll with them: ask the Registrar for slots, then open the subjects of each irregular student here.')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="overview mb-4">
        <section class="panel" aria-labelledby="regular-title">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="panel-title" id="regular-title">Regular students</h2>
                    <p class="muted small mb-0">Opened and closed by the Registrar. Each regular student takes the subjects of their year level this semester.</p>
                </div>
                @include('partials.status', ['status' => $open ? 'Open' : 'Closed'])
            </div>
            <div class="mt-3">
                <div class="count-big">{{ $regularsEnrolled }}<small>/{{ $regulars }}</small></div>
                <div class="stat-label">regular students enrolled this term</div>
            </div>
        </section>

        <div class="stack">
            <section class="panel" aria-label="Irregular slots">
                <div class="stat-label">Irregular slots used</div>
                <div class="count-big my-1">{{ $slots['used'] }}<small>/{{ $slots['approved'] }}</small></div>
                <div class="stat-label">{{ max(0, $slots['approved'] - $slots['used']) }} left</div>
            </section>
        </div>
    </div>

    @if ($term)
        <div class="card mb-4">
            <div class="card-header">Open subjects for an irregular student</div>
            <div class="card-body">
                <form method="GET" action="{{ route('faculty.enrollment') }}" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="student_no" class="form-label">Student ID</label>
                        <input type="search" id="student_no" name="student_no" class="form-control" placeholder="2026-00125" maxlength="15" value="{{ $search }}" required>
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-outline-primary w-100">Search</button></div>
                </form>

                @if ($search !== '' && ! $found)
                    <p class="text-secondary mt-3 mb-0">No student of {{ $department->code }} has the ID {{ $search }}.</p>
                @elseif ($found && ! $foundIrregular)
                    <p class="mt-3 mb-0"><span class="fw-semibold">{{ $found->full_name }}</span> ({{ $found->student_no }}) is a regular student and enrolls without your help once the Registrar opens enrollment.</p>
                @elseif ($found)
                    <hr>
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">{{ $found->full_name }}</div>
                            <div class="text-secondary">{{ $found->student_no }} · {{ $found->program->code }} {{ $found->year_level }} · Irregular</div>
                        </div>
                        <div>@include('partials.status', ['status' => $found->status])</div>
                    </div>

                    <form method="POST" action="{{ route('faculty.enrollment.advise', $found) }}">
                        @csrf
                        @method('PUT')
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr><th scope="col">Open</th><th scope="col">Subject</th><th scope="col">Title</th><th scope="col" class="text-end">Units</th><th scope="col">Section</th><th scope="col">Professor</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($sections as $section)
                                        @php $taken = in_array($section->id, $enrolledIds, true); @endphp
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input" id="open_{{ $section->id }}" name="sections[]" value="{{ $section->id }}"
                                                       @checked($taken || in_array($section->id, $openedIds, true)) @disabled($taken)>
                                                <label class="visually-hidden" for="open_{{ $section->id }}">Open {{ $section->subject->code }} {{ $section->section_code }}</label>
                                            </td>
                                            <th scope="row" class="fw-medium text-nowrap">{{ $section->subject->code }}</th>
                                            <td>{{ $section->subject->title }} @if ($taken) <span class="muted small">· already enrolled</span> @endif</td>
                                            <td class="text-end">{{ $section->subject->units }}</td>
                                            <td class="text-nowrap">{{ $section->section_code }}</td>
                                            <td class="muted">{{ $section->faculty->name }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-secondary">No subject is offered by your department this term. Add them in Subject loads first.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if ($sections->isNotEmpty())
                            <button type="submit" class="btn btn-primary">Save subjects for this student</button>
                            <span class="form-text ms-2">The student can enroll only in the subjects you tick. The first subject opened for a student uses one slot.</span>
                        @endif
                    </form>
                @endif
            </div>
        </div>

        <h2 class="panel-title mb-2">Irregular students with subjects opened</h2>
        <div class="card mb-4">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th scope="col">Student ID</th><th scope="col">Name</th><th scope="col">Subjects opened</th><th scope="col"><span class="visually-hidden">Change</span></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($advised as $rows)
                            @php $who = $rows->first()->student; @endphp
                            <tr>
                                <td class="text-nowrap">{{ $who->student_no }}</td>
                                <td>{{ $who->full_name }}</td>
                                <td>{{ $rows->map(fn ($row) => $row->section->subject->code)->sort()->join(', ') }}</td>
                                <td class="text-end"><a href="{{ route('faculty.enrollment', ['student_no' => $who->student_no]) }}" class="btn btn-sm btn-outline-secondary">Change</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-secondary">No irregular student has subjects opened yet this term.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card col-lg-8 mb-4">
            <div class="card-header">Ask the Registrar for irregular slots</div>
            <div class="card-body">
                <form method="POST" action="{{ route('faculty.enrollment.slots') }}" class="row g-3 align-items-end">
                    @csrf
                    <input type="hidden" name="category" value="Irregular">
                    <div class="col-md-5">
                        <label for="slots" class="form-label">Number of irregular students</label>
                        <input type="number" id="slots" name="slots" class="form-control" min="1" max="2000" value="{{ old('slots') }}" required>
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Send request</button></div>
                </form>
                <p class="form-text mb-0 mt-3">Approved requests add up. Ask again when you need more slots.</p>
            </div>
        </div>
    @endif

    <h2 class="panel-title mb-2">Slot requests this term</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Requested</th><th scope="col">Students</th><th scope="col" class="text-end">Slots</th><th scope="col">Requested by</th><th scope="col">Remarks</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($requests as $slotRequest)
                        <tr>
                            <td class="muted text-nowrap">{{ $slotRequest->created_at->format('M j, Y g:i A') }}</td>
                            <td>{{ $slotRequest->category }}</td>
                            <td class="text-end">{{ $slotRequest->slots }}</td>
                            <td>{{ $slotRequest->requester->name }}</td>
                            <td class="muted">{{ $slotRequest->remarks ?? '—' }}</td>
                            <td>@include('partials.status', ['status' => $slotRequest->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No slot requests yet this term.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
