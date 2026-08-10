<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Working Hours Report</title>
</head>
<body>
    <table border="1">
        <tr>
            <th colspan="10">Working Hours Report - {{ $reportSummary['month_label'] }}</th>
        </tr>
        <tr>
            <td colspan="10">Generated: {{ $generatedAt->format('d-m-Y h:i A') }}</td>
        </tr>
        <tr>
            <th>Employees</th>
            <th>Planned Hours</th>
            <th>Actual Hours</th>
            <th>Overtime</th>
            <th>Undertime</th>
            <th>Shift Count</th>
            <th>Attendance Days</th>
            <th>Missing Clock-outs</th>
            <th colspan="2">Month</th>
        </tr>
        <tr>
            <td>{{ $reportSummary['employees_count'] }}</td>
            <td>{{ $reportSummary['planned_hours_total_label'] }}</td>
            <td>{{ $reportSummary['actual_hours_total_label'] }}</td>
            <td>{{ $reportSummary['overtime_total_label'] }}</td>
            <td>{{ $reportSummary['undertime_total_label'] }}</td>
            <td>{{ $reportSummary['shift_count_total'] }}</td>
            <td>{{ $reportSummary['attendance_days_total'] }}</td>
            <td>{{ $reportSummary['missing_clock_out_total'] }}</td>
            <td colspan="2">{{ $reportSummary['month_label'] }}</td>
        </tr>
    </table>

    <br>

    <table border="1">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Company</th>
                <th>Planned Hours</th>
                <th>Actual Hours</th>
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
                    <td>{{ $row['employee'] }}</td>
                    <td>{{ $row['company'] }}</td>
                    <td>{{ $row['planned_hours_label'] }}</td>
                    <td>{{ $row['actual_hours_label'] }}</td>
                    <td>{{ $row['variance_hours_label'] }}</td>
                    <td>{{ $row['overtime_hours_label'] }}</td>
                    <td>{{ $row['undertime_hours_label'] }}</td>
                    <td>{{ $row['shift_count'] }}</td>
                    <td>{{ $row['attendance_days_count'] }}</td>
                    <td>{{ $row['missing_clock_out_count'] }}</td>
                    <td>{{ $row['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">No working hours data was found for this month and filter set.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
