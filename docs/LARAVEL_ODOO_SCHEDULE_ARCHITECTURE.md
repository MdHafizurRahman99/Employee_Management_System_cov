# Laravel Scheduler on Top of Odoo

This note reflects the clarified architecture:

- **Odoo is the backend source of truth**
- **Laravel is where we build the schedule product/UI**

## Non-negotiable datastore rule

Every schedule record and schedule-state field is stored in the Odoo database and accessed from Laravel through the Odoo XML-RPC API. Laravel must not create, update, or depend on local schedule tables. Local Laravel users may still be used for application authentication and mapping an authenticated user to an Odoo employee; they are not schedule storage.

Undo payloads follow the same rule: expiring, single-use undo batches are stored in Odoo `ems.schedule.undo.batch`. Laravel only flashes the opaque token needed to render the immediate Undo action.

## Codebases

Laravel app:

- `D:\Office\Employee_Management_System_cov`

Odoo project:

- `D:\Office\EPR\seaford-clinic-19`

## What We Confirmed In Odoo

### 1. Shift persistence is Odoo `planning.slot`

Laravel already reads and writes Odoo planning records through:

- [app/Services/Odoo/OdooManagerPlanningService.php](/D:/Office/Employee_Management_System_cov/app/Services/Odoo/OdooManagerPlanningService.php)
- [app/Services/Odoo/OdooPlanningService.php](/D:/Office/Employee_Management_System_cov/app/Services/Odoo/OdooPlanningService.php)

Confirmed Odoo models used from Laravel:

- `planning.slot`
- `planning.role`
- `hr.employee`
- `res.company`

### 2. The custom Odoo addon extends planning slots with EMS workflow metadata

In the custom Odoo project, we found:

- `hr_employee_weekly_availability`
- `hr_leave_planning_bridge`

The `hr_employee_weekly_availability` addon now includes `_inherit = 'planning.slot'` in
`models/schedule_management.py` for publish, confirmation, notification, claim, and work-location metadata.

Implication:

- the Deputy-like scheduler UI should be implemented in Laravel
- we can continue to rely on standard Odoo planning records as the backend
- scheduler-only metadata belongs in `hr_employee_weekly_availability` and is exposed through XML-RPC

### 3. Odoo already gives us weekly availability

Custom Odoo weekly availability model:

- [weekly_availability.py](</D:/Office/EPR/seaford-clinic-19/custom_addons/hr_employee_weekly_availability/models/weekly_availability.py>)

Related Laravel service:

- [OdooWeeklyAvailabilityService.php](/D:/Office/Employee_Management_System_cov/app/Services/Odoo/OdooWeeklyAvailabilityService.php)

Implication:

- Deputy-like availability and unavailability warnings can come from Odoo
- Laravel should render them inside the scheduler

### 3a. Odoo owns date-specific employee schedule diary entries

Odoo model:

- `hr.employee.schedule.entry`

Stored fields include:

- employee
- specific entry date
- available, unavailable, or note/preference type
- all-day or timed window
- title and note

Laravel integration:

- `app/Services/Odoo/OdooEmployeeScheduleEntryService.php`

Implication:

- Laravel must never create a local schedule-diary table
- employee diary CRUD uses Odoo `create`, `write`, and `unlink`
- manager roster reads and auto-scheduling use the same Odoo records
- timed or all-day diary unavailability is shown to managers as a warning during manual scheduling
- auto-scheduling prioritizes matching availability, then neutral employees; it respects diary unavailability by default and uses unavailable employees only as a last resort with an explicit manager override
- employees may voluntarily claim open shifts despite availability preferences; approved leave and real shift overlaps remain hard protections
- manager diary reads are limited to employees already visible in that scheduling workspace

Deployment order:

1. Deploy the updated `hr_employee_weekly_availability` addon to the Odoo server.
2. Upgrade that addon against the target Odoo database and restart Odoo.
3. Verify `fields_get` succeeds for `hr.employee.schedule.entry`.
4. Deploy the Laravel changes only after the Odoo model is available.

This order matters because Laravel intentionally has no local fallback table.

### 4. Odoo already bridges leave requests to shifts

Custom Odoo leave bridge:

- [hr_leave.py](</D:/Office/EPR/seaford-clinic-19/custom_addons/hr_leave_planning_bridge/models/hr_leave.py>)

This stores:

- linked `planning_slot_id`
- shift title snapshot
- role snapshot
- company snapshot
- planned start/end datetime snapshot

Implication:

