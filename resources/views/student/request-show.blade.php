@extends('layouts.app')

@section('eyebrow', $docRequest->reference_no.' · filed '.$docRequest->created_at->format('M j, Y'))
@section('title', $docRequest->type->name)

@section('actions')
    <a href="{{ route('student.requests') }}" class="btn btn-outline-secondary">@include('partials.icon', ['name' => 'arrow-left']) All requests</a>
@endsection

@section('content')
    @php $closed = in_array($docRequest->status, ['Rejected', 'Cancelled'], true); @endphp

    <div class="split">
        <div>
            <section class="panel mb-4" aria-label="Progress">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div class="muted">
                        {{ $docRequest->copies }} {{ Str::plural('copy', $docRequest->copies) }} · {{ $docRequest->purpose }} · ₱{{ number_format($docRequest->total_fee, 2) }}<br>
                        {{ $docRequest->release_method === 'E-copy' ? 'Certified e-copy' : 'Scheduled pickup' }}
                    </div>
                    @include('partials.status', ['status' => $docRequest->status])
                </div>

                @if ($closed)
                    <div class="alert alert-danger mb-0">
                        This request was {{ strtolower($docRequest->status) }}.
                        @if ($docRequest->reject_reason) Reason: {{ $docRequest->reject_reason }} @endif
                    </div>
                @else
                    @include('partials.stepper', ['status' => $docRequest->status])
                @endif
            </section>

            <section class="panel" aria-labelledby="history-title">
                <h2 class="panel-title mb-3" id="history-title">History</h2>
                <ol class="timeline">
                    @foreach ($docRequest->history as $entry)
                        <li>
                            <div class="fw-medium">{{ $steps[$entry->status] ?? $entry->status }}</div>
                            <div class="small muted">{{ $entry->created_at->format('M j, Y g:i A') }}@if ($entry->remarks) · {{ $entry->remarks }}@endif</div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <div>
            @if ($docRequest->status === 'Pending Payment')
                <section class="hero mb-4" aria-label="Payment">
                    <div class="eyebrow">Fee to pay</div>
                    <div class="amount">₱{{ number_format($docRequest->total_fee, 2) }}</div>
                    @if ($pendingProof)
                        <p class="detail mt-2 mb-0">Your payment is waiting for the Cashier to verify.</p>
                    @else
                        <a href="{{ route('student.payments') }}" class="btn btn-light">Pay online now</a>
                    @endif
                </section>

                @unless ($pendingProof)
                    <section class="panel mb-4" aria-labelledby="proof-title">
                        <h2 class="panel-title mb-1" id="proof-title">Already paid at a bank?</h2>
                        <p class="small muted">Upload the deposit slip or transfer receipt and the Cashier will verify it.</p>
                        <form method="POST" action="{{ route('student.requests.proof', $docRequest) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="method" class="form-label">Paid through</label>
                                <select id="method" name="method" class="form-select" required>
                                    <option>Bank Deposit</option>
                                    <option>E-wallet</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="proof" class="form-label">Proof of payment</label>
                                <input type="file" id="proof" name="proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required aria-describedby="proofHelp">
                                <div id="proofHelp" class="form-text">JPG, PNG, or PDF, up to 2 MB.</div>
                            </div>
                            <button type="submit" class="btn btn-outline-secondary">Upload proof</button>
                        </form>
                    </section>
                @endunless

                <form method="POST" action="{{ route('student.requests.cancel', $docRequest) }}" data-confirm="Cancel this request?">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Cancel request</button>
                </form>
            @elseif (! $closed && $docRequest->release_method === 'E-copy')
                <section class="panel" aria-labelledby="ecopy-title">
                    <h2 class="panel-title mb-2" id="ecopy-title">Certified e-copy</h2>
                    @if ($docRequest->ecopy_file && in_array($docRequest->status, ['Ready for Pickup', 'Released'], true))
                        <p class="muted">Your document is ready. The PDF is certified by the Registrar.</p>
                        <a href="{{ route('student.requests.ecopy', $docRequest) }}" class="btn btn-primary">@include('partials.icon', ['name' => 'download']) Download e-copy (PDF)</a>
                    @else
                        <p class="muted mb-0">The download button appears here once the Registrar uploads your certified e-copy.</p>
                    @endif
                </section>
            @elseif (! $closed)
                <section class="panel" aria-labelledby="claim-title">
                    <h2 class="panel-title mb-2" id="claim-title">Claiming</h2>
                    @if ($docRequest->status === 'Released')
                        <p class="muted">Released {{ $docRequest->released_at?->format('F j, Y') }}.</p>
                    @elseif ($docRequest->claim_date)
                        <p>Claim on or after <strong>{{ $docRequest->claim_date->format('F j, Y') }}</strong> at the Registrar's Office.</p>
                    @else
                        <p class="muted">The claim date appears here once the Registrar finishes preparing the document.</p>
                    @endif
                    <p class="muted">Bring your claim stub and school ID.</p>
                    <a href="{{ route('student.requests.stub', $docRequest) }}" class="btn btn-outline-secondary">@include('partials.icon', ['name' => 'receipt']) Open claim stub</a>
                </section>
            @endif
        </div>
    </div>
@endsection
