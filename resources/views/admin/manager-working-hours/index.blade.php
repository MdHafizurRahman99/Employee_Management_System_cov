@extends('layouts.admin.master')

@section('title')
    Working Hours Report
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Working Hours Report</h1>
                <p class="mb-0 text-muted">Compare planned shifts against actual attendance by employee and month.</p>
            </div>
            <div class="d-flex flex-wrap">
                <a href="{{ route('manager.working-hours.export.excel', request()->query()) }}"
                    class="btn btn-outline-success btn-sm mr-2 mb-2 mb-sm-0">
                    <i class="fas fa-file-excel mr-1"></i>
                    Export Excel
                </a>
                <a href="{{ route('manager.working-hours.export.pdf', request()->query()) }}"
                    class="btn btn-outline-danger btn-sm mr-2 mb-2 mb-sm-0">
                    <i class="fas fa-file-pdf mr-1"></i>
                    Export PDF
                </a>
                <a href="{{ route('manager.dashboard') }}" class="btn btn-outline-secondary btn-sm mb-2 mb-sm-0">
                    Back to Manager Dashboard
                </a>
            </div>
        </div>

        @if ($errors->has('manager_working_hours'))
            <div class="alert alert-danger">
                {{ $errors->first('manager_working_hours') }}
            </div>
        @endif

        @if ($odooReportError)
            <div class="alert alert-warning">
                {{ $odooReportError }}
            </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('manager.working-hours.index') }}">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label for="month">Month</label>
                            <input type="month" id="month" name="month" class="form-control"
                                value="{{ $selectedMonth->format('Y-m') }}">
                        </div>
                        <div class="form-group col-md-3">
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
                        <div class="form-group col-md-4">
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
                        <div class="form-group col-md-2">
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Month</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['month_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Planned Hours</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['planned_hours_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Actual Hours</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['actual_hours_total_label'] }}</div>
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
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Overtime</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['overtime_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Undertime</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['undertime_total_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12 mb-4">
                <div class="card border-left-secondary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Missing Clock-outs</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $reportSummary['missing_clock_out_total'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Monthly Comparison</h6>
                    <p class="mb-0 small text-muted">
                        {{ $reportSummary['shift_count_total'] }} planned shift(s),
                        {{ $reportSummary['attendance_days_total'] }} attendance day(s),
                        {{ $reportSummary['missing_clock_out_total'] }} open session(s).
                    </p>
                </div>
            </div>
            <div class="card-body">
                @if (! $hasManagerReportIdentity)
                    <div class="text-center py-5">
                        <i class="fas fa-user-lock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Manager access is required to view working hours reporting.</p>
                    </div>
                @elseif (empty($reportRows))
                    <div class="text-center py-5">
                        <i class="fas fa-chart-bar fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No working hours data was found for the selected filters.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
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
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold">{{ $row['employee'] }}</div>
                                            <div class="small text-muted">{{ $row['company'] }}</div>
                                        </td>
                                        <td>{{ $row['planned_hours_label'] }}</td>
                                        <td>{{ $row['actual_hours_label'] }}</td>
                                        <td class="{{ $row['variance_hours'] > 0 ? 'text-success' : ($row['variance_hours'] < 0 ? 'text-danger' : 'text-muted') }} font-weight-bold">
                                            {{ $row['variance_hours_label'] }}
                                        </td>
                                        <td>{{ $row['overtime_hours_label'] }}</td>
                                        <td>{{ $row['undertime_hours_label'] }}</td>
                                        <td>{{ $row['shift_count'] }}</td>
                                        <td>{{ $row['attendance_days_count'] }}</td>
                                        <td>{{ $row['missing_clock_out_count'] }}</td>
                                        <td>
                                            <span class="badge badge-{{ $row['status_class'] }}">
                                                {{ $row['status_label'] }}
                                            </span>
                                        </td>
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
