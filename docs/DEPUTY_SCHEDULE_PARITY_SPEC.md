# Deputy Schedule High-Fidelity Parity Spec

This document is the stricter follow-up to [DEPUTY_SCHEDULE_IMPLEMENTATION_GUIDE.md](./DEPUTY_SCHEDULE_IMPLEMENTATION_GUIDE.md).

Its purpose is different:

- the implementation guide explains the overall direction
- this parity spec defines what must match Deputy closely if we want a scheduler that feels and behaves like Deputy

## Goal

Build a new schedule workspace in this repo that aims for **high-fidelity Deputy parity** in:

- layout
- visual hierarchy
- workflow
- interaction model
- shift status behavior
- keyboard shortcuts
- publish flow
- drag-and-drop editing

This should **not** be treated as an upgrade to the old `staffschedule` page.
It should be treated as a **new scheduler product surface** built on the newer Odoo planning foundation.

## Official Deputy References Studied

All sources below are official Deputy Help Center pages and were rechecked on **July 10, 2026**.

### Core scheduler pages

- Schedule overview
  https://help.deputy.com/hc/en-au/articles/4688713423759-Schedule-overview
  Updated: **10 April 2026**
- Creating shifts on your schedule
  https://help.deputy.com/hc/en-au/articles/4688731978639-Creating-shifts-on-your-schedule
  Updated: **22 January 2026**
- Shift status
  https://help.deputy.com/hc/en-au/articles/6054132302991-Shift-status
  Updated: **15 January 2026**
- Publishing shifts
  https://help.deputy.com/hc/en-au/articles/4688746992399-Publishing-shifts
  Updated: **15 January 2026**

### Schedule management and advanced interactions

- Schedule filtering and management
  https://help.deputy.com/hc/en-au/articles/4688801660175-Schedule-filtering-and-management
  Updated: **15 January 2026**
- Bulk shift updates on the schedule
  https://help.deputy.com/hc/en-au/articles/4688835682191-Bulk-shift-updates-on-the-schedule
  Updated: **15 January 2026**
- Copy shifts to another date or area on the schedule
  https://help.deputy.com/hc/en-au/articles/4688828103439-Copy-shifts-to-another-date-or-area-on-the-schedule
  Published: about **February 2026** according to current Help Center metadata
- Schedule templates
  https://help.deputy.com/hc/en-au/articles/4688863723791-Schedule-templates
  Updated: **15 January 2026**
- Keyboard shortcuts when scheduling
  https://help.deputy.com/hc/en-au/articles/4688878025487-Keyboard-shortcuts-when-scheduling
  Updated: **10 March 2026**
- Undo schedule actions
  https://help.deputy.com/hc/en-au/articles/4688798647183-Undo-schedule-actions

### Shift creation variants and scheduling signals

- Open shifts
  https://help.deputy.com/hc/en-au/articles/4688698300687-Open-shifts
  Updated: **18 February 2026**
- Managing micro-scheduled shifts and timesheets
  https://help.deputy.com/hc/en-au/articles/10611651590159-Managing-micro-scheduled-shifts-and-timesheets
- How do I ensure that a team member is recommended for a shift?
  https://help.deputy.com/hc/en-au/articles/4688700112015-How-do-I-ensure-that-a-team-member-is-recommended-for-a-shift
- Viewing time off in the schedule
  https://help.deputy.com/hc/en-au/articles/4688865765775-Viewing-time-off-in-the-schedule
  Updated: **15 January 2026**
- Use area color coding when scheduling
  https://help.deputy.com/hc/en-au/articles/4688782461711-Use-area-color-coding-when-scheduling
  Updated: **15 January 2026**
- Using a simple staff coverage planner when scheduling
  https://help.deputy.com/hc/en-au/articles/4688843118223-Using-a-simple-staff-coverage-planner-when-scheduling
  Updated: **15 January 2026**
- Auto-fill empty shifts in the schedule
  https://help.deputy.com/hc/en-au/articles/4688889483919-Auto-fill-empty-shifts-in-the-schedule
  Updated: **15 January 2026**
- Creating your Locations
  https://help.deputy.com/hc/en-au/articles/4657694803087-Creating-your-Locations

## Visual References Already Saved In Repo

- [Main overview image](./images/deputy-schedule/01-main-overview.png)
- [Location quick select](./images/deputy-schedule/02-location-quick-select.png)
- [Multi location select](./images/deputy-schedule/03-location-multi-select.png)
- [Week by area view](./images/deputy-schedule/04-week-by-area.png)
- [Week by team member view](./images/deputy-schedule/05-week-by-team-member.png)
- [Options menu](./images/deputy-schedule/06-options-menu.png)
- [Publish button](./images/deputy-schedule/07-publish-button.png)
- [Status bar](./images/deputy-schedule/08-status-bar.png)

