@extends('layouts.app')

@section('title', 'Document requests')
@section('lead', 'Work the Paid tab from the top. A request reaches it as soon as the Cashier verifies the payment.')

@section('content')
    <ul class="nav nav-tabs mb-3">
        @foreach ($statuses as $option)
            <li class="nav-item">
                <a class="nav-link {{ $option === $status ? 'active' : '' }}" href="{{ route('registrar.requests', ['status' => $option]) }}" @if ($option === $status) aria-current="page" @endif>
                    {{ $option }} ({{ $counts[$option] ?? 0 }})
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Reference no.</th><th scope="col">Student</th><th scope="col">Document</th><th scope="col">Filed</th><th scope="col">Claim date</th><th scope="col"></th></tr>
                </thead>
                <tbody>
                    @forelse ($requests as $docRequest)
                        <tr>
                            <td>{{ $docRequest->reference_no }}</td>
                            <td>{{ $docRequest->student->student_no }} · {{ $docRequest->student->full_name }}</td>
                            <td>{{ $docRequest->type->name }}, {{ $docRequest->copies }} {{ Str::plural('copy', $docRequest->copies) }}</td>
                            <td>{{ $docRequest->created_at->format('M j, Y') }}</td>
                            <td>{{ $docRequest->claim_date?->format('M j, Y') ?? '—' }}</td>
                            <td class="text-end"><a href="{{ route('registrar.requests.show', $docRequest) }}" class="btn btn-sm btn-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No requests with this status.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $requests->links() }}
@endsection
