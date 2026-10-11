@extends('layouts.app')

@section('eyebrow', 'Payments')
@section('title', 'Cash payment slip')
@section('lead', 'Show this slip, on your phone or printed, at the Cashier window and pay in cash. The Cashier gives you an official receipt.')

@section('actions')
    <a href="{{ route('student.payments') }}" class="btn btn-outline-secondary">@include('partials.icon', ['name' => 'arrow-left']) Back to payments</a>
    <button type="button" class="btn btn-primary" data-print>@include('partials.icon', ['name' => 'printer']) Print slip</button>
@endsection

@section('content')
    <div class="stub">
        <div class="text-center mb-4">
            <div class="fw-semibold">Pamantasan ng Cabuyao · Cashier's Office</div>
            <div class="muted">Cash Payment Slip</div>
        </div>
        <dl class="row mb-3">
            <dt class="col-5">Student ID</dt><dd class="col-7 font-monospace">{{ $student->student_no }}</dd>
            <dt class="col-5">Name</dt><dd class="col-7">{{ $student->full_name }}</dd>
            <dt class="col-5">Program</dt><dd class="col-7 mb-0">{{ $student->program->code }} {{ $student->year_level }}</dd>
        </dl>
        <table class="table table-sm align-middle">
            <thead><tr><th scope="col">Charge</th><th scope="col" class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($payables as $line)
                    <tr><td>{{ $line->description }}</td><td class="num text-nowrap">₱{{ number_format($line->payable, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="total-row">
            <div class="stat-label">Amount to pay in cash</div>
            <div class="value">₱{{ number_format($total, 2) }}</div>
        </div>
        <p class="small muted mt-4 mb-0">Prepared {{ now()->format('F j, Y g:i A') }}. You may pay the full amount or part of it. Your balance and clearance update on this portal as soon as the Cashier posts the payment.</p>
    </div>
@endsection
