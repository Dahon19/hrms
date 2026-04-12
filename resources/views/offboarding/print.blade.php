<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Employee Clearance Form</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm 14mm;
        }
        body {
            font-family:
                DejaVu Sans,
                sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        h1,
        h2,
        h3 {
            margin: 0 0 8px;
        }
        .muted {
            color: #6b7280;
        }
        .section-title {
            margin-top: 16px;
            font-size: 13px;
            font-weight: bold;
        }
        .meta-table,
        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .meta-table td {
            padding: 6px 8px;
            border: 1px solid #d1d5db;
        }
        .grid th,
        .grid td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: top;
        }
        .grid th {
            background: #f3f4f6;
            text-align: left;
        }
        .signature-box {
            height: 44px;
            border-bottom: 1px solid #374151;
            margin-top: 18px;
        }
        .signature-meta {
            margin-top: 4px;
            font-size: 10px;
            color: #6b7280;
        }
        .check-cell {
            width: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $employee = $offboarding->employee;
        $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
        $positionName = $employee?->positions
            ?->map(fn ($assignment) => $assignment->position?->position)
            ->filter()
            ->unique()
            ->implode(', ');
        $positionName = $positionName !== '' ? $positionName : 'N/A';
        $sectionOrder = [
            'department_head' => 'Department Head',
            'finance' => 'Finance Office',
            'hr' => 'Human Resources',
        ];
        $clearanceSections = $offboarding->clearanceItems
            ->sortBy('display_order')
            ->groupBy('owner_role')
            ->sortBy(fn ($items, $role) => array_search($role, array_keys($sectionOrder), true));
    @endphp

    <h1>Employee Clearance Form</h1>
    <div class="muted">
        Hybrid resignation workflow with physical signatures and digital status
        tracking.
    </div>

    <div class="section-title">Employee Information</div>
    <table class="meta-table">
        <tr>
            <td><strong>Employee Name</strong><br />{{ $employeeName }}</td>
            <td>
                <strong>Employee ID</strong><br />{{ $employee->employee_id }}
            </td>
        </tr>
        <tr>
            <td>
                <strong>Department</strong
                ><br />{{ $employee->department?->department ?? 'N/A' }}
            </td>
            <td><strong>Position</strong><br />{{ $positionName }}</td>
        </tr>
        <tr>
            <td>
                <strong>Last Working Day</strong
                ><br />{{ optional($offboarding->display_last_working_day)->format('F d, Y') ?: 'Not set' }}
            </td>
            <td>
                <strong>Separation Reason</strong
                ><br />{{ $offboarding->display_reason ?: 'Not specified' }}
            </td>
        </tr>
    </table>

    @foreach ($clearanceSections as $ownerRole => $items)
        <div class="section-title">
            {{ $sectionOrder[$ownerRole] ?? str_replace('_', ' ', ucfirst($ownerRole)) }}
        </div>
        <table class="grid">
            <thead>
                <tr>
                    <th>Clearance Item</th>
                    <th class="check-cell">Done</th>
                    <th>Notes / Verification</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->item_name }}</td>
                        <td class="check-cell">
                            {{ $item->status === 'cleared' ? '?' : '' }}
                        </td>
                        <td>{{ $item->notes ?: $item->remarks ?: '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="signature-box"></div>
        <div class="signature-meta">
            Signature over printed name � {{ $sectionOrder[$ownerRole] ?? str_replace('_', ' ', ucfirst($ownerRole)) }}
        </div>
    @endforeach

    <div class="section-title">Human Resources Final Verification</div>
    <table class="grid">
        <tbody>
            <tr>
                <td>Exit interview completed</td>
                <td class="check-cell"></td>
                <td></td>
            </tr>
            <tr>
                <td>Final approval</td>
                <td class="check-cell"></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <div class="signature-box"></div>
    <div class="signature-meta">
        Signature over printed name � Human Resources
    </div>
</body>
</html>
