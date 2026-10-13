@extends('layouts.app')

@section('eyebrow', 'Regular student · '.$student->program->code.' '.$student->year_level)
@section('title', 'Enrollment')
@section('lead', ($term ? "For {$term->label}. " : '').'These are the subjects your curriculum gives your year level this semester. Once the Registrar opens enrollment, you can take all of them in one step, with no need to line up.')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="overview">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th scope="col">Subject</th><th scope="col">Title</th><th scope="col" class="text-end">Units</th><th scope="col">Section</th><th scope="col">Professor</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($subjects as $row)
                            <tr>
                                <th scope="row" class="fw-medium text-nowrap">{{ $row['subject']->code }}</th>
                                <td>{{ $row['subject']->title }}</td>
                                <td class="text-end">{{ $row['subject']->units }}</td>
                                @if ($row['section'])
                                    <td class="text-nowrap">{{ $row['section']->section_code }}</td>
                                    <td class="muted">{{ $row['section']->faculty->name }}</td>
                                @else
                                    <td colspan="2" class="muted small">Not offered yet this term</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-secondary">{{ $term ? 'Your curriculum has no subject for your year level this semester.' : 'No term is active.' }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($subjects->isNotEmpty())
                        <tfoot><tr><th scope="row" colspan="2">Total</th><td class="text-end fw-medium">{{ $subjects->sum(fn ($row) => $row['subject']->units) }}</td><td colspan="2"></td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="stack">
            <section class="hero queue-ticket" aria-labelledby="enroll-title">
                <div class="eyebrow" id="enroll-title">Enrollment</div>
                <div class="queue-number">{{ $open ? 'Open' : 'Closed' }}</div>

                @if ($enrolled)
                    <p class="title mb-0">You are enrolled in every offered subject of your year level.</p>
                @elseif (! $term)
                    <p class="title mb-0">Enrollment is not open: no term is active.</p>
                @elseif (! $open)
                    <p class="title">The Registrar has not opened enrollment yet.</p>
                    <p class="detail mb-0">This page updates by itself once it opens.</p>
                @elseif ($offered === 0)
                    <p class="title mb-0">None of your subjects is offered yet. Ask your department.</p>
                @else
                    <p class="title">Enrollment is open. Enroll in {{ $offered === 1 ? 'your subject' : "all {$offered} subjects" }} in one step.</p>
                    <form method="POST" action="{{ route('student.enroll') }}" data-confirm="Enroll in {{ $offered }} {{ $offered === 1 ? 'subject' : 'subjects' }} for {{ $term->label }}?">
                        @csrf
                        <button type="submit" class="btn btn-light">Enroll now</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection
