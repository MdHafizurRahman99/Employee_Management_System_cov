@extends('layouts.admin.master')

@section('title')
    Leave Requests
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Leave Requests</h1>
                <p class="mb-0 text-muted">Submit time off requests to Odoo and track their approval status.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('leave_request'))
            <div class="alert alert-danger">
                {{ $errors->first('leave_request') }}
            </div>
        @endif

        @if ($odooLeaveError)
            <div class="alert alert-warning">
                {{ $odooLeaveError }}
            </div>
        @endif

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Requests</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $leaveSummary['total'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $leaveSummary['pending'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $leaveSummary['approved'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Rejected</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $leaveSummary['rejected'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">New Leave Request</h6>
                    </div>
                    <div class="card-body">
                        @if (! $hasLeaveIdentity)
                            <div class="text-center py-4">
                                <i class="fas fa-user-clock fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">This account is not linked to an Odoo employee record yet.</p>
                            </div>
                        @elseif (empty($leaveTypes))
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">No leave types are currently available from Odoo.</p>
                            </div>
                        @else
                            <form action="{{ route('employee.leave.store') }}" method="POST">
                                @csrf

                                @if ($leaveFormPrefill['source_shift'])
                                    <div class="alert alert-info">
                                        <div class="font-weight-bold mb-1">Requesting unavailability for an assigned shift</div>
                                        <div>{{ $leaveFormPrefill['source_shift']['title'] }}</div>
                                        <div class="small">
                                            {{ $leaveFormPrefill['source_shift']['date_label'] ?? 'Assigned date' }}
                                            @if ($leaveFormPrefill['source_shift']['time_label'] ?? null)
                                                | {{ $leaveFormPrefill['source_shift']['time_label'] }}
                                            @endif
                                        </div>
                                        @if (($leaveFormPrefill['source_shift']['role'] ?? null) || ($leaveFormPrefill['source_shift']['company'] ?? null))
                                            <div class="small">
                                                @if ($leaveFormPrefill['source_shift']['role'] ?? null)
                                                    {{ $leaveFormPrefill['source_shift']['role'] }}
                                                @endif
                                                @if ($leaveFormPrefill['source_shift']['company'] ?? null)
                                                    @if ($leaveFormPrefill['source_shift']['role'] ?? null)
                                                        |
                                                    @endif
                                                    {{ $leaveFormPrefill['source_shift']['company'] }}
                                                @endif
                                            </div>
                                        @endif
                                        <div class="small mt-2 mb-0">
                                            This request is tied to the selected shift, so the dates are locked from that shift.
                                            @if ($leaveFormPrefill['start_hour'] && $leaveFormPrefill['end_hour'])
                                                If you choose an hourly leave type, the shift hours are ready too.
                                            @elseif ($leaveFormPrefill['is_multi_day_shift'])
                                                This shift spans multiple dates, so you may need a day-based leave type or a manual adjustment.
                                            @endif
                                        </div>
                                    </div>

                                    <input type="hidden" name="source" value="shift">
                                    <input type="hidden" name="source_shift_id"
                                        value="{{ $leaveFormPrefill['source_shift']['id'] }}">
                                    <input type="hidden" name="source_shift_title"
                                        value="{{ $leaveFormPrefill['source_shift']['title'] }}">
                                    <input type="hidden" name="source_shift_role"
                                        value="{{ $leaveFormPrefill['source_shift']['role'] }}">
                                    <input type="hidden" name="source_shift_company"
                                        value="{{ $leaveFormPrefill['source_shift']['company'] }}">
                                    <input type="hidden" name="source_shift_date_label"
                                        value="{{ $leaveFormPrefill['source_shift']['date_label'] }}">
                                    <input type="hidden" name="source_shift_time_label"
                                        value="{{ $leaveFormPrefill['source_shift']['time_label'] }}">
                                    <input type="hidden" name="source_shift_start_at"
                                        value="{{ $leaveFormPrefill['source_shift']['start_at'] }}">
                                    <input type="hidden" name="source_shift_end_at"
                                        value="{{ $leaveFormPrefill['source_shift']['end_at'] }}">
                                @endif

                                <div class="form-group">
                                    <label for="leave_type_id">Leave Type</label>
                                    <select name="leave_type_id" id="leave_type_id"
                                        class="form-control @error('leave_type_id') is-invalid @enderror" required>
                                        <option value="">Select leave type</option>
                                        @foreach ($leaveTypes as $leaveType)
                                            <option value="{{ $leaveType['id'] }}"
                                                data-request-unit="{{ $leaveType['request_unit'] }}"
                                                {{ (string) old('leave_type_id') === (string) $leaveType['id'] ? 'selected' : '' }}>
                                                {{ $leaveType['name'] }} ({{ $leaveType['request_unit_label'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('leave_type_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                @if ($leaveFormPrefill['source_shift'])
                                    <input type="hidden" name="start_date"
                                        value="{{ old('start_date', $leaveFormPrefill['start_date']) }}">
                                    <input type="hidden" name="end_date"
                                        value="{{ old('end_date', $leaveFormPrefill['end_date']) }}">

                                    <div class="border rounded p-3 mb-3 bg-light">
                                        <div class="font-weight-bold text-primary mb-1">Shift Dates</div>
                                        <div class="small text-muted">
                                            @if ($leaveFormPrefill['start_date'] === $leaveFormPrefill['end_date'])
                                                {{ \Carbon\Carbon::createFromFormat('Y-m-d', $leaveFormPrefill['start_date'])->format('D, d M Y') }}
                                            @else
                                                {{ \Carbon\Carbon::createFromFormat('Y-m-d', $leaveFormPrefill['start_date'])->format('D, d M Y') }}
                                                to
                                                {{ \Carbon\Carbon::createFromFormat('Y-m-d', $leaveFormPrefill['end_date'])->format('D, d M Y') }}
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-1">
                                            You do not need to select dates again for a shift-based unavailability request.
                                        </div>
                                    </div>
                                @else
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="start_date">Start Date</label>
                                            <input type="date" name="start_date" id="start_date"
                                                class="form-control @error('start_date') is-invalid @enderror"
                                                value="{{ old('start_date', $leaveFormPrefill['start_date']) }}" required>
                                            @error('start_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="end_date">End Date</label>
                                            <input type="date" name="end_date" id="end_date"
                                                class="form-control @error('end_date') is-invalid @enderror"
                                                value="{{ old('end_date', $leaveFormPrefill['end_date']) }}" required>
                                            @error('end_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endif

                                <div id="half-day-fields" class="border rounded p-3 mb-3 d-none">
                                    <div class="small text-muted mb-2">Use the same period for a half day, or different periods for a full day span.</div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="start_period">Start Period</label>
                                            <select name="start_period" id="start_period"
                                                class="form-control @error('start_period') is-invalid @enderror">
                                                <option value="am" {{ old('start_period', 'am') === 'am' ? 'selected' : '' }}>Morning</option>
                                                <option value="pm" {{ old('start_period') === 'pm' ? 'selected' : '' }}>Afternoon</option>
                                            </select>
                                            @error('start_period')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="end_period">End Period</label>
                                            <select name="end_period" id="end_period"
                                                class="form-control @error('end_period') is-invalid @enderror">
                                                <option value="am" {{ old('end_period', 'am') === 'am' ? 'selected' : '' }}>Morning</option>
                                                <option value="pm" {{ old('end_period') === 'pm' ? 'selected' : '' }}>Afternoon</option>
                                            </select>
                                            @error('end_period')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div id="hour-fields" class="border rounded p-3 mb-3 d-none">
                                    <div class="small text-muted mb-2">Hourly leave requests must stay within a single day.</div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="start_hour">Start Hour</label>
                                            <input type="number" step="0.25" min="0" max="23.99" name="start_hour"
                                                id="start_hour" class="form-control @error('start_hour') is-invalid @enderror"
                                                value="{{ old('start_hour', $leaveFormPrefill['start_hour']) }}"
                                                placeholder="9.00">
                                            @error('start_hour')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="end_hour">End Hour</label>
                                            <input type="number" step="0.25" min="0" max="24" name="end_hour"
                                                id="end_hour" class="form-control @error('end_hour') is-invalid @enderror"
                                                value="{{ old('end_hour', $leaveFormPrefill['end_hour']) }}"
                                                placeholder="17.00">
                                            @error('end_hour')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="reason">Reason</label>
                                    <textarea name="reason" id="reason" rows="4"
                                        class="form-control @error('reason') is-invalid @enderror"
                                        placeholder="Optional reason or notes for your request">{{ old('reason') }}</textarea>
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">
                                    Submit Leave Request
                                </button>
                            </form>
                        @endif

                        @if (collect($leaveTypes)->contains(fn($leaveType) => filled($leaveType['availability_note'])))
                            <div class="mt-4">
                                <h6 class="font-weight-bold text-muted text-uppercase small">Balance / Allocation Notes</h6>
                                <p class="small text-muted mb-2">
                                    Some leave types need available balance or an approved entitlement in Odoo before the request can be accepted.
                                </p>
                                <ul class="mb-0 pl-3">
                                    @foreach ($leaveTypes as $leaveType)
                                        @if ($leaveType['availability_note'])
                                            <li class="text-muted">
                                                {{ $leaveType['name'] }}: {{ $leaveType['availability_note'] }}
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Leave Requests</h6>
                    </div>
                    <div class="card-body">
                        @if (! $hasLeaveIdentity)
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">Leave history will appear here once this account is linked to Odoo.</p>
                            </div>
                        @elseif (empty($leaveRequests))
                            <div class="text-center py-4">
                                <i class="fas fa-plane-departure fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">No leave requests have been found yet.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Dates</th>
                                            <th>Duration</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($leaveRequests as $leaveRequest)
                                            <tr>
                                                <td>
                                                    <div class="font-weight-bold">{{ $leaveRequest['type'] }}</div>
                                                    <div class="small text-muted">
                                                        {{ $leaveRequest['request_unit_label'] }}
                                                    </div>
                                                    @if ($leaveRequest['reason'] !== '')
                                                        <div class="small text-muted mt-1">{{ $leaveRequest['reason'] }}</div>
                                                    @endif
                                                    @if (($leaveRequest['planning_slot_title'] ?? null) || ($leaveRequest['planning_role_name'] ?? null) || ($leaveRequest['planning_company_name'] ?? null))
                                                        <div class="small text-info mt-1">
                                                            Shift: {{ $leaveRequest['planning_slot_title'] ?? ($leaveRequest['planning_slot'] ?? 'Planning Shift') }}
                                                        </div>
                                                        <div class="small text-muted">
                                                            @if ($leaveRequest['planning_role_name'] ?? null)
                                                                {{ $leaveRequest['planning_role_name'] }}
                                                            @endif
                                                            @if (($leaveRequest['planning_role_name'] ?? null) && ($leaveRequest['planning_company_name'] ?? null))
                                                                |
                                                            @endif
                                                            @if ($leaveRequest['planning_company_name'] ?? null)
                                                                {{ $leaveRequest['planning_company_name'] }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div>{{ $leaveRequest['start_date_label'] }}</div>
                                                    <div class="small text-muted">to {{ $leaveRequest['end_date_label'] }}</div>
                                                    @if (($leaveRequest['planning_start_label'] ?? null) && ($leaveRequest['planning_end_label'] ?? null))
                                                        <div class="small text-muted">
                                                            Shift window: {{ $leaveRequest['planning_start_label'] }} to {{ $leaveRequest['planning_end_label'] }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>{{ $leaveRequest['duration_label'] }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $leaveRequest['status_class'] }}">
                                                        {{ $leaveRequest['status_label'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $leaveRequest['submitted_at_label'] }}</td>
                                                <td>
                                                    @if ($leaveRequest['can_cancel'])
                                                        <form action="{{ route('employee.leave.cancel', $leaveRequest['id']) }}"
                                                            method="POST" onsubmit="return confirm('Cancel this leave request?');">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                Cancel
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted small">No actions available</span>
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
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const leaveTypeSelect = document.getElementById('leave_type_id');
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const halfDayFields = document.getElementById('half-day-fields');
            const hourFields = document.getElementById('hour-fields');
            const isShiftBasedRequest = @json((bool) $leaveFormPrefill['source_shift']);

            if (!leaveTypeSelect) {
                return;
            }

            const syncLeaveForm = () => {
                const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
                const requestUnit = selectedOption ? selectedOption.dataset.requestUnit : '';
                const isHalfDay = requestUnit === 'half_day';
                const isHour = requestUnit === 'hour';

                halfDayFields.classList.toggle('d-none', !isHalfDay);
                hourFields.classList.toggle('d-none', !isHour);

                if (endDateInput) {
                    endDateInput.readOnly = isHour || isShiftBasedRequest;
                }

                if (isHour && startDateInput && endDateInput && startDateInput.value !== '') {
                    endDateInput.value = startDateInput.value;
                }
            };

            leaveTypeSelect.addEventListener('change', syncLeaveForm);
            if (startDateInput) {
                startDateInput.addEventListener('change', function() {
                    const selectedOption = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
                    const requestUnit = selectedOption ? selectedOption.dataset.requestUnit : '';

                    if (requestUnit === 'hour' && endDateInput && startDateInput.value !== '') {
                        endDateInput.value = startDateInput.value;
                    }
                });
            }

            syncLeaveForm();
        });
    </script>
@endsection
