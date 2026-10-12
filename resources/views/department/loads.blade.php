@extends('layouts.app')

@section('eyebrow', $department->code.' · '.($term?->label ?? 'No active term'))
@section('title', 'Subject loads')
@section('lead', 'Choose the professor who handles each subject. The subject then appears in that professor\'s My classes, where the grades are encoded.')

@section('content')
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Subject</th><th scope="col">Title</th><th scope="col">Section</th><th scope="col" class="text-end">Students</th><th scope="col">Grade sheet</th><th scope="col">Professor</th></tr>
                </thead>
                <tbody>
                    @forelse ($sections as $section)
                        @php $locked = in_array($section->sheet_status, ['Submitted', 'Approved'], true); @endphp
                        <tr>
                            <th scope="row" class="fw-medium text-nowrap">{{ $section->subject->code }}</th>
                            <td>{{ $section->subject->title }}</td>
                            <td class="text-nowrap">{{ $section->section_code }}</td>
                            <td class="text-end">{{ $section->enrollments_count }}</td>
                            <td>@include('partials.status', ['status' => $section->sheet_status])</td>
                            <td>
                                @if ($locked)
                                    {{ $section->faculty->name }}
                                @else
                                    <form method="POST" action="{{ route('faculty.loads.assign', $section) }}" class="d-flex gap-2">
                                        @csrf
                                        @method('PUT')
                                        <label for="faculty_{{ $section->id }}" class="visually-hidden">Professor for {{ $section->subject->code }}</label>
                                        <select id="faculty_{{ $section->id }}" name="faculty_id" class="form-select form-select-sm">
                                            @unless ($instructors->contains('id', $section->faculty_id))
                                                <option value="" selected disabled>{{ $section->faculty->name }}</option>
                                            @endunless
                                            @foreach ($instructors as $instructor)
                                                <option value="{{ $instructor->id }}" @selected($instructor->id === $section->faculty_id)>{{ $instructor->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No subjects are offered by your department this term.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-secondary mt-3">A subject whose grade sheet is submitted or approved keeps its professor. Professor accounts are created by the Administrator.</p>
@endsection