## Non-Negotiable Deputy Behaviors

These are the parity requirements we should treat as mandatory if the goal is "same like Deputy".

### 1. WYSIWYG schedule actions

Deputy repeatedly uses the rule:

- actions only affect the shifts currently visible on screen
- filters, dates, areas, and views directly change the action scope

This means our scheduler must make the visible range and active filters part of the action model for:

- publish
- bulk update
- delete all
- remove team members
- remove empty shifts
- copy day/week
- print/export

### 2. Same top-toolbar structure

The Deputy schedule toolbar is not optional decoration. It is the control center.

We need this order and behavior:

1. Location / Area selector
2. Date selector with prev/next arrows
3. View selector
4. Refresh
5. Auto-schedule / smart actions
6. Copy schedule / templates
7. Insights
8. Options
9. Publish

### 3. Same left roster concept

The left column is not just a filter list. It is a working roster.

Required behavior:

- searchable roster
- sort/filter dropdown
- team totals in current visible range
- click employee to highlight/show their shifts
- drag employee from roster into grid to create shift
- show `Open Shift` at the top as a draggable source
- allow adding team members from the bottom of the list

Deputy's official docs also show roster sorting by:

- display name
- first name
- last name
- total hours
- scheduled cost
- base cost
- stress
- age
- role
- most qualified
- distance
- tenure
- leave/available

We do not need every data point on day one, but we do need the UI architecture to support them.

### 4. Same schedule view family

Deputy supports:

- Day
- Week
- 2 Week
- 4 Week
- Monthly

And view modes:

- by Area
- by Team Member

From the official docs:

- 4-week view is only in Team Member view
- monthly view is only in Team Member view for general schedule filtering
- time off visibility uses Area views
- coverage planner uses Area views

For parity we should support at least:

- Week by Area
- Week by Team Member
- 2 Weeks by Area
- 2 Weeks by Team Member

Then add:

- Day by Area
- Day by Team Member
- 4 Weeks by Team Member
- Month by Team Member

### 5. Same shift states and visual meanings

Deputy has a clear shift-state language.

Required shift states:

- Empty shift
- Open shift
- Filled unpublished shift
- Published shift
- Pending confirmation shift
- Warning shift
- Locked shift
- Selected shift
- Related shifts for selected team member

Required meanings from the official docs:

- empty shifts cannot be published
- open unpublished shifts appear white
- published/open/filled shifts appear green
- unpublished filled shifts appear grey
- warning shifts can still be published
- locked shifts are not editable
- selected shift becomes dark purple
- other shifts of the same person become lavender

### 6. Same bottom status bar concept

Deputy's bottom status strip must be treated as a first-class feature.

It shows counts for the currently displayed schedule:

- empty shifts
- unpublished shifts
- published shifts
- shifts requiring confirmation
- open shifts
- warnings
- leave approved
- leave pending
- people unavailable

Parity requirement:

- clickable filters for the shift-state counts
- non-clickable informational counts for leave/unavailability
- always computed from the currently visible filtered set

### 7. Same area model

Deputy areas are important because they drive:

- grouping
- color coding
- training requirements
- preferred team members
- coverage panels
- copy targets
- open shift creation context

This repo currently has Odoo companies and roles, but that is not enough for full Deputy parity.

We use the Odoo `ems.schedule.area` model with:

- name
- color
- order
- company/location association
- optional Odoo role mapping
- required training tags
- preferred team members

### 8. Same creation flows

Deputy supports multiple ways to create shifts:

- click `+`
- double-click a schedule area/cell
- drag a team member into the grid
- drag `Open Shift` into the grid
- create from existing shift
- duplicate empty/open shifts
- repeat shift
- use templates
- use auto-schedule

Parity requirement:

- we should support all of these eventually
- the first production slice must already support:
  - plus-button create
  - empty-cell double-click create
  - roster drag-create
  - open-shift drag-create

### 9. Same drag-and-drop model

The user specifically asked for drag-and-drop like Deputy, so this is mandatory.

Required interactions:

- drag employee from roster to grid to create shift
- drag open shift source to grid to create open shift
- drag existing shift to another day
- drag existing shift to another area
- drag existing shift to another team member row
- drag micro-scheduled shifts to change date/time/team member/area
- resize shift horizontally to change time span

We should also support:

- hover affordances
- active drop targets
- invalid drop rejection with warning feedback

### 10. Same selection and bulk-edit model

Deputy supports multi-selection using keyboard modifiers.

