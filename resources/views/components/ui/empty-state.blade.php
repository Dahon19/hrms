@props ([
    'colspan' => 1,
    'icon' => 'cil-folder-open',
    'title' => 'No records found',
    'message' => 'Adjust search filters or create a new record.',
    'actionUrl' => null,
    'actionText' => 'Create New',
])
<tr>
    <td colspan="{{ $colspan }}" class="text-center py-5">
        <div class="hrms-empty-state">
            <i class="{{ $icon }} fs-1 mb-3 text-muted"></i>
            <h5 class="text-muted">{{ $title }}</h5>
            <p class="text-muted small mb-3">{{ $message }}</p>
            @if ($actionUrl)
                <a href="{{ $actionUrl }}" class="btn btn-primary btn-sm">
                    <i class="cil-plus me-1"></i> {{ $actionText }}
                </a>
            @endif
        </div>
    </td>
</tr>
