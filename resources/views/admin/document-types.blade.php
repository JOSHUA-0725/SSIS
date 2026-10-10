@extends('layouts.app')

@section('title', 'Document types and fees')
@section('lead', 'What students can request, what each copy costs, and how long it takes.')

@section('content')
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Document</th><th scope="col">Fee per copy (₱)</th><th scope="col">Processing days</th><th scope="col">Offered</th><th scope="col"></th></tr></thead>
                <tbody>
                    @foreach ($types as $type)
                        <tr>
                            <td>
                                <label for="name_{{ $type->id }}" class="visually-hidden">Name</label>
                                <input form="type_{{ $type->id }}" type="text" id="name_{{ $type->id }}" name="name" class="form-control mb-1" maxlength="80" value="{{ $type->name }}" required>
                                <label for="desc_{{ $type->id }}" class="visually-hidden">Description of {{ $type->name }}</label>
                                <input form="type_{{ $type->id }}" type="text" id="desc_{{ $type->id }}" name="description" class="form-control form-control-sm" maxlength="120" value="{{ $type->description }}" placeholder="Short description shown to students">
                            </td>
                            <td>
                                <label for="fee_{{ $type->id }}" class="visually-hidden">Fee per copy of {{ $type->name }}</label>
                                <input form="type_{{ $type->id }}" type="number" id="fee_{{ $type->id }}" name="fee_per_copy" class="form-control" step="0.01" min="0" value="{{ number_format($type->fee_per_copy, 2, '.', '') }}" required>
                            </td>
                            <td>
                                <label for="days_{{ $type->id }}" class="visually-hidden">Processing days of {{ $type->name }}</label>
                                <input form="type_{{ $type->id }}" type="number" id="days_{{ $type->id }}" name="processing_days" class="form-control" min="1" max="60" value="{{ $type->processing_days }}" required>
                            </td>
                            <td>
                                <label for="active_{{ $type->id }}" class="visually-hidden">Offered</label>
                                <select form="type_{{ $type->id }}" id="active_{{ $type->id }}" name="is_active" class="form-select">
                                    <option value="1" @selected($type->is_active)>Yes</option>
                                    <option value="0" @selected(! $type->is_active)>No</option>
                                </select>
                            </td>
                            <td class="text-end">
                                <form id="type_{{ $type->id }}" method="POST" action="{{ route('admin.doctypes.update', $type) }}">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card col-lg-8">
        <div class="card-header">Add a document type</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.doctypes.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label for="new_name" class="form-label">Document</label>
                    <input type="text" id="new_name" name="name" class="form-control" maxlength="80" required>
                </div>
                <div class="col-md-7">
                    <label for="new_desc" class="form-label">Description</label>
                    <input type="text" id="new_desc" name="description" class="form-control" maxlength="120">
                </div>
                <div class="col-md-3">
                    <label for="new_fee" class="form-label">Fee per copy (₱)</label>
                    <input type="number" id="new_fee" name="fee_per_copy" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="col-md-2">
                    <label for="new_days" class="form-label">Days</label>
                    <input type="number" id="new_days" name="processing_days" class="form-control" min="1" max="60" value="3" required>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Add</button></div>
            </form>
        </div>
    </div>
@endsection
