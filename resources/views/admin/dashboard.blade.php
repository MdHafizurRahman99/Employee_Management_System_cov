@extends('layouts.admin.master')
@section('title')
    Dashboard
@endsection

@section('css')
    <style>
        .dashboard-availability-card {
            border: 1px solid rgba(23, 53, 61, 0.1);
            border-radius: 1.5rem;
            background:
                radial-gradient(circle at top right, rgba(23, 125, 120, 0.15), transparent 30%),
                linear-gradient(135deg, #fcfaf7 0%, #f5fbfb 58%, #ffffff 100%);
            box-shadow: 0 18px 40px rgba(17, 39, 46, 0.07);
        }

        .dashboard-availability-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
            gap: 0.85rem;
        }

        .dashboard-availability-summary-card {
            border: 1px solid rgba(23, 53, 61, 0.08);
            border-radius: 1.1rem;
            background: rgba(255, 255, 255, 0.88);
            padding: 0.9rem 1rem;
        }

        .dashboard-availability-summary-label {
            color: #5d7580;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .dashboard-availability-summary-value {
            color: #17353d;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.7rem;
            line-height: 1;
            margin-top: 0.55rem;
        }

        .dashboard-availability-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
            gap: 0.75rem;
        }

        .dashboard-availability-day {
            border: 1px solid rgba(23, 53, 61, 0.08);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.92);
            padding: 0.8rem 0.9rem;
        }

        .dashboard-availability-day-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.65rem;
        }

        .dashboard-availability-day-name {
            color: #17353d;
            font-weight: 800;
        }

        .dashboard-availability-day-status {
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            padding: 0.28rem 0.55rem;
            text-transform: uppercase;
        }

        .dashboard-availability-day-status.status-success {
            background: rgba(23, 125, 120, 0.12);
            color: #0d5b57;
        }

        .dashboard-availability-day-status.status-danger {
            background: rgba(170, 74, 58, 0.12);
            color: #954433;
        }

        .dashboard-availability-day-status.status-primary {
            background: rgba(78, 115, 223, 0.12);
            color: #3759b9;
        }

        .dashboard-availability-day-status.status-muted {
            background: rgba(93, 117, 128, 0.12);
            color: #5d7580;
        }

        .dashboard-availability-note {
            color: #5d7580;
            font-size: 0.82rem;
            line-height: 1.5;
        }

        .dashboard-availability-rule {
            color: #17353d;
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .dashboard-availability-rule small {
            color: #5d7580;
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            margin-top: 0.12rem;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
            <div class="text-right">
                <span class="badge badge-primary px-3 py-2 text-uppercase">
                    {{ auth()->user()->auth_source === 'odoo' ? 'Connected Account' : 'Standard Account' }}
                </span>
            </div>
        </div>

        <div class="row">
            @if ($odooShiftError)
                <div class="col-12">
                    <div class="alert alert-warning">
                        {{ $odooShiftError }}
                    </div>
                </div>
            @endif

            <div class="col-lg-8 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h5 class="mb-1">Welcome back, {{ auth()->user()->name }}</h5>
                                <p class="mb-0 text-muted">
                                    You are signed in to the employee portal and your session is active.
                                </p>
                            </div>
                            <i class="fas fa-user-shield fa-2x text-primary"></i>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted pl-0" style="width: 220px;">Email</th>
                                        <td>{{ auth()->user()->email }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted pl-0">Auth Source</th>
                                        <td>{{ ucfirst(auth()->user()->auth_source ?? 'local') }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted pl-0">Odoo User ID</th>
                                        <td>{{ auth()->user()->odoo_user_id ?? 'Not linked' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted pl-0">Odoo Employee ID</th>
                                        <td>{{ auth()->user()->odoo_employee_id ?? 'Not linked' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted pl-0">Odoo Resource ID</th>
                                        <td>{{ auth()->user()->odoo_resource_id ?? 'Not linked' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted pl-0">Last Sync</th>
                                        <td>
                                            {{ optional(auth()->user()->odoo_last_synced_at)->format('M d, Y h:i A') ?? 'Not yet synced' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card shadow h-100">
                    <div class="card-body">
                        <h6 class="text-primary font-weight-bold text-uppercase mb-3">Today</h6>

                        @if ($todayShift)
                            <div class="border-left-success pl-3 mb-4">
                                <div class="font-weight-bold">{{ $todayShift['title'] }}</div>
                                <div class="text-muted small mb-2">{{ $todayShift['date_label'] }}</div>
                                <div class="mb-1">{{ $todayShift['start_label'] }} - {{ $todayShift['end_label'] }}</div>
                                <div class="small text-muted">{{ $todayShift['role'] }} | {{ $todayShift['company'] }}</div>
                            </div>
                        @elseif (auth()->user()->odoo_employee_id || auth()->user()->odoo_resource_id)
                            <p class="text-muted">No shift is scheduled for today.</p>
                        @else
                            <p class="text-muted">Shift information is not available for this account yet.</p>
                        @endif

                        <div class="d-flex flex-wrap">
                            <a href="{{ route('employee.shifts.index') }}" class="btn btn-primary btn-sm mr-2 mb-2">
                                View All Shifts
                            </a>
                            @if (auth()->user()->isOdooUser())
                                <a href="{{ route('employee.availability.index') }}"
                                    class="btn btn-outline-success btn-sm mb-2 mr-2">
                                    Manage Availability
                                </a>
                            @endif
                            @if (auth()->user()->isOdooUser())
                                <a href="{{ route('employee.attendance.index') }}"
                                    class="btn btn-outline-primary btn-sm mb-2">
                                    View Attendance
                                </a>
                                <a href="{{ route('employee.leave.index') }}"
                                    class="btn btn-outline-secondary btn-sm mb-2 ml-sm-2">
                                    Request Leave
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (auth()->user()->isOdooUser())
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card dashboard-availability-card">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start mb-4">
                                <div class="mb-3 mb-lg-0">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-2">Weekly Availability</div>
                                    <h4 class="mb-2 text-gray-900">Keep your recurring work pattern clear for the planning team.</h4>
                                    <p class="mb-0 text-muted">
                                        Use weekly availability for your normal recurring pattern, then use leave requests for one-off absences.
                                    </p>
                                </div>
                                <a href="{{ route('employee.availability.index') }}" class="btn btn-primary btn-sm">
                                    Open Availability Planner
                                </a>
                            </div>

                            @if ($odooAvailabilityError)
                                <div class="alert alert-warning mb-0">
                                    {{ $odooAvailabilityError }}
                                </div>
                            @elseif (! $hasAvailabilityIdentity)
                                <p class="text-muted mb-0">Availability will appear here once this account is linked to an Odoo employee record.</p>
                            @else
                                <div class="dashboard-availability-summary mb-4">
                                    <div class="dashboard-availability-summary-card">
                                        <div class="dashboard-availability-summary-label">Configured Days</div>
                                        <div class="dashboard-availability-summary-value">{{ $availabilitySummary['configured_days'] }}</div>
                                    </div>
                                    <div class="dashboard-availability-summary-card">
                                        <div class="dashboard-availability-summary-label">Total Rules</div>
                                        <div class="dashboard-availability-summary-value">{{ $availabilitySummary['total_rules'] }}</div>
                                    </div>
                                    <div class="dashboard-availability-summary-card">
                                        <div class="dashboard-availability-summary-label">Open Windows</div>
                                        <div class="dashboard-availability-summary-value">{{ $availabilitySummary['available_rules'] }}</div>
                                    </div>
                                    <div class="dashboard-availability-summary-card">
                                        <div class="dashboard-availability-summary-label">Blocked Windows</div>
                                        <div class="dashboard-availability-summary-value">{{ $availabilitySummary['unavailable_rules'] }}</div>
                                    </div>
                                </div>

                                <div class="dashboard-availability-strip">
                                    @foreach ($availabilityDays as $day)
                                        <div class="dashboard-availability-day">
                                            <div class="dashboard-availability-day-head">
                                                <span class="dashboard-availability-day-name">{{ $day['short_label'] }}</span>
                                                <span class="dashboard-availability-day-status status-{{ $day['status_class'] }}">
                                                    {{ $day['entry_count'] }}
                                                </span>
                                            </div>

                                            @if ($day['has_rules'])
                                                @foreach (array_slice($day['entries'], 0, 2) as $entry)
                                                    <div class="dashboard-availability-rule">
                                                        {{ $entry['availability_label'] }}
                                                        <small>{{ $entry['time_label'] }}</small>
                                                    </div>
                                                @endforeach

                                                @if ($day['entry_count'] > 2)
                                                    <div class="dashboard-availability-note mt-2">
                                                        +{{ $day['entry_count'] - 2 }} more rule{{ $day['entry_count'] - 2 === 1 ? '' : 's' }}
                                                    </div>
                                                @endif
                                            @else
                                                <div class="dashboard-availability-note">No recurring rule yet.</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if (!empty($upcomingShifts))
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Upcoming Shifts</h6>
                            <a href="{{ route('employee.shifts.index') }}" class="btn btn-outline-primary btn-sm">
                                View Shift List
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Role</th>
                                            <th>Company</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($upcomingShifts as $shift)
                                            <tr class="{{ $shift['is_today'] ? 'table-success' : '' }}">
                                                <td>
                                                    <div class="font-weight-bold">{{ $shift['date_label'] }}</div>
                                                    <div class="small text-muted">{{ $shift['title'] }}</div>
                                                </td>
                                                <td>{{ $shift['start_label'] }} - {{ $shift['end_label'] }}</td>
                                                <td>{{ $shift['role'] }}</td>
                                                <td>{{ $shift['company'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
