@extends('layouts.app')

@section('title', 'Grade sheets')
@section('lead', 'Review the sheets your faculty submitted. Approving a sheet locks it.')

@section('content')
    <ul class="nav nav-tabs mb-3">
        @foreach ($statuses as $option)
            <li class="nav-item">
                <a class="nav-link {{ $option === $status ? 'active' : '' }}" href="{{ route('head.sheets', ['status' => $option]) }}" @if ($option === $status) aria-current="page" @endif>{{ $option }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th scope="col">Term</th><th scope="col">Subject</th><th scope="col">Section</th><th scope="col">Faculty</th><th scope="col" class="text-end">Students</th><th scope="col"></th></tr></thead>
                <tbody>
                    @forelse ($sections as $section)
                        <tr>
                            <td>{{ $section->term->label }}</td>
                            <td>{{ $section->subject->code }} · {{ $section->subject->title }}</td>
                            <td>{{ $section->section_code }}</td>
                            <td>{{ $section->faculty->name }}</td>
                            <td class="text-end">{{ $section->enrollments_count }}</td>
                            <td class="text-end"><a href="{{ route('head.sheet', $section) }}" class="btn btn-sm btn-primary">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No {{ strtolower($status) }} grade sheets in your department.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
