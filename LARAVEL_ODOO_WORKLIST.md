# Laravel-Odoo Integration Worklist

## Project Context
- Target Odoo version: Odoo 19 Enterprise
- Laravel integration work should be validated against Odoo 19 Enterprise models, permissions, and API behavior.

## Goal
Implement the Seaford East Medical Clinic Laravel-Odoo integration with Odoo as the source of truth for employee access, shifts, attendance, leave, payroll, and reporting.

## Foundation Work
- [ ] Confirm Odoo connection details: base URL, database name, username, API key, timeout, and required model permissions.
- [ ] Add Odoo environment variables to `.env` and `.env.example`.
- [ ] Add Laravel config for Odoo, preferably in `config/services.php` or a dedicated `config/odoo.php`.
- [ ] Build a reusable Odoo service/client for authentication, `search_read`, `create`, `write`, `unlink`, and workflow/action calls.
- [ ] Add consistent error handling, logging, and retry-safe responses for Odoo API failures.
- [ ] Decide how local Laravel users should map to Odoo users and employees.
- [ ] Add local fields or a mapping table for `odoo_user_id`, `odoo_employee_id`, `odoo_resource_id`, and role metadata.
- [ ] Decide whether employee registration and password reset should be disabled if Odoo owns authentication.
- [ ] Define the rule for manager role detection from Odoo.
- [ ] Add a test strategy for Odoo integration, including mocks or fakes for API responses.

## Current App Cleanup Before Feature Work
- [ ] Replace the current Laravel-only login flow with Odoo-backed authentication.
- [ ] Disable or remove public self-registration if employees must come from Odoo.
- [ ] Review existing local schedule tables and decide whether they will be removed, ignored, or kept only as fallback.
- [ ] Tighten route authorization for shift and schedule actions that are currently too open.
- [ ] Build a real dashboard for employees and managers instead of the placeholder dashboard.
- [ ] Add a shared layout/state pattern for showing API errors, empty states, and sync warnings.

## Phase 1 - Employee Self Service

### Task 1.1 - Employee Login
- [ ] Build Odoo-backed login using employee email and password/API key flow.
- [ ] Validate credentials against Odoo.
- [ ] Create or update the local Laravel session user after successful Odoo login.
- [ ] Store the required Odoo identifiers in session or local user mapping.
- [ ] Redirect authenticated users to the employee dashboard.
- [ ] Handle invalid credentials, inactive employees, and API connection failures cleanly.

### Task 1.2 - View Assigned Shifts
- [ ] Fetch the logged-in employee's shifts from Odoo `planning.slot`.
- [ ] Filter by the employee's Odoo resource or employee ID.
- [ ] Show shift date, start time, end time, role, and company.
- [ ] Add a list view and/or calendar view.
- [ ] Highlight today's shift on the dashboard.
- [ ] Handle timezone formatting and empty schedules.
- [x] Store date-specific employee availability, unavailability, and schedule notes in Odoo.
- [x] Render Odoo schedule diary entries in the employee calendar.
- [x] Show Odoo schedule diary signals in the manager roster.
- [x] Surface Odoo diary unavailability as a manual-scheduling warning, respect it in auto-scheduling by default with a manager override, and allow voluntary open-shift claims.
- [x] Prefer employees who explicitly marked the proposed shift time available and treat unavailable employees as override-only last-resort candidates.
- [x] Limit manager diary reads to employees visible in the current scheduling workspace.

### Task 1.3 - View Attendance Records
- [x] Fetch attendance records from Odoo `hr.attendance`.
- [x] Show clock-in, clock-out, and total worked hours per day.
- [x] Show the current month summary.
- [x] Allow viewing previous months.
- [x] Handle missing clock-outs and incomplete days clearly.

