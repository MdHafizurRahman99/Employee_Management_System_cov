@extends('layouts.admin.master')

@section('title')
    My Shifts
@endsection

@section('css')
    <style>
        .employee-shift-calendar {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .employee-shift-weekday {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
            padding: 0 0.25rem;
        }

        .employee-shift-day {
            display: block;
            min-height: 145px;
            padding: 0.9rem;
            border: 1px solid #dce6ff;
            border-radius: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
            box-shadow: 0 10px 24px rgba(28, 69, 135, 0.06);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            color: inherit;
            text-decoration: none;
        }

        .employee-shift-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(28, 69, 135, 0.12);
            border-color: #9fb7ff;
            color: inherit;
            text-decoration: none;
        }

        .employee-shift-day.is-outside {
            opacity: 0.55;
            background: #f8fafc;
        }

        .employee-shift-day.is-selected {
            border-color: #4e73df;
            box-shadow: 0 18px 32px rgba(78, 115, 223, 0.18);
        }

        .employee-shift-day.is-today {
            background: linear-gradient(180deg, #effbf3 0%, #ffffff 100%);
        }

        .employee-shift-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .employee-shift-number {
            font-size: 1.05rem;
            font-weight: 800;
            color: #1f2937;
        }

        .employee-shift-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.75rem;
            height: 1.75rem;
            padding: 0 0.5rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #4e73df;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .employee-shift-stack {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .employee-shift-pill {
            padding: 0.45rem 0.55rem;
            border-radius: 0.75rem;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.25;
        }

        .employee-shift-pill small {
            display: block;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 600;
            margin-top: 0.2rem;
        }

        .employee-shift-empty {
            color: #9ca3af;
            font-size: 0.78rem;
            line-height: 1.35;
        }

        .employee-shift-detail {
            border: 1px solid #e5e7eb;
            border-radius: 0.9rem;
            padding: 0.9rem 1rem;
            background: #fff;
        }

        .employee-shift-detail + .employee-shift-detail {
            margin-top: 0.75rem;
        }

        .diary-shell {
            --diary-ink: #24352f;
            --diary-paper: #fffdf7;
            --diary-line: rgba(68, 94, 84, 0.13);
            --diary-green: #24735a;
            --diary-rust: #a64e38;
            --diary-gold: #b8872f;
        }

        .diary-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(70, 84, 75, 0.14);
            border-left: 9px solid #365e50;
            border-radius: 0.45rem 1.4rem 1.4rem 0.45rem;
            background:
                linear-gradient(90deg, rgba(54, 94, 80, 0.06) 1px, transparent 1px) 2.2rem 0 / 2px 100%,
                repeating-linear-gradient(180deg, transparent 0, transparent 31px, rgba(73, 115, 99, 0.08) 32px),
                var(--diary-paper);
            box-shadow: 0 22px 45px rgba(44, 64, 56, 0.1);
            padding: 1.65rem 1.8rem 1.65rem 3.6rem;
        }

        .diary-hero::before {
            content: "";
            position: absolute;
            left: 1rem;
            top: 0.75rem;
            bottom: 0.75rem;
            width: 0.65rem;
            background: repeating-linear-gradient(180deg, #d9c7a5 0 7px, transparent 7px 18px);
            opacity: 0.8;
        }

        .diary-kicker {
            color: var(--diary-green);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .diary-hero h1 {
            color: var(--diary-ink);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(1.85rem, 3vw, 2.8rem);
            margin: 0.35rem 0;
        }

        .diary-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
        }

        .diary-key, .diary-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .diary-key { padding: 0.4rem 0.65rem; }
        .diary-key.is-shift { background: #eaf1ff; color: #2857a7; }
        .diary-key.is-available, .diary-pill.is-available { background: #e5f4ec; color: #176448; }
        .diary-key.is-unavailable, .diary-pill.is-unavailable { background: #fbe9e5; color: #98422f; }
        .diary-key.is-note, .diary-pill.is-note { background: #fff3d6; color: #805b17; }

        .diary-day-items {
            display: flex;
            flex-direction: column;
            gap: 0.32rem;
            margin-top: 0.45rem;
        }

        .diary-pill {
            border-radius: 0.55rem;
            justify-content: flex-start;
            overflow: hidden;
            padding: 0.38rem 0.48rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .diary-entry-card {
            position: relative;
            border: 1px solid var(--diary-line);
            border-radius: 1rem;
            background: var(--diary-paper);
            padding: 1rem 1.05rem 1rem 1.2rem;
        }

        .diary-entry-card + .diary-entry-card { margin-top: 0.7rem; }
        .diary-entry-card.is-available { border-left: 5px solid var(--diary-green); }
        .diary-entry-card.is-unavailable { border-left: 5px solid var(--diary-rust); }
        .diary-entry-card.is-note { border-left: 5px solid var(--diary-gold); }

        .diary-entry-type {
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .diary-modal .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 28px 70px rgba(25, 46, 38, 0.22);
        }

        .diary-modal .modal-header {
            background: linear-gradient(135deg, #f7f2e8, #edf6f1);
        }

        @media (max-width: 1199.98px) {
            .employee-shift-calendar {
                gap: 0.55rem;
            }

            .employee-shift-day {
                min-height: 125px;
                padding: 0.75rem;
            }
        }

        @media (max-width: 767.98px) {
            .employee-shift-calendar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .employee-shift-weekday {
                display: none;
            }
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid diary-shell pt-3">
        <div class="diary-hero mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="mr-3">
                    <div class="diary-kicker">My work diary</div>
                    <h1>Plan the month in your own words.</h1>
                    <p class="mb-3 text-muted">Your shifts and personal scheduling signals live together here. Managers can see what you add when they prepare the rota.</p>
                    <div class="diary-legend">
                        <span class="diary-key is-shift"><i class="fas fa-briefcase"></i> Assigned shift</span>
                        <span class="diary-key is-available"><i class="fas fa-check-circle"></i> Available</span>
                        <span class="diary-key is-unavailable"><i class="fas fa-ban"></i> Unavailable</span>
                        <span class="diary-key is-note"><i class="fas fa-sticky-note"></i> Note</span>
                    </div>
                </div>
                <div class="mt-3 mt-lg-0 text-right">
                    @if ($hasLeaveIdentity)
                        <button type="button" class="btn btn-success mb-2 add-diary-entry"
                            data-date="{{ $selectedCalendarDateValue }}" data-toggle="modal" data-target="#calendar_entry_modal">
                            <i class="fas fa-plus mr-1" aria-hidden="true"></i> Add Diary Entry
                        </button>
                    @else
                        <span class="d-block small text-muted mb-2">Connect this account to an Odoo employee to use the diary.</span>
                    @endif
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status" aria-live="polite">{{ session('success') }}</div>
        @endif
        @if ($errors->hasAny(['calendar_entry', 'entry_date', 'entry_type', 'title', 'notes', 'is_all_day', 'start_time', 'end_time']))
            <div class="alert alert-danger" role="alert" aria-live="polite">
                @foreach (['calendar_entry', 'entry_date', 'entry_type', 'title', 'notes', 'is_all_day', 'start_time', 'end_time'] as $diaryField)
                    @error($diaryField)<div>{{ $message }}</div>@enderror
                @endforeach
            </div>
        @endif

        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="h4 mb-0 text-gray-800">Schedule calendar</h2>
                <p class="mb-0 text-muted">Odoo shifts plus {{ count($diaryEntries) }} diary entr{{ count($diaryEntries) === 1 ? 'y' : 'ies' }} this month.</p>
            </div>

            <div class="d-flex align-items-center">
                <a href="{{ route('employee.shifts.index', ['month' => $previousMonth->format('Y-m')]) }}"
                    class="btn btn-outline-secondary btn-sm mr-2">
                    <i class="fas fa-chevron-left mr-1"></i>
                    Prev
                </a>
                <span class="font-weight-bold text-primary">{{ $selectedMonth->format('F Y') }}</span>
                <a href="{{ route('employee.shifts.index', ['month' => $nextMonth->format('Y-m')]) }}"
                    class="btn btn-outline-secondary btn-sm ml-2">
                    Next
                    <i class="fas fa-chevron-right ml-1"></i>
                </a>
            </div>
        </div>

        @if ($odooShiftError)
            <div class="alert alert-warning">
                {{ $odooShiftError }}
            </div>
        @endif
        @if ($odooDiaryError)
            <div class="alert alert-warning">
                {{ $odooDiaryError }}
            </div>
        @endif

        @if ($todayShift)
            <div class="card shadow border-left-success mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Shift</div>
                            <h5 class="mb-1">{{ $todayShift['title'] }}</h5>
                            <p class="mb-0 text-muted">
                                {{ $todayShift['date_label'] }} | {{ $todayShift['start_label'] }} -
                                {{ $todayShift['end_label'] }}
                            </p>
                        </div>
                        <div class="text-sm-right">
                            <div class="font-weight-bold">{{ $todayShift['role'] }}</div>
                            <div class="text-muted">{{ $todayShift['company'] }}</div>
                            <div class="small text-muted"><i class="fas fa-map-marker-alt mr-1 text-danger"></i>{{ $todayShift['work_location'] ?? 'No work location' }}</div>
                            @if ($hasLeaveIdentity && ($todayShift['can_request_unavailability'] ?? false) && ($todayShift['request_unavailability_url'] ?? null))
                                <a href="{{ $todayShift['request_unavailability_url'] }}"
                                    class="btn btn-outline-success btn-sm mt-3">
                                    <i class="fas fa-user-clock mr-1"></i>
                                    Request Unavailability
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Shift Calendar</h6>
                    <p class="mb-0 small text-muted">Use the calendar to see which days have assigned shifts before reviewing the full list.</p>
                </div>
                <span class="badge badge-light mt-2 mt-sm-0">{{ count($shifts) }} shift{{ count($shifts) === 1 ? '' : 's' }} in {{ $selectedMonth->format('F') }}</span>
            </div>
            <div class="card-body">
                <div class="employee-shift-calendar mb-3">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                        <div class="employee-shift-weekday">{{ $weekday }}</div>
                    @endforeach

                    @foreach ($shiftCalendar as $week)
                        @foreach ($week as $day)
                            <a href="{{ route('employee.shifts.index', ['month' => $day['date']->format('Y-m'), 'day' => $day['date_value']]) }}"
                                class="employee-shift-day {{ $day['is_current_month'] ? '' : 'is-outside' }} {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_selected'] ? 'is-selected' : '' }}">
                                <div class="employee-shift-top">
                                    <span class="employee-shift-number">{{ $day['day_number'] }}</span>
                                    <span class="employee-shift-count" title="Assigned shifts">{{ $day['shift_count'] }}</span>
                                </div>
                                <div class="employee-shift-stack">
                                    @if ($day['shift_count'] === 0)
                                        <div class="employee-shift-empty">No assigned shifts</div>
                                    @else
                                        @foreach (array_slice($day['shifts'], 0, 3) as $shift)
                                            <div class="employee-shift-pill">
                                                {{ $shift['title'] }}
                                                <small>{{ $shift['start_label'] }} - {{ $shift['end_label'] }}</small>
                                            </div>
                                        @endforeach

                                        @if ($day['shift_count'] > 3)
                                            <div class="employee-shift-empty">+{{ $day['shift_count'] - 3 }} more</div>
                                        @endif
                                    @endif
                                </div>
                                @if (($day['diary_count'] ?? 0) > 0)
                                    <div class="diary-day-items">
                                        @foreach (array_slice($day['diary_entries'], 0, 2) as $entry)
                                            <div class="diary-pill is-{{ $entry['type_class'] }}">
                                                <i class="fas {{ $entry['icon'] }}"></i>
                                                {{ $entry['title'] }}
                                            </div>
                                        @endforeach
                                        @if ($day['diary_count'] > 2)
                                            <div class="employee-shift-empty">+{{ $day['diary_count'] - 2 }} diary entries</div>
                                        @endif
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-success">My diary for {{ $selectedCalendarDateLabel }}</h6>
                    <p class="mb-0 small text-muted">Availability and notes here are visible to schedule managers.</p>
                </div>
                @if ($hasLeaveIdentity)
                    <button type="button" class="btn btn-success btn-sm mt-2 mt-sm-0 add-diary-entry"
                        data-date="{{ $selectedCalendarDateValue }}" data-toggle="modal" data-target="#calendar_entry_modal">
                        <i class="fas fa-plus mr-1" aria-hidden="true"></i>Add to This Day
                    </button>
                @endif
            </div>
            <div class="card-body">
                @forelse ($selectedDiaryEntries as $entry)
                    <div class="diary-entry-card is-{{ $entry['type_class'] }}">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div class="mr-3">
                                <div class="diary-entry-type text-{{ $entry['type_class'] === 'available' ? 'success' : ($entry['type_class'] === 'unavailable' ? 'danger' : 'warning') }}">
                                    <i class="fas {{ $entry['icon'] }} mr-1"></i>{{ $entry['type_label'] }}
                                </div>
                                <div class="font-weight-bold mt-1">{{ $entry['title'] }}</div>
                                @if ($entry['notes'])<div class="small text-muted mt-1">{{ $entry['notes'] }}</div>@endif
                            </div>
                            <div class="text-right">
                                <div class="font-weight-bold">{{ $entry['time_label'] }}</div>
                                <button type="button" class="btn btn-link btn-sm edit-diary-entry"
                                    data-toggle="modal" data-target="#calendar_entry_modal"
                                    data-id="{{ $entry['id'] }}"
                                    data-date="{{ $entry['date_value'] }}"
                                    data-type="{{ $entry['entry_type'] }}"
                                    data-title="{{ $entry['title'] }}"
                                    data-notes="{{ $entry['notes'] }}"
                                    data-all-day="{{ $entry['is_all_day'] ? '1' : '0' }}"
                                    data-start="{{ $entry['start_time_value'] }}"
                                    data-end="{{ $entry['end_time_value'] }}">
                                    <i class="fas fa-pen mr-1"></i>Edit
                                </button>
                                <form action="{{ route('employee.calendar-entries.destroy', $entry['id']) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this diary entry?')">
                                    @csrf
                                    <button class="btn btn-link btn-sm text-danger" type="submit"><i class="fas fa-trash mr-1"></i>Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4">
                        <i class="fas fa-book-open fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Nothing written for this day yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Shifts for {{ $selectedCalendarDateLabel }}</h6>
                    <p class="mb-0 small text-muted">This keeps the month view compact while still showing the selected day in detail.</p>
                </div>
                <span class="badge badge-primary mt-2 mt-sm-0">{{ count($selectedCalendarShifts) }} shift{{ count($selectedCalendarShifts) === 1 ? '' : 's' }}</span>
            </div>
            <div class="card-body">
                @if (empty($selectedCalendarShifts))
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-day fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No shifts are assigned on {{ $selectedCalendarDateLabel }}.</p>
                    </div>
                @else
                    @foreach ($selectedCalendarShifts as $shift)
                        <div class="employee-shift-detail">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div class="mb-2 mb-sm-0">
                                    <div class="font-weight-bold">{{ $shift['title'] }}</div>
                                    <div class="small text-muted">{{ $shift['role'] }}</div>
                                </div>
                                <div class="text-sm-right">
                                    <div class="font-weight-bold text-primary">{{ $shift['start_label'] }} - {{ $shift['end_label'] }}</div>
                                    <div class="small text-muted">{{ $shift['company'] }}</div>
                                    <div class="small text-muted"><i class="fas fa-map-marker-alt mr-1 text-danger"></i>{{ $shift['work_location'] ?? 'No work location' }}</div>
                                    @if (($shift['requires_confirmation'] ?? false) && ($shift['confirmation_status'] ?? 'pending') === 'pending')
                                        <div class="mt-3 d-flex flex-wrap justify-content-end">
                                            <form action="{{ route('employee.shifts.respond', $shift['id']) }}" method="POST" class="mr-2 mb-2">@csrf<input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}"><input type="hidden" name="status" value="accepted"><button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check mr-1"></i>Accept</button></form>
                                            <form action="{{ route('employee.shifts.respond', $shift['id']) }}" method="POST" class="mb-2">@csrf<input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}"><input type="hidden" name="status" value="declined"><input name="note" class="form-control form-control-sm mb-1" maxlength="500" placeholder="Reason for declining" required><button class="btn btn-outline-danger btn-sm" type="submit"><i class="fas fa-times mr-1"></i>Decline</button></form>
                                        </div>
                                    @elseif (($shift['requires_confirmation'] ?? false) && ($shift['confirmation_status'] ?? null))
                                        <span class="badge badge-{{ $shift['confirmation_status'] === 'accepted' ? 'success' : 'danger' }} mt-2">{{ ucfirst($shift['confirmation_status']) }}</span>
                                    @endif
                                    @if ($hasLeaveIdentity && ($shift['can_request_unavailability'] ?? false) && ($shift['request_unavailability_url'] ?? null))
                                        <a href="{{ $shift['request_unavailability_url'] }}"
                                            class="btn btn-outline-primary btn-sm mt-3">
                                            <i class="fas fa-user-clock mr-1"></i>
                                            Request Unavailability
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Shift List</h6>
            </div>
            <div class="card-body">
                @if (empty($shifts))
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-alt fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No shifts were found for {{ $selectedMonth->format('F Y') }}.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Role</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shifts as $shift)
                                    <tr class="{{ $shift['is_today'] ? 'table-success' : ($shift['date_value'] === $selectedCalendarDateValue ? 'table-primary' : '') }}">
                                        <td>
                                            <div class="font-weight-bold">{{ $shift['date_label'] }}</div>
                                            <div class="small text-muted">{{ $shift['title'] }}</div>
                                        </td>
                                        <td>{{ $shift['start_label'] }}</td>
                                        <td>{{ $shift['end_label'] }}</td>
                                        <td>{{ $shift['role'] }}</td>
                                        <td>{{ $shift['company'] }}<div class="small text-muted"><i class="fas fa-map-marker-alt mr-1"></i>{{ $shift['work_location'] ?? 'No work location' }}</div></td>
                                        <td>
                                            @if ($shift['is_today'])
                                                <span class="badge badge-success">Today</span>
                                            @elseif ($shift['end_at']->isPast())
                                                <span class="badge badge-secondary">Completed</span>
                                            @else
                                                <span class="badge badge-primary">Upcoming</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($hasLeaveIdentity && ($shift['can_request_unavailability'] ?? false) && ($shift['request_unavailability_url'] ?? null))
                                                <a href="{{ $shift['request_unavailability_url'] }}"
                                                    class="btn btn-outline-primary btn-sm">
                                                    Request Unavailability
                                                </a>
                                            @elseif (! $hasLeaveIdentity)
                                                <span class="text-muted small">Unavailable</span>
                                            @else
                                                <span class="text-muted small">Not available</span>
                                            @endif
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

    <div id="calendar_entry_modal" class="modal fade diary-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form id="calendar-entry-form" method="POST" action="{{ route('employee.calendar-entries.store') }}">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <div class="diary-kicker">Employee scheduling signal</div>
                            <h5 class="modal-title mt-1">Add diary entry</h5>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="diary_entry_date">Date</label>
                            <input id="diary_entry_date" name="entry_date" type="date" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="form-group">
                            <label for="diary_entry_type">What do you want the scheduler to know?</label>
                            <select id="diary_entry_type" name="entry_type" class="form-control" required>
                                <option value="available">I am available</option>
                                <option value="unavailable">I am unavailable</option>
                                <option value="note">A note or preference</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="diary_title">Short title</label>
                            <input id="diary_title" name="title" type="text" maxlength="120" class="form-control" autocomplete="off" placeholder="Example: Can cover the morning…">
                        </div>
                        <div class="custom-control custom-checkbox mb-3">
                            <input type="checkbox" class="custom-control-input" id="diary_all_day" name="is_all_day" value="1" checked>
                            <label class="custom-control-label" for="diary_all_day">Applies all day</label>
                        </div>
                        <div id="diary-time-fields" class="form-row d-none">
                            <div class="form-group col-6"><label for="diary_start">Start</label><input id="diary_start" name="start_time" type="time" class="form-control"></div>
                            <div class="form-group col-6"><label for="diary_end">End</label><input id="diary_end" name="end_time" type="time" class="form-control"></div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="diary_notes">Details</label>
                            <textarea id="diary_notes" name="notes" rows="3" maxlength="1000" class="form-control" autocomplete="off" placeholder="Anything helpful for the manager when creating the schedule…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-bookmark mr-1"></i>Save entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('calendar-entry-form');
            const modalTitle = document.querySelector('#calendar_entry_modal .modal-title');
            const date = document.getElementById('diary_entry_date');
            const type = document.getElementById('diary_entry_type');
            const title = document.getElementById('diary_title');
            const notes = document.getElementById('diary_notes');
            const allDay = document.getElementById('diary_all_day');
            const start = document.getElementById('diary_start');
            const end = document.getElementById('diary_end');
            const timeFields = document.getElementById('diary-time-fields');
            const storeUrl = @json(route('employee.calendar-entries.store'));
            const updateUrl = @json(route('employee.calendar-entries.update', ['calendarEntry' => '__ENTRY__']));

            const syncTimeFields = () => {
                timeFields.classList.toggle('d-none', allDay.checked);
                start.required = !allDay.checked;
                end.required = !allDay.checked;
            };

            document.querySelectorAll('.add-diary-entry').forEach((button) => {
                button.addEventListener('click', () => {
                    form.action = storeUrl;
                    modalTitle.textContent = 'Add diary entry';
                    form.reset();
                    date.value = button.dataset.date;
                    type.value = 'available';
                    allDay.checked = true;
                    syncTimeFields();
                });
            });

            document.querySelectorAll('.edit-diary-entry').forEach((button) => {
                button.addEventListener('click', () => {
                    form.action = updateUrl.replace('__ENTRY__', button.dataset.id);
                    modalTitle.textContent = 'Edit diary entry';
                    date.value = button.dataset.date;
                    type.value = button.dataset.type;
                    title.value = button.dataset.title || '';
                    notes.value = button.dataset.notes || '';
                    allDay.checked = button.dataset.allDay === '1';
                    start.value = button.dataset.start || '';
                    end.value = button.dataset.end || '';
                    syncTimeFields();
                });
            });

            allDay.addEventListener('change', syncTimeFields);
            syncTimeFields();

            @if ($errors->hasAny(['calendar_entry', 'entry_date', 'entry_type', 'title', 'notes', 'is_all_day', 'start_time', 'end_time']))
                date.value = @json(old('entry_date', $selectedCalendarDateValue));
                type.value = @json(old('entry_type', 'available'));
                title.value = @json(old('title', ''));
                notes.value = @json(old('notes', ''));
                allDay.checked = @json((bool) old('is_all_day', true));
                start.value = @json(old('start_time', ''));
                end.value = @json(old('end_time', ''));
                syncTimeFields();
                $('#calendar_entry_modal').modal('show');
            @endif
        });
    </script>
@endsection
