@extends('layouts.admin.master')

@section('title')
    Generate Payslips
@endsection

@section('content')
    @php($generatedPayslip = session('generated_payslip'))

    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Generate Payslips</h1>
                <p class="mb-0 text-muted">Create and compute Odoo payslips for employees using a selected pay period.</p>
            </div>
            <a href="{{ route('manager.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                Back to Manager Dashboard
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('manager_payslip'))
            <div class="alert alert-danger">
                {{ $errors->first('manager_payslip') }}
            </div>
        @endif

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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Payroll Status</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $payrollAvailable ? 'Available' : 'Unavailable' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ count($employees) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Recent Payslips</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ count($recentPayslips) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Last Generated Net Pay</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            {{ is_array($generatedPayslip) ? number_format((float) ($generatedPayslip['net_pay'] ?? 0), 2) : '0.00' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">New Odoo Payslip</h6>
                    </div>
                    <div class="card-body">
                        @if (! $payrollAvailable)
                            <div class="text-center py-4">
                                <i class="fas fa-file-invoice-dollar fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">Payslip generation is currently unavailable for this Odoo connection.</p>
                            </div>
                        @elseif (empty($employees))
                            <div class="text-center py-4">
                                <i class="fas fa-users-slash fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">No employees eligible for payslip generation were returned.</p>
                            </div>
                        @else
                            <form action="{{ route('manager.payslips.store') }}" method="POST">
                                @csrf

                                <div class="form-group">
                                    <label for="employee_id">Employee</label>
                                    <select name="employee_id" id="employee_id"
                                        class="form-control @error('employee_id') is-invalid @enderror" required>
                                        <option value="">Select employee</option>
                                        @foreach ($employees as $employee)
                                            <option value="{{ $employee['id'] }}"
                                                {{ (string) old('employee_id') === (string) $employee['id'] ? 'selected' : '' }}>
                                                {{ $employee['name'] }}{{ $employee['company'] ? ' - '.$employee['company'] : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="period_start">Period Start</label>
                                        <input type="date" name="period_start" id="period_start"
                                            class="form-control @error('period_start') is-invalid @enderror"
                                            value="{{ old('period_start', now()->startOfMonth()->format('Y-m-d')) }}" required>
                                        @error('period_start')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="period_end">Period End</label>
                                        <input type="date" name="period_end" id="period_end"
                                            class="form-control @error('period_end') is-invalid @enderror"
                                            value="{{ old('period_end', now()->endOfMonth()->format('Y-m-d')) }}" required>
                                        @error('period_end')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">
                                    Create and Compute Payslip
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Generated Payslip Summary</h6>
                    </div>
                    <div class="card-body">
                        @if (! is_array($generatedPayslip))
                            <div class="text-center py-4">
                                <i class="fas fa-receipt fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">The latest generated payslip summary will appear here after computation.</p>
                            </div>
                        @else
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="small text-uppercase text-muted">Employee</div>
                                    <div class="font-weight-bold">{{ $generatedPayslip['employee'] ?? 'Employee' }}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="small text-uppercase text-muted">Status</div>
                                    <div class="font-weight-bold">{{ $generatedPayslip['state_label'] ?? 'Draft' }}</div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <div class="small text-uppercase text-muted">Pay Period</div>
                                    <div class="font-weight-bold">{{ $generatedPayslip['period_label'] ?? 'N/A' }}</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="small text-uppercase text-muted">Gross Pay</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ number_format((float) ($generatedPayslip['gross_pay'] ?? 0), 2) }}
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="small text-uppercase text-muted">Deductions</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ number_format((float) ($generatedPayslip['deductions'] ?? 0), 2) }}
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="small text-uppercase text-muted">Net Pay</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        {{ number_format((float) ($generatedPayslip['net_pay'] ?? 0), 2) }}
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="small text-uppercase text-muted">Reference</div>
                                    <div class="font-weight-bold">{{ $generatedPayslip['number'] ?? $generatedPayslip['title'] ?? 'Payslip' }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Odoo Payslips</h6>
            </div>
            <div class="card-body">
                @if (empty($recentPayslips))
                    <div class="text-center py-4">
                        <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Recent payslips will appear here after payroll is available and payslips have been generated.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th>Gross Pay</th>
                                    <th>Deductions</th>
                                    <th>Net Pay</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentPayslips as $payslip)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold">{{ $payslip['employee'] }}</div>
                                            <div class="small text-muted">{{ $payslip['number'] ?: $payslip['title'] }}</div>
                                        </td>
                                        <td>{{ $payslip['period_label'] }}</td>
                                        <td>{{ number_format((float) $payslip['gross_pay'], 2) }}</td>
                                        <td>{{ number_format((float) $payslip['deductions'], 2) }}</td>
                                        <td>{{ number_format((float) $payslip['net_pay'], 2) }}</td>
                                        <td>{{ $payslip['state_label'] }}</td>
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
