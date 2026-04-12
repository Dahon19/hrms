@props ([
    'status' => 'draft',
    'text' => null,
    'variant' => null,
])

@php
    $normalizedStatus = strtolower(trim((string) $status));

    $resolvedVariant = $variant ?: match ($normalizedStatus) {
        'draft', 'pending review', 'queued', 'inactive', 'unknown' => 'secondary',
        'pending',
        'in progress',
        'in_progress',
        'needs revision',
        'needs_revision',
        'expiring soon',
        'late' => 'warning',
        'submitted',
        'review',
        'on process',
        'on_process',
        'processing',
        'open',
        'official business',
        'official_business' => 'info',
        'excused',
        'on leave',
        'on_leave' => 'primary',
        'approved', 'active', 'success', 'verified', 'present', 'finalized', 'final', 'valid' => 'success',
        'rejected', 'declined', 'danger', 'failed', 'absent', 'expired' => 'danger',
        'completed', 'done', 'closed', 'archived', 'locked' => 'dark',
        default => 'secondary',
    };

    $displayText = $text ?? ucwords(str_replace('_', ' ', $normalizedStatus));
@endphp

<span
    {{ $attributes->merge([
        'class' => 'badge badge-' . $resolvedVariant . ' ui-table-status-badge ui-table-status-badge--' . $resolvedVariant,
    ]) }}
>
    {{ $displayText }}
</span>
