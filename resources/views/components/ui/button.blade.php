@props ([
    'variant' => null,
    'size' => 'sm',
    'icon' => null,
    'tag' => null,
    'type' => 'button',
    'loading' => false,
])

@php
    $slotContent = trim((string) $slot);

    $semanticMap = [
        'create' => 'btn-primary',
        'add' => 'btn-primary',
        'new' => 'btn-primary',
        'register' => 'btn-primary',
        'upload' => 'btn-primary',
        'remind' => 'btn-outline-info',
        'reupload' => 'btn-outline-warning',
        'email' => 'btn-outline-secondary',
        'documents' => 'btn-outline-secondary',
        'rfid' => 'btn-outline-secondary',
        'save' => 'btn-primary',
        'submit' => 'btn-primary',
        'view' => 'btn-outline-secondary',
        'print' => 'btn-outline-secondary',
        'export' => 'btn-outline-secondary',
        'details' => 'btn-outline-secondary',
        'edit' => 'btn-outline-primary',
        'approve' => 'btn-success',
        'archive' => 'btn-warning',
        'delete' => 'btn-danger',
        'decline' => 'btn-danger',
        'cancel' => 'btn-outline-secondary',
        'secondary' => 'btn-secondary',
        'warning' => 'btn-warning',
        'info' => 'btn-info',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        'primary' => 'btn-primary',
    ];

    $semanticIconMap = [
        'view' => 'cil-zoom',
        'print' => 'cil-print',
        'export' => 'cil-description',
        'details' => 'cil-chevron-up',
        'email' => 'cil-envelope-open',
        'documents' => 'cil-folder-open',
        'rfid' => 'cil-credit-card',
        'edit' => 'cil-pencil',
        'archive' => 'cil-inbox',
        'delete' => 'cil-trash',
        'decline' => 'cil-x',
        'approve' => 'cil-check',
        'remind' => 'cil-bell',
        'reupload' => 'cil-action-undo',
        'upload' => 'cil-data-transfer-up',
        'create' => 'cil-plus',
        'add' => 'cil-plus',
        'new' => 'cil-plus',
        'register' => 'cil-plus',
        'save' => 'cil-save',
        'submit' => 'cil-paper-plane',
        'cancel' => 'cil-x',
    ];

    $semanticLabelMap = [
        'create' => 'Create',
        'add' => 'Add',
        'new' => 'New',
        'register' => 'Register',
        'upload' => 'Upload',
        'documents' => 'Documents',
        'rfid' => 'RFID',
        'save' => 'Save',
        'submit' => 'Submit',
        'view' => 'View',
        'print' => 'Print',
        'export' => 'Export',
        'details' => 'Details',
        'email' => 'Email',
        'edit' => 'Edit',
        'approve' => 'Approve',
        'remind' => 'Remind',
        'reupload' => 'Request Reupload',
        'archive' => 'Archive',
        'delete' => 'Delete',
        'decline' => 'Decline',
        'cancel' => 'Cancel',
    ];

    $iconAliasMap = [
        'cil-eye' => 'cil-zoom',
        'fas fa-eye' => 'cil-zoom',
        'fas fa-chevron-down' => 'cil-chevron-bottom',
        'fas fa-folder-open' => 'cil-folder-open',
        'fas fa-id-card' => 'cil-credit-card',
        'fas fa-pen' => 'cil-pencil',
        'cil-archive' => 'cil-inbox',
        'fas fa-box-archive' => 'cil-inbox',
        'fas fa-trash' => 'cil-trash',
        'fas fa-check' => 'cil-check',
        'fas fa-check-circle' => 'cil-check-circle',
        'cil-cloud-upload' => 'cil-data-transfer-up',
        'fas fa-cloud-upload-alt' => 'cil-data-transfer-up',
        'fas fa-plus' => 'cil-plus',
        'fas fa-save' => 'cil-save',
        'fas fa-paper-plane' => 'cil-paper-plane',
        'fas fa-times' => 'cil-x',
        'fas fa-rotate-left' => 'cil-action-undo',
        'fas fa-chevron-up' => 'cil-chevron-top',
        'fas fa-bell' => 'cil-bell',
        'fas fa-print' => 'cil-print',
        'fas fa-download' => 'cil-description',
    ];

    $nativeTypes = ['button', 'submit', 'reset'];
    $semanticType = in_array($type, $nativeTypes, true) ? null : strtolower((string) $type);
    $resolvedVariant = $variant ?: $semanticType ?: 'primary';
    $semanticKey = $semanticType ?: (array_key_exists($resolvedVariant, $semanticIconMap) ? $resolvedVariant : null);
    $variantClass = $semanticMap[$resolvedVariant] ?? $resolvedVariant;

    if (is_string($variantClass) && !str_starts_with($variantClass, 'btn-')) {
        if (preg_match('/^(outline|soft)-[a-z0-9_-]+$/i', $variantClass)) {
            $variantClass = 'btn-' . $variantClass;
        } elseif (preg_match('/^(primary|secondary|success|danger|warning|info|light|dark)$/i', $variantClass)) {
            $variantClass = 'btn-' . $variantClass;
        }
    }
    $nativeType = in_array($type, $nativeTypes, true) ? $type : 'button';
    $resolvedIcon = $iconAliasMap[$icon] ?? $icon ?? ($semanticKey ? ($semanticIconMap[$semanticKey] ?? null) : null);
    $attributeLabel = (string) ($attributes->get('aria-label') ?: $attributes->get('title') ?: '');
    $resolvedLabel = $slotContent !== ''
        ? $slotContent
        : ($semanticKey
            ? ($semanticLabelMap[$semanticKey] ?? '')
            : $attributeLabel);

    $resolvedSize = $size;
    if (
        $semanticType &&
        in_array($semanticType, ['create', 'add', 'new', 'register', 'upload', 'save', 'submit', 'cancel'], true) &&
        $size === 'sm'
    ) {
        $resolvedSize = '';
    }

    $sizeClass = $resolvedSize === 'sm' ? 'btn-sm' : ($resolvedSize === 'lg' ? 'btn-lg' : '');
    $buttonClass = trim("btn hrms-btn {$sizeClass} {$variantClass}");
    $elementTag = $tag ?: ($attributes->has('href') ? 'a' : 'button');
    $coreUiToggle = $attributes->get('data-coreui-toggle');
    $coreUiTarget = $attributes->get('data-coreui-target');
    $coreUiDismiss = $attributes->get('data-coreui-dismiss');
    $legacyToggle = $attributes->get('data-toggle');
    $legacyTarget = $attributes->get('data-target');
    $legacyDismiss = $attributes->get('data-dismiss');

    $normalizedAttributes = $attributes
        ->except(['data-toggle', 'data-target', 'data-dismiss'])
        ->merge([
            'data-coreui-toggle' => $coreUiToggle ?: ($legacyToggle === 'modal' ? 'modal' : null),
            'data-coreui-target' => $coreUiTarget ?: $legacyTarget,
            'data-coreui-dismiss' => $coreUiDismiss ?: $legacyDismiss,
        ]);

    $actionClass = match ($semanticKey) {
        'view' => 'crud-btn-view',
        'details' => 'crud-btn-view',
        'email' => 'crud-btn-view',
        'documents' => 'crud-btn-documents',
        'rfid' => 'crud-btn-rfid',
        'edit' => 'crud-btn-edit',
        'delete' => 'crud-btn-delete',
        'approve' => 'crud-btn-approve',
        'remind' => 'crud-btn-remind',
        'reupload' => 'crud-btn-reupload',
        'archive' => 'crud-btn-archive',
        'create', 'add', 'new', 'register', 'upload' => 'crud-btn-create',
        'save', 'submit' => 'crud-btn-save',
        'decline' => 'crud-btn-delete',
        'cancel' => 'crud-btn-cancel',
        default => '',
    };

    $sharedActionClass = $semanticKey && in_array($semanticKey, ['view', 'details', 'email', 'documents', 'rfid', 'edit', 'delete', 'approve', 'archive', 'upload', 'remind', 'reupload', 'decline'], true)
        ? 'action-btn'
        : '';
    $isActionButton = $sharedActionClass !== '';
    $hasLabel = !$isActionButton && $resolvedLabel !== '';
    $isDisabled = filter_var($attributes->get('disabled', false), FILTER_VALIDATE_BOOL) || $loading;
    $buttonAriaLabel = $normalizedAttributes->get('aria-label') ?: ($resolvedLabel !== '' ? $resolvedLabel : null);
    $buttonTitle = $normalizedAttributes->get('title') ?: ($resolvedLabel !== '' ? $resolvedLabel : null);
    $contentGapClass = $hasLabel ? 'gap-2' : '';
    $normalizedAttributes = $normalizedAttributes->merge([
        'aria-label' => $buttonAriaLabel,
        'title' => $buttonTitle,
    ]);
