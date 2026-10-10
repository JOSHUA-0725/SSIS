@extends('layouts.app')

@section('title', 'My classes')

@section('content')
    <p class="text-secondary">
        {{ $term?->label ?? 'No active term' }} ·
        Grade encoding is <strong>{{ $term?->encoding_open ? 'open' : 'closed' }}</strong>
    </p>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Subject</th><th scope="col">Section</th><th scope="col" class="text-end">Students</th><th scope="col">Grade sheet</th><th scope="col"></th></tr></thead>
                <tbody>
                    @forelse ($sections as $section)
                        <tr>
                            <td>{{ $section->subject->code }} · {{ $section->subject->title }}</td>
                            <td>{{ $section->section_code }}</td>
                            <td class="text-end">{{ $section->enrollments_count }}</td>
                            <td>
                                @include('partials.status', ['status' => $section->sheet_status])
                                @if ($section->return_remarks) <div class="small">Remarks: {{ $section->return_remarks }}</div> @endif
                            </td>
                            <td class="text-end"><a href="{{ route('faculty.encode', $section) }}" class="btn btn-sm btn-primary">Open grade sheet</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No class sections are assigned to you this term.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
