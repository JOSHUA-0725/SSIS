@extends('layouts.app')

@section('title', 'Document requests')
@section('lead', 'Request official documents online, pay the fee in cash at the Cashier, and claim them on a scheduled date or as a certified e-copy.')

@section('content')
    <div class="split">
        <section class="panel" aria-labelledby="new-title">
            <h2 class="panel-title mb-3" id="new-title">New request</h2>

            @if ($types->isEmpty())
                <p class="muted mb-0">No documents can be requested right now.</p>
            @else
                <form method="POST" action="{{ route('student.requests.store') }}">
                    @csrf
                    <fieldset class="mb-3">
                        <legend class="form-label">Document</legend>
                        @foreach ($types as $type)
                            <label class="choice">
                                <input type="radio" name="document_type_id" value="{{ $type->id }}" data-fee="{{ $type->fee_per_copy }}" @checked($selected === $type->id) required>
                                <span class="box">
                                    <span class="radio"></span>
                                    <span class="text">
                                        <span class="name d-block">{{ $type->name }}</span>
                                        <span class="sub">{{ $type->description ? $type->description.' · ' : '' }}{{ $type->processing_days }} working {{ Str::plural('day', $type->processing_days) }}</span>
                                    </span>
                                    <span class="price">₱{{ number_format($type->fee_per_copy, 2) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="copies" class="form-label">Copies</label>
                            <div class="counter">
                                <button type="button" data-step="-1" aria-label="Fewer copies">@include('partials.icon', ['name' => 'minus'])</button>
                                <input type="number" id="copies" name="copies" min="1" max="{{ \App\Services\DocumentRequestService::MAX_COPIES }}" value="{{ old('copies', 1) }}" required>
                                <button type="button" data-step="1" aria-label="More copies">@include('partials.icon', ['name' => 'plus'])</button>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label for="purpose" class="form-label">Purpose</label>
                            <select id="purpose" name="purpose" class="form-select" required>
                                <option value="">Select purpose</option>
                                @foreach ($purposes as $purpose)
                                    <option @selected(old('purpose') === $purpose)>{{ $purpose }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <fieldset>
                        <legend class="form-label">Release via</legend>
                        <div class="choice-row">
                            <label class="choice">
                                <input type="radio" name="release_method" value="Pickup" @checked(old('release_method', 'Pickup') === 'Pickup')>
                                <span class="box">Scheduled pickup</span>
                            </label>
                            <label class="choice">
                                <input type="radio" name="release_method" value="E-copy" @checked(old('release_method') === 'E-copy')>
                                <span class="box">Certified e-copy</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="total-row">
                        <div>
                            <div class="stat-label">Total</div>
                            <div class="value" id="total_fee" aria-live="polite">₱{{ number_format($types->firstWhere('id', $selected)?->fee_per_copy ?? 0, 2) }}</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit request</button>
                    </div>
                </form>
            @endif
        </section>

        <section aria-labelledby="mine-title">
            <h2 class="panel-title mb-3" id="mine-title">My requests</h2>
            @forelse ($requests as $docRequest)
                @php $closed = in_array($docRequest->status, ['Rejected', 'Cancelled'], true); @endphp
                <a class="card request-card" href="{{ route('student.requests.show', $docRequest) }}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="mono">{{ $docRequest->reference_no }} · {{ $docRequest->created_at->format('M j, Y') }}</span>
                        @include('partials.status', ['status' => $docRequest->status])
                    </div>
                    <div class="name mt-1">{{ $docRequest->type->name }}@if ($docRequest->copies > 1) <span class="muted">×{{ $docRequest->copies }}</span>@endif</div>
                    <div class="small muted mb-3">{{ $docRequest->purpose }} · ₱{{ number_format($docRequest->total_fee, 2) }} · {{ $docRequest->release_method === 'E-copy' ? 'Certified e-copy' : 'Scheduled pickup' }}</div>

                    @if ($closed)
                        <div class="small muted">{{ $docRequest->reject_reason ? 'Reason: '.$docRequest->reject_reason : 'This request was cancelled.' }}</div>
                    @else
                        @include('partials.stepper', ['status' => $docRequest->status])
                        @if ($docRequest->status === 'Pending Payment')
                            <div class="note-success">Pay the fee on the Payments page so processing can start.</div>
                        @elseif ($docRequest->status === 'Ready for Pickup')
                            <div class="note-success">
                                {{ $docRequest->release_method === 'E-copy'
                                    ? 'Your certified e-copy is ready. Open this request to download it.'
                                    : 'Claim at the Registrar'.($docRequest->claim_date ? ' on or after '.$docRequest->claim_date->format('M j, Y') : '').'. Bring your claim stub and school ID.' }}
                            </div>
                        @endif
                    @endif
                </a>
            @empty
                <div class="panel"><p class="muted mb-0">You have not requested any document yet.</p></div>
            @endforelse
        </section>
    </div>
@endsection