@endphp

@if ($elementTag === 'a')
    <a
        {{ $normalizedAttributes->merge(['class' => trim("{$buttonClass} {$actionClass} {$sharedActionClass} {$contentGapClass}")]) }}
    >
        @if ($loading)
            <span class="spinner-border spinner-border-sm hrms-btn__spinner" aria-hidden="true"></span>
        @elseif ($resolvedIcon)
            <i
                class="{{ $resolvedIcon }} hrms-btn__icon"
                aria-hidden="true"
            ></i>
        @endif
        @if ($hasLabel)
            <span class="hrms-btn__label">{{ $resolvedLabel }}</span>
        @endif
    </a>
@else
    <button
        type="{{ $nativeType }}"
        @disabled($isDisabled)
        {{ $normalizedAttributes->merge(['class' => trim("{$buttonClass} {$actionClass} {$sharedActionClass} {$contentGapClass}")]) }}
    >
        @if ($loading)
            <span class="spinner-border spinner-border-sm hrms-btn__spinner" aria-hidden="true"></span>
        @elseif ($resolvedIcon)
            <i
                class="{{ $resolvedIcon }} hrms-btn__icon"
                aria-hidden="true"
            ></i>
        @endif
        @if ($hasLabel)
            <span class="hrms-btn__label">{{ $resolvedLabel }}</span>
        @endif
    </button>
@endif
