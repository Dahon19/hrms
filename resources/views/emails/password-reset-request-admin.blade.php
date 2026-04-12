<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Password Reset Request</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2d3d">
    <h2 style="margin: 0 0 12px">Password Reset Request</h2>
    <p style="
            margin: 0 0 10px;
        ">A user has requested a password reset and needs admin verification.</p>
    <table style="border-collapse: collapse">
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold">Name:</td>
            <td style="padding: 4px 0">{{ $userName }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold">Email:</td>
            <td style="padding: 4px 0">{{ $userEmail }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold">
                Requested At:
            </td>
            <td style="padding: 4px 0">{{ $requestedAt }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold">
                Request IP:
            </td>
            <td style="padding: 4px 0">{{ $requestIp }}</td>
        </tr>
    </table>
    <p style="
            margin: 16px 0 0;
            font-size: 12px;
            color: #6c757d;
        ">This is an automated message from Northeastern College HRMS.</p>
</body>
</html>