Required behavior:

- `Ctrl` / `Command` multi-select of shifts
- edit menu actions on selected shifts
- bulk update fields across visible shifts
- delete selected shifts
- link shifts for micro-scheduling later

Official docs confirm bulk update fields include:

- start time
- end time
- meal break length
- rest break length
- team member
- area
- shift note
- publish status

### 11. Same copy and template workflows

Deputy supports:

- copy single shift
- copy day
- copy week
- copy 2-week schedule
- copy 4-week schedule
- save template
- load template
- repeat shift
- duplicate shift

Exact behavior we should preserve:

- single shift copy uses `C` then `V`
- copy mode can be cancelled with `Esc`
- copy checks staff availability
- template loading is constrained by current view range

### 12. Same publish workflow

Deputy publishing is not a boolean toggle. It is a workflow.

Required publish concepts:

- purple publish button when unpublished shifts exist
- green button when everything visible is published
- publish only the currently visible range
- choose publish type:
  - publish updates
  - publish all
- choose areas to publish
- choose notification type:
  - require confirmation
  - notify with SMS/email/app
  - notify email/app
  - mark as published without notifying

For our product, even if SMS is not built immediately, the workflow shape should match this structure.

### 13. Same recommendation/warning model

Deputy recommends or warns on assignment based on factors including:

- overlapping shifts
- training
- unavailability
- approved leave
- stress profiles
- onboarding completed
- preferred team members

For MVP we should at least implement warning checks for:

- overlapping shifts
- leave/unavailability
- area qualification or role match

This repo already has conflict logic in Odoo planning services, so this is a good starting point.

### 14. Same time-off visibility concept

Deputy exposes a `Time off` area at the top of Area views with toggles for:

- Leave
- Unavailability
- Grouping on/off

Color semantics from the official docs:

- pink = leave
- purple = unavailability

We should replicate this as a dedicated strip rather than burying leave info inside random row badges.

### 15. Same coverage planner concept

Deputy can show coverage counts by area/day and uses colors to indicate staffing adequacy.

Required parity direction:

- boxed count per area/day
- required coverage editable by managers
- red when understaffed
- grey when met/exceeded
- usable in Area views

### 16. Same undo model for bulk actions

Deputy shows a temporary yellow undo prompt after bulk actions such as:

- template load
- auto-fill empty shifts
- clone empty shifts

We should keep this exact interaction concept for:

- bulk create
- copy day/week
- template load
- bulk update
- mass delete

### 17. Same keyboard shortcuts

Official Deputy shortcuts we should aim to preserve:

- `M` monthly view
- `4` 4-week view
- `F` or `2` 2-week view
- `W` or `1` weekly view
- `D` daily view
- `A` add shift
- `S` toggle stats panel
- `X` cut selected shift
- `C` copy selected shift
- `V` paste selected shift
- `Ctrl/Cmd + Shift + V` paste and replace
- `O` convert selected shifts to open
- `N` jump to next shift of current team member
- `P` publish weekly schedule
- `Backspace/Delete` delete selected shift
- `L` clone selected shift
- `R` remove team member from selected shift
- `Esc` deselect shift / exit modes

## Current Repo Gap Analysis

### Already present

- Odoo-backed create/update/delete for planning slots
- conflict checking
- stale-write protection
- monthly calendar summaries
- employee self-view

Relevant files:

- [app/Http/Controllers/ManagerShiftController.php](../app/Http/Controllers/ManagerShiftController.php)
- [app/Services/Odoo/OdooManagerPlanningService.php](../app/Services/Odoo/OdooManagerPlanningService.php)
- [app/Http/Controllers/EmployeeShiftController.php](../app/Http/Controllers/EmployeeShiftController.php)
- [resources/views/admin/manager-shifts/create.blade.php](../resources/views/admin/manager-shifts/create.blade.php)
- [resources/views/admin/employee-shifts/index.blade.php](../resources/views/admin/employee-shifts/index.blade.php)

### Implemented Deputy interaction surface

- dedicated weekly scheduler workspace
- area model
- left roster panel
- drag-create
- drag-move
- drag-resize
- open shifts model
- publish workflow and counts
- shift state coloring system
- bottom status strip
- time-off strip
- coverage planner
- multi-selection and bulk-edit UI
- keyboard shortcut layer
- copy/paste interaction layer
- undo toast/prompt for bulk actions
- template save/load
- preview-first coverage auto-scheduling

These capabilities use Odoo `planning.slot` and the Odoo-only `ems.schedule.*` models. The remaining parity work is detailed browser-level visual and interaction refinement, not a second Laravel schedule datastore.

