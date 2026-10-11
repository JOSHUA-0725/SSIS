@extends('layouts.app')

@section('eyebrow', 'Regular student')
@section('title', 'Enrollment')
@section('lead', ($term ? "For {$term->label}. " : '').'Regular students take every subject of one block section, so there is no need to line up.')

@section('content')
    <div class="overview">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr><th scope="col">Subject</th><th scope="col">Title</th><th scope="col" class="text-end">Units</th><th scope="col">Faculty</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($sections as $section)
                            <tr>
                                <th scope="row" class="fw-medium">{{ $section->subject->code }}</th>
                                <td>{{ $section->subject->title }}</td>
                                <td class="text-end">{{ $section->subject->units }}</td>
                                <td class="muted">{{ $section->faculty->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-secondary">{{ $student->block_section ? "Block {$student->block_section} has no subjects yet this term." : 'No block section is set for you yet. Visit the Registrar.' }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($sections->isNotEmpty())
                        <tfoot><tr><th scope="row" colspan="2">Total</th><td class="text-end fw-medium">{{ $sections->sum('subject.units') }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>

        <div class="stack">
            <section class="hero queue-ticket" aria-labelledby="block-title">
                <div class="eyebrow" id="block-title">Your block section</div>
                <div class="queue-number">{{ $student->block_section ?? '—' }}</div>

                @if ($enrolled)
                    <p class="title mb-0">You are enrolled in every subject of your block.</p>
                @elseif (! $term)
                    <p class="title mb-0">Enrollment is not open: no term is active.</p>
                @elseif ($sections->isEmpty())
                    <p class="title mb-0">There is nothing to enroll in yet.</p>
                @elseif (! $open)
                    <p class="title">Your department has not opened enrollment yet.</p>
                    <p class="detail mb-0">This page updates by itself once it opens.</p>
                @else
                    <p class="title">Enrollment is open. Enroll in all {{ $sections->count() }} subjects of your block in one step.</p>
                    <form method="POST" action="{{ route('student.enroll') }}" data-confirm="Enroll in block {{ $student->block_section }}?">
                        @csrf
                        <button type="submit" class="btn btn-light">Enroll in my block</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection
