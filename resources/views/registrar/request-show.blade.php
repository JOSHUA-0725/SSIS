@extends('layouts.app')

@section('title', 'Request '.$docRequest->reference_no)

@section('actions')
    <a href="{{ route('registrar.requests', ['status' => $docRequest->status]) }}" class="btn btn-outline-secondary">Back to queue</a>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between">
                    <span>Request details</span>
                    @include('partials.status', ['status' => $docRequest->status])
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-4">Student</dt><dd class="col-8">{{ $docRequest->student->student_no }} · {{ $docRequest->student->full_name }}</dd>
                        <dt class="col-4">Program</dt><dd class="col-8">{{ $docRequest->student->program->code }} {{ $docRequest->student->year_level }}</dd>
                        <dt class="col-4">Document</dt><dd class="col-8">{{ $docRequest->type->name }}</dd>
                        <dt class="col-4">Copies</dt><dd class="col-8">{{ $docRequest->copies }}</dd>
                        <dt class="col-4">Purpose</dt><dd class="col-8">{{ $docRequest->purpose }}</dd>
                        <dt class="col-4">Fee</dt><dd class="col-8">₱{{ number_format($docRequest->total_fee, 2) }}</dd>
                        <dt class="col-4">Release via</dt><dd class="col-8">{{ $docRequest->release_method === 'E-copy' ? 'Certified e-copy' : 'Scheduled pickup' }}</dd>
                        @if ($docRequest->release_method === 'Pickup')
                            <dt class="col-4">Claim date</dt><dd class="col-8">{{ $docRequest->claim_date?->format('F j, Y') ?? '—' }}</dd>
                        @elseif ($docRequest->ecopy_file)
                            <dt class="col-4">E-copy</dt><dd class="col-8"><a href="{{ route('registrar.requests.ecopy', $docRequest) }}">Download the uploaded PDF</a></dd>
                        @endif
                        @if ($docRequest->released_at)
                            <dt class="col-4">Released</dt><dd class="col-8 mb-0">{{ $docRequest->released_at->format('M j, Y g:i A') }} by {{ $docRequest->releaser?->name }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Status history</div>
                <ul class="list-group list-group-flush">
                    @foreach ($docRequest->history as $entry)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-3">
                                <span class="fw-semibold">{{ $entry->status }}</span>
                                <span class="small text-secondary">{{ $entry->created_at->format('M j, Y g:i A') }}</span>
                            </div>
                            <div class="small text-secondary">{{ $entry->user?->name ?? 'System' }}@if ($entry->remarks) · {{ $entry->remarks }}@endif</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">Next step</div>
                <div class="card-body">
                    @switch($docRequest->status)
                        @case('Pending Payment')
                            <p class="mb-0 text-secondary">Waiting for payment. Processing starts after the Cashier posts the payment.</p>
                            @break
                        @case('Paid')
                            <form method="POST" action="{{ route('registrar.requests.update', $docRequest) }}" class="mb-3">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="action" value="process">
                                <button type="submit" class="btn btn-primary">Start processing</button>
                            </form>
                            @include('registrar.partials.reject')
                            @break
                        @case('Processing')
                            @if ($docRequest->release_method === 'E-copy')
                                <form method="POST" action="{{ route('registrar.requests.update', $docRequest) }}" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="action" value="ready">
                                    <div class="col">
                                        <label for="ecopy" class="form-label">Certified e-copy (PDF, up to 5 MB)</label>
                                        <input type="file" id="ecopy" name="ecopy" class="form-control" accept=".pdf" required>
                                    </div>
                                    <div class="col-auto"><button type="submit" class="btn btn-primary">Upload and mark Ready</button></div>
                                </form>
                            @else
                            <form method="POST" action="{{ route('registrar.requests.update', $docRequest) }}" class="row g-2 align-items-end mb-3">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="action" value="ready">
                                <div class="col">
                                    <label for="claim_date" class="form-label">Claim date</label>
                                    <input type="date" id="claim_date" name="claim_date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('claim_date', today()->toDateString()) }}" required>
                                </div>
                                <div class="col-auto"><button type="submit" class="btn btn-primary">Set Ready for Pickup</button></div>
                            </form>
                            @endif
                            @include('registrar.partials.reject')
                            @break
                        @case('Ready for Pickup')
                            @if ($docRequest->release_method === 'E-copy')
                                <p>The student can download the e-copy now. The request is marked Released at the first download, or you can close it here.</p>
                            @else
                                <p>Check the student's claim stub and school ID before releasing.</p>
                            @endif
                            <form method="POST" action="{{ route('registrar.requests.update', $docRequest) }}" data-confirm="Mark this document as released to the student?">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="action" value="release">
                                <button type="submit" class="btn btn-primary">Mark as Released</button>
                            </form>
                            @break
                        @default
                            <p class="mb-0 text-secondary">This request is closed. No further action is available.</p>
                    @endswitch
                </div>
            </div>
        </div>
    </div>
@endsection
