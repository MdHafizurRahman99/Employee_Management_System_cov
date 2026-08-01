# Deputy Schedule UI Parity Plan

## Target

Match the supplied Deputy schedule references closely while retaining the current Laravel, Bootstrap, and Odoo-backed scheduling behavior. This is a workspace redesign, not a second scheduling system.

## Reference hierarchy

1. `01-main-overview.png` for the command bar and overall density.
2. `05-week-by-team-member.png` for employee rows and shift cards.
3. `04-week-by-area.png` for grouped areas and open shifts.
4. `08-status-bar.png` for the persistent schedule state strip.
5. Location, options, and publish screenshots for menus and actions.

## Delivery plan

## Progress — 15 July 2026

- Pass 1 workspace shell and visual parity: implemented.
- Multi-location picker and remembered display preferences: implemented.
- Responsive mobile day-focus grid: implemented.
- Focus-visible schedule controls: implemented.
- Compact shift overflow menus and publish split-state presets: implemented.
- Keyboard grid movement and non-pointer move/resize actions: implemented.
- Named schedule templates with conflict preview and rollback: implemented.
- Local scheduling areas mapped to Odoo roles with weekday coverage targets: implemented.
- Weekly day notes, holiday labels, and area-scoped blocked-time conflict warnings: implemented.
- Planned breaks and configurable missing-break, long-shift, and minimum-rest audit warnings: implemented.
- Auditable weekly wage forecast with confirmed effective rates, location budgets, unpaid-break adjustment, and visible unknown-rate exclusions: implemented.
- Remaining Pass 3 work: deeper screen-reader announcement testing with a browser accessibility tree.
- Pass 4 visual QA still requires a browser session with representative live Odoo schedule data.

### Current visual-QA blocker

- Local MySQL at `127.0.0.1:3306` is not running, so authenticated schedule pages and pending migrations cannot be rendered with representative data.
- No Chrome, Edge, Chromium, Playwright, or Puppeteer executable is installed in the workspace environment.
- Blade compilation and schedule regression tests remain available, but screenshot comparison must be completed once the database and browser are available.

### Pass 1 — workspace shell and visual parity

- Replace the oversized dashboard header with a compact Deputy-like command bar.
- Put location, week navigation, view selection, workspace tools, confirmations, and publish in one row.
- Make the roster grid the dominant surface and remove dashboard cards from the default view.
- Move checks, workload, copy, and templates behind Workspace tools.
- Match Deputy grid density, sticky headers, left roster rail, shift colours, borders, and fixed bottom status strip.
- Preserve drag/drop, resize, inline add, copy/paste, bulk edit, filters, publishing, and confirmations.

### Pass 2 — interaction parity

- Location quick-select and multi-location menu.
- Deputy-style options popover for copy day/week, display density, and workspace tools.
- Publish split-button states and confirmation counts.
- Row overflow actions and compact shift-card action menu.
- Saved local display preferences.

### Pass 3 — responsive and accessibility parity

- Horizontal schedule viewport with sticky roster column on tablets.
- Focus-visible keyboard movement and action menus.
- Mobile day focus view instead of compressing seven columns.
- Screen-reader labels for shift state, warnings, and drag alternatives.

### Pass 4 — visual QA

- Compare the implementation against every supplied screenshot at desktop and tablet widths.
- Verify both team-member and area modes with empty, open, published, warning, leave, and unavailable states.
- Run Blade compilation, focused schedule tests, route checks, and manual browser interaction checks.

## Non-negotiable constraints

- Odoo `planning.slot` remains the schedule source of truth.
- No button may imply a backend feature that is not implemented.
- Existing schedule mutations keep conflict and stale-write validation.
- Manager and employee workflows already delivered must remain reachable.
