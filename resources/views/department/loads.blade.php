@extends('layouts.app')

@section('eyebrow', $department->code.' · '.($term?->label ?? 'No active term'))
@section('title', 'Subject loads')
@section('lead', 'Subjects are offered automatically when the Registrar opens enrollment. For each class, choose an available professor and the section they handle. The year level comes from the curriculum. The subject then appears in that professor\'s My classes, where the grades are encoded.')

@section('content')
    @php
        // "Prof. Liza Santos · 2 classes" tells Faculty who still has room this semester.
        $load = fn ($instructor) => $instructor->name.' · '.($loadCounts[$instructor->id] ?? 0).' '.(($loadCounts[$instructor->id] ?? 0) === 1 ? 'class' : 'classes');
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th scope="col">Subject</th><th scope="col">Title</th><th scope="col">Year</th><th scope="col" class="text-end">Students</th><th scope="col">Professor</th><th scope="col">Section</th><th scope="col"><span class="visually-hidden">Save</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($sections as $section)
                        @php
                            $locked = in_array($section->sheet_status, ['Submitted', 'Approved'], true);
                            $block = $section->block();
                            $placed = $curriculum->get($section->subject_id);
                            $year = $placed?->year_level ?? ($block['year'] ?? null);
                            $current = $block ? $block['program'].'-'.$block['letter'] : null;
                            // A curriculum subject is offered only to sections of its own program.
                            $choices = $placed ? array_values(array_filter($blocks, fn ($value) => str_starts_with($value, $placed->program->code.'-'))) : $blocks;
                            // "BSIT 3A" when the year is fixed; "BSIT A" when Faculty still chooses the year beside it.
                            $name = fn (string $value) => $year && ($placed || $locked) ? str_replace('-', ' '.$year, $value) : str_replace('-', ' ', $value);
                            $form = 'load_'.$section->id;
                        @endphp
                        <tr>
                            <th scope="row" class="fw-medium text-nowrap">{{ $section->subject->code }}</th>
                            <td>{{ $section->subject->title }}</td>
                            <td class="text-nowrap">
                                @if ($placed || $locked)
                                    {{ $year ? ($years[$year] ?? $year) : '—' }}
                                @else
                                    <label for="year_{{ $section->id }}" class="visually-hidden">Year level for {{ $section->subject->code }}</label>
                                    <select id="year_{{ $section->id }}" name="year_level" form="{{ $form }}" class="form-select form-select-sm load-select-sm">
                                        @unless ($block)
                                            <option value="" selected disabled>Choose year</option>
                                        @endunless
                                        @foreach ($years as $value => $label)
                                            <option value="{{ $value }}" @selected($block && $block['year'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="text-end">{{ $section->enrollments_count }}</td>
                            @if ($locked)
                                <td>{{ $section->faculty->name }}</td>
                                <td class="text-nowrap">{{ $current ? $name($current) : $section->section_code }}</td>
                                <td class="small text-secondary">Grades already submitted</td>
                            @else
                                <td>
                                    <label for="faculty_{{ $section->id }}" class="visually-hidden">Professor for {{ $section->subject->code }}</label>
                                    <select id="faculty_{{ $section->id }}" name="faculty_id" form="{{ $form }}" class="form-select form-select-sm load-select">
                                        @unless ($instructors->contains('id', $section->faculty_id))
                                            <option value="" selected disabled>{{ $section->faculty->name }}</option>
                                        @endunless
                                        @foreach ($instructors as $instructor)
                                            <option value="{{ $instructor->id }}" @selected($instructor->id === $section->faculty_id)>{{ $load($instructor) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <label for="section_{{ $section->id }}" class="visually-hidden">Section for {{ $section->subject->code }}</label>
                                    <select id="section_{{ $section->id }}" name="section" form="{{ $form }}" class="form-select form-select-sm load-select-sm">
                                        @unless ($current && in_array($current, $choices, true))
                                            <option value="" selected disabled>{{ $current ? $name($current) : $section->section_code }}</option>
                                        @endunless
                                        @foreach ($choices as $value)
                                            <option value="{{ $value }}" @selected($current === $value)>{{ $name($value) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-end">
                                    <form id="{{ $form }}" method="POST" action="{{ route('faculty.loads.assign', $section) }}">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-secondary">No subjects are offered by your department this term.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-secondary mt-3">A subject whose grades are already submitted keeps its professor and section. Professor accounts are created by the Administrator.</p>

    @if ($term && $offerable->isNotEmpty())
        <div class="card mt-4">
            <div class="card-body">
                <h2 class="h5">Open a section</h2>
                <p class="text-secondary">Add a subject of this semester, or another section of one already listed, and give it to an available professor.</p>
                <form method="POST" action="{{ route('faculty.loads.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-12 col-lg-5">
                        <label for="new_subject" class="form-label">Subject</label>
                        <select id="new_subject" name="subject_id" class="form-select" required>
                            <option value="" selected disabled>Choose a subject</option>
                            @foreach ($offerable->groupBy('year_level') as $level => $rows)
                                <optgroup label="{{ $years[$level] ?? "Year {$level}" }}">
                                    @foreach ($rows as $row)
                                        <option value="{{ $row->subject_id }}" @selected((int) old('subject_id') === $row->subject_id)>{{ $row->subject->code }} · {{ $row->subject->title }} ({{ $row->program->code }})</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-2">
                        <label for="new_letter" class="form-label">Section</label>
                        <select id="new_letter" name="letter" class="form-select" required>
                            @foreach ($letters as $letter)
                                <option value="{{ $letter }}" @selected(old('letter') === $letter)>Section {{ $letter }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-lg-3">
                        <label for="new_faculty" class="form-label">Professor</label>
                        <select id="new_faculty" name="faculty_id" class="form-select" required>
                            <option value="" selected disabled>Choose a professor</option>
                            @foreach ($instructors as $instructor)
                                <option value="{{ $instructor->id }}" @selected((int) old('faculty_id') === $instructor->id)>{{ $load($instructor) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <button type="submit" class="btn btn-primary w-100">Add section</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
