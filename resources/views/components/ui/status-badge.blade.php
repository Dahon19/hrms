@props (['status' => 'draft', 'text' => null])
<x-ui.table-status-badge :status="$status" :text="$text" {{ $attributes }} />
