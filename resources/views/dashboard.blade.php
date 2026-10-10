@extends('layouts.app')

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', preg_replace('/^(Prof|Dr|Engr|Atty|Mr|Ms|Mrs)\.?\s+/i', '', auth()->user()->name))[0];
@endphp

@section('eyebrow', 'Active term: '.($term?->label ?? 'none set'))
@section('title', $greeting.', '.$firstName)

@section('content')

    <div class="row g-3 mb-4">
        @foreach ($cards as [$label, $value, $url])
            <div class="col-sm-6 col-lg-4">
                <a href="{{ $url }}" class="card text-decoration-none h-100">
                    <div class="card-body">
                        <div class="stat-label">{{ $label }}</div>
                        <div class="stat-value">{{ $value }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @include('partials.recent-notifications')
@endsection
