<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Recognition Certificate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #1f2937;
        }
        .certificate-wrap {
            border: 10px solid #0f4c81;
            padding: 40px 48px;
            min-height: 900px;
            position: relative;
        }
        .title {
            text-align: center;
            font-size: 38px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 40px;
            margin-bottom: 10px;
            color: #0f4c81;
        }
        .subtitle {
            text-align: center;
            font-size: 16px;
            color: #374151;
            margin-bottom: 48px;
        }
        .content {
            text-align: center;
            font-size: 18px;
            line-height: 1.7;
            margin: 10px auto;
            max-width: 620px;
        }
        .employee-name {
            font-size: 40px;
            font-weight: 700;
            color: #0b3d62;
            margin: 30px 0 14px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .award-title {
            font-size: 27px;
            font-weight: 700;
            color: #111827;
            margin: 10px 0 20px;
        }
        .meta {
            margin-top: 44px;
            text-align: center;
            color: #4b5563;
            font-size: 14px;
        }
        .footer {
            position: absolute;
            left: 48px;
            right: 48px;
            bottom: 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 14px;
            color: #374151;
        }
        .signature {
            text-align: center;
            width: 260px;
        }
        .signature-line {
            border-top: 1px solid #1f2937;
            margin-bottom: 8px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="certificate-wrap">
        <div class="title">Certificate of Recognition</div>
        <div class="subtitle">Human Resource Management System</div>
        <div class="content">
            This certifies that
            <div class="employee-name">
                {{ trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) }}
            </div>
            has been recognized for
            <div class="award-title">{{ $reward->award_title }}</div>
            under the
            <strong>{{ strtoupper($reward->award_type) }}</strong> category.
        </div>
        <div class="meta">
            <div>Employee ID: {{ $employee->employee_id }}</div>
            <div>
                Department: {{ $employee->department?->department ?? 'N/A' }}
            </div>
            <div>
                Award Date: {{ optional($reward->award_date)->format('F d, Y') }}
            </div>
            @if ($reward->remarks)
                <div style="margin-top: 10px">
                    Remarks: {{ $reward->remarks }}
                </div>
            @endif
        </div>
        <div class="footer">
            <div class="signature">
                <div class="signature-line"></div>
                <div>Employee</div>
            </div>
            <div class="signature">
                <div class="signature-line"></div>
                <div>Authorized HR Signatory</div>
            </div>
        </div>
    </div>
</body>
</html>