- Laravel can show leave-aware warnings and shift context without inventing a second leave system

### 5. Employees can work across multiple companies

Custom Odoo employee company coverage:

- [hr_employee.py](</D:/Office/EPR/seaford-clinic-19/custom_addons/hr_employee_weekly_availability/models/hr_employee.py>)
- [company_assignment.py](</D:/Office/EPR/seaford-clinic-19/custom_addons/hr_employee_weekly_availability/models/company_assignment.py>)

This adds:

- employee company coverage scope
- multiple company assignments
- working-company access sync

Implication:

- in the Laravel scheduler, company filtering matters
- one employee may legitimately appear schedulable across more than one company

## What We Confirmed In Laravel

### 1. The current manager schedule page is already more advanced than a simple CRUD form

Current page:

- [resources/views/admin/manager-shifts/create.blade.php](/D:/Office/Employee_Management_System_cov/resources/views/admin/manager-shifts/create.blade.php)

It already contains:

- a weekly roster grid
- `Week by Team Member` layout
- roster filters
- open-shift row
- quick-add per cell
- shift copy clipboard
- template chips
- roster summary/status bar
- shift edit/delete controls

This is important because it changes the plan:

- we do **not** need to start from zero
- we should evolve this into the real Laravel scheduler workspace

### 2. The controller already loads weekly roster data

Controller:

- [ManagerShiftController.php](/D:/Office/Employee_Management_System_cov/app/Http/Controllers/ManagerShiftController.php)

Service:

- [OdooManagerPlanningService.php](/D:/Office/Employee_Management_System_cov/app/Services/Odoo/OdooManagerPlanningService.php)

The service already prepares:

- monthly shifts
- selected-day shifts
- weekly roster rows
- roster alerts
- role/company breakdown
- reusable shift templates

Implication:

- the backend shape for a Laravel scheduler workspace already exists in partial form

## Correct Architecture Going Forward

### Keep in Odoo

- actual shift records
- employees
- companies
- planning roles
- leave-linked shift context
- weekly availability
- date-specific employee schedule diary entries

### Build in Laravel

- the Deputy-like scheduler UI
- drag and drop
- resize interactions
- multi-select
- copy/paste UX
- publish workflow UI
- status bar
- coverage panels
- area/grouping presentation
- keyboard shortcuts

## Important Design Decision: Roles vs Areas

Odoo currently gives us:

- `planning.role`
- `company`

That is enough for basic scheduling, but not full Deputy parity.

Deputy has a stronger `Area` concept used for:

- grouping
- colors
- training requirements
- preferred staff
- coverage counts

So for Laravel we should treat:

- `company` as the location filter
- `planning.role` as the initial area-like grouping

Deputy-style areas must use the Odoo `ems.schedule.area` model from `hr_employee_weekly_availability`, mapped to Odoo roles and companies. Laravel must not add a second schedule datastore.

## Recommended Implementation Strategy

### Best path

Use the current manager schedule page as the base and evolve it.

Why this is better than starting over:

- Odoo integration is already wired
- weekly roster data is already prepared
- the UI already has Deputy-like building blocks
- we reduce risk and avoid redoing working integration code

### What to refactor

The current page should gradually become the dedicated scheduler workspace.

Recommended end state:

- route stays `manager/shifts/create` for now or gets an alias like `manager/schedule`
- controller becomes a schedule workspace controller
- Blade view becomes a scheduler shell
- more interaction moves into dedicated JS modules

## What Still Needs To Be Built For Deputy Parity

Even with the current progress, we still need:

- true drag-create from roster
- drag-move existing shifts
- horizontal resize to change start/end times
- stronger open-shift workflow
- publish state/workflow
- bottom status counts by state
- time-off strip
- WYSIWYG bulk actions
- keyboard shortcuts
- undo for bulk actions

## Recommended Next Step

The next implementation slice should be:

1. Promote the current manager shift page into the official scheduler workspace.
2. Extract the page JS into dedicated `resources/js/schedule/*` modules.
3. Add drag-and-drop on the weekly roster grid first.
4. Keep Odoo `planning.slot` as the only shift persistence layer.
5. Add missing concepts to the Odoo addon and expose them through XML-RPC; never create Laravel schedule persistence.

## Short Version

Yes, the schedule should be implemented in Laravel.

Odoo should remain the backend for:

- shifts
- employees
- companies
- roles
- availability
- leave-linked shift context

And the right path is to **upgrade the existing Laravel manager schedule workspace**, not replace the Odoo backend or rebuild a second scheduling engine.
