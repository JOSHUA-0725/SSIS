@extends('layouts.app')

@section('eyebrow', $category.' student')
@section('title', 'Enrollment queue')
@section('lead', ($term ? "For {$term->label}. " : '').'Get a number here, then wait for it to be called at the Registrar window. This page updates by itself.')

@section('content')
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
                @elseif ($slots === 0 && ! $holdsSlot)
                    <p class="mb-0">There are no open enrollment slots for {{ $category }} students of your department right now. Check again later or ask your department.</p>
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
            <section class="panel" aria-label="Slots left">
                <div class="stat-label">{{ $category }} slots left in your department</div>
                <div class="stat-value">{{ $slots }}</div>
            </section>
        </div>
    </div>
@endsection
