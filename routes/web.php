<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusniessProfileController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeAvailabilityController;
use App\Http\Controllers\EmployeeAttendanceController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\EmployeePayHistoryController;
use App\Http\Controllers\EmployeeShiftController;
use App\Http\Controllers\EmployeeCalendarEntryController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\ManagerAttendanceController;
use App\Http\Controllers\ManagerLeaveApprovalController;
use App\Http\Controllers\ManagerLeaveReportController;
use App\Http\Controllers\ManagerPayHistoryController;
use App\Http\Controllers\ManagerPayrollSummaryReportController;
use App\Http\Controllers\ManagerPayslipController;
use App\Http\Controllers\ManagerShiftController;
use App\Http\Controllers\ManagerAutoScheduleController;
use App\Http\Controllers\ManagerScheduleUndoController;
use App\Http\Controllers\ManagerScheduleTemplateController;
use App\Http\Controllers\ManagerScheduleAreaController;
use App\Http\Controllers\ManagerScheduleDayController;
use App\Http\Controllers\ManagerScheduleComplianceController;
use App\Http\Controllers\ManagerScheduleBudgetController;
use App\Http\Controllers\ManagerWorkingHoursReportController;
use App\Http\Controllers\Permission\PermissionController;
use App\Http\Controllers\Permission\RolesPermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TryTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('front-end.home.home');
})->name('/');

