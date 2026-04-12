<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>SPMS Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            margin-top: 10px;
        }
        .subtitle {
            text-align: center;
            margin-bottom: 12px;
            color: #4b5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th,
        td {
            border: 1px solid #111827;
            padding: 5px 6px;
            vertical-align: top;
        }
        th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.4px;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="title">SPMS Cycle Report</div>
    <div class="subtitle">
        {{ $cycle->title }} | {{ optional($cycle->period_start)->format('M d, Y') }} - {{ optional($cycle->period_end)->format('M d, Y') }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th class="text-center">Status</th>
                <th class="text-center">Total Score</th>
                <th class="text-center">Rating</th>
                <th>Evaluator</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($evaluations as $evaluation)
                <tr>
                    <td>
                        {{ trim(($evaluation->employee->first_name ?? '') . ' ' . ($evaluation->employee->last_name ?? '')) }} ({{ $evaluation->employee->employee_id ?? 'N/A' }})
                    </td>
                    <td>
                        {{ $evaluation->employee->department?->department ?? 'N/A' }}
                    </td>
                    <td class="text-center">
                        {{ strtoupper($evaluation->status === 'final' ? 'finalized' : $evaluation->status) }}
                    </td>
                    <td class="text-center">
                        {{ number_format((float) $evaluation->total_score, 2) }}
                    </td>
                    <td class="text-center">
                        {{ strtoupper((string) ($evaluation->rating_label ?: $scoringService->scoreLabel((float) $evaluation->total_score))) }}
                    </td>
                    <td>{{ $evaluation->evaluator?->name ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">
                        No evaluations found for this cycle.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
