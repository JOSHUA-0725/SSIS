@extends('layouts.app')

@section('eyebrow', $department->code.' · '.($term?->label ?? 'No active term'))
@section('title', 'Enrollment')
@section('lead', 'Regular students enroll by block section once you open enrollment. Irregular and first-year students are enrolled within the slots the Registrar approves.')

@section('content')
    <div class="overview mb-4">
        <section class="panel" aria-labelledby="block-title">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="panel-title" id="block-title">Regular students · block sections</h2>
                    <p class="muted small mb-0">While enrollment is open, each regular student can enroll in every subject of their block in one step.</p>
                </div>
                @include('partials.status', ['status' => $open ? 'Open' : 'Closed'])
            </div>
            <div class="d-flex justify-content-between align-items-end gap-3 mt-3">
                <div>
                    <div class="count-big">{{ $regularsEnrolled }}<small>/{{ $regulars }}</small></div>
                    <div class="stat-label">regular students enrolled this term</div>
                </div>
                @if ($term)
                    <form method="POST" action="{{ route('faculty.enrollment.block') }}" data-confirm="{{ $open ? 'Close enrollment for regular students?' : 'Open enrollment for regular students?' }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="open" value="{{ $open ? 0 : 1 }}">
                        <button type="submit" class="btn {{ $open ? 'btn-outline-danger' : 'btn-primary' }}">{{ $open ? 'Close enrollment' : 'Open enrollment' }}</button>
                    </form>
                @endif
            </div>
        </section>

        <div class="stack">
            @foreach ($categories as $category => $count)
                <section class="panel" aria-label="{{ $category }} slots">
                    <div class="stat-label">{{ $category }} slots used</div>
                    <div class="count-big my-1">{{ $count['used'] }}<small>/{{ $count['approved'] }}</small></div>
                    <div class="stat-label">{{ max(0, $count['approved'] - $count['used']) }} left</div>
                </section>
            @endforeach
        </div>
    </div>

    @if ($term)
        <div class="card col-lg-8 mb-4">
            <div class="card-header">Ask the Registrar for slots</div>
            <div class="card-body">
                <form method="POST" action="{{ route('faculty.enrollment.slots') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label for="category" class="form-label">Students</label>
                        <select id="category" name="category" class="form-select" required>
                            @foreach ($categories->keys() as $category)
                                <option @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="slots" class="form-label">Number of slots</label>
                        <input type="number" id="slots" name="slots" class="form-control" min="1" max="2000" value="{{ old('slots') }}" required>
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Send request</button></div>
                </form>
                <p class="form-text mb-0 mt-3">Approved requests add up. Ask again when you need more slots.</p>
            </div>
        </div>
    @endif

    <h2 class="panel-title mb-2">Slot requests this term</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Requested</th><th scope="col">Students</th><th scope="col" class="text-end">Slots</th><th scope="col">Requested by</th><th scope="col">Remarks</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($requests as $slotRequest)
                        <tr>
                            <td class="muted text-nowrap">{{ $slotRequest->created_at->format('M j, Y g:i A') }}</td>
                            <td>{{ $slotRequest->category }}</td>
                            <td class="text-end">{{ $slotRequest->slots }}</td>
                            <td>{{ $slotRequest->requester->name }}</td>
                            <td class="muted">{{ $slotRequest->remarks ?? '—' }}</td>
                            <td>@include('partials.status', ['status' => $slotRequest->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No slot requests yet this term.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
