@extends('layouts.app')

@section('title', 'Audit log')
@section('lead', 'Every login, failed login, denied page, and change to a record, with who did it and from where.')

@section('content')
    <form method="GET" class="row g-2 mb-3">
        <div class="col-sm-4">
            <label for="action" class="visually-hidden">Action</label>
            <select id="action" name="action" class="form-select" data-autosubmit>
                <option value="">All actions</option>
                @foreach ($actions as $option)
                    <option @selected($option === $action)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="col">
            <label for="q" class="visually-hidden">Username</label>
            <input type="search" id="q" name="q" class="form-control" value="{{ $q }}" placeholder="Filter by username">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
    </form>

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th scope="col">When</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Details</th><th scope="col">IP address</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at->format('M j, Y g:i:s A') }}</td>
                            <td>{{ $log->user?->username ?? '—' }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->table_name }}@if ($log->record_id) #{{ $log->record_id }}@endif</td>
                            <td class="small text-prewrap">@if ($log->old_value){{ $log->old_value }} → @endif{{ $log->new_value }}</td>
                            <td>{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No audit entries.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $logs->links() }}
@endsection
