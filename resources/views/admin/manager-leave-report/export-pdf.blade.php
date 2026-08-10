<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Report</title>
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
    </style>
</head>
<body>
    <h1>Leave Report</h1>
    <p class="meta">
        Range: {{ $reportSummary['range_label'] }} |
        Generated: {{ $generatedAt->format('d-m-Y h:i A') }}
    </p>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Employees</div>
                <div class="value">{{ $reportSummary['employees_count'] }}</div>
            </td>
            <td>
                <div class="label">Leave Types</div>
                <div class="value">{{ $reportSummary['leave_types_count'] }}</div>
            </td>
            <td>
                <div class="label">Day-Based Leave</div>
                <div class="value">{{ $reportSummary['day_based_total_label'] }}</div>
            </td>
            <td>
                <div class="label">Hourly Leave</div>
                <div class="value">{{ $reportSummary['hour_based_total_label'] }}</div>
            </td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Unit</th>
                <th>Taken</th>
                <th>Remaining Balance</th>
                <th>Requests</th>
                <th>Last Leave</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reportRows as $row)
                <tr>
                    <td>
                        <div>{{ $row['employee'] }}</div>
                        <div class="muted">{{ $row['company'] }}</div>
                    </td>
                    <td>{{ $row['leave_type'] }}</td>
                    <td>{{ $row['request_unit'] === 'hour' ? 'Hourly' : 'Day Based' }}</td>
                    <td>{{ $row['taken_label'] }}</td>
                    <td>{{ $row['remaining_balance_label'] }}</td>
                    <td>{{ $row['request_count'] }}</td>
                    <td>{{ $row['last_leave_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No leave report data was found for this filter set.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
