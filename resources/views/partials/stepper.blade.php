@php
    // $steps maps a stored status to the word shown under each dot.
    $order = array_keys($steps);
    $position = $status === 'Released' ? count($order) : array_search($status, $order, true);
    $position = $position === false ? -1 : $position;
    // A request being processed is still on that step; otherwise it waits on the next one.
    $doneThrough = $status === 'Processing' ? $position - 1 : $position;
    $current = $status === 'Processing' ? $position : ($position + 1 < count($order) ? $position + 1 : null);
@endphp
<ol class="stepper" aria-label="Request progress">
    @foreach (array_values($steps) as $index => $label)
        @php $state = $index <= $doneThrough ? 'done' : ($index === $current ? 'current' : 'upcoming'); @endphp
        <li class="{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
            <span class="dot">@if ($state === 'done') @include('partials.icon', ['name' => 'check']) @else {{ $index + 1 }} @endif</span>
            {{ $label }}
        </li>
    @endforeach
</ol>
