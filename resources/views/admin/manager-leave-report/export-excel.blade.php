<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Report</title>
</head>
<body>
    <table border="1">
        <tr>
            <th colspan="7">Leave Report - {{ $reportSummary['range_label'] }}</th>
        </tr>
        <tr>
            <td colspan="7">Generated: {{ $generatedAt->format('d-m-Y h:i A') }}</td>
        </tr>
        <tr>
            <th>Employees</th>
            <th>Leave Types</th>
            <th>Day-Based Leave</th>
            <th>Hourly Leave</th>
            <th>Approved Requests</th>
            <th>Rows With Balance</th>
            <th>Range</th>
        </tr>
        <tr>
            <td>{{ $reportSummary['employees_count'] }}</td>
            <td>{{ $reportSummary['leave_types_count'] }}</td>
            <td>{{ $reportSummary['day_based_total_label'] }}</td>
            <td>{{ $reportSummary['hour_based_total_label'] }}</td>
            <td>{{ $reportSummary['request_count_total'] }}</td>
            <td>{{ $reportSummary['balance_rows_count'] }}</td>
            <td>{{ $reportSummary['range_label'] }}</td>
        </tr>
    </table>

    <br>

    <table border="1">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Company</th>
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
                    <td>{{ $row['employee'] }}</td>
                    <td>{{ $row['company'] }}</td>
                    <td>{{ $row['leave_type'] }}</td>
                    <td>{{ $row['request_unit'] === 'hour' ? 'Hourly' : 'Day Based' }}</td>
                    <td>{{ $row['taken_label'] }}</td>
                    <td>{{ $row['remaining_balance_label'] }}</td>
                    <td>{{ $row['request_count'] }}</td>
                    <td>{{ $row['last_leave_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">No leave report data was found for this filter set.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