### Task 1.4 - Submit Leave Request
- [x] Build a leave request form in Laravel.
- [x] Fetch leave types from Odoo `hr.leave.type`.
- [x] Submit leave requests to Odoo `hr.leave`.
- [x] Show request status: Draft, Pending, Approved, Rejected.
- [x] Allow employees to cancel pending leave requests.
- [x] Validate date ranges, leave type availability, and overlapping requests.

## Phase 2 - Manager Features

### Task 2.1 - Manager Login and Role Detection
- [x] Detect whether the logged-in Odoo user is a Manager or Employee.
- [x] Route managers to a manager dashboard with extra controls.
- [x] Restrict manager-only pages and actions from regular employees.
- [x] Keep local Laravel permissions aligned with Odoo role data.

### Task 2.2 - Create Shifts
- [x] Build a shift creation form for managers.
- [x] Fetch selectable employees, roles, and company data from Odoo.
- [x] Create shifts in Odoo `planning.slot`.
- [x] Validate time conflicts before submission.
- [x] Show success and failure states clearly.

### Task 2.3 - Edit and Delete Shifts
- [x] Allow managers to edit existing Odoo shifts.
- [x] Submit updates through the Odoo `write` method.
- [x] Allow deletion through the Odoo `unlink` method.
- [x] Add confirmation before delete.
- [x] Prevent unauthorized edits and handle stale data conflicts.

### Task 2.4 - View Team Attendance
- [x] Fetch team attendance from Odoo `hr.attendance`.
- [x] Filter by employee name and date range.
- [x] Show worked hours and missing clock-outs.
- [x] Allow managers to submit manual corrections through Odoo.
- [x] Audit or log manual attendance corrections if needed.

### Task 2.5 - Approve or Reject Leave Requests
- [x] Show pending leave requests for the manager's team.
- [x] Approve leave by triggering Odoo validation.
- [x] Reject leave by triggering the Odoo refusal action.
- [x] Notify the employee when leave status changes.
- [x] Record status updates and failure messages safely.

## Phase 3 - Payroll and Reports

### Task 3.1 - Generate Payslips
- [x] Build a payslip generation screen for managers.
- [x] Allow selection of employee and pay period.
- [x] Create payslips in Odoo `hr.payslip`.
- [x] Trigger payslip computation.
- [x] Show gross pay, deductions, and net pay.

### Task 3.2 - View Pay History
- [x] Fetch payslips from Odoo `hr.payslip`.
- [x] Show employee pay history sorted by most recent first.
- [x] Allow employees to see only their own history.
- [x] Allow managers to see team pay history.

### Task 3.3 - Working Hours Report
- [x] Compare planned hours from `planning.slot` with actual hours from `hr.attendance`.
- [x] Show overtime and undertime by employee and month.
- [x] Add filters for month, employee, and company if needed.
- [x] Export the report to PDF and Excel.

### Task 3.4 - Leave Report
- [x] Show leave days taken per employee by leave type.
- [x] Show remaining leave balance.
- [x] Add filters for date range, employee, and leave type.
- [x] Export the report to PDF and Excel.

### Task 3.5 - Payroll Summary Report
- [x] Show total payroll cost per month.
- [x] Break totals down by role and company.
- [x] Add month-over-month comparison.
- [x] Export the report to PDF and Excel.

## Suggested Build Order
- [ ] 1. Finish Odoo foundation and mapping fields.
- [ ] 2. Implement Task 1.1 Odoo login.
- [ ] 3. Build the real employee dashboard shell.
- [ ] 4. Implement shifts, then attendance, then leave for employees.
- [ ] 5. Implement manager role detection and manager dashboard.
- [ ] 6. Replace local shift CRUD with Odoo-backed shift CRUD.
- [ ] 7. Add leave approval and team attendance tools.
- [ ] 8. Implement payslips and reports last.

## Notes on Existing Local Features
- [ ] Review whether current local tables `staff`, `shifts`, and `staff_schedules` should remain in use.
- [ ] Review whether current sidebar items should be renamed to match the new employee and manager flows.
- [ ] Review whether current email handling can be reused for leave status notifications.