### Legacy page should not be used as the base

The old page:

- [app/Http/Controllers/StaffScheduleController.php](../app/Http/Controllers/StaffScheduleController.php)
- [resources/views/admin/staffSchedule/list.blade.php](../resources/views/admin/staffSchedule/list.blade.php)

has some useful ideas:

- weekly matrix
- `publish` field

but it is not suitable as the platform for high-fidelity Deputy parity because it:

- uses a separate local schedule truth
- is hard-coded and repetitive
- is not built around interactive grid state
- does not align with Odoo planning slots

## Architecture Consequence

If the requirement is truly:

- same UI as Deputy
- drag and drop like Deputy
- same working feel as Deputy

then the scheduler surface must be built as a **rich client-side workspace**, not as a mostly static Blade page with form posts.

### Recommended structure

Keep:

- Laravel routes
- Laravel auth/permissions
- Odoo service layer
- Blade admin shell

But build the scheduler itself as a mounted JS application inside the page.

### Recommended frontend shape

Use:

- Blade for outer page chrome
- a dedicated scheduler root container
- Vite-built scheduler modules
- a client-side state store for filters, selection, view, and shift objects
- drag/resize handling in JS

Do not continue extending the current `manager-shifts/create.blade.php` page if our goal is Deputy parity.

### Recommended new feature surface

- `manager/schedule`
- `manager/schedule/api/*`

Suggested server files:

- `app/Http/Controllers/ManagerScheduleController.php`
- `app/Http/Controllers/ManagerScheduleApiController.php`
- `app/Services/Scheduling/ManagerScheduleWorkspaceService.php`

Suggested frontend files:

- `resources/views/admin/manager-schedule/index.blade.php`
- `resources/js/schedule/index.js`
- `resources/js/schedule/store.js`
- `resources/js/schedule/grid.js`
- `resources/js/schedule/drag.js`
- `resources/js/schedule/shortcuts.js`
- `resources/js/schedule/dialogs.js`
- `resources/js/schedule/status-bar.js`

## Data Model Required For Parity

### Keep Odoo as the shift source of truth

Actual scheduled shift records should remain Odoo `planning.slot`.

### Add Odoo addon models

Odoo `ems.schedule.area`

- visual/grouping area
- color
- order
- company/location mapping
- optional Odoo role mapping

Odoo `planning.slot` fields prefixed with `ems_`

- `odoo_slot_id`
- `schedule_area_id`
- `status_override` nullable
- `is_open`
- `published_at`
- `published_by`
- `requires_confirmation`
- `warning_code`

`schedule_visibility_rules`

- per company/location settings
- hidden areas
- publish defaults
- schedule lock rules in the Odoo addon if needed

`schedule_coverage_targets`

- area/day required staff counts

Odoo `ems.schedule.template`

- template header

`schedule_template_items`

- template shifts

`schedule_bulk_action_log`

- bulk action payload
- undo token
- created by
- expires at

## High-Fidelity Build Order

### Phase 0: Parity foundation

Build first:

- new route and page shell
- single source of schedule JSON for visible range
- area model
- week by area renderer
- left roster panel
- selected shift state model

### Phase 1: Core Deputy-like interactions

Build next:

- click/plus/double-click create
- roster drag-create
- open-shift drag-create
- drag-move existing shifts
- publish button and publish dialog
- status strip

### Phase 2: Bulk and power-user behavior

- multi-select
- bulk update
- copy/paste
- clone
- repeat
- undo prompt
- keyboard shortcuts

### Phase 3: Extended Deputy parity

- week by team member
- 2-week views
- time-off strip
- coverage planner
- templates
- area color coding

### Phase 4: Advanced Deputy parity

- 4-week and monthly team-member views
- micro-scheduling / linked sub-shifts
- open shifts with approval
- richer warnings and recommendation engine
- insights/stats panel equivalents

## Product Decision

If we want a screen that simply schedules staff, we can keep extending the current manager page.

If we want a screen that feels like Deputy, we should do this instead:

- stop treating scheduling as a normal CRUD page
- build a dedicated scheduler workspace
- keep Odoo for actual shift persistence
- add Odoo addon models for everything Deputy layers on top

## Short Version

The user request is valid:

- yes, we can aim for Deputy-like drag-and-drop
- yes, we can aim for very similar UI
- yes, we can keep the detailed interaction model close to Deputy

But that means a bigger architectural decision:

- **new scheduler workspace**
- **rich JS interaction layer**
- **new Odoo scheduling metadata models**
- **Odoo kept as shift persistence**

Without that, we will only get a simplified schedule page, not a true Deputy-like scheduler.
