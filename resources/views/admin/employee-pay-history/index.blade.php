@extends('layouts.admin.master')

@section('title')
    My Pay History
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">My Pay History</h1>
                <p class="mb-0 text-muted">Review your Odoo payslips, gross pay, deductions, and net pay.</p>
            </div>
            <div class="badge badge-light px-3 py-2">
                Latest Period: {{ $paySummary['latest_period_label'] }}
            </div>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Gross Pay</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $paySummary['gross_pay_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Deductions</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $paySummary['deductions_total_label'] }}</div>
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

        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Payslip History</h6>
            </div>
            <div class="card-body">
                @if (! $hasPayrollIdentity)
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice-dollar fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">This account is not linked to an Odoo employee payroll identity yet.</p>
                    </div>
                @elseif (! $payrollAvailable)
                    <div class="text-center py-5">
                        <i class="fas fa-ban fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Pay history will appear here once payroll is available in Odoo.</p>
                    </div>
                @elseif (empty($payslips))
                    <div class="text-center py-5">
                        <i class="fas fa-receipt fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No payslips were found for this employee yet.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
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
                                        <td>{{ $payslip['period_label'] }}</td>
                                        <td>
                                            <div class="font-weight-bold">{{ $payslip['number'] ?: $payslip['title'] }}</div>
                                            <div class="small text-muted">{{ $payslip['company'] }}</div>
                                        </td>
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
