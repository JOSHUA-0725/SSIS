@extends('layouts.app')

@section('title', 'Clearance · '.$office->name)

@section('content')
    <p class="text-secondary">{{ $term?->label ?? 'No active term' }}</p>

    <ul class="nav nav-tabs mb-3">
        @foreach ($statuses as $option)
            <li class="nav-item">
                <a class="nav-link {{ $option === $status ? 'active' : '' }}" href="{{ route('clearance.index', ['status' => $option]) }}" @if ($option === $status) aria-current="page" @endif>{{ $option }}</a>
            </li>
        @endforeach
    </ul>

    @include('partials.search', ['placeholder' => 'Search student no. or name', 'keep' => ['status' => $status]])

    @forelse ($items as $item)
        @php
            $student = $item->clearance->student;
            $others = $item->clearance->items->where('id', '!=', $item->id)->sortBy('office.sequence_no');
            $blocked = $office->code === 'REG' ? $others->where('status', '!=', 'Cleared') : collect();
        @endphp
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-5">
                        <div class="fw-semibold">{{ $student->student_no }} · {{ $student->full_name }}</div>
                        <div class="text-secondary">{{ $student->program->code }} {{ $student->year_level }}</div>
                        @if ($item->remarks) <div class="small">Remarks: {{ $item->remarks }}</div> @endif
                        @if ($office->code === 'REG')
                            <div class="small mt-1">
                                @foreach ($others as $other)
                                    <span class="me-2 text-nowrap">{{ $other->office->name }}: @include('partials.status', ['status' => $other->status])</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-7">
                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-end">
                            @if ($item->status !== 'Cleared')
                                <form method="POST" action="{{ route('clearance.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="Cleared">
                                    <button type="submit" class="btn btn-success" @disabled($blocked->isNotEmpty())>Clear</button>
                                </form>
                            @endif
                            @if ($item->status !== 'On Hold')
                                <form method="POST" action="{{ route('clearance.update', $item) }}" class="d-flex gap-2 align-items-end">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="On Hold">
                                    <div>
                                        <label for="remarks_{{ $item->id }}" class="visually-hidden">Reason for the hold on {{ $student->student_no }}</label>
                                        <input type="text" id="remarks_{{ $item->id }}" name="remarks" class="form-control" placeholder="Reason for hold" maxlength="255" required>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger text-nowrap">Place on hold</button>
                                </form>
                            @endif
                            @if ($item->status !== 'Pending')
                                <form method="POST" action="{{ route('clearance.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="Pending">
                                    <button type="submit" class="btn btn-outline-secondary text-nowrap">Reset to pending</button>
                                </form>
                            @endif
                        </div>
                        @if ($blocked->isNotEmpty())
                            <div class="small text-secondary text-lg-end mt-1">Final clearance is available after: {{ $blocked->pluck('office.name')->join(', ') }}.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <p class="text-secondary">No students with {{ strtolower($status) }} clearance for this office.</p>
    @endforelse

    {{ $items->links() }}
@endsection
