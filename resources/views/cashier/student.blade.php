@extends('layouts.app')

@section('title', 'Student ledger')

@section('actions')
    <a href="{{ route('cashier.payments') }}" class="btn btn-outline-secondary">Find another student</a>
@endsection

@section('content')
    @php $selected = old('assessment_id', request('pay', $unpaid->first()?->id)); @endphp

    <div class="card bg-body-tertiary mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between gap-3">
            <div>
                <div class="fw-semibold">{{ $student->student_no }} · {{ $student->full_name }}</div>
                <div class="text-secondary">{{ $student->program->code }} {{ $student->year_level }} · {{ $student->status }}</div>
            </div>
            <div class="text-end">
                <div class="text-secondary">Balance due</div>
                <div class="stat-value">₱{{ number_format($balance, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Post a payment</div>
                <div class="card-body">
                    @if ($unpaid->isEmpty())
                        <p class="text-secondary mb-0">This student has no unpaid charges.</p>
                    @else
                        <form method="POST" action="{{ route('cashier.payments.store', $student) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="assessment_id" class="form-label">Pay for</label>
                                <select id="assessment_id" name="assessment_id" class="form-select" required>
                                    @foreach ($unpaid as $assessment)
                                        <option value="{{ $assessment->id }}" @selected($selected == $assessment->id)>
                                            {{ $assessment->fee_type }}: {{ $assessment->description }} — balance ₱{{ number_format($assessment->balance, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="amount" class="form-label">Amount (₱)</label>
                                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" value="{{ old('amount', $unpaid->firstWhere('id', $selected)?->balance) }}" required>
                                </div>
                                <div class="col-sm-6">
                                    <label for="or_number" class="form-label">OR number</label>
                                    <input type="text" id="or_number" name="or_number" class="form-control" maxlength="20" value="{{ old('or_number') }}" required>
                                </div>
                            </div>
                            <div class="row g-3 align-items-end">
                                <div class="col-sm-6">
                                    <label for="method" class="form-label">Method</label>
                                    <select id="method" name="method" class="form-select">
                                        @foreach (['Cash', 'Bank Deposit', 'E-wallet', 'GCash', 'Maya', 'Online Banking'] as $method)
                                            <option @selected(old('method') === $method)>{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-6"><button type="submit" class="btn btn-primary w-100">Post payment</button></div>
                            </div>
                            <p class="form-text mb-0 mt-3">Paying a document fee in full sets the linked request to Paid.</p>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Add a charge · {{ $term?->label ?? 'no active term' }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('cashier.assessments.store', $student) }}">
                        @csrf
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="fee_type" class="form-label">Fee type</label>
                                <select id="fee_type" name="fee_type" class="form-select">
                                    @foreach (['Tuition', 'Miscellaneous', 'Laboratory', 'Other'] as $type)
                                        <option>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label for="charge_amount" class="form-label">Amount (₱)</label>
                                <input type="number" id="charge_amount" name="amount" class="form-control" step="0.01" min="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" id="description" name="description" class="form-control" maxlength="150" required>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Add charge</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Charges</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Term</th><th scope="col">Type</th><th scope="col">Description</th><th scope="col" class="text-end">Amount</th><th scope="col" class="text-end">Balance</th></tr></thead>
                <tbody>
                    @forelse ($assessments as $assessment)
                        <tr>
                            <td>{{ $assessment->term->label }}</td>
                            <td>{{ $assessment->fee_type }}</td>
                            <td>{{ $assessment->description }}</td>
                            <td class="text-end">₱{{ number_format($assessment->amount, 2) }}</td>
                            <td class="text-end fw-semibold">₱{{ number_format($assessment->balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No charges.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Payments</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Date</th><th scope="col">OR number</th><th scope="col">For</th><th scope="col">Method</th><th scope="col" class="text-end">Amount</th><th scope="col">Status</th><th scope="col">Posted by</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at->format('M j, Y') }}</td>
                            <td>{{ $payment->or_number ?? $payment->reference_no ?? '—' }}</td>
                            <td>{{ $payment->assessment->description }}</td>
                            <td>{{ $payment->method }}</td>
                            <td class="text-end">₱{{ number_format($payment->amount, 2) }}</td>
                            <td>@include('partials.status', ['status' => $payment->status])</td>
                            <td>{{ $payment->cashier?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary">No payments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
