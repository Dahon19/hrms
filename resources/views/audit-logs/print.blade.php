<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        p { margin: 0 0 10px; color: #475569; }
        .meta { margin-bottom: 14px; font-size: 10px; color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: top; text-align: left; }
        th { background: #e2e8f0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
    </style>
</head>
<body>
    <h1>Audit Logs Report</h1>
    <p>System activity, changes, and downloads.</p>
    <div class="meta">
        Printed: {{ $printedAt->format('M d, Y h:i A') }}
        @if (($filters['action'] ?? '') !== '')
            | Action: {{ \Illuminate\Support\Str::headline($filters['action']) }}
        @endif
        @if (($filters['user'] ?? '') !== '')
            | User: {{ $filters['user'] }}
        @endif
        @if (($filters['start'] ?? '') !== '')
            | From: {{ $filters['start'] }}
        @endif
        @if (($filters['end'] ?? '') !== '')
            | To: {{ $filters['end'] }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Model</th>
                <th>Record</th>
                <th>Summary</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('M d, Y H:i') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td>{{ \Illuminate\Support\Str::headline($log->action) }}</td>
                    <td>{{ class_basename($log->auditable_type) }}</td>
                    <td>{{ $log->auditable_id }}</td>
                    <td>{{ $log->summary_text ?: '-' }}</td>
                    <td>{{ $log->ip_address ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No audit logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
