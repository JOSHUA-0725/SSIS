@extends('layouts.app')

@section('title', 'Payments')
@section('lead', 'Pay fees online or in cash at the Cashier. Payments are reflected in your balance and clearance automatically.')

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
                    <form method="GET" action="{{ route('student.payments.checkout') }}">
                        <fieldset>
                            <legend class="form-label">Pay with</legend>
                            @foreach ($methods as $index => $method)
                                @php $name = $method === 'Online Banking' ? 'online banking' : $method; @endphp
                                <label class="choice">
                                    <input type="radio" name="method" value="{{ $method }}" @checked($index === 0) required data-action="Pay ₱{{ number_format($payable, 2) }} with {{ $name }}">
                                    <span class="box"><span class="radio"></span><span class="name">{{ ucfirst($name) }}</span></span>
                                </label>
                            @endforeach
                            <label class="choice">
                                <input type="radio" name="method" value="Cash" required data-action="Get a cash payment slip">
                                <span class="box">
                                    <span class="radio"></span>
                                    <span class="text"><span class="name d-block">Cash</span><span class="sub">Pay at the Cashier window on campus</span></span>
                                </span>
                            </label>
                        </fieldset>
                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-2" data-pay-button>Pay ₱{{ number_format($payable, 2) }}</button>
                    </form>
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
