<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Eligibility Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
            margin-bottom: 14px;
            color: #4b5563;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th,
        td {
            border: 1px solid #1f2937;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="title">Eligibility Report</div>
    <div class="subtitle">
        Year: {{ $year }} | Department: {{ $department?->department ?? 'All Departments' }}
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Department</th>
                <th>Tenure</th>
                <th>Attendance</th>
                <th>SPMS</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $employee = $row['employee']; $eligibility = $row['eligibility']; @endphp
                <tr>
                    <td>
                        {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }} ({{ $employee->employee_id }})
                    </td>
                    <td>{{ $employee->department?->department ?? 'N/A' }}</td>
                    <td>
                        @if ($eligibility['tenure']['eligible']) Eligible ({{ $eligibility['tenure']['milestone'] }}years) @else Not
                        Eligible @endif
                    </td>
                    <td>
                        {{ $eligibility['attendance']['eligible'] ? 'Qualified' : 'Not Qualified' }}
                    </td>
                    <td>
                        @if ($eligibility['performance']['eligible']) Qualified ({{ strtoupper((string) ($eligibility['performance']['rating'] ?? 'N/A')) }}) @else Not
                        Eligible @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center">
                        No eligible employees found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
