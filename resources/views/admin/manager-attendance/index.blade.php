@extends('layouts.admin.master')

@section('title')
    Team Attendance
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Team Attendance</h1>
                <p class="mb-0 text-muted">Review and correct attendance records for employees you manage in Odoo.</p>
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

        @if ($errors->has('manager_attendance'))
            <div class="alert alert-danger">
                {{ $errors->first('manager_attendance') }}
            </div>
        @endif

        @if ($odooAttendanceError)
            <div class="alert alert-warning">
                {{ $odooAttendanceError }}
            </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('manager.attendance.index') }}">
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
                        <div class="form-group col-md-4">
                            <label for="employee_id">Employee Name</label>
                            <select id="employee_id" name="employee_id" class="form-control">
                                <option value="">All team members</option>
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Range</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">{{ $attendanceSummary['range_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Worked Hours</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $attendanceSummary['total_worked_hours_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Missing Clock-outs</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $attendanceSummary['missing_clock_out_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Employees Shown</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $attendanceSummary['employees_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Records</h6>
                    <p class="mb-0 small text-muted">
                        {{ $attendanceSummary['records_count'] }} record(s) loaded for the selected range.
                    </p>
                </div>
            </div>
            <div class="card-body">
                @if (! $hasManagerAttendanceIdentity)
                    <div class="text-center py-5">
                        <i class="fas fa-user-lock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Manager access is required to view team attendance.</p>
                    </div>
                @elseif (empty($attendanceRecords))
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No team attendance records were found for the selected filters.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Worked Hours</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendanceRecords as $record)
                                    <tr class="{{ $record['missing_clock_out'] ? 'table-warning' : '' }}">
                                        <td>{{ $record['employee'] }}</td>
                                        <td>{{ $record['check_in_label'] }}</td>
                                        <td class="{{ $record['missing_clock_out'] ? 'text-danger font-weight-bold' : '' }}">
                                            {{ $record['check_out_label'] }}
                                        </td>
                                        <td class="{{ $record['worked_hours_label'] === 'Pending' ? 'text-warning font-weight-bold' : '' }}">
                                            {{ $record['worked_hours_label'] }}
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $record['status_class'] }}">
                                                {{ $record['status_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $record['updated_label'] }}</td>
                                        <td>
                                            <button type="button"
                                                class="btn btn-outline-primary btn-sm edit-attendance-record"
                                                data-toggle="modal"
                                                data-target="#edit_attendance_record"
                                                data-attendance-id="{{ $record['id'] }}"
                                                data-check-in="{{ $record['check_in_form_value'] }}"
                                                data-check-out="{{ $record['check_out_form_value'] }}"
                                                data-write-date="{{ $record['write_date_value'] }}"
                                                data-correction-url="{{ route('manager.attendance.correct', $record['id']) }}">
                                                Correct
                                            </button>
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

    <div id="edit_attendance_record" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Correct Attendance Record</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="attendance-correction-form" action="" method="POST">
                        @csrf
                        <input type="hidden" name="last_known_write_date" id="attendance_last_known_write_date">
                        <input type="hidden" name="editing_attendance_id" id="editing_attendance_id">
                        <input type="hidden" name="from_date" value="{{ $selectedFromDate->format('Y-m-d') }}">
                        <input type="hidden" name="to_date" value="{{ $selectedToDate->format('Y-m-d') }}">
                        @if ($selectedEmployeeId)
                            <input type="hidden" name="employee_id" value="{{ $selectedEmployeeId }}">
                        @endif

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="attendance_check_in">Corrected Check In</label>
                                <input type="datetime-local" name="check_in" id="attendance_check_in"
                                    class="form-control" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="attendance_check_out">Corrected Check Out</label>
                                <input type="datetime-local" name="check_out" id="attendance_check_out"
                                    class="form-control">
                                <small class="form-text text-muted">Leave blank if the record should remain open.</small>
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label for="correction_note">Correction Note</label>
                            <textarea name="correction_note" id="correction_note" rows="4" class="form-control"
                                placeholder="Optional note for the correction audit trail"></textarea>
                        </div>

                        <div class="mt-4 text-right">
                            <button type="submit" class="btn btn-primary">
                                Save Correction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-attendance-record');
            const correctionForm = document.getElementById('attendance-correction-form');
            const previousCheckIn = @json(old('check_in'));
            const previousCheckOut = @json(old('check_out'));
            const previousCorrectionNote = @json(old('correction_note'));

            editButtons.forEach((button) => {
                button.addEventListener('click', function() {
                    if (!correctionForm) {
                        return;
                    }

                    correctionForm.setAttribute('action', button.dataset.correctionUrl || '');
                    document.getElementById('editing_attendance_id').value = button.dataset.attendanceId || '';
                    document.getElementById('attendance_last_known_write_date').value = button.dataset.writeDate || '';
                    document.getElementById('attendance_check_in').value = button.dataset.checkIn || '';
                    document.getElementById('attendance_check_out').value = button.dataset.checkOut || '';
                    document.getElementById('correction_note').value = '';
                });
            });

            const editingAttendanceId = @json(old('editing_attendance_id'));

            if (editingAttendanceId) {
                const matchingButton = document.querySelector('.edit-attendance-record[data-attendance-id="' + editingAttendanceId + '"]');

                if (matchingButton) {
                    matchingButton.click();
                    if (previousCheckIn) {
                        document.getElementById('attendance_check_in').value = previousCheckIn;
                    }
                    document.getElementById('attendance_check_out').value = previousCheckOut || '';
                    document.getElementById('correction_note').value = previousCorrectionNote || '';
                }
            }
        });
    </script>
@endsection
