@extends('layouts.app')

@section('title', 'Daily collection report')

@section('actions')
    <a href="{{ route('cashier.report', ['date' => $date, 'export' => 'csv']) }}" class="btn btn-outline-secondary">Export to Excel (CSV)</a>
    <button type="button" class="btn btn-outline-secondary" data-print>Print or save as PDF</button>
@endsection

@section('content')
    <form method="GET" class="row g-2 align-items-end mb-3 d-print-none">
        <div class="col-auto">
            <label for="date" class="form-label">Date</label>
            <input type="date" id="date" name="date" class="form-control" value="{{ $date }}">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-primary">View</button></div>
    </form>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>{{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }} · {{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }}</span>
            <span class="fw-semibold">Total ₱{{ number_format($total, 2) }}</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">OR number</th><th scope="col">Time</th><th scope="col">Student</th><th scope="col">Description</th><th scope="col">Method</th><th scope="col">Posted by</th><th scope="col" class="text-end">Amount</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->or_number ?? $payment->reference_no }}</td>
                            <td>{{ $payment->paid_at->format('g:i A') }}</td>
                            <td>{{ $payment->assessment->student->student_no }} · {{ $payment->assessment->student->full_name }}</td>
                            <td>{{ $payment->assessment->description }}</td>
                            <td>{{ $payment->method }}</td>
                            <td>{{ $payment->cashier?->name }}</td>
                            <td class="text-end">₱{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary">No payments were posted on this date.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
