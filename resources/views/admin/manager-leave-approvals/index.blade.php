@extends('layouts.admin.master')

@section('title')
    Leave Approvals
@endsection

@section('content')
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Leave Approvals</h1>
                <p class="mb-0 text-muted">Review pending team leave requests and push approval decisions to Odoo.</p>
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

        @if ($errors->has('manager_leave'))
            <div class="alert alert-danger">
                {{ $errors->first('manager_leave') }}
            </div>
        @endif

        @if ($odooLeaveError)
            <div class="alert alert-warning">
                {{ $odooLeaveError }}
            </div>
        @endif

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('manager.leave-approvals.index') }}">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-8">
                            <label for="employee_id">Employee Name</label>
                            <select id="employee_id" name="employee_id" class="form-control">
                                <option value="">All managed employees</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee['id'] }}"
                                        {{ $selectedEmployeeId === $employee['id'] ? 'selected' : '' }}>
                                        {{ $employee['name'] }}{{ $employee['company'] ? ' - '.$employee['company'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <button type="submit" class="btn btn-primary btn-block">Apply</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Requests</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $leaveSummary['pending_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Employees</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $leaveSummary['employees_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-12 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Second Approval Needed</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $leaveSummary['double_approval_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Pending Team Leave Requests</h6>
            </div>
            <div class="card-body">
                @if (! $hasManagerLeaveIdentity)
                    <div class="text-center py-5">
                        <i class="fas fa-user-lock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">Leave approvals require a manager account linked to Odoo.</p>
                    </div>
                @elseif (empty($leaveRequests))
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No pending leave requests were found for the selected filters.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Leave Type</th>
                                    <th>Dates</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($leaveRequests as $leaveRequest)
                                    <tr>
                                        <td>{{ $leaveRequest['employee'] }}</td>
                                        <td>
                                            <div class="font-weight-bold">{{ $leaveRequest['type'] }}</div>
                                            <div class="small text-muted">{{ $leaveRequest['request_unit_label'] }}</div>
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
                                        <td>{{ $leaveRequest['updated_label'] }}</td>
                                        <td class="text-nowrap">
                                            @if ($leaveRequest['can_approve_action'])
                                                <form action="{{ route('manager.leave-approvals.approve', $leaveRequest['id']) }}"
                                                    method="POST" class="d-inline-block mr-2">
                                                    @csrf
                                                    @if ($selectedEmployeeId)
                                                        <input type="hidden" name="employee_id" value="{{ $selectedEmployeeId }}">
                                                    @endif
                                                    <input type="hidden" name="last_known_write_date" value="{{ $leaveRequest['write_date_value'] }}">
                                                    <button type="submit" class="btn btn-outline-success btn-sm">
                                                        Approve
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($leaveRequest['can_refuse_action'])
                                                <button type="button"
                                                    class="btn btn-outline-danger btn-sm refuse-leave-request"
                                                    data-toggle="modal"
                                                    data-target="#refuse_leave_request"
                                                    data-leave-request-id="{{ $leaveRequest['id'] }}"
                                                    data-write-date="{{ $leaveRequest['write_date_value'] }}"
                                                    data-refuse-url="{{ route('manager.leave-approvals.refuse', $leaveRequest['id']) }}">
                                                    Reject
                                                </button>
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

    <div id="refuse_leave_request" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Leave Request</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="leave-refusal-form" action="" method="POST">
                        @csrf
                        <input type="hidden" name="last_known_write_date" id="leave_last_known_write_date">
                        <input type="hidden" name="editing_leave_request_id" id="editing_leave_request_id">
                        @if ($selectedEmployeeId)
                            <input type="hidden" name="employee_id" value="{{ $selectedEmployeeId }}">
                        @endif

                        <div class="form-group mb-0">
                            <label for="manager_note">Manager Note</label>
                            <textarea name="manager_note" id="manager_note" rows="4" class="form-control"
                                placeholder="Optional note for the employee and audit log"></textarea>
                        </div>

                        <div class="mt-4 text-right">
                            <button type="submit" class="btn btn-danger">
                                Confirm Rejection
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
            const refuseButtons = document.querySelectorAll('.refuse-leave-request');
            const refusalForm = document.getElementById('leave-refusal-form');
            const previousManagerNote = @json(old('manager_note'));

            refuseButtons.forEach((button) => {
                button.addEventListener('click', function() {
                    if (!refusalForm) {
                        return;
                    }

                    refusalForm.setAttribute('action', button.dataset.refuseUrl || '');
                    document.getElementById('editing_leave_request_id').value = button.dataset.leaveRequestId || '';
                    document.getElementById('leave_last_known_write_date').value = button.dataset.writeDate || '';
                    document.getElementById('manager_note').value = '';
                });
            });

            const editingLeaveRequestId = @json(old('editing_leave_request_id'));

            if (editingLeaveRequestId) {
                const matchingButton = document.querySelector('.refuse-leave-request[data-leave-request-id="' + editingLeaveRequestId + '"]');

                if (matchingButton) {
                    matchingButton.click();
                    document.getElementById('manager_note').value = previousManagerNote || '';
                }
            }
        });
    </script>
@endsection
