@extends('layouts.app')

@section('title', 'Verify payments')
@section('lead', 'Confirm online payments after checking that the money arrived. Confirming updates the student ledger, clearance, and any document request.')

@section('content')
    <h2 class="panel-title mb-3">Online payments</h2>
    @forelse ($online as $reference => $payments)
        @php $student = $payments->first()->assessment->student; @endphp
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-7">
                        <div class="mono">{{ $reference }} · {{ $payments->first()->method }} · {{ $payments->first()->paid_at->format('M j, g:i A') }}</div>
                        <div class="fw-semibold">{{ $student->student_no }} · {{ $student->full_name }}</div>
                        <ul class="list-unstyled small muted mb-0 mt-1">
                            @foreach ($payments as $payment)
                                <li>{{ $payment->assessment->description }} — ₱{{ number_format($payment->amount, 2) }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                            <span class="stat-value me-2">₱{{ number_format($payments->sum('amount'), 2) }}</span>
                            <form method="POST" action="{{ route('cashier.online.decide', $reference) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-primary">Confirm</button>
                            </form>
                            <form method="POST" action="{{ route('cashier.online.decide', $reference) }}" data-confirm="Reject payment {{ $reference }}? The student will be asked to pay again.">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-outline-danger">Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="panel mb-3"><p class="muted mb-0">No online payments are waiting for verification.</p></div>
    @endforelse

    <h2 class="panel-title mt-4 mb-3">Uploaded proofs of payment</h2>
    @forelse ($proofs as $proof)
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <div class="fw-semibold">{{ $proof->assessment->student->student_no }} · {{ $proof->assessment->student->full_name }}</div>
                        <div>{{ $proof->assessment->description }}</div>
                        <div class="muted">₱{{ number_format($proof->amount, 2) }} · {{ $proof->method }} · uploaded {{ $proof->created_at->format('M j, g:i A') }}</div>
                        <a href="{{ route('cashier.proofs.file', $proof) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary mt-2">View uploaded proof</a>
                    </div>
                    <div class="col-lg-5">
                        <form method="POST" action="{{ route('cashier.proofs.decide', $proof) }}" class="row g-2 align-items-end">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="action" value="verify">
                            <div class="col">
                                <label for="or_{{ $proof->id }}" class="form-label">OR number</label>
                                <input type="text" id="or_{{ $proof->id }}" name="or_number" class="form-control" maxlength="20" required>
                            </div>
                            <div class="col-auto"><button type="submit" class="btn btn-primary">Verify and post</button></div>
                        </form>
                    </div>
                    <div class="col-lg-2 text-lg-end">
                        <form method="POST" action="{{ route('cashier.proofs.decide', $proof) }}" data-confirm="Void this proof? The student will be asked to pay again.">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="action" value="void">
                            <button type="submit" class="btn btn-outline-danger">Void</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="panel"><p class="muted mb-0">No uploaded proofs are waiting for verification.</p></div>
    @endforelse
@endsection
