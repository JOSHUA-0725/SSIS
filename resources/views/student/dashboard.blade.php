@extends('layouts.app')

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp

@section('eyebrow', ($term?->label ?? 'No active term').' · '.$student->program->code.' '.$student->year_level)
@section('title', $greeting.', '.$student->first_name)

@section('content')
    <div class="overview">
        <section class="panel" aria-labelledby="clearance-title">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="panel-title" id="clearance-title">Enrollment clearance</h2>
                    <p class="muted small mb-0">You can enroll once every office has signed off — no need to visit each one.</p>
                </div>
                <div class="text-end">
                    <div class="count-big">{{ $cleared }}<small>/{{ $items->count() }}</small></div>
                    <div class="stat-label">offices cleared</div>
                </div>
            </div>

            @if ($items->isEmpty())
                <p class="muted mt-4 mb-0">No clearance is open for you this term.</p>
            @else
                <div class="segments" aria-hidden="true">
                    @foreach ($items as $item)
                        <span class="{{ ['Cleared' => 'is-cleared', 'On Hold' => 'is-hold'][$item->display] ?? '' }}"></span>
                    @endforeach
                </div>
                <ul class="rows mb-3">
                    @foreach ($items as $item)
                        <li>
                            <div>
                                <div class="fw-medium">{{ $item->office->name }}</div>
                                <div class="sub">{{ $item->note }}</div>
                            </div>
                            @include('partials.status', ['status' => $item->display])
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('student.clearance') }}" class="link-arrow">View signatories and remarks @include('partials.icon', ['name' => 'arrow-right'])</a>
            @endif
        </section>

        <div class="stack">
            <section class="hero" aria-label="{{ $blocker['eyebrow'] }}">
                <div class="eyebrow">{{ $blocker['eyebrow'] }}</div>
                <p class="title">{{ $blocker['title'] }}</p>
                @if (! empty($blocker['detail'])) <p class="detail mb-0">{{ $blocker['detail'] }}</p> @endif
                @if ($blocker['amount'] !== null) <div class="amount">₱{{ number_format($blocker['amount'], 2) }}</div> @endif
                <a href="{{ $blocker['url'] }}" class="btn btn-light">{{ $blocker['cta'] }}</a>
            </section>

            <section class="panel" aria-label="Latest grades">
                @if ($grades && $grades['gwa'] !== null)
                    <div class="stat-label">{{ $gradeTerm->label }} GWA</div>
                    <div class="stat-value my-1">{{ number_format($grades['gwa'], 2) }}</div>
                    <p class="muted small">
                        {{ $grades['rows']->count() }} {{ Str::plural('subject', $grades['rows']->count()) }} · {{ $grades['units'] }} units earned{{ $grades['allPassed'] ? ' · all passed' : '' }}
                    </p>
                @else
                    <div class="stat-label">Grades</div>
                    <p class="muted small mt-1">No released grades yet.</p>
                @endif
                <a href="{{ route('student.grades') }}" class="link-arrow">See all grades @include('partials.icon', ['name' => 'arrow-right'])</a>
            </section>
        </div>
    </div>

    <section class="panel mt-4" aria-labelledby="requests-title">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="panel-title" id="requests-title">Document requests in progress</h2>
            <a href="{{ route('student.requests') }}" class="btn btn-outline-secondary">Request a document</a>
        </div>
        @forelse ($requests as $docRequest)
            <a class="request-row" href="{{ route('student.requests.show', $docRequest) }}">
                <div>
                    <div class="mono">{{ $docRequest->reference_no }}</div>
                    <div class="name">{{ $docRequest->type->name }}@if ($docRequest->copies > 1) <span class="muted">×{{ $docRequest->copies }}</span>@endif</div>
                    <div class="small muted">
                        @if ($docRequest->status === 'Pending Payment') Pay ₱{{ number_format($docRequest->total_fee, 2) }} to start processing
                        @elseif ($docRequest->status === 'Ready for Pickup') {{ $docRequest->release_method === 'E-copy' ? 'E-copy ready to download' : 'Pickup at the Registrar'.($docRequest->claim_date ? ' from '.$docRequest->claim_date->format('M j') : '') }}
                        @else {{ $docRequest->purpose }}
                        @endif
                    </div>
                </div>
                @include('partials.stepper', ['status' => $docRequest->status])
            </a>
        @empty
            <p class="muted mb-0 mt-3">You have no requests in progress.</p>
        @endforelse
    </section>

    @if ($notifications->isNotEmpty())
        <section class="panel mt-4" aria-labelledby="updates-title">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h2 class="panel-title" id="updates-title">Latest updates</h2>
                <a href="{{ route('notifications') }}" class="link-arrow">View all @include('partials.icon', ['name' => 'arrow-right'])</a>
            </div>
            <ul class="rows">
                @foreach ($notifications as $notification)
                    <li>
                        <div>
                            <div class="fw-medium">{{ $notification->title }}</div>
                            <div class="sub">{{ $notification->message }}</div>
                        </div>
                        <span class="small muted text-nowrap">{{ $notification->created_at->diffForHumans(short: true) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
