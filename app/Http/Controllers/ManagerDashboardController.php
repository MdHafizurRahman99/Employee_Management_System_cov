<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ManagerDashboardController extends Controller
{
    /**
     * Display the manager dashboard.
     */
    public function show(Request $request): View
    {
        return view('admin.manager-dashboard', [
            'managerQuickLinks' => [
                [
                    'title' => 'Payroll Summary Report',
                    'description' => 'Review monthly payroll totals, company and role breakdowns, and period-to-period movement.',
                    'route' => route('manager.payroll-summary.index'),
                    'button' => 'View Payroll Summary',
                    'class' => 'warning',
                ],
                [
                    'title' => 'Leave Report',
                    'description' => 'Review approved leave by employee and leave type, then export the report to Excel or PDF.',
                    'route' => route('manager.leave-report.index'),
                    'button' => 'View Leave Report',
                    'class' => 'success',
                ],
                [
                    'title' => 'Working Hours Report',
                    'description' => 'Compare planned shifts against actual attendance, then export the report to Excel or PDF.',
                    'route' => route('manager.working-hours.index'),
                    'button' => 'View Hours Report',
                    'class' => 'dark',
                ],
                [
                    'title' => 'Generate Payslips',
                    'description' => 'Generate payslips and review gross pay, deductions, and net pay.',
                    'route' => route('manager.payslips.create'),
                    'button' => 'View Payslips',
                    'class' => 'warning',
                ],
                [
                    'title' => 'Team Pay History',
                    'description' => 'Review historical team payslips and payroll totals.',
                    'route' => route('manager.pay-history.index'),
                    'button' => 'View Pay History',
                    'class' => 'primary',
                ],
                [
                    'title' => 'Team Schedule',
                    'description' => 'Build, update, or remove employee shift assignments from the weekly roster.',
                    'route' => route('manager.shifts.create'),
                    'button' => 'Open Schedule',
                    'class' => 'secondary',
                ],
                [
                    'title' => 'Team Attendance',
                    'description' => 'Review team attendance, monitor missing clock-outs, and submit corrections.',
                    'route' => route('manager.attendance.index'),
                    'button' => 'View Team Attendance',
                    'class' => 'dark',
                ],
                [
                    'title' => 'Leave Approvals',
                    'description' => 'Review and action pending team leave requests.',
                    'route' => route('manager.leave-approvals.index'),
                    'button' => 'View Leave Approvals',
                    'class' => 'primary',
                ],
                [
                    'title' => 'My Shifts',
                    'description' => 'Review your upcoming shift schedule.',
                    'route' => route('employee.shifts.index'),
                    'button' => 'View Shifts',
                    'class' => 'info',
                ],
                [
                    'title' => 'My Attendance',
                    'description' => 'Review your personal attendance history.',
                    'route' => route('employee.attendance.index'),
                    'button' => 'View Attendance',
                    'class' => 'success',
                ],
                [
                    'title' => 'Leave Requests',
                    'description' => 'Submit or review your own leave requests.',
                    'route' => route('employee.leave.index'),
                    'button' => 'View Leave Requests',
                    'class' => 'info',
                ],
            ],
            'managerWorklist' => [
                'Review payroll totals with company and role breakdowns.',
                'Review approved leave by employee and leave type, including remaining balances.',
                'Compare planned and actual hours by employee.',
                'Generate payslips and review gross pay, deductions, and net pay.',
                'Review team pay history and payroll totals for managed employees.',
                'Create, edit, and remove shift assignments for the team.',
                'Review team attendance and submit manual corrections where required.',
                'Review and action employee leave requests.',
            ],
            'isOdooManager' => $request->user()?->isOdooManager() ?? false,
        ]);
    }
}
