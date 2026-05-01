<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Attendance Records</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0.55in;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
        }
        .report-header {
            margin-bottom: 16px;
        }
        .report-title {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: 700;
        }
        .report-subtitle,
        .report-meta {
            margin: 0;
            color: #475569;
            font-size: 11px;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            margin-bottom: 14px;
        }
        .meta-grid td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            vertical-align: top;
        }
        .meta-label {
            display: block;
            margin-bottom: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.04em;
        }
        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 0 -8px 14px;
        }
        .summary-grid td {
            width: 25%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
            vertical-align: top;
        }
        .summary-value {
            display: block;
            margin-top: 4px;
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }
        .summary-value.is-success {
            color: #15803d;
        }
        .summary-value.is-danger {
            color: #b91c1c;
        }
        .summary-value.is-warning {
            color: #b45309;
        }
        .records-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .records-table th,
        .records-table td {
            border: 1px solid #cbd5e1;
            padding: 9px 10px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .records-table th {
            background: #f8fafc;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }
        .records-table td {
            font-size: 11px;
        }
        .employee-name {
            font-weight: 700;
            color: #0f172a;
        }
        .employee-meta {
            margin-top: 3px;
            color: #64748b;
            font-size: 10px;
        }
        .text-center {
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            min-width: 76px;
            padding: 5px 10px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            text-align: center;
        }
        .status-present {
            color: #166534;
            background: #dcfce7;
            border-color: #86efac;
        }
        .status-late {
            color: #92400e;
            background: #fef3c7;
            border-color: #fcd34d;
        }
        .status-absent {
            color: #475569;
            background: #e2e8f0;
            border-color: #cbd5e1;
        }
        .status-official-business {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #93c5fd;
        }
        .status-excused {
            color: #4338ca;
            background: #e0e7ff;
            border-color: #a5b4fc;
        }
        .status-holiday {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fca5a5;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1 class="report-title">Attendance Records</h1>
        <p class="report-subtitle">Historical attendance logs by selected period.</p>
        <p class="report-meta">
            Printed {{ $printedAt->format('F j, Y g:i A') }}
            @if ($printedBy) by{{ $printedBy->name }} @endif
        </p>
    </div>
    <table class="meta-grid">
        <tr>
            <td>
                <span class="meta-label">Report Period</span>
                {{ $filters['label'] ?? now()->format('F j, Y') }}
            </td>
            <td>
                <span class="meta-label">View Mode</span>
                {{ ucfirst($filters['period'] ?? 'Weekly') }}
            </td>
            <td>
                <span class="meta-label">Employee Filter</span>
                {{ $selectedEmployeeId ? 'Specific employee' : 'All employees' }}
            </td>
        </tr>
    </table>
    <table class="summary-grid">
        <tr>
            <td>
                <span class="meta-label">Employees</span>
                <span
                    class="summary-value"
                    >{{ $totals['employees'] ?? 0 }}</span
                >
            </td>
            <td>
                <span class="meta-label">Present Days</span>
                <span
                    class="summary-value is-success"
                    >{{ $totals['present_days'] ?? 0 }}</span
                >
            </td>
            <td>
                <span class="meta-label">Absent Days</span>
                <span
                    class="summary-value is-danger"
                    >{{ $totals['absent_days'] ?? 0 }}</span
                >
            </td>
            <td>
                <span class="meta-label">Late Days</span>
                <span
                    class="summary-value is-warning"
                    >{{ $totals['late_days'] ?? 0 }}</span
                >
            </td>
            <td>
                <span class="meta-label">Official Business</span>
                <span
                    class="summary-value"
                    style="color: #1d4ed8"
                    >{{ $totals['official_business_days'] ?? 0 }}</span
                >
            </td>
        </tr>
    </table>
    <table class="records-table">
        <thead>
            <tr>
                <th style="width: 30%">Employee</th>
                @if ($attendanceSetting->require_four_taps)
                    <th style="width: 11%" class="text-center">M-In</th>
                    <th style="width: 11%" class="text-center">M-Out</th>
                    <th style="width: 11%" class="text-center">A-In</th>
                    <th style="width: 11%" class="text-center">A-Out</th>
                @else
                    <th style="width: 22%" class="text-center">Time In</th>
                    <th style="width: 22%" class="text-center">Time Out</th>
                @endif
                <th style="width: 12%" class="text-center">Status</th>
                <th style="width: 14%" class="text-center">Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($attendance as $att)
                @php $emp = $att->employee; $morningIn = $att->morning_time_in ? \Carbon\Carbon::parse($att->morning_time_in)->format('g:i A') : '--'; $morningOut = $att->morning_time_out ? \Carbon\Carbon::parse($att->morning_time_out)->format('g:i A') : '--'; $afternoonIn = $att->afternoon_time_in ? \Carbon\Carbon::parse($att->afternoon_time_in)->format('g:i A') : '--'; $afternoonOut = $att->afternoon_time_out ? \Carbon\Carbon::parse($att->afternoon_time_out)->format('g:i A') : '--'; $statusClass = match ($att->status) { 'present' => 'status-present', 'late' => 'status-late', 'official_business' => 'status-official-business', 'excused' => 'status-excused', 'holiday' => 'status-holiday', default => 'status-absent', }; $statusLabel = match ($att->status) { 'official_business' => 'Official Business', 'excused' => 'Excused', 'holiday' => 'Holiday', default => ucfirst($att->status), }; @endphp
                <tr>
                    <td>
                        <div class="employee-name">
                            {{ ($emp->first_name ?? 'Unknown') . ' ' . ($emp->last_name ?? '') }}
                        </div>
                        <div class="employee-meta">
                            #{{ $emp->employee_id ?? 'N/A' }} &middot; {{ $emp->department?->department ?? 'No Department' }}
                        </div>
                    </td>
                    @if ($attendanceSetting->require_four_taps)
                        <td class="text-center">{{ $morningIn }}</td>
                        <td class="text-center">{{ $morningOut }}</td>
                        <td class="text-center">{{ $afternoonIn }}</td>
                        <td class="text-center">{{ $afternoonOut }}</td>
                    @else
                        <td class="text-center">{{ $morningIn }}</td>
                        <td class="text-center">{{ $afternoonOut }}</td>
                    @endif
                    <td class="text-center">
                        <span
                            class="status-badge {{ $statusClass }}"
                            >{{ $statusLabel }}</span
                        >
                    </td>
                    <td class="text-center">
                        {{ \Carbon\Carbon::parse($att->date)->format('M d, Y') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">
                        No attendance records found for the selected filter.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
