@extends('layouts.admin.master')

@section('title')
    Payroll Summary Report
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Payroll Summary Report</h1>
                <p class="mb-0 text-muted">Review monthly payroll totals, company and role breakdowns, and month-over-month movement.</p>
            </div>
            <div class="d-flex flex-wrap">
                <a href="{{ route('manager.payroll-summary.export.excel', request()->query()) }}"
                    class="btn btn-outline-success btn-sm mr-2 mb-2 mb-sm-0">
                    <i class="fas fa-file-excel mr-1"></i>
                    Export Excel
                </a>
                <a href="{{ route('manager.payroll-summary.export.pdf', request()->query()) }}"
                    class="btn btn-outline-danger btn-sm mr-2 mb-2 mb-sm-0">
                    <i class="fas fa-file-pdf mr-1"></i>
                    Export PDF
                </a>
                <a href="{{ route('manager.dashboard') }}" class="btn btn-outline-secondary btn-sm mb-2 mb-sm-0">
                    Back to Manager Dashboard
                </a>
            </div>
        </div>

        @if ($errors->has('manager_payroll_summary'))
            <div class="alert alert-danger">
                {{ $errors->first('manager_payroll_summary') }}
            </div>
        @endif

        @if ($odooPayrollSummaryError)
            <div class="alert alert-warning">
                {{ $odooPayrollSummaryError }}
            </div>
        @endif

        @if ($payrollMessage)
            <div class="alert alert-{{ $payrollAvailable ? 'info' : 'warning' }}">
                {{ $payrollMessage }}
            </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('manager.payroll-summary.index') }}">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="month">Month</label>
                            <input type="month" id="month" name="month" class="form-control"
                                value="{{ $selectedMonth->format('Y-m') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="company_id">Company</label>
                            <select id="company_id" name="company_id" class="form-control">
                                <option value="">All team companies</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company['id'] }}"
                                        {{ $selectedCompanyId === $company['id'] ? 'selected' : '' }}>
                                        {{ $company['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">Apply</button>
                        </div>
                        <div class="form-group col-md-2">
                            <a href="{{ route('manager.payroll-summary.index') }}" class="btn btn-outline-secondary btn-block">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Month</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['month_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Payroll Cost</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['gross_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Deductions</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['deductions_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Net Pay</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['net_total_label'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Payslips</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['payslip_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['employees_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12 mb-4">
                <div class="card border-left-dark shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Month-over-Month</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $comparison['change_value_label'] }}</div>
                        <div class="small text-muted">{{ $comparison['change_percent_label'] }} vs {{ $comparison['previous_month_label'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Month-over-Month Comparison</h6>
            </div>
            <div class="card-body">
                @if (! $hasManagerPayrollIdentity)
                    <div class="text-center py-4">
                        <i class="fas fa-user-lock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Manager access is required to view payroll summary reporting.</p>
                    </div>
                @else
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="small text-uppercase text-muted">Current Month</div>
                            <div class="font-weight-bold">{{ $comparison['current_month_label'] }}</div>
                            <div>{{ $comparison['current_gross_total_label'] }}</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="small text-uppercase text-muted">Previous Month</div>
                            <div class="font-weight-bold">{{ $comparison['previous_month_label'] }}</div>
                            <div>{{ $comparison['previous_gross_total_label'] }}</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="small text-uppercase text-muted">Movement</div>
                            <div class="font-weight-bold">{{ $comparison['direction_label'] }}</div>
                            <div>{{ $comparison['change_value_label'] }} ({{ $comparison['change_percent_label'] }})</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Company Breakdown</h6>
                    </div>
                    <div class="card-body">
                        @if (! $payrollAvailable)
                            <div class="text-center py-4">
                                <i class="fas fa-ban fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">Company totals will appear here once payroll is available in Odoo.</p>
                            </div>
                        @elseif (empty($companyBreakdown))
                            <div class="text-center py-4">
                                <i class="fas fa-building fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">No company totals were found for the selected month and filter.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Company</th>
                                            <th>Payslips</th>
                                            <th>Employees</th>
                                            <th>Gross</th>
                                            <th>Net</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($companyBreakdown as $row)
                                            <tr>
                                                <td>{{ $row['company'] }}</td>
                                                <td>{{ $row['payslip_count'] }}</td>
                                                <td>{{ $row['employees_count'] }}</td>
                                                <td>{{ $row['gross_total_label'] }}</td>
                                                <td>{{ $row['net_total_label'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Role Breakdown</h6>
                    </div>
                    <div class="card-body">
                        @if (! $payrollAvailable)
                            <div class="text-center py-4">
                                <i class="fas fa-ban fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">Role totals will appear here once payroll is available in Odoo.</p>
                            </div>
                        @elseif (empty($roleBreakdown))
                            <div class="text-center py-4">
                                <i class="fas fa-user-tag fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">No role totals were found for the selected month and filter.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Role</th>
                                            <th>Payslips</th>
                                            <th>Employees</th>
                                            <th>Gross</th>
                                            <th>Net</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($roleBreakdown as $row)
                                            <tr>
                                                <td>{{ $row['role'] }}</td>
                                                <td>{{ $row['payslip_count'] }}</td>
                                                <td>{{ $row['employees_count'] }}</td>
                                                <td>{{ $row['gross_total_label'] }}</td>
                                                <td>{{ $row['net_total_label'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
