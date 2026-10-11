@extends('layouts.app')

@section('title', 'Enrollment queue')
@section('lead', 'Today\'s line, '.now()->format('F j, Y').'. Calling the next number marks the one you are serving as done.')

@section('actions')
    <form method="POST" action="{{ route('registrar.queue.next') }}">
        @csrf
        <button type="submit" class="btn btn-primary" @disabled($waiting->isEmpty())>Call next number</button>
    </form>
@endsection

@section('content')
    <div class="overview">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th scope="col">Number</th><th scope="col">Student no.</th><th scope="col">Name</th><th scope="col">Program</th><th scope="col">Taken</th><th scope="col">Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($waiting as $ticket)
                            <tr>
                                <th scope="row" class="fw-medium">{{ $ticket->label }}</th>
                                <td>{{ $ticket->student->student_no }}</td>
                                <td>{{ $ticket->student->full_name }}</td>
                                <td>{{ $ticket->student->program->code }} {{ $ticket->student->year_level }}</td>
                                <td class="muted text-nowrap">{{ $ticket->created_at->format('g:i A') }}</td>
                                <td>@include('partials.status', ['status' => $ticket->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-secondary">No one is waiting in line.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="stack">
            <section class="hero queue-ticket" aria-labelledby="serving-title">
                <div class="eyebrow" id="serving-title">You are serving</div>
                @if ($mine)
                    <div class="queue-number">{{ $mine->label }}</div>
                    <p class="title">{{ $mine->student->full_name }}</p>
                    <p class="detail mb-0">{{ $mine->student->student_no }} · {{ $mine->student->program->code }} {{ $mine->student->year_level }} · called {{ $mine->called_at->format('g:i A') }}</p>
                    <a href="{{ route('registrar.students.show', $mine->student) }}" class="btn btn-light">Open student record</a>
                    <form method="POST" action="{{ route('registrar.queue.close', $mine) }}" class="d-flex gap-2 mt-2">
                        @csrf
                        @method('PUT')
                        <button type="submit" name="status" value="Done" class="btn btn-light mt-0">Done</button>
                        <button type="submit" name="status" value="Skipped" class="btn btn-outline-light mt-0">No show</button>
                    </form>
                @else
                    <div class="queue-number">—</div>
                    <p class="detail mb-0">Call the next number when you are ready.</p>
                @endif
            </section>

            @if ($others->isNotEmpty())
                <section class="panel" aria-label="Other windows">
                    <h2 class="panel-title">Other windows</h2>
                    <ul class="rows">
                        @foreach ($others as $ticket)
                            <li>
                                <div>
                                    <div class="fw-medium">{{ $ticket->label }}</div>
                                    <div class="sub">{{ $ticket->server?->name }}</div>
                                </div>
                                @include('partials.status', ['status' => $ticket->status])
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </div>

    @if ($closed->isNotEmpty())
        <h2 class="panel-title mt-4 mb-2">Finished today</h2>
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th scope="col">Number</th><th scope="col">Student no.</th><th scope="col">Name</th><th scope="col">Served by</th><th scope="col">Finished</th><th scope="col">Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($closed as $ticket)
                            <tr>
                                <th scope="row" class="fw-medium">{{ $ticket->label }}</th>
                                <td>{{ $ticket->student->student_no }}</td>
                                <td>{{ $ticket->student->full_name }}</td>
                                <td class="muted">{{ $ticket->server?->name ?? '—' }}</td>
                                <td class="muted text-nowrap">{{ $ticket->finished_at?->format('g:i A') ?? '—' }}</td>
                                <td>@include('partials.status', ['status' => $ticket->status])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
