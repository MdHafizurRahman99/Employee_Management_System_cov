<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Working Hours Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { margin: 0 0 4px 0; font-size: 22px; }
        p.meta { margin: 0 0 14px 0; color: #6b7280; }
        .summary { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
        .summary td { width: 25%; border: 1px solid #d1d5db; padding: 10px; vertical-align: top; }
        .summary .label { font-size: 10px; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
        .summary .value { font-size: 16px; font-weight: bold; color: #111827; }
        table.report { width: 100%; border-collapse: collapse; }
        table.report th, table.report td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        table.report th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .muted { color: #6b7280; font-size: 10px; }
        .success { color: #047857; font-weight: bold; }
        .danger { color: #b91c1c; font-weight: bold; }
        .secondary { color: #4b5563; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Working Hours Report</h1>
    <p class="meta">
        Month: {{ $reportSummary['month_label'] }} |
        Generated: {{ $generatedAt->format('d-m-Y h:i A') }}
    </p>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Employees</div>
                <div class="value">{{ $reportSummary['employees_count'] }}</div>
            </td>
            <td>
                <div class="label">Planned Hours</div>
                <div class="value">{{ $reportSummary['planned_hours_total_label'] }}</div>
            </td>
            <td>
                <div class="label">Actual Hours</div>
                <div class="value">{{ $reportSummary['actual_hours_total_label'] }}</div>
            </td>
            <td>
                <div class="label">Missing Clock-outs</div>
                <div class="value">{{ $reportSummary['missing_clock_out_total'] }}</div>
            </td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Planned</th>
                <th>Actual</th>
                <th>Variance</th>
                <th>Overtime</th>
                <th>Undertime</th>
                <th>Shifts</th>
                <th>Attendance Days</th>
                <th>Incomplete Sessions</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportRows as $row)
                <tr>
                    <td>
                        <div>{{ $row['employee'] }}</div>
                        <div class="muted">{{ $row['company'] }}</div>
                    </td>
                    <td>{{ $row['planned_hours_label'] }}</td>
                    <td>{{ $row['actual_hours_label'] }}</td>
                    <td class="{{ $row['variance_hours'] > 0 ? 'success' : ($row['variance_hours'] < 0 ? 'danger' : 'secondary') }}">
                        {{ $row['variance_hours_label'] }}
                    </td>
                    <td>{{ $row['overtime_hours_label'] }}</td>
                    <td>{{ $row['undertime_hours_label'] }}</td>
                    <td>{{ $row['shift_count'] }}</td>
                    <td>{{ $row['attendance_days_count'] }}</td>
                    <td>{{ $row['missing_clock_out_count'] }}</td>
                    <td>{{ $row['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No working hours data was found for this month and filter set.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
