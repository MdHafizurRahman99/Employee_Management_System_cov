<?php

namespace App\Services\Odoo;

use App\Models\User;
use App\Services\Scheduling\SchedulePublishService;
use App\Support\ShiftCalendarBuilder;
use Carbon\Carbon;

class OdooManagerPlanningService
{
    private const VISIBLE_PERIOD_DAYS = 14;

    private ?array $planningFields = null;

    private ?array $employeeFields = null;

    private ?array $roleFields = null;

    private ?array $leaveFields = null;

    private ?array $weeklyAvailabilityFields = null;

    private ?array $workLocations = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount,
        private readonly ?SchedulePublishService $publishService = null,
        private readonly ?OdooEmployeeScheduleEntryService $scheduleEntryService = null
    ) {
    }

    /**
     * @return array{
     *     employees:array<int, array<string, mixed>>,
     *     roles:array<int, array<string, mixed>>,
     *     companies:array<int, array<string, mixed>>,
     *     recentShifts:array<int, array<string, mixed>>
     * }
     */
    public function getShiftCreationPageData(): array
    {
        return $this->getShiftCreationPageDataForMonth(now()->startOfMonth());
    }

    /**
     * @return array{
     *     employees:array<int, array<string, mixed>>,
     *     roles:array<int, array<string, mixed>>,
     *     companies:array<int, array<string, mixed>>,
     *     recentShifts:array<int, array<string, mixed>>,
     *     shiftCalendar:array<int, array<int, array<string, mixed>>>,
     *     selectedCalendarDate:Carbon,
     *     selectedCalendarDateLabel:string,
     *     selectedCalendarDateValue:string,
     *     selectedCalendarShifts:array<int, array<string, mixed>>,
     *     weeklyRoster:array<string, mixed>,
     *     weeklyAreaBoard:array<string, mixed>
     * }
     */
    public function getShiftCreationPageDataForMonth(Carbon $month, ?Carbon $selectedDay = null): array
    {
        $month = $month->copy()->startOfMonth();
        $rangeAnchor = $selectedDay?->copy()
            ?? (now()->isSameMonth($month) ? now() : $month->copy()->startOfMonth());
        $recentShifts = $this->getRecentShifts($month, $rangeAnchor);
        $recentShifts = $this->publishService()->decorateShifts($recentShifts);
        $calendar = $this->calendarBuilder()->build($month, $recentShifts, $selectedDay);
        $employees = $this->getSelectableEmployees();
        $roles = $this->getSelectableRoles();
        $timeOff = $this->buildWeeklyTimeOffData($calendar['selected_date'], $employees);
        $employeeDiary = [
            'entries' => [],
            'by_employee_date' => [],
            'by_date' => [],
            'count' => 0,
        ];
        $employeeDiaryError = null;

        if ($this->scheduleEntryService) {
            try {
                $employeeDiary = $this->scheduleEntryService->getForManagerRange(
                    $calendar['selected_date']->copy()->startOfWeek(),
                    $this->visiblePeriodEnd($calendar['selected_date']),
                    array_values(array_filter(array_map(
                        fn (array $employee): int => (int) ($employee['id'] ?? 0),
                        $employees
                    )))
                );
            } catch (OdooException $exception) {
                // Schedule diary is an optional add-on feature. Its absence must not
                // prevent the core Odoo roster and shift controls from loading.
                $employeeDiaryError = $exception->getMessage();
            }
        }

        return [
            'employees' => $employees,
            'roles' => $roles,
            'companies' => $this->getSelectableCompanies(),
            'workLocations' => $this->getSelectableWorkLocations(),
            'recentShifts' => $recentShifts,
            'shiftCalendar' => $calendar['weeks'],
            'selectedCalendarDate' => $calendar['selected_date'],
            'selectedCalendarDateLabel' => $calendar['selected_date_label'],
            'selectedCalendarDateValue' => $calendar['selected_date_value'],
            'selectedCalendarShifts' => $calendar['selected_date_shifts'],
            'weeklyRoster' => $this->buildWeeklyRoster($calendar['selected_date'], $employees, $recentShifts, $timeOff),
            'weeklyAreaBoard' => $this->buildWeeklyAreaBoard($calendar['selected_date'], $roles, $recentShifts),
            'employeeDiary' => $employeeDiary,
            'employeeDiaryError' => $employeeDiaryError,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWeeklyShiftsForDate(Carbon $selectedDate): array
    {
        $selectedDate = $selectedDate->copy()->startOfDay();
        $month = $selectedDate->copy()->startOfMonth();
        $weekStart = $selectedDate->copy()->startOfWeek();
        $weekEnd = $selectedDate->copy()->endOfWeek();
        $recentShifts = $this->publishService()->decorateShifts($this->getRecentShifts($month, $selectedDate));

        return array_values(array_filter($recentShifts, function (array $shift) use ($weekStart, $weekEnd): bool {
            $startAt = $shift['start_at'] ?? null;

            return $startAt instanceof Carbon
                && $startAt->copy()->startOfDay()->betweenIncluded($weekStart, $weekEnd);
        }));
    }

    /**
     * Return every shift in the same 14-day window shown on the manager schedule.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVisiblePeriodShiftsForDate(Carbon $selectedDate): array
    {
        $selectedDate = $selectedDate->copy()->startOfDay();
        $periodStart = $selectedDate->copy()->startOfWeek();
        $periodEnd = $this->visiblePeriodEnd($selectedDate);
        $recentShifts = $this->publishService()->decorateShifts(
            $this->getRecentShifts($selectedDate->copy()->startOfMonth(), $selectedDate)
        );

        return array_values(array_filter($recentShifts, function (array $shift) use ($periodStart, $periodEnd): bool {
            $startAt = $shift['start_at'] ?? null;

            return $startAt instanceof Carbon
                && $startAt->copy()->startOfDay()->betweenIncluded($periodStart, $periodEnd);
        }));
    }

    /** @return array<int, array<string, mixed>> */
    public function getOpenShiftsForEmployee(User $user, Carbon $selectedDate): array
    {
        $employee = $this->findEmployeeForUser($user);

        if (! $employee) {
            throw new OdooException('Your account is not linked to an active Odoo employee.');
        }

        return array_values(array_filter($this->getWeeklyShiftsForDate($selectedDate), function (array $shift) use ($employee): bool {
            $startAt = $shift['start_at'] ?? null;

            return empty($shift['employee_id'])
                && $startAt instanceof Carbon
                && $startAt->isFuture()
                && $this->employeeCanWorkShift($employee, $shift);
        }));
    }

    public function claimOpenShift(User $user, int $slotId, string $lastKnownWriteDate): void
    {
        $employee = $this->findEmployeeForUser($user);

        if (! $employee) {
            throw new OdooException('Your account is not linked to an active Odoo employee.');
        }

        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);
        $shift = $startField && $endField ? $this->getShiftForManager($slotId, $startField, $endField) : null;

        if (! $shift || ! empty($shift['employee_id'])) {
            throw new OdooException('This open shift has already been claimed or is no longer available.');
        }

        if (! ($shift['start_at'] ?? null) instanceof Carbon || ! $shift['start_at']->isFuture()) {
            throw new OdooException('Past or started shifts cannot be claimed.');
        }

        if (! $this->employeeCanWorkShift($employee, $shift)) {
            throw new OdooException('This shift is outside your company or eligible planning roles.');
        }

        $this->guardEmployeeClaimLeave($employee, $shift['start_at']);

        $this->updateShift($slotId, [
            'employee_id' => $employee['id'],
            'role_id' => $shift['role_id'],
            'company_id' => $shift['company_id'],
            'work_location_id' => $shift['work_location_id'],
            'shift_date' => $shift['shift_date_value'],
            'start_time' => $shift['start_time_value'],
            'end_time' => $shift['end_time_value'],
            'title' => $shift['title_value'] ?? null,
            'note' => $shift['note'] ?? null,
            'last_known_write_date' => $lastKnownWriteDate,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function findEmployeeForUser(User $user): ?array
    {
        if (! $user->odoo_employee_id) {
            return null;
        }

        return $this->findById($this->getSelectableEmployees(), (int) $user->odoo_employee_id);
    }

    /** @param array<string,mixed> $employee @param array<string,mixed> $shift */
    private function employeeCanWorkShift(array $employee, array $shift): bool
    {
        $employeeCompanyId = (int) ($employee['company_id'] ?? 0);
        $shiftCompanyId = (int) ($shift['company_id'] ?? 0);

        if ($employeeCompanyId > 0 && $shiftCompanyId > 0 && $employeeCompanyId !== $shiftCompanyId) {
            return false;
        }

        $roleIds = array_map('intval', $employee['planning_role_ids'] ?? []);

        return $roleIds === [] || in_array((int) ($shift['role_id'] ?? 0), $roleIds, true);
    }

    /** @param array<string,mixed> $employee */
    private function guardEmployeeClaimLeave(
        array $employee,
        Carbon $shiftStart
    ): void
    {
        $employeeId = (int) ($employee['id'] ?? 0);
        $weekStart = $shiftStart->copy()->startOfWeek();
        $weekEnd = $shiftStart->copy()->endOfWeek();
        $date = $shiftStart->toDateString();

        $approvedLeave = array_filter(
            $this->fetchWeeklyLeaveSignals([$employeeId], $weekStart, $weekEnd),
            fn (array $signal): bool => ($signal['date_value'] ?? '') === $date && ($signal['kind'] ?? '') === 'leave-approved'
        );

        if ($approvedLeave !== []) {
            throw new OdooException('You have approved leave on this shift date.');
        }
    }

    public function createShift(array $data): int
    {
        return count($this->createShiftsReturningIds($data));
    }

    /** @return array<int,int> */
    public function createShiftsReturningIds(array $data): array
    {
        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);

        if (! $startField || ! $endField) {
            throw new OdooException('The Odoo planning model does not expose the expected shift datetime fields.');
        }

        $employeeId = isset($data['employee_id']) && is_numeric($data['employee_id'])
            ? (int) $data['employee_id']
            : 0;
        $employee = $employeeId > 0
            ? $this->findById($this->getSelectableEmployees(), $employeeId)
            : null;
        $role = $this->findById($this->getSelectableRoles(), (int) $data['role_id']);
        $company = $this->findById($this->getSelectableCompanies(), (int) $data['company_id']);
        $workLocationId = isset($data['work_location_id']) && is_numeric($data['work_location_id'])
            ? (int) $data['work_location_id']
            : 0;
        $selectableWorkLocations = $this->getSelectableWorkLocations();
        $workLocation = $this->findById($selectableWorkLocations, $workLocationId);
        $isExistingShiftCopy = ($data['_copy_existing_shift'] ?? false) === true;

        if ($employeeId > 0 && ! $employee) {
            throw new OdooException('Please choose a valid employee.');
        }

        if (! $role) {
            throw new OdooException('Please choose a valid planning role.');
        }

        if (! $company) {
            throw new OdooException('Please choose a valid company.');
        }

        if ($employee && (int) ($employee['company_id'] ?? 0) > 0
            && (int) $employee['company_id'] !== (int) $company['id']) {
            throw new OdooException('The selected employee belongs to a different company.');
        }

        $companyHasWorkLocations = (bool) array_filter(
            $selectableWorkLocations,
            fn (array $location): bool => (int) ($location['company_id'] ?? 0) === (int) $company['id']
        );

        if (! $workLocation && $companyHasWorkLocations && ! $isExistingShiftCopy) {
            throw new OdooException('Please choose a valid Odoo work location.');
        }

        if ($workLocation && (int) $workLocation['company_id'] !== (int) $company['id']) {
            throw new OdooException('The selected work location belongs to a different company.');
        }

        if (! isset($fields['ems_work_location_id'])) {
            throw new OdooException('Upgrade hr_employee_weekly_availability in Odoo before scheduling by work location.');
        }

        if ($role['company_id'] && $role['company_id'] !== $company['id']) {
            throw new OdooException('The selected role belongs to a different company.');
        }

        $shiftWindows = $this->buildShiftWindows(
            (string) $data['shift_date'],
            (string) ($data['shift_end_date'] ?? $data['shift_date']),
            (string) $data['start_time'],
            (string) $data['end_time']
        );

        foreach ($shiftWindows as $window) {
            if ($employee) {
                $this->guardShiftConflicts($employee, $startField, $endField, $window['start_at'], $window['end_at']);
            }
        }

        $createdIds = [];

        foreach ($shiftWindows as $window) {
            $payload = $this->buildSlotPayload(
                $fields,
                $employee,
                $role,
                $company,
                $window['start_at'],
                $window['end_at'],
                $data,
                false
            );

            $slotId = $this->serviceAccount->executeKw('planning.slot', 'create', [$payload]);

            if (! is_numeric($slotId) || (int) $slotId < 1) {
                throw new OdooException('Odoo did not confirm the shift creation.');
            }

            $createdIds[] = (int) $slotId;
        }

        return $createdIds;
    }

    public function updateShift(int $slotId, array $data): void
    {
        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);

        if (! $startField || ! $endField) {
            throw new OdooException('The Odoo planning model does not expose the expected shift datetime fields.');
        }

        $existingShift = $this->getShiftForManager($slotId, $startField, $endField);

        if (! $existingShift) {
            throw new OdooException('The selected shift could not be found.');
        }

        $this->guardAgainstStaleShift($existingShift, (string) ($data['last_known_write_date'] ?? ''));

        $employeeId = isset($data['employee_id']) && is_numeric($data['employee_id'])
            ? (int) $data['employee_id']
            : 0;
        $employee = $employeeId > 0
            ? $this->findById($this->getSelectableEmployees(), $employeeId)
            : null;
        $role = $this->findById($this->getSelectableRoles(), (int) $data['role_id']);
        $company = $this->findById($this->getSelectableCompanies(), (int) $data['company_id']);
        $workLocation = $this->findById($this->getSelectableWorkLocations(), (int) ($data['work_location_id'] ?? 0));

        if ($employeeId > 0 && ! $employee) {
            throw new OdooException('Please choose a valid employee.');
        }

        if (! $role) {
            throw new OdooException('Please choose a valid planning role.');
        }

        if (! $company) {
            throw new OdooException('Please choose a valid company.');
        }

        if (! $workLocation) {
            throw new OdooException('Please choose a valid Odoo work location.');
        }

        if ((int) $workLocation['company_id'] !== (int) $company['id']) {
            throw new OdooException('The selected work location belongs to a different company.');
        }

        if (! isset($fields['ems_work_location_id'])) {
            throw new OdooException('Upgrade hr_employee_weekly_availability in Odoo before scheduling by work location.');
        }

        if ($role['company_id'] && $role['company_id'] !== $company['id']) {
            throw new OdooException('The selected role belongs to a different company.');
        }

        $startAt = $this->parseFormDateTime((string) $data['shift_date'], (string) $data['start_time']);
        $endAt = $this->parseFormDateTime((string) $data['shift_date'], (string) $data['end_time']);

        if (! $startAt || ! $endAt) {
            throw new OdooException('Please provide a valid shift date and time range.');
        }

        if (! $endAt->gt($startAt)) {
            throw new OdooException('The shift end time must be later than the start time.');
        }

        if ($employee) {
            $this->guardShiftConflicts($employee, $startField, $endField, $startAt, $endAt, $slotId);
        }

        $payload = $this->buildSlotPayload($fields, $employee, $role, $company, $startAt, $endAt, $data, true);
        $result = $this->serviceAccount->executeKw('planning.slot', 'write', [
            [$slotId],
            $payload,
        ]);

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the shift update.');
        }
    }

    public function deleteShift(int $slotId, string $lastKnownWriteDate = ''): void
    {
        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);

        if (! $startField || ! $endField) {
            throw new OdooException('The Odoo planning model does not expose the expected shift datetime fields.');
        }

        $existingShift = $this->getShiftForManager($slotId, $startField, $endField);

        if (! $existingShift) {
            throw new OdooException('The selected shift could not be found.');
        }

        $this->guardAgainstStaleShift($existingShift, $lastKnownWriteDate);

        $result = $this->serviceAccount->executeKw('planning.slot', 'unlink', [[$slotId]]);

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the shift deletion.');
        }
    }

    /** Return the current Odoo shift snapshot used for concurrency-safe undo journals. */
    public function getShiftSnapshot(int $slotId): ?array
    {
        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);

        return $startField && $endField ? $this->getShiftForManager($slotId, $startField, $endField) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSelectableEmployees(): array
    {
        $fields = $this->employeeFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['resource_id']),
            $this->resolveField($fields, ['company_id']),
            $this->resolveField($fields, ['work_email']),
            $this->resolveField($fields, ['active']),
            $this->resolveField($fields, ['planning_role_ids', 'role_ids']),
            $this->resolveField($fields, ['work_location_id']),
        ])));

        $domain = [];

        if (isset($fields['active'])) {
            $domain[] = ['active', '=', true];
        }

        $records = $this->serviceAccount->executeKw(
            'hr.employee',
            'search_read',
            [$domain],
            [
                'fields' => $requestedFields,
                'order' => 'name asc',
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $resource = $this->extractManyToOne($record['resource_id'] ?? null);
            $company = $this->extractManyToOne($record['company_id'] ?? null);
            $workLocation = $this->extractManyToOne($record['work_location_id'] ?? null);

            return [
                'id' => (int) $record['id'],
                'name' => (string) ($record['name'] ?? 'Employee'),
                'resource_id' => $resource['id'],
                'resource_name' => $resource['name'],
                'company_id' => $company['id'],
                'company' => $company['name'] ?? 'N/A',
                'work_email' => (string) ($record['work_email'] ?? ''),
                'planning_role_ids' => is_array(($record['planning_role_ids'] ?? $record['role_ids'] ?? null))
                    ? array_values(array_filter(array_map('intval', $record['planning_role_ids'] ?? $record['role_ids'])))
                    : [],
                'work_location_id' => $workLocation['id'],
                'work_location' => $workLocation['name'],
            ];
        }, $records)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSelectableRoles(): array
    {
        $fields = $this->roleFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['company_id']),
            $this->resolveField($fields, ['active']),
        ])));

        $domain = [];

        if (isset($fields['active'])) {
            $domain[] = ['active', '=', true];
        }

        $records = $this->serviceAccount->executeKw(
            'planning.role',
            'search_read',
            [$domain],
            [
                'fields' => $requestedFields,
                'order' => 'name asc',
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $company = $this->extractManyToOne($record['company_id'] ?? null);

            return [
                'id' => (int) $record['id'],
                'name' => (string) ($record['name'] ?? 'Role'),
                'company_id' => $company['id'],
                'company' => $company['name'],
            ];
        }, $records)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSelectableCompanies(): array
    {
        $records = $this->serviceAccount->executeKw(
            'res.company',
            'search_read',
            [[]],
            [
                'fields' => ['id', 'name'],
                'order' => 'name asc',
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            return [
                'id' => (int) $record['id'],
                'name' => (string) ($record['name'] ?? 'Company'),
            ];
        }, $records)));
    }

    /** @return array<int,array<string,mixed>> */
    public function getSelectableWorkLocations(): array
    {
        if ($this->workLocations !== null) {
            return $this->workLocations;
        }

        $records = $this->serviceAccount->executeKw('hr.work.location', 'search_read', [[['active', '=', true]]], [
            'fields' => ['id', 'name', 'company_id', 'address_id', 'location_type', 'location_number'],
            'order' => 'company_id asc, name asc',
        ]);
        if (! is_array($records)) {
            return $this->workLocations = [];
        }

        return $this->workLocations = array_values(array_filter(array_map(function(mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) return null;
            $company=$this->extractManyToOne($record['company_id']??null);$address=$this->extractManyToOne($record['address_id']??null);
            return ['id'=>(int)$record['id'],'name'=>(string)($record['name']??'Work Location'),'company_id'=>$company['id'],'company'=>$company['name'],'address_id'=>$address['id'],'address'=>$address['name'],'location_type'=>(string)($record['location_type']??'office'),'location_number'=>(string)($record['location_number']??'')];
        },$records)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRecentShifts(Carbon $month, ?Carbon $rangeAnchor = null): array
    {
        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);

        if (! $startField || ! $endField) {
            return [];
        }

        $rangeStart = $month->copy()->startOfMonth();
        $rangeEnd = $month->copy()->endOfMonth()->endOfDay();

        if ($rangeAnchor) {
            $rangeStart = $rangeStart->min($rangeAnchor->copy()->startOfWeek());
            $rangeEnd = $rangeEnd->max($this->visiblePeriodEnd($rangeAnchor)->endOfDay());
        }

        $records = $this->serviceAccount->executeKw(
            'planning.slot',
            'search_read',
            [[
                [$startField, '>=', $rangeStart->toDateTimeString()],
                [$startField, '<=', $rangeEnd->toDateTimeString()],
            ]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $startField,
                    $endField,
                    'write_date',
                    $this->resolveField($fields, ['name']),
                    $this->resolveField($fields, ['note']),
                    $this->resolveField($fields, ['role_id']),
                    $this->resolveField($fields, ['company_id']),
                    $this->resolveField($fields, ['employee_id']),
                    $this->resolveField($fields, ['ems_work_location_id']),
                    ...array_values(array_intersect(['ems_work_location_id','ems_publish_state','ems_published_at','ems_published_by','ems_requires_confirmation','ems_confirmation_status','ems_confirmation_note','ems_confirmation_responded_at','ems_confirmation_responded_by','ems_was_open_shift_claim','ems_claimed_at','ems_claimed_by','ems_notification_mode','ems_notification_status','ems_notification_sent_at','ems_reminder_sent_at','ems_notification_error'], array_keys($fields))),
                ]))),
                'order' => $startField.' asc',
                'limit' => 500,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $record) => is_array($record) ? $this->normalizeShift($record, $startField, $endField) : null,
            $records
        )));
    }

    /**
     * @param  array<string, mixed>  $employee
     */
    private function guardShiftConflicts(
        array $employee,
        string $startField,
        string $endField,
        Carbon $startAt,
        Carbon $endAt,
        ?int $ignoreSlotId = null
    ): void {
        $fields = $this->planningFields();
        $identityField = null;
        $identityValue = null;

        if ($employee['resource_id'] && isset($fields['resource_id'])) {
            $identityField = 'resource_id';
            $identityValue = $employee['resource_id'];
        } elseif (isset($fields['employee_id'])) {
            $identityField = 'employee_id';
            $identityValue = $employee['id'];
        }

        if (! $identityField || ! $identityValue) {
            throw new OdooException('The selected employee cannot be scheduled because no Odoo planning identity is available.');
        }

        $domain = [
            [$identityField, '=', $identityValue],
            [$startField, '<', $this->toOdooDateTime($endAt)],
            [$endField, '>', $this->toOdooDateTime($startAt)],
        ];

        if ($ignoreSlotId) {
            $domain[] = ['id', '!=', $ignoreSlotId];
        }

        $overlapCount = $this->serviceAccount->executeKw(
            'planning.slot',
            'search_count',
            [$domain]
        );

        if (is_numeric($overlapCount) && (int) $overlapCount > 0) {
            throw new OdooException('This employee already has a shift that overlaps with the selected time.');
        }
    }

    private function planningFields(): array
    {
        if ($this->planningFields !== null) {
            return $this->planningFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'planning.slot',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->planningFields = is_array($fields) ? $fields : [];

        return $this->planningFields;
    }

    private function leaveFields(): array
    {
        if ($this->leaveFields !== null) {
            return $this->leaveFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.leave',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->leaveFields = is_array($fields) ? $fields : [];

        return $this->leaveFields;
    }

    private function weeklyAvailabilityFields(): array
    {
        if ($this->weeklyAvailabilityFields !== null) {
            return $this->weeklyAvailabilityFields;
        }

        try {
            $fields = $this->serviceAccount->executeKw(
                'hr.employee.weekly.availability',
                'fields_get',
                [],
                [
                    'attributes' => ['string', 'type', 'relation'],
                ]
            );
        } catch (OdooException) {
            $this->weeklyAvailabilityFields = [];

            return $this->weeklyAvailabilityFields;
        }

        $this->weeklyAvailabilityFields = is_array($fields) ? $fields : [];

        return $this->weeklyAvailabilityFields;
    }

    private function employeeFields(): array
    {
        if ($this->employeeFields !== null) {
            return $this->employeeFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.employee',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->employeeFields = is_array($fields) ? $fields : [];

        return $this->employeeFields;
    }

    private function roleFields(): array
    {
        if ($this->roleFields !== null) {
            return $this->roleFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'planning.role',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->roleFields = is_array($fields) ? $fields : [];

        return $this->roleFields;
    }

    private function resolveField(array $fields, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($fields[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function findById(array $items, int $id): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function parseFormDateTime(string $date, string $time): ?Carbon
    {
        try {
            return Carbon::createFromFormat(
                'Y-m-d H:i',
                $date.' '.$time,
                config('app.timezone')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{start_at:Carbon,end_at:Carbon}>
     */
    private function buildShiftWindows(
        string $startDate,
        string $endDate,
        string $startTime,
        string $endTime
    ): array {
        $rangeStart = $this->parseFormDate($startDate);
        $rangeEnd = $this->parseFormDate($endDate);

        if (! $rangeStart || ! $rangeEnd) {
            throw new OdooException('Please provide a valid shift date or date range.');
        }

        if ($rangeEnd->lt($rangeStart)) {
            throw new OdooException('The shift end date must be the same as or later than the start date.');
        }

        $windows = [];
        $cursor = $rangeStart->copy();

        while ($cursor->lte($rangeEnd)) {
            $startAt = $this->parseFormDateTime($cursor->format('Y-m-d'), $startTime);
            $endAt = $this->parseFormDateTime($cursor->format('Y-m-d'), $endTime);

            if (! $startAt || ! $endAt) {
                throw new OdooException('Please provide a valid shift date and time range.');
            }

            if (! $endAt->gt($startAt)) {
                throw new OdooException('The shift end time must be later than the start time.');
            }

            $windows[] = [
                'start_at' => $startAt,
                'end_at' => $endAt,
            ];

            $cursor->addDay();
        }

        return $windows;
    }

    private function parseFormDate(string $date): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function toOdooDateTime(Carbon $dateTime): string
    {
        return $dateTime->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateValue(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            try {
                return Carbon::parse($value, config('app.timezone'))->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function formatFloatHour(float $value): string
    {
        $hours = (int) floor($value);
        $minutes = (int) round(($value - $hours) * 60);

        if ($minutes === 60) {
            $hours++;
            $minutes = 0;
        }

        $period = $hours >= 12 ? 'PM' : 'AM';
        $displayHour = $hours % 12;
        $displayHour = $displayHour === 0 ? 12 : $displayHour;

        return sprintf('%02d:%02d %s', $displayHour, $minutes, $period);
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $employee
     * @param  array<string, mixed>  $role
     * @param  array<string, mixed>  $company
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildSlotPayload(
        array $fields,
        ?array $employee,
        array $role,
        array $company,
        Carbon $startAt,
        Carbon $endAt,
        array $data,
        bool $clearEmployee = false
    ): array {
        $payload = [
            $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']) => $this->toOdooDateTime($startAt),
            $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']) => $this->toOdooDateTime($endAt),
        ];

        if (isset($fields['employee_id']) && $employee) {
            $payload['employee_id'] = $employee['id'];
        } elseif ($clearEmployee && isset($fields['employee_id'])) {
            $payload['employee_id'] = false;
        }

        if (isset($fields['resource_id']) && $employee && $employee['resource_id']) {
            $payload['resource_id'] = $employee['resource_id'];
        } elseif ($clearEmployee && isset($fields['resource_id'])) {
            $payload['resource_id'] = false;
        }

        if (isset($fields['role_id'])) {
            $payload['role_id'] = $role['id'];
        }

        if (isset($fields['company_id'])) {
            $payload['company_id'] = $company['id'];
        }

        if (isset($fields['ems_work_location_id'])) {
            $workLocationId = isset($data['work_location_id']) && is_numeric($data['work_location_id'])
                ? (int) $data['work_location_id']
                : 0;
            $payload['ems_work_location_id'] = $workLocationId > 0 ? $workLocationId : false;
        }

        if (isset($fields['allocated_hours'])) {
            $payload['allocated_hours'] = round($startAt->diffInMinutes($endAt) / 60, 2);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));

        if ($title !== '' && isset($fields['name'])) {
            $payload['name'] = $title;
        } elseif (isset($fields['name'])) {
            $payload['name'] = $employee
                ? $role['name'].' - '.$employee['name']
                : $role['name'].' - Open Shift';
        }

        if (isset($fields['note'])) {
            $payload['note'] = $note;
        }

        return array_filter($payload, fn (mixed $value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>|null
     */
    private function normalizeShift(array $record, string $startField, string $endField): ?array
    {
        $startAt = $this->parseDateTime($record[$startField] ?? null);
        $endAt = $this->parseDateTime($record[$endField] ?? null);

        if (! $startAt || ! $endAt) {
            return null;
        }

        $role = $this->extractManyToOne($record['role_id'] ?? null);
        $company = $this->extractManyToOne($record['company_id'] ?? null);
        $employee = $this->extractManyToOne($record['employee_id'] ?? null);
        $workLocation = $this->extractManyToOne($record['ems_work_location_id'] ?? null);
        $writeDate = isset($record['write_date']) && is_string($record['write_date']) ? $record['write_date'] : '';

        return [
            'id' => (int) ($record['id'] ?? 0),
            'title' => (string) ($record['name'] ?? $role['name'] ?? 'Shift'),
            'title_value' => (string) ($record['name'] ?? ''),
            'note' => (string) ($record['note'] ?? ''),
            'employee' => $employee['name'] ?? 'Unassigned',
            'employee_id' => $employee['id'],
            'role' => $role['name'] ?? 'Unassigned',
            'role_id' => $role['id'],
            'company' => $company['name'] ?? 'N/A',
            'company_id' => $company['id'],
            'work_location' => $workLocation['name'] ?? 'No work location',
            'work_location_id' => $workLocation['id'],
            'date_value' => $startAt->toDateString(),
            'date_label' => $startAt->format('D, d M Y'),
            'time_label' => $startAt->format('h:i A').' - '.$endAt->format('h:i A'),
            'shift_date_value' => $startAt->format('Y-m-d'),
            'start_time_value' => $startAt->format('H:i'),
            'end_time_value' => $endAt->format('H:i'),
            'duration_minutes' => $startAt->diffInMinutes($endAt),
            'duration_label' => $this->formatMinutesAsHours($startAt->diffInMinutes($endAt)),
            'tone' => $this->shiftTone($role['name'] ?? $record['name'] ?? 'Shift'),
            'write_date_value' => $writeDate,
            'updated_label' => $writeDate !== '' ? ($this->parseDateTime($writeDate)?->format('d M Y h:i A') ?? 'N/A') : 'N/A',
            'start_at' => $startAt,
            'end_at' => $endAt,
            '_odoo_schedule_meta' => array_intersect_key($record, array_flip(['ems_publish_state','ems_published_at','ems_published_by','ems_requires_confirmation','ems_confirmation_status','ems_confirmation_note','ems_confirmation_responded_at','ems_confirmation_responded_by','ems_was_open_shift_claim','ems_claimed_at','ems_claimed_by','ems_notification_mode','ems_notification_status','ems_notification_sent_at','ems_reminder_sent_at','ems_notification_error'])),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @param  array<int, array<string, mixed>>  $shifts
     * @return array<string, mixed>
     */
    private function buildWeeklyRoster(Carbon $selectedDate, array $employees, array $shifts, array $timeOff = []): array
    {
        $weekStart = $selectedDate->copy()->startOfWeek();
        $weekEnd = $this->visiblePeriodEnd($selectedDate);
        $weekDates = [];
        $shiftsByEmployeeAndDate = [];
        $weekShifts = [];
        $employeeNamesFromShifts = [];
        $dayMinutes = [];
        $dayShiftCounts = [];

        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $dateValue = $cursor->toDateString();
            $weekDates[] = $dateValue;
            $dayMinutes[$dateValue] = 0;
            $dayShiftCounts[$dateValue] = 0;
            $cursor->addDay();
        }

        foreach ($shifts as $shift) {
            $dateValue = (string) ($shift['date_value'] ?? '');

            if ($dateValue === '' || ! in_array($dateValue, $weekDates, true)) {
                continue;
            }

            $employeeId = isset($shift['employee_id']) && is_numeric($shift['employee_id'])
                ? (int) $shift['employee_id']
                : 0;
            $minutes = $this->shiftDurationMinutes($shift);

            $shiftsByEmployeeAndDate[$employeeId][$dateValue][] = $shift;
            $weekShifts[] = $shift;
            $dayMinutes[$dateValue] += $minutes;
            $dayShiftCounts[$dateValue]++;

            if ($employeeId > 0) {
                $employeeNamesFromShifts[$employeeId] = [
                    'id' => $employeeId,
                    'name' => (string) ($shift['employee'] ?? 'Employee'),
                    'company' => (string) ($shift['company'] ?? 'N/A'),
                    'company_id' => $shift['company_id'] ?? null,
                    'work_email' => '',
                ];
            }
        }

        $days = [];
        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $dateValue = $cursor->toDateString();
            $dayTimeOff = $timeOff['days'][$dateValue] ?? [
                'approved_leave_count' => 0,
                'pending_leave_count' => 0,
                'unavailable_count' => 0,
                'entries' => [],
            ];
            $days[] = [
                'date' => $cursor->copy(),
                'date_value' => $dateValue,
                'weekday' => $cursor->format('D'),
                'day_number' => $cursor->format('d'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $cursor->isSameDay($selectedDate),
                'shift_count' => $dayShiftCounts[$dateValue],
                'hours_label' => $this->formatMinutesAsHours($dayMinutes[$dateValue]),
                'time_off' => $dayTimeOff,
            ];
            $cursor->addDay();
        }

        $rows = [];
        $seenEmployeeIds = [];

        if (isset($shiftsByEmployeeAndDate[0])) {
            $rows[] = $this->buildRosterRow([
                'id' => 0,
                'name' => 'Open Shifts',
                'company' => 'Unassigned',
                'company_id' => null,
                'work_email' => '',
                'is_open' => true,
            ], $days, $shiftsByEmployeeAndDate[0]);
        }

        foreach ($employees as $employee) {
            if (! isset($employee['id']) || ! is_numeric($employee['id'])) {
                continue;
            }

            $employeeId = (int) $employee['id'];
            $rows[] = $this->buildRosterRow(
                $employee,
                $days,
                $shiftsByEmployeeAndDate[$employeeId] ?? [],
                $timeOff['by_employee_date'][$employeeId] ?? []
            );
            $seenEmployeeIds[$employeeId] = true;
        }

        foreach ($employeeNamesFromShifts as $employeeId => $employee) {
            if (isset($seenEmployeeIds[$employeeId])) {
                continue;
            }

            $rows[] = $this->buildRosterRow(
                $employee,
                $days,
                $shiftsByEmployeeAndDate[$employeeId] ?? [],
                $timeOff['by_employee_date'][$employeeId] ?? []
            );
        }

        $scheduledPeople = array_values(array_unique(array_filter(array_map(
            fn (array $shift): ?int => isset($shift['employee_id']) && is_numeric($shift['employee_id'])
                ? (int) $shift['employee_id']
                : null,
            $weekShifts
        ))));
        $totalMinutes = array_sum(array_map(fn (array $shift): int => $this->shiftDurationMinutes($shift), $weekShifts));
        $openShiftCount = count($shiftsByEmployeeAndDate[0] ?? []);
        $publishedShiftCount = count(array_filter($weekShifts, fn (array $shift): bool => (bool) ($shift['is_published'] ?? false)));
        $updatedShiftCount = count(array_filter($weekShifts, fn (array $shift): bool => (bool) ($shift['was_published'] ?? false)));
        $unpublishedShiftCount = count($weekShifts) - $publishedShiftCount;
        $confirmationShiftCount = count(array_filter(
            $weekShifts,
            fn (array $shift): bool => (bool) ($shift['is_published'] ?? false) && (bool) ($shift['requires_confirmation'] ?? false)
        ));
        $insights = $this->buildRosterInsights($days, $rows, $weekShifts, $dayShiftCounts, $dayMinutes);

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_label' => $this->formatWeekLabel($weekStart, $weekEnd),
            'previous_week_day' => $weekStart->copy()->subWeeks(2),
            'next_week_day' => $weekStart->copy()->addWeeks(2),
            'days' => $days,
            'rows' => $rows,
            'summary' => [
                'shift_count' => count($weekShifts),
                'scheduled_hours' => $this->formatMinutesAsHours($totalMinutes),
                'people_scheduled' => count($scheduledPeople),
                'open_shifts' => $openShiftCount,
                'published_shifts' => $publishedShiftCount,
                'unpublished_shifts' => $unpublishedShiftCount,
                'updated_shifts' => $updatedShiftCount,
                'confirmation_shifts' => $confirmationShiftCount,
                'approved_leave' => (int) ($timeOff['summary']['approved_leave'] ?? 0),
                'pending_leave' => (int) ($timeOff['summary']['pending_leave'] ?? 0),
                'unavailable_people' => (int) ($timeOff['summary']['unavailable_people'] ?? 0),
                'coverage_days' => count(array_filter($dayShiftCounts)),
                'average_shift' => count($weekShifts) > 0
                    ? $this->formatMinutesAsHours((int) round($totalMinutes / count($weekShifts)))
                    : '0h',
                'busiest_day' => $insights['busiest_day'],
                'unscheduled_people' => $insights['unscheduled_people'],
                'overtime_risks' => $insights['overtime_risks'],
                'long_shifts' => $insights['long_shifts'],
            ],
            'alerts' => $insights['alerts'],
            'role_breakdown' => $insights['role_breakdown'],
            'company_breakdown' => $insights['company_breakdown'],
            'shift_templates' => $insights['shift_templates'],
            'time_off_days' => $timeOff['days'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $employee
     * @param  array<int, array<string, mixed>>  $days
     * @param  array<string, array<int, array<string, mixed>>>  $shiftsByDate
     * @return array<string, mixed>
     */
    private function buildRosterRow(array $employee, array $days, array $shiftsByDate, array $timeOffByDate = []): array
    {
        $cells = [];
        $totalMinutes = 0;
        $shiftCount = 0;
        $timeOffCount = 0;

        foreach ($days as $day) {
            $dateValue = (string) $day['date_value'];
            $shifts = $shiftsByDate[$dateValue] ?? [];
            $timeOffEntries = $timeOffByDate[$dateValue] ?? [];
            $minutes = array_sum(array_map(fn (array $shift): int => $this->shiftDurationMinutes($shift), $shifts));

            $cells[$dateValue] = [
                'date_value' => $dateValue,
                'shifts' => $shifts,
                'time_off' => $timeOffEntries,
                'shift_count' => count($shifts),
                'hours_label' => $this->formatMinutesAsHours($minutes),
            ];

            $totalMinutes += $minutes;
            $shiftCount += count($shifts);
            $timeOffCount += count($timeOffEntries);
        }

        $name = (string) ($employee['name'] ?? 'Employee');

        return [
            'employee_id' => isset($employee['id']) && is_numeric($employee['id']) ? (int) $employee['id'] : 0,
            'employee' => $name,
            'company' => (string) ($employee['company'] ?? 'N/A'),
            'company_id' => isset($employee['company_id']) && is_numeric($employee['company_id']) ? (int) $employee['company_id'] : null,
            'work_location_id' => isset($employee['work_location_id']) && is_numeric($employee['work_location_id']) ? (int) $employee['work_location_id'] : null,
            'work_location' => (string) ($employee['work_location'] ?? ''),
            'work_email' => (string) ($employee['work_email'] ?? ''),
            'initials' => $this->initials($name),
            'is_open' => (bool) ($employee['is_open'] ?? false),
            'cells' => $cells,
            'shift_count' => $shiftCount,
            'time_off_count' => $timeOffCount,
            'scheduled_minutes' => $totalMinutes,
            'scheduled_hours' => $this->formatMinutesAsHours($totalMinutes),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $roles
     * @param  array<int, array<string, mixed>>  $shifts
     * @return array<string, mixed>
     */
    private function buildWeeklyAreaBoard(Carbon $selectedDate, array $roles, array $shifts): array
    {
        $weekStart = $selectedDate->copy()->startOfWeek();
        $weekEnd = $this->visiblePeriodEnd($selectedDate);
        $days = [];
        $cursor = $weekStart->copy();
        $shiftsByRoleAndDate = [];
        $roleNamesFromShifts = [];

        while ($cursor->lte($weekEnd)) {
            $days[] = [
                'date' => $cursor->copy(),
                'date_value' => $cursor->toDateString(),
                'weekday' => $cursor->format('D'),
                'day_number' => $cursor->format('d'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $cursor->isSameDay($selectedDate),
            ];
            $cursor->addDay();
        }

        foreach ($shifts as $shift) {
            $startAt = $shift['start_at'] ?? null;

            if (! ($startAt instanceof Carbon) || ! $startAt->copy()->startOfDay()->betweenIncluded($weekStart, $weekEnd)) {
                continue;
            }

            $roleId = isset($shift['role_id']) && is_numeric($shift['role_id']) ? (int) $shift['role_id'] : 0;
            $dateValue = (string) ($shift['date_value'] ?? '');

            if ($roleId === 0 || $dateValue === '') {
                continue;
            }

            $shiftsByRoleAndDate[$roleId][$dateValue][] = $shift;

            if (! isset($roleNamesFromShifts[$roleId])) {
                $roleNamesFromShifts[$roleId] = [
                    'id' => $roleId,
                    'name' => (string) ($shift['role'] ?? 'Role'),
                    'company_id' => $shift['company_id'] ?? null,
                    'company' => (string) ($shift['company'] ?? 'N/A'),
                ];
            }
        }

        $rows = [];
        $seenRoleIds = [];

        foreach ($roles as $role) {
            if (! isset($role['id']) || ! is_numeric($role['id'])) {
                continue;
            }

            $roleId = (int) $role['id'];
            $rows[] = $this->buildAreaRow($role, $days, $shiftsByRoleAndDate[$roleId] ?? []);
            $seenRoleIds[$roleId] = true;
        }

        foreach ($roleNamesFromShifts as $roleId => $role) {
            if (isset($seenRoleIds[$roleId])) {
                continue;
            }

            $rows[] = $this->buildAreaRow($role, $days, $shiftsByRoleAndDate[$roleId] ?? []);
        }

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_label' => $this->formatWeekLabel($weekStart, $weekEnd),
            'previous_week_day' => $weekStart->copy()->subWeeks(2),
            'next_week_day' => $weekStart->copy()->addWeeks(2),
            'days' => $days,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $role
     * @param  array<int, array<string, mixed>>  $days
     * @param  array<string, array<int, array<string, mixed>>>  $shiftsByDate
     * @return array<string, mixed>
     */
    private function buildAreaRow(array $role, array $days, array $shiftsByDate): array
    {
        $cells = [];
        $totalMinutes = 0;
        $shiftCount = 0;
        $openShiftCount = 0;

        foreach ($days as $day) {
            $dateValue = (string) $day['date_value'];
            $shifts = $shiftsByDate[$dateValue] ?? [];
            $minutes = array_sum(array_map(fn (array $shift): int => $this->shiftDurationMinutes($shift), $shifts));
            $assignedCount = count(array_filter($shifts, fn (array $shift): bool => ! empty($shift['employee_id'])));
            $openCount = count($shifts) - $assignedCount;

            $cells[$dateValue] = [
                'date_value' => $dateValue,
                'shifts' => $shifts,
                'shift_count' => count($shifts),
                'assigned_count' => $assignedCount,
                'open_count' => $openCount,
                'hours_label' => $this->formatMinutesAsHours($minutes),
            ];

            $totalMinutes += $minutes;
            $shiftCount += count($shifts);
            $openShiftCount += $openCount;
        }

        return [
            'role_id' => isset($role['id']) && is_numeric($role['id']) ? (int) $role['id'] : 0,
            'role' => (string) ($role['name'] ?? 'Role'),
            'company_id' => isset($role['company_id']) && is_numeric($role['company_id']) ? (int) $role['company_id'] : null,
            'company' => (string) ($role['company'] ?? 'N/A'),
            'tone' => $this->shiftTone((string) ($role['name'] ?? 'Role')),
            'cells' => $cells,
            'shift_count' => $shiftCount,
            'open_shift_count' => $openShiftCount,
            'scheduled_hours' => $this->formatMinutesAsHours($totalMinutes),
        ];
    }

    /**
     * @param  array<string, mixed>  $shift
     */
    private function shiftDurationMinutes(array $shift): int
    {
        if (isset($shift['duration_minutes']) && is_numeric($shift['duration_minutes'])) {
            return max(0, (int) $shift['duration_minutes']);
        }

        if (($shift['start_at'] ?? null) instanceof Carbon && ($shift['end_at'] ?? null) instanceof Carbon) {
            return max(0, $shift['start_at']->diffInMinutes($shift['end_at']));
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $weekShifts
     * @param  array<string, int>  $dayShiftCounts
     * @param  array<string, int>  $dayMinutes
     * @return array<string, mixed>
     */
    private function buildRosterInsights(
        array $days,
        array $rows,
        array $weekShifts,
        array $dayShiftCounts,
        array $dayMinutes
    ): array {
        $roleStats = [];
        $companyStats = [];
        $longShiftCount = 0;
        $openShiftCount = 0;

        foreach ($weekShifts as $shift) {
            $minutes = $this->shiftDurationMinutes($shift);
            $roleId = isset($shift['role_id']) && is_numeric($shift['role_id']) ? (int) $shift['role_id'] : 0;
            $roleName = trim((string) ($shift['role'] ?? 'Unassigned')) ?: 'Unassigned';
            $companyId = isset($shift['company_id']) && is_numeric($shift['company_id']) ? (int) $shift['company_id'] : 0;
            $companyName = trim((string) ($shift['company'] ?? 'N/A')) ?: 'N/A';

            $roleStats[$roleId] ??= [
                'id' => $roleId,
                'name' => $roleName,
                'shift_count' => 0,
                'minutes' => 0,
                'tone' => $this->shiftTone($roleName),
            ];
            $roleStats[$roleId]['shift_count']++;
            $roleStats[$roleId]['minutes'] += $minutes;

            $companyStats[$companyId] ??= [
                'id' => $companyId,
                'name' => $companyName,
                'shift_count' => 0,
                'minutes' => 0,
            ];
            $companyStats[$companyId]['shift_count']++;
            $companyStats[$companyId]['minutes'] += $minutes;

            if ($minutes >= 600) {
                $longShiftCount++;
            }

            if (empty($shift['employee_id'])) {
                $openShiftCount++;
            }
        }

        $unscheduledPeople = count(array_filter(
            $rows,
            fn (array $row): bool => ! ($row['is_open'] ?? false) && (int) ($row['shift_count'] ?? 0) === 0
        ));
        $overtimeRisks = count(array_filter(
            $rows,
            fn (array $row): bool => ! ($row['is_open'] ?? false) && (int) ($row['scheduled_minutes'] ?? 0) > 2400
        ));
        $coveredDays = count(array_filter($dayShiftCounts));
        $alerts = [];

        if ($openShiftCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-inbox',
                'title' => $openShiftCount.' open shift'.($openShiftCount === 1 ? '' : 's'),
                'message' => 'Assign these before publishing the roster to employees.',
            ];
        }

        $unpublishedShiftCount = count(array_filter($weekShifts, fn (array $shift): bool => ! ($shift['is_published'] ?? false)));

        if ($unpublishedShiftCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-bullhorn',
                'title' => $unpublishedShiftCount.' unpublished shift'.($unpublishedShiftCount === 1 ? '' : 's'),
                'message' => 'Publish this visible week after your edits are complete.',
            ];
        }

        if ($overtimeRisks > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'fa-exclamation-triangle',
                'title' => $overtimeRisks.' overtime risk'.($overtimeRisks === 1 ? '' : 's'),
                'message' => 'One or more team members are scheduled above 40 hours this week.',
            ];
        }

        if ($longShiftCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-hourglass-half',
                'title' => $longShiftCount.' long shift'.($longShiftCount === 1 ? '' : 's'),
                'message' => 'Review shifts of 10 hours or more for fatigue and break coverage.',
            ];
        }

        if ($unscheduledPeople > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-user-clock',
                'title' => $unscheduledPeople.' unscheduled team member'.($unscheduledPeople === 1 ? '' : 's'),
                'message' => 'Use filters or quick add to fill coverage gaps.',
            ];
        }

        if ($coveredDays < 7 && count($weekShifts) > 0) {
            $uncoveredDays = 7 - $coveredDays;
            $alerts[] = [
                'type' => 'info',
                'icon' => 'fa-calendar-day',
                'title' => $uncoveredDays.' uncovered day'.($uncoveredDays === 1 ? '' : 's'),
                'message' => 'Some days in this week have no scheduled shifts.',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'type' => 'success',
                'icon' => 'fa-check-circle',
                'title' => 'Roster checks clear',
                'message' => 'No open shifts, long shifts, or overtime warnings were found.',
            ];
        }

        return [
            'alerts' => $alerts,
            'role_breakdown' => $this->formatBreakdownRows($roleStats),
            'company_breakdown' => $this->formatBreakdownRows($companyStats),
            'shift_templates' => $this->buildShiftTemplates($weekShifts),
            'busiest_day' => $this->resolveBusiestDayLabel($days, $dayShiftCounts, $dayMinutes),
            'unscheduled_people' => $unscheduledPeople,
            'overtime_risks' => $overtimeRisks,
            'long_shifts' => $longShiftCount,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     * @return array<int, array<string, mixed>>
     */
    private function formatBreakdownRows(array $stats): array
    {
        usort($stats, fn (array $first, array $second): int => $second['minutes'] <=> $first['minutes']);
        $maxMinutes = max(array_map(fn (array $row): int => (int) $row['minutes'], $stats) ?: [0]);

        return array_map(function (array $row) use ($maxMinutes): array {
            $minutes = (int) $row['minutes'];

            return $row + [
                'hours_label' => $this->formatMinutesAsHours($minutes),
                'share' => $maxMinutes > 0 ? max(8, (int) round(($minutes / $maxMinutes) * 100)) : 0,
            ];
        }, array_slice($stats, 0, 6));
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     * @param  array<string, int>  $dayShiftCounts
     * @param  array<string, int>  $dayMinutes
     */
    private function resolveBusiestDayLabel(array $days, array $dayShiftCounts, array $dayMinutes): string
    {
        $bestDate = '';
        $bestShiftCount = 0;
        $bestMinutes = 0;

        foreach ($dayShiftCounts as $dateValue => $shiftCount) {
            $minutes = $dayMinutes[$dateValue] ?? 0;

            if ($shiftCount > $bestShiftCount || ($shiftCount === $bestShiftCount && $minutes > $bestMinutes)) {
                $bestDate = $dateValue;
                $bestShiftCount = $shiftCount;
                $bestMinutes = $minutes;
            }
        }

        if ($bestDate === '' || $bestShiftCount === 0) {
            return 'No shifts';
        }

        foreach ($days as $day) {
            if (($day['date_value'] ?? '') === $bestDate && ($day['date'] ?? null) instanceof Carbon) {
                return $day['date']->format('D, M j').' ('.$bestShiftCount.')';
            }
        }

        return $bestDate.' ('.$bestShiftCount.')';
    }

    /**
     * @param  array<int, array<string, mixed>>  $weekShifts
     * @return array<int, array<string, mixed>>
     */
    private function buildShiftTemplates(array $weekShifts): array
    {
        $templates = [];

        foreach ($weekShifts as $shift) {
            $key = implode('|', [
                $shift['role_id'] ?? 0,
                $shift['company_id'] ?? 0,
                $shift['work_location_id'] ?? 0,
                $shift['start_time_value'] ?? '',
                $shift['end_time_value'] ?? '',
            ]);

            if (! isset($templates[$key])) {
                $templates[$key] = [
                    'title' => (string) ($shift['role'] ?? 'Shift'),
                    'role_id' => $shift['role_id'] ?? '',
                    'company_id' => $shift['company_id'] ?? '',
                    'work_location_id' => $shift['work_location_id'] ?? '',
                    'start_time' => (string) ($shift['start_time_value'] ?? ''),
                    'end_time' => (string) ($shift['end_time_value'] ?? ''),
                    'note' => (string) ($shift['note'] ?? ''),
                    'time_label' => (string) ($shift['time_label'] ?? ''),
                    'count' => 0,
                    'tone' => (string) ($shift['tone'] ?? 'green'),
                ];
            }

            $templates[$key]['count']++;
        }

        usort($templates, fn (array $first, array $second): int => $second['count'] <=> $first['count']);

        return array_slice(array_values($templates), 0, 6);
    }

    private function formatMinutesAsHours(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours.'h';
        }

        if ($hours === 0) {
            return $remainingMinutes.'m';
        }

        return $hours.'h '.$remainingMinutes.'m';
    }

    private function formatWeekLabel(Carbon $weekStart, Carbon $weekEnd): string
    {
        if ($weekStart->isSameMonth($weekEnd)) {
            return $weekStart->format('M j').' - '.$weekEnd->format('j, Y');
        }

        return $weekStart->format('M j').' - '.$weekEnd->format('M j, Y');
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= strtoupper(substr($part, 0, 1));
            }

            if (strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'OS';
    }

    private function shiftTone(string $seed): string
    {
        $tones = ['green', 'blue', 'amber', 'mint', 'slate'];
        $index = abs(crc32($seed)) % count($tones);

        return $tones[$index];
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array{
     *     days:array<string, array<string, mixed>>,
     *     by_employee_date:array<int, array<string, array<int, array<string, mixed>>>>,
     *     summary:array<string, int>
     * }
     */
    private function buildWeeklyTimeOffData(Carbon $selectedDate, array $employees): array
    {
        $weekStart = $selectedDate->copy()->startOfWeek();
        $weekEnd = $this->visiblePeriodEnd($selectedDate);
        $employeeIds = array_values(array_filter(array_map(
            fn (array $employee): ?int => isset($employee['id']) && is_numeric($employee['id']) ? (int) $employee['id'] : null,
            $employees
        )));

        $days = [];
        $byEmployeeDate = [];
        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $days[$cursor->toDateString()] = [
                'approved_leave_count' => 0,
                'pending_leave_count' => 0,
                'unavailable_count' => 0,
                'entries' => [],
            ];
            $cursor->addDay();
        }

        if ($employeeIds === []) {
            return [
                'days' => $days,
                'by_employee_date' => [],
                'summary' => [
                    'approved_leave' => 0,
                    'pending_leave' => 0,
                    'unavailable_people' => 0,
                ],
            ];
        }

        $approvedLeaveEmployees = [];
        $pendingLeaveEmployees = [];
        $unavailableEmployees = [];

        foreach ($this->fetchWeeklyLeaveSignals($employeeIds, $weekStart, $weekEnd) as $signal) {
            $dateValue = (string) ($signal['date_value'] ?? '');
            $employeeId = isset($signal['employee_id']) && is_numeric($signal['employee_id']) ? (int) $signal['employee_id'] : 0;

            if ($dateValue === '' || $employeeId === 0 || ! isset($days[$dateValue])) {
                continue;
            }

            $days[$dateValue]['entries'][] = $signal;
            $byEmployeeDate[$employeeId][$dateValue][] = $signal;

            if (($signal['kind'] ?? '') === 'leave-approved') {
                $days[$dateValue]['approved_leave_count']++;
                $approvedLeaveEmployees[$employeeId] = true;
            } elseif (($signal['kind'] ?? '') === 'leave-pending') {
                $days[$dateValue]['pending_leave_count']++;
                $pendingLeaveEmployees[$employeeId] = true;
            }
        }

        foreach ($this->fetchWeeklyAvailabilitySignals($employeeIds, $weekStart, $weekEnd) as $signal) {
            $dateValue = (string) ($signal['date_value'] ?? '');
            $employeeId = isset($signal['employee_id']) && is_numeric($signal['employee_id']) ? (int) $signal['employee_id'] : 0;

            if ($dateValue === '' || $employeeId === 0 || ! isset($days[$dateValue])) {
                continue;
            }

            $days[$dateValue]['entries'][] = $signal;
            $byEmployeeDate[$employeeId][$dateValue][] = $signal;
            $days[$dateValue]['unavailable_count']++;
            $unavailableEmployees[$employeeId] = true;
        }

        return [
            'days' => $days,
            'by_employee_date' => $byEmployeeDate,
            'summary' => [
                'approved_leave' => count($approvedLeaveEmployees),
                'pending_leave' => count($pendingLeaveEmployees),
                'unavailable_people' => count($unavailableEmployees),
            ],
        ];
    }

    private function visiblePeriodEnd(Carbon $selectedDate): Carbon
    {
        return $selectedDate->copy()->startOfWeek()->addDays(self::VISIBLE_PERIOD_DAYS - 1);
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchWeeklyLeaveSignals(array $employeeIds, Carbon $weekStart, Carbon $weekEnd): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $fields = $this->leaveFields();
        $records = $this->serviceAccount->executeKw(
            'hr.leave',
            'search_read',
            [[
                ['employee_id', 'in', $employeeIds],
                ['state', 'in', ['confirm', 'validate1', 'validate']],
                ['request_date_from', '<=', $weekEnd->toDateString()],
                ['request_date_to', '>=', $weekStart->toDateString()],
            ]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $this->resolveField($fields, ['employee_id']),
                    $this->resolveField($fields, ['holiday_status_id']),
                    $this->resolveField($fields, ['state']),
                    $this->resolveField($fields, ['request_date_from']),
                    $this->resolveField($fields, ['request_date_to']),
                    $this->resolveField($fields, ['leave_type_request_unit']),
                    $this->resolveField($fields, ['number_of_hours']),
                    $this->resolveField($fields, ['planning_start_datetime']),
                    $this->resolveField($fields, ['planning_end_datetime']),
                ]))),
                'order' => 'request_date_from asc, id asc',
                'limit' => 300,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        $signals = [];

        foreach ($records as $record) {
            if (! is_array($record) || empty($record['id'])) {
                continue;
            }

            $employee = $this->extractManyToOne($record['employee_id'] ?? null);
            $employeeId = $employee['id'] ?? 0;
            $state = (string) ($record['state'] ?? '');
            $startDate = $this->parseDateValue($record['request_date_from'] ?? null);
            $endDate = $this->parseDateValue($record['request_date_to'] ?? null);

            if ($employeeId === 0 || ! $startDate || ! $endDate) {
                continue;
            }

            $cursor = $startDate->copy()->max($weekStart);
            $rangeEnd = $endDate->copy()->min($weekEnd);
            $leaveType = $this->extractManyToOne($record['holiday_status_id'] ?? null)['name'] ?? 'Time Off';
            $planningStartAt = $this->parseDateTime($record['planning_start_datetime'] ?? null);
            $planningEndAt = $this->parseDateTime($record['planning_end_datetime'] ?? null);
            $requestUnit = (string) ($record['leave_type_request_unit'] ?? 'day');
            $timeLabel = 'Full day';

            if ($requestUnit === 'hour' && $planningStartAt && $planningEndAt && $planningStartAt->isSameDay($planningEndAt)) {
                $timeLabel = $planningStartAt->format('h:i A').' - '.$planningEndAt->format('h:i A');
            } elseif (isset($record['number_of_hours']) && is_numeric($record['number_of_hours'])) {
                $timeLabel = number_format((float) $record['number_of_hours'], 2).'h';
            }

            while ($cursor->lte($rangeEnd)) {
                $signals[] = [
                    'kind' => $state === 'validate' ? 'leave-approved' : 'leave-pending',
                    'employee_id' => $employeeId,
                    'employee' => $employee['name'] ?? 'Employee',
                    'date_value' => $cursor->toDateString(),
                    'label' => $leaveType,
                    'short_label' => $state === 'validate' ? 'Approved Leave' : 'Pending Leave',
                    'time_label' => $timeLabel,
                    'status_label' => $state === 'validate' ? 'Approved' : 'Pending',
                ];

                $cursor->addDay();
            }
        }

        return $signals;
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchWeeklyAvailabilitySignals(array $employeeIds, Carbon $weekStart, Carbon $weekEnd): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $fields = $this->weeklyAvailabilityFields();

        if ($fields === []) {
            return [];
        }

        $records = $this->serviceAccount->executeKw(
            'hr.employee.weekly.availability',
            'search_read',
            [[
                ['employee_id', 'in', $employeeIds],
                ['availability_type', '=', 'unavailable'],
            ]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $this->resolveField($fields, ['employee_id']),
                    $this->resolveField($fields, ['day_of_week']),
                    $this->resolveField($fields, ['availability_type']),
                    $this->resolveField($fields, ['is_full_day']),
                    $this->resolveField($fields, ['start_time']),
                    $this->resolveField($fields, ['end_time']),
                    $this->resolveField($fields, ['time_range_display']),
                ]))),
                'order' => 'employee_id asc, day_of_week asc, start_time asc, end_time asc, id asc',
                'limit' => 500,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        $signals = [];
        $datesByWeekday = [];
        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $weekdayKey = (string) (($cursor->dayOfWeekIso + 6) % 7);
            $datesByWeekday[$weekdayKey][] = $cursor->toDateString();
            $cursor->addDay();
        }

        foreach ($records as $record) {
            if (! is_array($record) || empty($record['id'])) {
                continue;
            }

            $employee = $this->extractManyToOne($record['employee_id'] ?? null);
            $employeeId = $employee['id'] ?? 0;
            $weekdayKey = isset($record['day_of_week']) ? (string) $record['day_of_week'] : '';

            if ($employeeId === 0 || $weekdayKey === '' || ! isset($datesByWeekday[$weekdayKey])) {
                continue;
            }

            $isFullDay = (bool) ($record['is_full_day'] ?? false);
            $timeLabel = 'Unavailable';

            if (isset($record['time_range_display']) && is_string($record['time_range_display']) && trim($record['time_range_display']) !== '') {
                $timeLabel = trim($record['time_range_display']);
            } elseif ($isFullDay) {
                $timeLabel = 'Unavailable all day';
            } elseif (isset($record['start_time'], $record['end_time']) && is_numeric($record['start_time']) && is_numeric($record['end_time'])) {
                $timeLabel = $this->formatFloatHour((float) $record['start_time']).' - '.$this->formatFloatHour((float) $record['end_time']);
            }

            foreach ($datesByWeekday[$weekdayKey] as $dateValue) {
                $signals[] = [
                    'kind' => 'unavailable',
                    'employee_id' => $employeeId,
                    'employee' => $employee['name'] ?? 'Employee',
                    'date_value' => $dateValue,
                    'label' => 'Unavailable',
                    'short_label' => 'Unavailable',
                    'time_label' => $timeLabel,
                    'status_label' => $isFullDay ? 'Blocked all day' : 'Custom rule',
                ];
            }
        }

        return $signals;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getShiftForManager(int $slotId, string $startField, string $endField): ?array
    {
        $fields = $this->planningFields();
        $records = $this->serviceAccount->executeKw(
            'planning.slot',
            'search_read',
            [[['id', '=', $slotId]]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $startField,
                    $endField,
                    'write_date',
                    $this->resolveField($fields, ['name']),
                    $this->resolveField($fields, ['note']),
                    $this->resolveField($fields, ['role_id']),
                    $this->resolveField($fields, ['company_id']),
                    $this->resolveField($fields, ['employee_id']),
                    $this->resolveField($fields, ['ems_work_location_id']),
                ]))),
                'limit' => 1,
            ]
        );

        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return $this->normalizeShift($records[0], $startField, $endField);
    }

    /**
     * @param  array<string, mixed>  $shift
     */
    private function guardAgainstStaleShift(array $shift, string $lastKnownWriteDate): void
    {
        $currentWriteDate = (string) ($shift['write_date_value'] ?? '');
        $lastKnownWriteDate = trim($lastKnownWriteDate);

        if ($currentWriteDate !== '' && $lastKnownWriteDate !== '' && $currentWriteDate !== $lastKnownWriteDate) {
            throw new OdooException('This shift was updated by someone else. Please reload the page before trying again.');
        }
    }

    /**
     * @return array{id:int|null,name:string|null}
     */
    private function extractManyToOne(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'id' => isset($value[0]) && is_numeric($value[0]) ? (int) $value[0] : null,
                'name' => isset($value[1]) ? (string) $value[1] : null,
            ];
        }

        return ['id' => null, 'name' => null];
    }

    private function calendarBuilder(): ShiftCalendarBuilder
    {
        return new ShiftCalendarBuilder();
    }

    private function publishService(): SchedulePublishService
    {
        return $this->publishService ?? app(SchedulePublishService::class);
    }

}
