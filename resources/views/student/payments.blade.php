@extends('layouts.app')

@section('title', 'Payments')
@section('lead', 'Pay fees in cash at the Cashier. Payments are reflected in your balance and clearance as soon as the Cashier posts them.')

@section('content')
    <section class="panel mb-4" aria-label="Outstanding balance">
        <div class="pay-grid">
            <div>
                <div class="stat-label">Outstanding balance</div>
                <div class="amount-xl">₱{{ number_format($balance, 2) }}</div>

                @if ($payables->isEmpty())
                    <p class="muted mt-3 mb-0">No charges yet. You have nothing to pay.</p>
                @else
                    <ul class="lines">
                        @foreach ($payables as $line)
                            <li>
                                <span class="muted">{{ $line->description }}</span>
                                <span class="text-nowrap">₱{{ number_format($line->balance, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($pending > 0)
                    <p class="small mt-3 mb-0"><span class="pill pill-warning">Verifying</span> ₱{{ number_format($pending, 2) }} already sent and waiting for the Cashier.</p>
                @endif
            </div>

            <div>
                @if ($payable > 0)
                    <div class="stat-label mb-2">How to pay</div>
                    <p class="fw-medium mb-1">Pay in cash at the Cashier window on campus</p>
                    <p class="muted small">Show your payment slip, on your phone or printed. The Cashier posts the payment and gives you an official receipt.</p>
                    <a href="{{ route('student.payments.slip') }}" class="btn btn-primary btn-lg w-100">@include('partials.icon', ['name' => 'receipt']) Get a cash payment slip</a>
                @elseif ($pending > 0)
                    <div class="stat-label mb-2">Nothing more to pay right now</div>
                    <p class="muted mb-0">Your payment is with the Cashier for verification. Your balance and clearance update as soon as it is confirmed.</p>
                @else
                    <div class="stat-label mb-2">You are fully paid</div>
                    <p class="muted mb-0">There is nothing to pay. New charges, such as document fees, appear here.</p>
                @endif
            </div>
        </div>
    </section>

    <h2 class="panel-title mb-3">Payment history</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr><th scope="col">Date</th><th scope="col">Description</th><th scope="col">Method</th><th scope="col">Reference</th><th scope="col">Status</th><th scope="col" class="num">Amount</th></tr>
                </thead>
                <tbody>
                    @forelse ($history as $payment)
                        <tr>
                            <td class="muted text-nowrap">{{ $payment->paid_at->format('M j, Y') }}</td>
                            <td class="fw-medium">{{ $payment->assessment->description }}</td>
                            <td>{{ $payment->method }}</td>
                            <td class="mono text-nowrap">{{ $payment->reference_no ?? $payment->or_number ?? '—' }}</td>
                            <td>@include('partials.status', ['status' => $payment->status])</td>
                            <td class="num text-nowrap">₱{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
