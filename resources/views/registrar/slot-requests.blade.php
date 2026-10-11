@extends('layouts.app')

@section('title', 'Slot requests')
@section('lead', 'Departments ask here for the number of irregular and first-year students they may enroll. Approved slots add up per department and term.')

@section('content')
    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Requested</th><th scope="col">Department</th><th scope="col">Term</th><th scope="col">Students</th><th scope="col" class="text-end">Slots</th><th scope="col">Requested by</th><th scope="col">Status</th><th scope="col"></th></tr>
                </thead>
                <tbody>
                    @forelse ($requests as $slotRequest)
                        <tr>
                            <td class="muted text-nowrap">{{ $slotRequest->created_at->format('M j, Y g:i A') }}</td>
                            <td>{{ $slotRequest->department->code }}</td>
                            <td>{{ $slotRequest->term->label }}</td>
                            <td>{{ $slotRequest->category }}</td>
                            <td class="text-end">{{ $slotRequest->slots }}</td>
                            <td>{{ $slotRequest->requester->name }}</td>
                            <td>
                                @include('partials.status', ['status' => $slotRequest->status])
                                @if ($slotRequest->remarks) <div class="small muted">{{ $slotRequest->remarks }}</div> @endif
                            </td>
                            <td class="text-end">
                                @if ($slotRequest->status === 'Pending')
                                    <form method="POST" action="{{ route('registrar.slots.decide', $slotRequest) }}" class="d-flex gap-2 justify-content-end">
                                        @csrf
                                        @method('PUT')
                                        <label for="remarks_{{ $slotRequest->id }}" class="visually-hidden">Remarks</label>
                                        <input type="text" id="remarks_{{ $slotRequest->id }}" name="remarks" class="form-control form-control-sm" maxlength="255" placeholder="Remarks (optional)">
                                        <button type="submit" name="status" value="Approved" class="btn btn-sm btn-primary">Approve</button>
                                        <button type="submit" name="status" value="Rejected" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                @else
                                    <span class="small muted text-nowrap">{{ $slotRequest->decider?->name }} · {{ $slotRequest->decided_at?->format('M j, g:i A') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-secondary">No slot requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $requests->links() }}
    <p class="small text-secondary">Every decision on this page is written to the audit log.</p>
@endsection
