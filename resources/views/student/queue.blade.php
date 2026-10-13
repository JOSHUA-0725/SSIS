@extends('layouts.app')

@section('eyebrow', $category.' student')
@section('title', 'Enrollment')
@section('lead', ($term ? "For {$term->label}. " : '').'As an irregular student you enroll in the subjects your department opens for you. If you need the Registrar, get a queue number below.')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <h2 class="panel-title mb-2">Subjects opened for you</h2>
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Subject</th><th scope="col">Title</th><th scope="col" class="text-end">Units</th><th scope="col">Section</th><th scope="col">Professor</th></tr>
                </thead>
                <tbody>
                    @forelse ($advised as $section)
                        <tr>
                            <th scope="row" class="fw-medium text-nowrap">{{ $section->subject->code }}</th>
                            <td>{{ $section->subject->title }}</td>
                            <td class="text-end">{{ $section->subject->units }}</td>
                            <td class="text-nowrap">{{ $section->section_code }}</td>
                            <td class="muted">{{ $section->faculty->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">Your department has not opened any subject for you yet. Ask your department's faculty; you will be notified when they do.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($advised->isNotEmpty())
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                @if ($advisedEnrolled)
                    <span class="fw-medium">You are enrolled in every subject opened for you.</span>
                @else
                    <form method="POST" action="{{ route('student.enroll') }}" data-confirm="Enroll in the {{ $advised->count() }} {{ $advised->count() === 1 ? 'subject' : 'subjects' }} opened for you?">
                        @csrf
                        <button type="submit" class="btn btn-primary">Enroll in these subjects</button>
                    </form>
                    <span class="muted small">{{ $advised->sum('subject.units') }} units in all.</span>
                @endif
            </div>
        @endif
    </div>

    <h2 class="panel-title mb-2">Registrar queue</h2>
    <div class="overview">
        @if ($ticket)
            <section class="hero queue-ticket" aria-labelledby="ticket-title">
                <div class="eyebrow" id="ticket-title">Your queue number</div>
                <div class="queue-number">{{ $ticket->label }}</div>

                @if ($ticket->status === 'Serving')
                    <p class="title">It is your turn. Please go to the Registrar window now.</p>
                    <p class="detail mb-0">Called at {{ $ticket->called_at->format('g:i A') }}.</p>
                @else
                    <p class="title">
                        @if ($ahead === 0) You are next in line. Please stay near the Registrar window.
                        @else {{ $ahead }} {{ $ahead === 1 ? 'student is' : 'students are' }} ahead of you.
                        @endif
                    </p>
                    <p class="detail mb-0">Taken at {{ $ticket->created_at->format('g:i A') }}. You will be notified when your number is called.</p>
                    <form method="POST" action="{{ route('student.queue.leave') }}" data-confirm="Cancel queue number {{ $ticket->label }}? You will lose your place in line.">
                        @csrf
                        <button type="submit" class="btn btn-light">Cancel my number</button>
                    </form>
                @endif
            </section>
        @else
            <section class="panel" aria-labelledby="join-title">
                <h2 class="panel-title" id="join-title">Line up for enrollment</h2>
                @if ($last?->status === 'Skipped')
                    <p class="mt-2 mb-0">Your number {{ $last->label }} was called but no one came, so it was skipped. Get a new number to line up again.</p>
                @elseif ($last?->status === 'Done')
                    <p class="mt-2 mb-0">Your number {{ $last->label }} was served today at {{ $last->finished_at->format('g:i A') }}.</p>
                @endif
                <p class="muted mt-2">Numbers are called in order and start again every day. You can hold one number at a time, and you may cancel it while you are still waiting.</p>

                @if (! $term)
                    <p class="mb-0">Enrollment is not open: no term is active.</p>
                @else
                    <form method="POST" action="{{ route('student.queue.join') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">@include('partials.icon', ['name' => 'plus']) Get a queue number</button>
                    </form>
                @endif
            </section>
        @endif

        <div class="stack">
            <section class="panel" aria-label="Now serving">
                <div class="stat-label">Now serving</div>
                @forelse ($serving as $current)
                    <div class="stat-value">{{ $current->label }}</div>
                @empty
                    <div class="stat-value">—</div>
                    <p class="muted small mb-0">No number has been called yet.</p>
                @endforelse
            </section>
            <section class="panel" aria-label="Waiting in line">
                <div class="stat-label">Waiting in line</div>
                <div class="stat-value">{{ $waiting }}</div>
            </section>
        </div>
    </div>
@endsection
