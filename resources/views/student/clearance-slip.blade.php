@extends('layouts.app')

@section('title', 'Clearance slip')

@section('actions')
    <a href="{{ route('student.clearance') }}" class="btn btn-outline-secondary">Back</a>
    <button type="button" class="btn btn-primary" data-print>@include('partials.icon', ['name' => 'printer']) Print or save as PDF</button>
@endsection

@section('content')
    <div class="stub">
        <div class="text-center mb-4">
            <div class="fw-semibold">Office of the Registrar</div>
            <h2 class="h4 mt-1 mb-0">Enrollment Clearance Slip</h2>
            <div class="muted">{{ $term->label }}</div>
        </div>
        <dl class="row mb-3">
            <dt class="col-4">Student no.</dt><dd class="col-8">{{ $student->student_no }}</dd>
            <dt class="col-4">Name</dt><dd class="col-8">{{ $student->full_name }}</dd>
            <dt class="col-4">Program</dt><dd class="col-8 mb-0">{{ $student->program->code }} {{ $student->year_level }}</dd>
        </dl>
        <table class="table table-sm align-middle">
            <thead><tr><th scope="col">Office</th><th scope="col">Signed by</th><th scope="col">Date</th><th scope="col">Status</th></tr></thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->office->name }}</td>
                        <td>{{ $item->signer?->name ?? 'System' }}</td>
                        <td>{{ $item->signed_at?->format('M j, Y') ?? '—' }}</td>
                        <td>{{ $item->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="fw-semibold mt-3 mb-1">Cleared for enrollment.</p>
        <p class="small muted mb-0">Clearance no. CL-{{ $term->id }}-{{ str_pad($clearance->id, 6, '0', STR_PAD_LEFT) }} · Issued {{ now()->format('F j, Y g:i A') }} · Verify at the Registrar using the student number.</p>
    </div>
@endsection
