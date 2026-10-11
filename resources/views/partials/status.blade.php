@php
    $tone = match ($status) {
        'Cleared', 'Paid', 'Posted', 'Approved', 'Released', 'Active', 'Passed', 'Enrolled', 'Ready for Pickup', 'Done', 'Serving', 'Open' => 'success',
        'Pending', 'Pending Payment', 'Draft', 'For Verification', 'Submitted', 'INC', 'Processing', 'Waiting' => 'warning',
        'On Hold', 'Rejected', 'Failed', 'Voided', 'Inactive', 'Returned', 'Dropped', 'Cancelled', 'Skipped' => 'danger',
        default => 'secondary',
    };
    // What students and staff read; the stored value stays the same.
    $label = ['On Hold' => 'Deficiency', 'Ready for Pickup' => 'Ready', 'Pending Payment' => 'Awaiting payment', 'For Verification' => 'Verifying'][$status] ?? $status;
@endphp
<span class="pill pill-{{ $tone }}">{{ $label }}</span>
