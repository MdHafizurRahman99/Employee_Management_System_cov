@extends('layouts.admin.master')

@section('title')
    Leave Report
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Leave Report</h1>
                <p class="mb-0 text-muted">Review approved leave taken by employee and leave type, plus remaining balances.</p>
            </div>
            <div class="d-flex flex-wrap">
                <a href="{{ route('manager.leave-report.export.excel', request()->query()) }}"
                    class="btn btn-outline-success btn-sm mr-2 mb-2 mb-sm-0">
                    <i class="fas fa-file-excel mr-1"></i>
                    Export Excel
                </a>
                <a href="{{ route('manager.leave-report.export.pdf', request()->query()) }}"
                    class="btn btn-outline-danger btn-sm mr-2 mb-2 mb-sm-0">
                    <i class="fas fa-file-pdf mr-1"></i>
                    Export PDF
                </a>
                <a href="{{ route('manager.dashboard') }}" class="btn btn-outline-secondary btn-sm mb-2 mb-sm-0">
                    Back to Manager Dashboard
                </a>
            </div>
        </div>

        @if ($errors->has('manager_leave_report'))
            <div class="alert alert-danger">
                {{ $errors->first('manager_leave_report') }}
            </div>
        @endif

        @if ($odooLeaveReportError)
            <div class="alert alert-warning">
                {{ $odooLeaveReportError }}
            </div>
        @endif

        @if ($leaveMessage)
            <div class="alert alert-{{ $leaveAvailable ? 'info' : 'warning' }}">
                {{ $leaveMessage }}
            </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('manager.leave-report.index') }}">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label for="from_date">From Date</label>
                            <input type="date" id="from_date" name="from_date" class="form-control"
                                value="{{ $selectedFromDate->format('Y-m-d') }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="to_date">To Date</label>
                            <input type="date" id="to_date" name="to_date" class="form-control"
                                value="{{ $selectedToDate->format('Y-m-d') }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="employee_id">Employee</label>
                            <select id="employee_id" name="employee_id" class="form-control">
                                <option value="">All team employees</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee['id'] }}"
                                        {{ $selectedEmployeeId === $employee['id'] ? 'selected' : '' }}>
                                        {{ $employee['name'] }}{{ $employee['company'] ? ' - '.$employee['company'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="leave_type_id">Leave Type</label>
                            <select id="leave_type_id" name="leave_type_id" class="form-control">
                                <option value="">All leave types</option>
                                @foreach ($leaveTypes as $leaveType)
                                    <option value="{{ $leaveType['id'] }}"
                                        {{ $selectedLeaveTypeId === $leaveType['id'] ? 'selected' : '' }}>
                                        {{ $leaveType['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2 ml-auto mb-0">
                            <button type="submit" class="btn btn-primary btn-block">Apply</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Range</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['range_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Day-Based Leave</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['day_based_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Hourly Leave</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['hour_based_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Employees Shown</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['employees_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Leave Rows</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['row_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Leave Types</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['leave_types_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12 mb-4">
                <div class="card border-left-dark shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Rows With Balance</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['balance_rows_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Approved Leave Taken</h6>
                    <p class="mb-0 small text-muted">
                        {{ $reportSummary['request_count_total'] }} approved leave request(s) matched the selected filters.
                    </p>
                </div>
            </div>
            <div class="card-body">
                @if (! $hasManagerLeaveIdentity)
                    <div class="text-center py-5">
                        <i class="fas fa-user-lock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Manager access is required to view leave reporting.</p>
                    </div>
                @elseif (! $leaveAvailable)
                    <div class="text-center py-5">
                        <i class="fas fa-ban fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Leave report data will appear here once Time Off is available in Odoo.</p>
                    </div>
                @elseif (empty($reportRows))
                    <div class="text-center py-5">
                        <i class="fas fa-plane-slash fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No approved leave records were found for the selected filters.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
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
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold">{{ $row['employee'] }}</div>
                                            <div class="small text-muted">{{ $row['company'] }}</div>
                                        </td>
                                        <td>{{ $row['leave_type'] }}</td>
                                        <td>{{ $row['request_unit'] === 'hour' ? 'Hourly' : 'Day Based' }}</td>
                                        <td class="font-weight-bold">{{ $row['taken_label'] }}</td>
                                        <td>{{ $row['remaining_balance_label'] }}</td>
                                        <td>{{ $row['request_count'] }}</td>
                                        <td>{{ $row['last_leave_label'] }}</td>
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