Route::get('/dashboard', [DashboardController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/manager/dashboard', [ManagerDashboardController::class, 'show'])
    ->middleware(['auth', 'can:access-manager-tools'])
    ->name('manager.dashboard');

Route::middleware(['auth', 'can:access-manager-tools'])->group(function () {
    Route::get('manager/attendance', [ManagerAttendanceController::class, 'index'])->name('manager.attendance.index');
    Route::post('manager/attendance/{attendance}/correct', [ManagerAttendanceController::class, 'correct'])->name('manager.attendance.correct');
    Route::get('manager/leave-approvals', [ManagerLeaveApprovalController::class, 'index'])->name('manager.leave-approvals.index');
    Route::post('manager/leave-approvals/{leaveRequest}/approve', [ManagerLeaveApprovalController::class, 'approve'])->name('manager.leave-approvals.approve');
    Route::post('manager/leave-approvals/{leaveRequest}/refuse', [ManagerLeaveApprovalController::class, 'refuse'])->name('manager.leave-approvals.refuse');
    Route::get('manager/leave-report', [ManagerLeaveReportController::class, 'index'])->name('manager.leave-report.index');
    Route::get('manager/leave-report/export/excel', [ManagerLeaveReportController::class, 'exportExcel'])->name('manager.leave-report.export.excel');
    Route::get('manager/leave-report/export/pdf', [ManagerLeaveReportController::class, 'exportPdf'])->name('manager.leave-report.export.pdf');
    Route::get('manager/pay-history', [ManagerPayHistoryController::class, 'index'])->name('manager.pay-history.index');
    Route::get('manager/payroll-summary', [ManagerPayrollSummaryReportController::class, 'index'])->name('manager.payroll-summary.index');
    Route::get('manager/payroll-summary/export/excel', [ManagerPayrollSummaryReportController::class, 'exportExcel'])->name('manager.payroll-summary.export.excel');
    Route::get('manager/payroll-summary/export/pdf', [ManagerPayrollSummaryReportController::class, 'exportPdf'])->name('manager.payroll-summary.export.pdf');
    Route::get('manager/payslips/create', [ManagerPayslipController::class, 'create'])->name('manager.payslips.create');
    Route::post('manager/payslips', [ManagerPayslipController::class, 'store'])->name('manager.payslips.store');
    Route::get('manager/working-hours', [ManagerWorkingHoursReportController::class, 'index'])->name('manager.working-hours.index');
    Route::get('manager/working-hours/export/excel', [ManagerWorkingHoursReportController::class, 'exportExcel'])->name('manager.working-hours.export.excel');
    Route::get('manager/working-hours/export/pdf', [ManagerWorkingHoursReportController::class, 'exportPdf'])->name('manager.working-hours.export.pdf');
    Route::get('manager/shifts/create', [ManagerShiftController::class, 'create'])->name('manager.shifts.create');
    Route::get('manager/shifts/confirmations', [ManagerShiftController::class, 'confirmations'])->name('manager.shifts.confirmations');
    Route::post('manager/shifts/{shift}/remind', [ManagerShiftController::class, 'remindConfirmation'])->name('manager.shifts.remind');
    Route::post('manager/shifts', [ManagerShiftController::class, 'store'])->name('manager.shifts.store');
    Route::post('manager/shifts/publish-week', [ManagerShiftController::class, 'publishWeek'])->name('manager.shifts.publish-week');
    Route::post('manager/shifts/bulk-delete', [ManagerShiftController::class, 'bulkDelete'])->name('manager.shifts.bulk-delete');
    Route::post('manager/shifts/bulk-open', [ManagerShiftController::class, 'bulkOpen'])->name('manager.shifts.bulk-open');
    Route::post('manager/shifts/bulk-update', [ManagerShiftController::class, 'bulkUpdate'])->name('manager.shifts.bulk-update');
    Route::post('manager/shifts/copy-period', [ManagerShiftController::class, 'copyPeriod'])->name('manager.shifts.copy-period');
    Route::post('manager/shifts/{shift}/update', [ManagerShiftController::class, 'update'])->name('manager.shifts.update');
    Route::post('manager/shifts/{shift}/delete', [ManagerShiftController::class, 'destroy'])->name('manager.shifts.destroy');
    Route::get('manager/auto-schedule', [ManagerAutoScheduleController::class, 'index'])->name('manager.auto-schedule.index');
    Route::post('manager/auto-schedule/apply', [ManagerAutoScheduleController::class, 'apply'])->name('manager.auto-schedule.apply');
    Route::post('manager/schedule/undo', ManagerScheduleUndoController::class)->name('manager.schedule.undo');
    Route::get('manager/schedule-templates', [ManagerScheduleTemplateController::class, 'index'])->name('manager.schedule-templates.index');
    Route::post('manager/schedule-templates', [ManagerScheduleTemplateController::class, 'store'])->name('manager.schedule-templates.store');
    Route::post('manager/schedule-templates/{template}/apply', [ManagerScheduleTemplateController::class, 'apply'])->name('manager.schedule-templates.apply');
    Route::post('manager/schedule-templates/{template}/archive', [ManagerScheduleTemplateController::class, 'archive'])->name('manager.schedule-templates.archive');
    Route::get('manager/schedule-areas', [ManagerScheduleAreaController::class, 'index'])->name('manager.schedule-areas.index');
    Route::post('manager/schedule-areas', [ManagerScheduleAreaController::class, 'store'])->name('manager.schedule-areas.store');
    Route::post('manager/schedule-areas/{area}', [ManagerScheduleAreaController::class, 'update'])->name('manager.schedule-areas.update');
    Route::post('manager/schedule-areas/{area}/archive', [ManagerScheduleAreaController::class, 'destroy'])->name('manager.schedule-areas.destroy');
    Route::get('manager/schedule-days', [ManagerScheduleDayController::class, 'index'])->name('manager.schedule-days.index');
    Route::post('manager/schedule-days', [ManagerScheduleDayController::class, 'store'])->name('manager.schedule-days.store');
    Route::post('manager/schedule-days/{dayMeta}/delete', [ManagerScheduleDayController::class, 'destroy'])->name('manager.schedule-days.destroy');
    Route::get('manager/schedule-compliance', [ManagerScheduleComplianceController::class, 'index'])->name('manager.schedule-compliance.index');
    Route::post('manager/schedule-compliance/rules', [ManagerScheduleComplianceController::class, 'storeRule'])->name('manager.schedule-compliance.rules');
    Route::post('manager/schedule-compliance/breaks', [ManagerScheduleComplianceController::class, 'storeBreak'])->name('manager.schedule-compliance.breaks.store');
    Route::post('manager/schedule-compliance/breaks/{shiftBreak}/delete', [ManagerScheduleComplianceController::class, 'destroyBreak'])->name('manager.schedule-compliance.breaks.destroy');
    Route::get('manager/schedule-budget', [ManagerScheduleBudgetController::class, 'index'])->name('manager.schedule-budget.index');
    Route::post('manager/schedule-budget/rates', [ManagerScheduleBudgetController::class, 'storeRate'])->name('manager.schedule-budget.rates');
    Route::post('manager/schedule-budget/budgets', [ManagerScheduleBudgetController::class, 'storeBudget'])->name('manager.schedule-budget.budgets');
});

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('employee/availability', [EmployeeAvailabilityController::class, 'index'])->name('employee.availability.index');
    Route::post('employee/availability', [EmployeeAvailabilityController::class, 'store'])->name('employee.availability.store');
    Route::post('employee/availability/{availability}/update', [EmployeeAvailabilityController::class, 'update'])->name('employee.availability.update');
    Route::post('employee/availability/{availability}/delete', [EmployeeAvailabilityController::class, 'destroy'])->name('employee.availability.destroy');
    Route::get('employee/attendance', [EmployeeAttendanceController::class, 'index'])->name('employee.attendance.index');
    Route::post('employee/attendance/check-in', [EmployeeAttendanceController::class, 'checkIn'])->name('employee.attendance.check-in');
    Route::post('employee/attendance/start-break', [EmployeeAttendanceController::class, 'startBreak'])->name('employee.attendance.start-break');
    Route::post('employee/attendance/end-break', [EmployeeAttendanceController::class, 'endBreak'])->name('employee.attendance.end-break');
    Route::post('employee/attendance/check-out', [EmployeeAttendanceController::class, 'checkOut'])->name('employee.attendance.check-out');
    Route::get('employee/leave-requests', [EmployeeLeaveController::class, 'index'])->name('employee.leave.index');
    Route::post('employee/leave-requests', [EmployeeLeaveController::class, 'store'])->name('employee.leave.store');
    Route::post('employee/leave-requests/{leaveRequest}/cancel', [EmployeeLeaveController::class, 'cancel'])->name('employee.leave.cancel');
    Route::get('employee/pay-history', [EmployeePayHistoryController::class, 'index'])->name('employee.pay-history.index');
    Route::get('employee/shifts', [EmployeeShiftController::class, 'index'])->name('employee.shifts.index');
    Route::post('employee/shifts/{shift}/respond', [EmployeeShiftController::class, 'respond'])->name('employee.shifts.respond');
    Route::post('employee/calendar-entries', [EmployeeCalendarEntryController::class, 'store'])->name('employee.calendar-entries.store');
    Route::post('employee/calendar-entries/{calendarEntry}', [EmployeeCalendarEntryController::class, 'update'])
        ->whereNumber('calendarEntry')
        ->name('employee.calendar-entries.update');
    Route::post('employee/calendar-entries/{calendarEntry}/delete', [EmployeeCalendarEntryController::class, 'destroy'])
        ->whereNumber('calendarEntry')
        ->name('employee.calendar-entries.destroy');
    Route::get('employee/open-shifts', [EmployeeShiftController::class, 'openShifts'])->name('employee.open-shifts.index');
    Route::post('employee/open-shifts/{shift}/claim', [EmployeeShiftController::class, 'claimOpenShift'])->name('employee.open-shifts.claim');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('client-request/create', [ClientRequestController::class, 'create'])->name('client_request.create')->middleware('permission:client_request.add');
    Route::post('client-request/store', [ClientRequestController::class, 'store'])->name('client_request.store')->middleware('permission:client_request.add');
    Route::get('client-request/edit/{id}', [ClientRequestController::class, 'edit'])->name('client_request.edit')->middleware('permission:client_request.edit');
    Route::post('client-request/update/{id}', [ClientRequestController::class, 'update'])->name('client_request.update')->middleware('permission:client_request.edit');
    Route::get('client-request/index', [ClientRequestController::class, 'index'])->name('client_request.index')->middleware('permission:client_request.view');
    Route::post('client-request/destroy/{id}', [ClientRequestController::class, 'destroy'])->name('client_request.destroy')->middleware('permission:client_request.delete');

    // Route::resource('client', ClientController::class);
    Route::get('client/create', [ClientController::class, 'create'])->name('client.create')->middleware('permission:client.add');
    Route::get('client/request', [ClientController::class, 'createRequest'])->name('client.request')->middleware('permission:client.view');
    Route::post('client/store', [ClientController::class, 'store'])->name('client.store')->middleware('permission:client.add');
    Route::get('client/edit/{client}', [ClientController::class, 'edit'])->name('client.edit')->middleware('permission:client.edit');
    Route::put('client/update/{client}', [ClientController::class, 'update'])->name('client.update')->middleware('permission:client.edit');
    Route::get('client/index', [ClientController::class, 'index'])->name('client.index')->middleware('permission:client.view');
    Route::get('my-requests', [ClientController::class, 'userIndex'])->name('user.index')->middleware('permission:client.add');
    Route::delete('client/destroy/{client}', [ClientController::class, 'destroy'])->name('client.destroy')->middleware('permission:client.delete');
    Route::get('business/create', [BusinessController::class, 'create'])->name('business.create')->middleware('permission:business.add');
    Route::post('business/store', [BusinessController::class, 'store'])->name('business.store')->middleware('permission:business.add');
    Route::get('business/edit/{business}', [BusinessController::class, 'edit'])->name('business.edit')->middleware('permission:business.edit');
    Route::put('business/update/{business}', [BusinessController::class, 'update'])->name('business.update')->middleware('permission:business.edit');
    Route::get('business/index', [BusinessController::class, 'index'])->name('business.index')->middleware('permission:business.view');
    Route::delete('business/destroy/{business}', [BusinessController::class, 'destroy'])->name('business.destroy')->middleware('permission:business.delete');

    // Route::resource('business', BusinessController::class);

    Route::get('/client-status/{client_id}', [ClientController::class, 'status'])->name('client.status')->middleware('permission:client.approve');
    Route::get('/business-status/{business_id}', [BusinessController::class, 'status'])->name('business.status')->middleware('permission:business.approve');

    Route::get('permission/create', [PermissionController::class, 'create'])->name('permission.create')->middleware('permission:permission.add');
    Route::get('permission/index', [PermissionController::class, 'index'])->name('permission.index');
    Route::post('permission/store', [PermissionController::class, 'store'])->name('permission.store')->middleware('permission:permission.add');
    Route::get('permission/edit/{id}', [PermissionController::class, 'edit'])->name('permission.edit')->middleware('permission:permission.edit');
    Route::post('permission/destroy/{id}', [PermissionController::class, 'destroy'])->name('permission.destroy')->middleware('permission:permission.delete');
    Route::post('permission/update/{id}', [PermissionController::class, 'update'])->name('permission.update')->middleware('permission:permission.edit');

    Route::get('role/create', [RoleController::class, 'create'])->name('role.create')->middleware('permission:permission.add');
    Route::get('role/index', [RoleController::class, 'index'])->name('role.index');
    Route::post('role/store', [RoleController::class, 'store'])->name('role.store')->middleware('permission:role.add');
    Route::get('role/edit/{role}', [RoleController::class, 'edit'])->name('role.edit')->middleware('permission:role.edit');
    Route::post('role/destroy/{role}', [RoleController::class, 'destroy'])->name('role.destroy')->middleware('permission:role.delete');
    Route::post('role/update/{role}', [RoleController::class, 'update'])->name('role.update')->middleware('permission:role.edit');

    Route::get('roles-permission/create', [RolesPermissionController::class, 'create'])->name('roles-permission.create')->middleware('permission:roles_permission.add');
    Route::get('roles-permission/index', [RolesPermissionController::class, 'index'])->name('roles-permission.index')->middleware('permission:roles_permission.view');
    Route::post('roles-permission/store', [RolesPermissionController::class, 'store'])->name('roles-permission.store')->middleware('permission:roles_permission.add');
    Route::get('roles-permission/edit/{roles_permission}', [RolesPermissionController::class, 'edit'])->name('roles-permission.edit')->middleware('permission:roles_permission.edit');
    Route::post('roles-permission/destroy/{roles_permission}', [RolesPermissionController::class, 'destroy'])->name('roles-permission.destroy')->middleware('permission:roles_permission.add');
    Route::post('roles-permission/update/{roles_permission}', [RolesPermissionController::class, 'update'])->name('roles-permission.update')->middleware('permission:roles_permission.edit');

    Route::get('admin/create', [AdminController::class, 'create'])->name('admin.create')->middleware('permission:admin.add');
    Route::post('admin/store', [AdminController::class, 'store'])->name('admin.store')->middleware('permission:admin.add');
    Route::get('admin/edit/{id}', [AdminController::class, 'edit'])->name('admin.edit')->middleware('permission:admin.edit');
    Route::post('admin/update/{id}', [AdminController::class, 'update'])->name('admin.update')->middleware('permission:admin.edit');
    Route::get('admin/index', [AdminController::class, 'index'])->name('admin.index')->middleware('permission:admin.view');
    Route::post('admin/destroy/{id}', [AdminController::class, 'destroy'])->name('admin.destroy')->middleware('permission:admin.delete');

    // Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create')->middleware('permission:staff.add');
    // Route::post('staff/store', [StaffController::class, 'store'])->name('staff.store');
    Route::post('staff/store', [StaffController::class, 'store'])->name('staff.store')->middleware('permission:staff.add');
    Route::get('staff/edit/{id}', [StaffController::class, 'edit'])->name('staff.edit')->middleware('permission:staff.edit');
    Route::post('staff/update/{id}', [StaffController::class, 'update'])->name('staff.update')->middleware('permission:staff.edit');
    Route::get('staff/index', [StaffController::class, 'index'])->name('staff.index')->middleware('permission:staff.view');
    Route::post('staff/destroy/{id}', [StaffController::class, 'destroy'])->name('staff.destroy')->middleware('permission:staff.delete');

});

// use for test 
Route::resource('test', TryTestController::class);
require __DIR__ . '/auth.php';
