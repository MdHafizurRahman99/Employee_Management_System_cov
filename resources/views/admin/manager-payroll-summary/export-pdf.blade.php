<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Summary Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { margin: 0 0 4px 0; font-size: 22px; }
        p.meta { margin: 0 0 14px 0; color: #6b7280; }
        .summary { width: 100%; margin-bottom: 18px; border-collapse: collapse; }
        .summary td { width: 25%; border: 1px solid #d1d5db; padding: 10px; vertical-align: top; }
        .summary .label { font-size: 10px; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
        .summary .value { font-size: 16px; font-weight: bold; color: #111827; }
        table.report { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.report th, table.report td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        table.report th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .section-title { margin: 18px 0 8px; font-size: 16px; }
    </style>
</head>
<body>
    <h1>Payroll Summary Report</h1>
    <p class="meta">
        Month: {{ $reportSummary['month_label'] }} |
        Generated: {{ $generatedAt->format('d M Y h:i A') }}
    </p>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Payroll Cost</div>
                <div class="value">{{ $reportSummary['gross_total_label'] }}</div>
            </td>
            <td>
                <div class="label">Deductions</div>
                <div class="value">{{ $reportSummary['deductions_total_label'] }}</div>
            </td>
            <td>
                <div class="label">Net Pay</div>
                <div class="value">{{ $reportSummary['net_total_label'] }}</div>
            </td>
            <td>
                <div class="label">MoM Change</div>
                <div class="value">{{ $comparison['change_value_label'] }}</div>
            </td>
        </tr>
    </table>

    <p class="meta">
        Current: {{ $comparison['current_month_label'] }} ({{ $comparison['current_gross_total_label'] }}) |
        Previous: {{ $comparison['previous_month_label'] }} ({{ $comparison['previous_gross_total_label'] }}) |
        Movement: {{ $comparison['direction_label'] }} ({{ $comparison['change_percent_label'] }})
    </p>

    <div class="section-title">Company Breakdown</div>
    <table class="report">
        <thead>
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

    <div class="section-title">Role Breakdown</div>
    <table class="report">
        <thead>
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
