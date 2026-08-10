<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Summary Report</title>
</head>
<body>
    <table border="1">
        <tr>
            <th colspan="8">Payroll Summary Report - {{ $reportSummary['month_label'] }}</th>
        </tr>
        <tr>
            <td colspan="8">Generated: {{ $generatedAt->format('d-m-Y h:i A') }}</td>
        </tr>
        <tr>
            <th>Payslips</th>
            <th>Employees</th>
            <th>Payroll Cost</th>
            <th>Deductions</th>
            <th>Net Pay</th>
            <th>Previous Month</th>
            <th>MoM Change</th>
            <th>MoM Percent</th>
        </tr>
        <tr>
            <td>{{ $reportSummary['payslip_count'] }}</td>
            <td>{{ $reportSummary['employees_count'] }}</td>
            <td>{{ $reportSummary['gross_total_label'] }}</td>
            <td>{{ $reportSummary['deductions_total_label'] }}</td>
            <td>{{ $reportSummary['net_total_label'] }}</td>
            <td>{{ $comparison['previous_gross_total_label'] }}</td>
            <td>{{ $comparison['change_value_label'] }}</td>
            <td>{{ $comparison['change_percent_label'] }}</td>
        </tr>
    </table>

    <br>

    <table border="1">
        <thead>
            <tr>
                <th colspan="6">Company Breakdown</th>
            </tr>
            <tr>
                <th>Company</th>
                <th>Payslips</th>
                <th>Employees</th>
                <th>Gross</th>
                <th>Deductions</th>
                <th>Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($companyBreakdown as $row)
                <tr>
                    <td>{{ $row['company'] }}</td>
                    <td>{{ $row['payslip_count'] }}</td>
                    <td>{{ $row['employees_count'] }}</td>
                    <td>{{ $row['gross_total_label'] }}</td>
                    <td>{{ $row['deductions_total_label'] }}</td>
                    <td>{{ $row['net_total_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No company totals were found for this month and filter set.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <br>

    <table border="1">
        <thead>
            <tr>
                <th colspan="6">Role Breakdown</th>
            </tr>
            <tr>
                <th>Role</th>
                <th>Payslips</th>
                <th>Employees</th>
                <th>Gross</th>
                <th>Deductions</th>
                <th>Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($roleBreakdown as $row)
                <tr>
                    <td>{{ $row['role'] }}</td>
                    <td>{{ $row['payslip_count'] }}</td>
                    <td>{{ $row['employees_count'] }}</td>
                    <td>{{ $row['gross_total_label'] }}</td>
                    <td>{{ $row['deductions_total_label'] }}</td>
                    <td>{{ $row['net_total_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No role totals were found for this month and filter set.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
