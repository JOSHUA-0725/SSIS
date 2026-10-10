@extends('layouts.app')

@section('title', 'Claim stub')

@section('actions')
    <a href="{{ route('student.requests.show', $docRequest) }}" class="btn btn-outline-secondary">Back</a>
    <button type="button" class="btn btn-primary" data-print>@include('partials.icon', ['name' => 'printer']) Print or save as PDF</button>
@endsection

@section('content')
    <div class="stub">
        <div class="text-center mb-3">
            <div class="fw-semibold">Office of the Registrar</div>
            <div class="muted">Document Claim Stub</div>
        </div>
        <p class="text-center fs-4 fw-semibold font-monospace mb-4">{{ $docRequest->reference_no }}</p>
        <dl class="row mb-0">
            <dt class="col-5">Student no.</dt><dd class="col-7">{{ $docRequest->student->student_no }}</dd>
            <dt class="col-5">Name</dt><dd class="col-7">{{ $docRequest->student->full_name }}</dd>
            <dt class="col-5">Program</dt><dd class="col-7">{{ $docRequest->student->program->code }} {{ $docRequest->student->year_level }}</dd>
            <dt class="col-5">Document</dt><dd class="col-7">{{ $docRequest->type->name }}</dd>
            <dt class="col-5">Copies</dt><dd class="col-7">{{ $docRequest->copies }}</dd>
            <dt class="col-5">Fee paid</dt><dd class="col-7">₱{{ number_format($docRequest->total_fee, 2) }}</dd>
            <dt class="col-5">Status</dt><dd class="col-7">{{ $docRequest->status }}</dd>
            <dt class="col-5">Claim date</dt><dd class="col-7 mb-0">{{ $docRequest->claim_date?->format('F j, Y') ?? 'To be announced' }}</dd>
        </dl>
        <p class="small muted mt-4 mb-0">Present this stub and your school ID at the Registrar's window.</p>
    </div>
@endsection
