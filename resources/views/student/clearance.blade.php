@extends('layouts.app')

@section('title', 'Enrollment clearance')
@section('lead', ($term ? "For {$term->label}. " : '').'Each office signs off digitally — you are notified the moment one changes.')

@section('actions')
    @if ($clearance && $clearance->overall_status === 'Cleared')
        <a href="{{ route('student.clearance.slip') }}" class="btn btn-primary">@include('partials.icon', ['name' => 'download']) Download clearance slip</a>
    @else
        <button type="button" class="btn btn-primary" disabled aria-describedby="slip-note">@include('partials.icon', ['name' => 'download']) Download clearance slip</button>
    @endif
@endsection

@section('content')
    @if (! $clearance)
        <div class="panel"><p class="muted mb-0">There is no clearance for you this term. Clearance opens for enrolled students when a term is active.</p></div>
    @else
        @foreach ($holds as $hold)
            <div class="banner" role="alert">
                <span class="msg">@include('partials.icon', ['name' => 'alert']) <span><strong>{{ $hold->office->name }}:</strong> {{ $hold->remarks }}</span></span>
            </div>
        @endforeach
        @if ($cashierPending && $schoolBalance > 0)
            <div class="banner" role="alert">
                <span class="msg">@include('partials.icon', ['name' => 'alert']) <span><strong>Cashier:</strong> Outstanding balance of ₱{{ number_format($schoolBalance, 2) }}</span></span>
                <a href="{{ route('student.payments') }}" class="btn btn-danger btn-sm">Settle balance</a>
            </div>
        @endif

        <div class="card">
            <div class="progress-line">
                <progress class="bar" value="{{ $cleared }}" max="{{ max($items->count(), 1) }}">{{ $cleared }} of {{ $items->count() }}</progress>
                <span>{{ $cleared }} of {{ $items->count() }} cleared</span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th scope="col">Office</th><th scope="col">Signatory</th><th scope="col">Remarks</th><th scope="col">Signed</th><th scope="col">Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <th scope="row" class="fw-medium">{{ $item->office->name }}</th>
                                <td class="muted">{{ $item->signatory ?? 'To be assigned' }}</td>
                                <td>{{ $item->note }}</td>
                                <td class="muted text-nowrap">{{ $item->status === 'Cleared' && $item->signed_at ? $item->signed_at->format('M j, Y') : '—' }}</td>
                                <td>@include('partials.status', ['status' => $item->display])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($clearance->overall_status !== 'Cleared')
            <p class="small muted mt-3" id="slip-note">The clearance slip can be downloaded once all {{ $items->count() }} offices have cleared you.</p>
        @endif
    @endif
@endsection
