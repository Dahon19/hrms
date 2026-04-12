<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Travel Order</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0.6in;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111827;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .header h1,
        .header p {
            margin: 0;
        }
        .title {
            margin-top: 18px;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .meta,
        .details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .meta td,
        .details td,
        .details th {
            border: 1px solid #cbd5e1;
            padding: 10px;
            vertical-align: top;
        }
        .label {
            display: block;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .purpose {
            min-height: 120px;
            white-space: pre-line;
        }
        .signatures {
            width: 100%;
            margin-top: 36px;
            border-collapse: collapse;
        }
        .signatures td {
            width: 25%;
            padding: 14px 12px 0;
            vertical-align: top;
        }
        .sign-line {
            border-top: 1px solid #111827;
            padding-top: 8px;
            text-align: center;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="header">
        <p>Northeastern College</p>
        <p>Human Resource Management System</p>
        <div class="title">Travel Order</div>
    </div>
    <table class="meta">
        <tr>
            <td>
                <span class="label">Employee</span>
                {{ trim(($travelOrder->employee?->first_name ?? '') . ' ' . ($travelOrder->employee?->last_name ?? '')) }}
            </td>
            <td>
                <span class="label">Department</span>
                {{ $travelOrder->employee?->department?->department ?? 'N/A' }}
            </td>
            <td>
                <span class="label">Position</span>
                {{ $travelOrder->position?->position ?? 'N/A' }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Destination</span>
                {{ $travelOrder->destination }}
            </td>
            <td>
                <span class="label">Date From</span>
                {{ $travelOrder->date_from?->format('F d, Y') }}
            </td>
            <td>
                <span class="label">Date To</span>
                {{ $travelOrder->date_to?->format('F d, Y') }}
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Transport Mode</span>
                {{ $travelOrder->transport_mode ?: 'N/A' }}
            </td>
            <td>
                <span class="label">Budget Proposal</span>
                {{ $travelOrder->budget_proposal !== null ? 'PHP ' . number_format((float) $travelOrder->budget_proposal, 2) : 'N/A' }}
            </td>
            <td>
                <span class="label">Status</span>
                {{ $travelOrder->statusLabel() }}
            </td>
        </tr>
    </table>
    <table class="details">
        <tr>
            <th>Purpose of Travel</th>
        </tr>
        <tr>
            <td class="purpose">{{ $travelOrder->purpose }}</td>
        </tr>
    </table>
    <table class="details">
        <tr>
            <td>
                <span class="label">Departure Time</span>
                {{ $travelOrder->departure_time ?: 'N/A' }}
            </td>
            <td>
                <span class="label">Return Time</span>
                {{ $travelOrder->return_time ?: 'N/A' }}
            </td>
            <td>
                <span class="label">Requested By</span>
                {{ $travelOrder->submittedBy?->name ?? 'Employee' }}
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="label">Remarks</span>
                {{ $travelOrder->remarks ?: 'None' }}
            </td>
        </tr>
    </table>
    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line">
                    {{ $travelOrder->submittedBy?->name ?? 'Employee' }}<br />
                    <span class="muted">Requested By</span>
                </div>
            </td>
            <td>
                <div class="sign-line">
                    {{ $travelOrder->departmentApprovedBy?->name ?? 'Pending' }}<br />
                    <span class="muted">Department Approval</span>
                </div>
            </td>
            <td>
                <div class="sign-line">
                    {{ $travelOrder->hrReviewedBy?->name ?? 'Pending' }}<br />
                    <span class="muted">For Final Approval</span>
                </div>
            </td>
            <td>
                <div class="sign-line">
                    {{ $travelOrder->finalApprovedBy?->name ?? 'Pending' }}<br />
                    <span class="muted">Final Approval</span>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
