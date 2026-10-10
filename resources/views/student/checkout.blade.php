@extends('layouts.app')

@section('eyebrow', 'Payments')
@section('title', 'Confirm '.($method === 'Online Banking' ? 'online banking' : $method).' payment')

@section('content')
    <div class="split">
        <section class="panel" aria-label="Payment summary">
            <div class="stat-label">You are paying</div>
            <div class="amount-xl">₱{{ number_format($total, 2) }}</div>
            <ul class="lines">
                @foreach ($payables as $line)
                    <li>
                        <span class="muted">{{ $line->description }}</span>
                        <span class="text-nowrap">₱{{ number_format($line->payable, 2) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="panel" aria-labelledby="confirm-title">
            <h2 class="panel-title mb-2" id="confirm-title">{{ $method === 'Online Banking' ? 'Online banking' : $method }}</h2>
            <div class="alert alert-warning">
                <strong>Demo payment channel.</strong> This system is not yet connected to a live {{ $method === 'Online Banking' ? 'bank' : $method }} merchant account, so no real money is moved. Confirming records the payment with a reference number, exactly as a live gateway would report it.
            </div>
            <p class="muted">After you confirm, the payment shows as Verifying. Your balance, clearance, and document requests update when the Cashier confirms it.</p>
            <form method="POST" action="{{ route('student.payments.pay') }}" class="d-flex flex-wrap gap-2">
                @csrf
                <input type="hidden" name="method" value="{{ $method }}">
                <a href="{{ route('student.payments') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary flex-grow-1">Confirm payment of ₱{{ number_format($total, 2) }}</button>
            </form>
        </section>
    </div>
@endsection
