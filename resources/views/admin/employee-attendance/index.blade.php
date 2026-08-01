@extends('layouts.admin.master')

@section('title')
    My Attendance
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">My Attendance</h1>
                <p class="mb-0 text-muted">Track your workday here. Attendance records from Odoo remain available below for reference.</p>
            </div>

            <div class="d-flex align-items-center">
                <a href="{{ route('employee.attendance.index', ['month' => $previousMonth->format('Y-m')]) }}"
                    class="btn btn-outline-secondary btn-sm mr-2">
                    <i class="fas fa-chevron-left mr-1"></i>
                    Prev
                </a>
                <span class="font-weight-bold text-primary">{{ $selectedMonth->format('F Y') }}</span>
                @if ($canViewNextMonth)
                    <a href="{{ route('employee.attendance.index', ['month' => $nextMonth->format('Y-m')]) }}"
                        class="btn btn-outline-secondary btn-sm ml-2">
                        Next
                        <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('attendance_tracker'))
            <div class="alert alert-warning">
                {{ $errors->first('attendance_tracker') }}
            </div>
        @endif

        @if ($odooAttendanceError)
            <div class="alert alert-warning">
                {{ $odooAttendanceError }}
            </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Live Attendance Tracker</h6>
                    <p class="mb-0 small text-muted">Break time is tracked separately and deducted from payment-ready hours.</p>
                </div>
                <span class="badge badge-{{ $attendanceTracker['status_class'] }} mt-2 mt-lg-0">
                    {{ $attendanceTracker['status_label'] }}
                </span>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-lg-8 mb-3 mb-lg-0">
                        <div class="row">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="small text-uppercase text-muted font-weight-bold">Checked In Since</div>
                                <div class="h6 mb-0 text-gray-800">
                                    {{ $attendanceTracker['active_session_started_label'] ?? 'Not checked in' }}
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="small text-uppercase text-muted font-weight-bold">Live Gross Hours</div>
                                <div class="h6 mb-0 text-gray-800">{{ $attendanceTracker['live_gross_hours_label'] }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-uppercase text-muted font-weight-bold">Live Payable Hours</div>
                                <div class="h6 mb-0 text-success">{{ $attendanceTracker['live_payable_hours_label'] }}</div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="small text-uppercase text-muted font-weight-bold">Break Started</div>
                                <div class="h6 mb-0 text-gray-800">
                                    {{ $attendanceTracker['active_break_started_label'] ?? 'No active break' }}
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="small text-uppercase text-muted font-weight-bold">Live Break Time</div>
                                <div class="h6 mb-0 text-warning">{{ $attendanceTracker['live_break_hours_label'] }}</div>
                            </div>
                            <div class="col-md-4">
                                <div class="small text-uppercase text-muted font-weight-bold">Selected Month</div>
                                <div class="h6 mb-0 text-gray-800">{{ $selectedMonth->format('F Y') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            <form method="POST" action="{{ route('employee.attendance.check-in') }}" class="mr-2 mb-2">
                                @csrf
                                <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                                <button type="submit" class="btn btn-success btn-sm" {{ $attendanceTracker['can_check_in'] ? '' : 'disabled' }}>
                                    <i class="fas fa-sign-in-alt mr-1"></i>
                                    Check In
                                </button>
                            </form>
                            <form method="POST" action="{{ route('employee.attendance.start-break') }}" class="mr-2 mb-2">
                                @csrf
                                <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                                <button type="submit" class="btn btn-warning btn-sm" {{ $attendanceTracker['can_start_break'] ? '' : 'disabled' }}>
                                    <i class="fas fa-coffee mr-1"></i>
                                    Start Break
                                </button>
                            </form>
                            <form method="POST" action="{{ route('employee.attendance.end-break') }}" class="mr-2 mb-2">
                                @csrf
                                <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                                <button type="submit" class="btn btn-info btn-sm" {{ $attendanceTracker['can_end_break'] ? '' : 'disabled' }}>
                                    <i class="fas fa-play mr-1"></i>
                                    End Break
                                </button>
                            </form>
                            <form method="POST" action="{{ route('employee.attendance.check-out') }}" class="mb-2">
                                @csrf
                                <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                                <button type="submit" class="btn btn-danger btn-sm" {{ $attendanceTracker['can_check_out'] ? '' : 'disabled' }}>
                                    <i class="fas fa-sign-out-alt mr-1"></i>
                                    Check Out
                                </button>
                            </form>
                        </div>
                        <p class="small text-muted mb-0 text-lg-right">
                            Checking out during a break closes the break at the same time for payroll calculations.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Days Tracked</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $trackedAttendanceSummary['total_days'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Payable Hours</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $trackedAttendanceSummary['total_payable_hours_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Break Time</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $trackedAttendanceSummary['total_break_hours_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Days In Progress</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $trackedAttendanceSummary['open_days'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Payment-Ready Attendance for {{ $selectedMonth->format('F Y') }}</h6>
                    <p class="mb-0 small text-muted">
                        {{ $trackedAttendanceSummary['total_sessions'] }} session(s),
                        {{ $trackedAttendanceSummary['total_breaks'] }} break(s),
                        {{ $trackedAttendanceSummary['average_payable_hours_label'] }} average payable hours across complete days.
                    </p>
                </div>
                <span class="badge badge-light mt-2 mt-sm-0">Closed sessions only count toward payroll totals.</span>
            </div>
            <div class="card-body">
                @if (empty($trackedAttendanceDays))
                    <div class="text-center py-5">
                        <i class="fas fa-stopwatch fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No locally tracked attendance has been recorded for {{ $selectedMonth->format('F Y') }}.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>First Check In</th>
                                    <th>Last Check Out</th>
                                    <th>Gross Hours</th>
                                    <th>Break Hours</th>
                                    <th>Payable Hours</th>
                                    <th>Sessions</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trackedAttendanceDays as $day)
                                    <tr class="{{ $day['open_session_count'] > 0 ? 'table-warning' : ($day['is_today'] ? 'table-success' : '') }}">
                                        <td>
                                            <div class="font-weight-bold">{{ $day['date_label'] }}</div>
                                            @if ($day['is_today'])
                                                <div class="small text-muted">Today</div>
                                            @endif
                                        </td>
                                        <td>{{ $day['first_check_in_label'] }}</td>
                                        <td class="{{ $day['open_session_count'] > 0 ? 'text-warning font-weight-bold' : '' }}">{{ $day['last_check_out_label'] }}</td>
                                        <td>{{ $day['gross_hours_label'] }}</td>
                                        <td>{{ $day['break_hours_label'] }}</td>
                                        <td class="font-weight-bold text-success">{{ $day['payable_hours_label'] }}</td>
                                        <td>
                                            <div>{{ $day['session_count'] }} session{{ $day['session_count'] === 1 ? '' : 's' }}</div>
                                            <div class="small text-muted">{{ $day['break_count'] }} break{{ $day['break_count'] === 1 ? '' : 's' }}</div>
                                            @if ($day['open_session_count'] > 0)
                                                <div class="small text-muted">
                                                    {{ $day['open_session_count'] }} open
                                                </div>
                                            @endif
                                            @if ($day['open_break_count'] > 0)
                                                <div class="small text-muted">
                                                    {{ $day['open_break_count'] }} active break
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $day['status_class'] }}">{{ $day['status_label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="row mb-2">
            <div class="col-12">
                <p class="small text-muted mb-3">
                    Odoo summary for {{ $currentMonthSummary['month_label'] }}. Open sessions are excluded
                    from worked hours until a clock-out is recorded in Odoo.
                </p>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Odoo Days With Attendance</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $currentMonthSummary['total_days'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Odoo Worked Hours</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            {{ $currentMonthSummary['total_worked_hours_label'] }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Odoo Days Missing Clock-out</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $currentMonthSummary['open_days'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Odoo Attendance Reference for {{ $selectedMonth->format('F Y') }}</h6>
                    <p class="mb-0 small text-muted">
                        {{ $selectedMonthSummary['total_days'] }} day(s) recorded,
                        {{ $selectedMonthSummary['total_worked_hours_label'] }},
                        {{ $selectedMonthSummary['open_days'] }} day(s) needing attention.
                    </p>
                </div>
                @if (! $selectedMonth->isSameMonth($currentMonth))
                    <span class="badge badge-light mt-2 mt-sm-0">Summary cards stay on {{ $currentMonth->format('F Y') }}</span>
                @endif
            </div>
            <div class="card-body">
                @if (! $hasAttendanceIdentity)
                    <div class="text-center py-5">
                        <i class="fas fa-user-clock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">This account is not linked to an Odoo employee record yet.</p>
                    </div>
                @elseif (empty($attendanceDays))
                    <div class="text-center py-5">
                        <i class="fas fa-clock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No Odoo attendance records were found for {{ $selectedMonth->format('F Y') }}.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Worked Hours</th>
                                    <th>Sessions</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendanceDays as $day)
                                    <tr class="{{ $day['missing_clock_out'] ? 'table-warning' : ($day['is_today'] ? 'table-success' : '') }}">
                                        <td>
                                            <div class="font-weight-bold">{{ $day['date_label'] }}</div>
                                            @if ($day['is_today'])
                                                <div class="small text-muted">Today</div>
                                            @endif
                                        </td>
                                        <td>{{ $day['clock_in_label'] }}</td>
                                        <td>
                                            <div class="{{ $day['missing_clock_out'] ? 'text-danger font-weight-bold' : '' }}">
                                                {{ $day['clock_out_label'] }}
                                            </div>
                                            @if ($day['clock_out_note'])
                                                <div class="small text-muted">{{ $day['clock_out_note'] }}</div>
                                            @endif
                                        </td>
                                        <td class="{{ $day['worked_hours_label'] === 'Pending' ? 'text-warning font-weight-bold' : '' }}">
                                            {{ $day['worked_hours_label'] }}
                                        </td>
                                        <td>
                                            <div>{{ $day['session_count'] }} session{{ $day['session_count'] === 1 ? '' : 's' }}</div>
                                            @if ($day['open_sessions_count'] > 0)
                                                <div class="small text-muted">
                                                    {{ $day['open_sessions_count'] }} open
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $day['status_class'] }}">{{ $day['status_label'] }}</span>
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
