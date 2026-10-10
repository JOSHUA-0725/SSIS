@extends('layouts.app')

@section('title', 'Post payment')
@section('lead', 'Find a student to receive a payment over the counter, or pick an unpaid document request below.')

@section('content')
    @include('partials.search', ['placeholder' => 'Search student no. or name'])

    @if ($q !== '')
        <div class="card mb-4">
            <div class="card-header">Search results</div>
            <div class="list-group list-group-flush">
                @forelse ($students as $student)
                    <a href="{{ route('cashier.student', $student) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span>{{ $student->student_no }} · {{ $student->full_name }}</span>
                        <span class="text-secondary">{{ $student->program->code }} {{ $student->year_level }}</span>
                    </a>
                @empty
                    <div class="list-group-item text-secondary">No students found.</div>
                @endforelse
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">Document requests waiting for payment</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Reference no.</th><th scope="col">Student</th><th scope="col">Description</th><th scope="col" class="text-end">Amount</th><th scope="col"></th></tr></thead>
                <tbody>
                    @forelse ($pending as $assessment)
                        <tr>
                            <td>{{ $assessment->documentRequest->reference_no }}</td>
                            <td>{{ $assessment->student->student_no }} · {{ $assessment->student->full_name }}</td>
                            <td>{{ $assessment->description }}</td>
                            <td class="text-end">₱{{ number_format($assessment->amount, 2) }}</td>
                            <td class="text-end"><a href="{{ route('cashier.student', ['student' => $assessment->student, 'pay' => $assessment->id]) }}" class="btn btn-sm btn-primary">Receive payment</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No unpaid document requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
