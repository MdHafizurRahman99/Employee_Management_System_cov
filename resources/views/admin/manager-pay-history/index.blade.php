@extends('layouts.admin.master')

@section('title')
    Team Pay History
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Team Pay History</h1>
                <p class="mb-0 text-muted">Review team payslips from Odoo and filter by employee when needed.</p>
            </div>
            <a href="{{ route('manager.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                Back to Manager Dashboard
            </a>
        </div>

        @if ($odooPayrollError)
            <div class="alert alert-warning">
                {{ $odooPayrollError }}
            </div>
        @endif

        @if ($payrollMessage)
            <div class="alert alert-{{ $payrollAvailable ? 'info' : 'warning' }}">
                {{ $payrollMessage }}
            </div>
        @endif

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Payslips</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $paySummary['payslip_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $paySummary['employees_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Gross Pay</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $paySummary['gross_pay_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Net Pay</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $paySummary['net_pay_total_label'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filter Team Payslips</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('manager.pay-history.index') }}">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-6 mb-md-0">
                            <label for="employee_id">Employee</label>
                            <select name="employee_id" id="employee_id" class="form-control">
                                <option value="">All team employees</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee['id'] }}"
                                        {{ (string) $selectedEmployeeId === (string) $employee['id'] ? 'selected' : '' }}>
                                        {{ $employee['name'] }}{{ $employee['company'] ? ' - '.$employee['company'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3 mb-md-0">
                            <button type="submit" class="btn btn-primary btn-block">Apply Filter</button>
                        </div>
                        <div class="form-group col-md-3 mb-0">
                            <a href="{{ route('manager.pay-history.index') }}" class="btn btn-outline-secondary btn-block">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <h6 class="m-0 font-weight-bold text-primary">Team Payslip History</h6>
                <span class="badge badge-light mt-2 mt-sm-0">Latest Period: {{ $paySummary['latest_period_label'] }}</span>
            </div>
            <div class="card-body">
                @if (! $hasManagerPayrollIdentity)
                    <div class="text-center py-5">
                        <i class="fas fa-user-lock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Manager access is required to view team pay history.</p>
                    </div>
                @elseif (! $payrollAvailable)
                    <div class="text-center py-5">
                        <i class="fas fa-ban fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Team payslips will appear here once payroll is available in Odoo.</p>
                    </div>
                @elseif (empty($payslips))
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No team payslips were found for the selected filter.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th>Reference</th>
                                    <th>Gross Pay</th>
                                    <th>Deductions</th>
                                    <th>Net Pay</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payslips as $payslip)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold">{{ $payslip['employee'] }}</div>
                                            <div class="small text-muted">{{ $payslip['company'] }}</div>
                                        </td>
                                        <td>{{ $payslip['period_label'] }}</td>
                                        <td>{{ $payslip['number'] ?: $payslip['title'] }}</td>
                                        <td>{{ number_format((float) $payslip['gross_pay'], 2) }}</td>
                                        <td>{{ number_format((float) $payslip['deductions'], 2) }}</td>
                                        <td class="font-weight-bold">{{ number_format((float) $payslip['net_pay'], 2) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $payslip['state'] === 'done' ? 'success' : ($payslip['state'] === 'draft' ? 'secondary' : 'info') }}">
                                                {{ $payslip['state_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $payslip['updated_label'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
