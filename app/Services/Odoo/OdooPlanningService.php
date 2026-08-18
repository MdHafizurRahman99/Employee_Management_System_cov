<?php

namespace App\Services\Odoo;

use App\Models\User;
use App\Support\ShiftCalendarBuilder;
use Carbon\Carbon;
use App\Services\Scheduling\SchedulePublishService;

class OdooPlanningService
{
    private ?array $planningFields = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount,
        private readonly ?SchedulePublishService $publishService = null
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getShiftsForMonth(User $user, Carbon $month): array
    {
        $identityField = $this->resolveIdentityField($user);

        if (! $identityField) {
            return [];
        }

        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);

        if (! $startField || ! $endField) {
            throw new OdooException('The Odoo planning model does not expose the expected shift datetime fields.');
        }

        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $startField,
            $endField,
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['role_id']),
            $this->resolveField($fields, ['company_id']),
            $this->resolveField($fields, ['ems_work_location_id']),
            $this->resolveField($fields, ['resource_id']),
            $this->resolveField($fields, ['employee_id']),
            isset($fields['write_date']) ? 'write_date' : null,
            ...array_values(array_intersect(['ems_publish_state','ems_published_at','ems_published_by','ems_requires_confirmation','ems_confirmation_status','ems_confirmation_note','ems_confirmation_responded_at','ems_confirmation_responded_by','ems_notification_mode','ems_notification_status','ems_notification_sent_at','ems_reminder_sent_at','ems_notification_error'], array_keys($fields))),
        ])));

        $domain = [
            [$identityField['field'], '=', $identityField['value']],
            [$startField, '>=', $month->copy()->startOfMonth()->toDateTimeString()],
            [$startField, '<=', $month->copy()->endOfMonth()->endOfDay()->toDateTimeString()],
        ];

        $records = $this->serviceAccount->executeKw(
            'planning.slot',
            'search_read',
            [$domain],
            [
                'fields' => $requestedFields,
                'order' => $startField . ' asc',
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        $shifts = array_values(array_filter(array_map(
            fn (mixed $record) => is_array($record) ? $this->normalizeShift($record, $startField, $endField) : null,
            $records
        )));

        return ($this->publishService ?? app(SchedulePublishService::class))->decorateShifts($shifts);
    }

    public function getTodayShiftForUser(User $user): ?array
    {
        $today = now();
        $shifts = $this->getShiftsForMonth($user, $today->copy()->startOfMonth());

        foreach ($shifts as $shift) {
            if ($shift['is_today']) {
                return $shift;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingShiftsForUser(User $user, int $limit = 5): array
    {
        $month = now()->startOfMonth();
        $shifts = $this->getShiftsForMonth($user, $month);

        $upcoming = array_values(array_filter($shifts, fn (array $shift) => $shift['end_at']?->isFuture()));

        if (count($upcoming) >= $limit) {
            return array_slice($upcoming, 0, $limit);
        }

        $nextMonthShifts = $this->getShiftsForMonth($user, $month->copy()->addMonthNoOverflow());

        $merged = array_merge($upcoming, array_values(array_filter(
            $nextMonthShifts,
            fn (array $shift) => $shift['end_at']?->isFuture()
        )));

        return array_slice($merged, 0, $limit);
    }

    /**
     * @return array{
     *     shifts:array<int, array<string, mixed>>,
     *     todayShift:?array<string, mixed>,
     *     shiftCalendar:array<int, array<int, array<string, mixed>>>,
     *     selectedCalendarDate:Carbon,
     *     selectedCalendarDateLabel:string,
     *     selectedCalendarDateValue:string,
     *     selectedCalendarShifts:array<int, array<string, mixed>>
     * }
     */
    public function getShiftPageData(User $user, Carbon $month, ?Carbon $selectedDay = null): array
    {
        $month = $month->copy()->startOfMonth();
        $shifts = $this->getShiftsForMonth($user, $month);
        $calendar = $this->calendarBuilder()->build($month, $shifts, $selectedDay);

        return [
            'shifts' => $shifts,
            'todayShift' => $this->findTodayShift($shifts),
            'shiftCalendar' => $calendar['weeks'],
            'selectedCalendarDate' => $calendar['selected_date'],
            'selectedCalendarDateLabel' => $calendar['selected_date_label'],
            'selectedCalendarDateValue' => $calendar['selected_date_value'],
            'selectedCalendarShifts' => $calendar['selected_date_shifts'],
        ];
    }

    private function normalizeShift(array $record, string $startField, string $endField): ?array
    {
        if (empty($record[$startField]) || empty($record[$endField])) {
            return null;
        }

        $startAt = $this->parseDateTime($record[$startField]);
        $endAt = $this->parseDateTime($record[$endField]);

        if (! $startAt || ! $endAt) {
            return null;
        }

        $today = now()->toDateString();
        $role = $this->extractManyToOne($record['role_id'] ?? null);
        $company = $this->extractManyToOne($record['company_id'] ?? null);
        $employee = $this->extractManyToOne($record['employee_id'] ?? null);
        $workLocation = $this->extractManyToOne($record['ems_work_location_id'] ?? null);

        return [
            'id' => (int) ($record['id'] ?? 0),
            'title' => (string) ($record['name'] ?? $role['name'] ?? 'Assigned Shift'),
            'date_value' => $startAt->toDateString(),
            'date_label' => $startAt->format('d-m-Y'),
            'start_label' => $startAt->format('h:i A'),
            'end_label' => $endAt->format('h:i A'),
            'role' => $role['name'] ?? 'Unassigned',
            'company' => $company['name'] ?? 'N/A',
            'work_location' => $workLocation['name'] ?? 'No work location',
            'work_location_id' => $workLocation['id'],
            'employee' => $employee['name'] ?? null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_today' => $startAt->toDateString() === $today,
            'write_date_value' => is_string($record['write_date'] ?? null) ? $record['write_date'] : '',
            '_odoo_schedule_meta' => array_intersect_key($record, array_flip(['ems_publish_state','ems_published_at','ems_published_by','ems_requires_confirmation','ems_confirmation_status','ems_confirmation_note','ems_confirmation_responded_at','ems_confirmation_responded_by','ems_notification_mode','ems_notification_status','ems_notification_sent_at','ems_reminder_sent_at','ems_notification_error'])),
        ];
    }

    private function resolveIdentityField(User $user): ?array
    {
        $fields = $this->planningFields();

        if ($user->odoo_resource_id && isset($fields['resource_id'])) {
            return ['field' => 'resource_id', 'value' => (int) $user->odoo_resource_id];
        }

        if ($user->odoo_employee_id && isset($fields['employee_id'])) {
            return ['field' => 'employee_id', 'value' => (int) $user->odoo_employee_id];
        }

        return null;
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

    private function resolveField(array $fields, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($fields[$candidate])) {
                return $candidate;
            }
        }

        return null;
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

    /**
     * @param  array<int, array<string, mixed>>  $shifts
     * @return array<string, mixed>|null
     */
    private function findTodayShift(array $shifts): ?array
    {
        foreach ($shifts as $shift) {
            if (($shift['is_today'] ?? false) === true) {
                return $shift;
            }
        }

        return null;
    }

    private function calendarBuilder(): ShiftCalendarBuilder
    {
        return new ShiftCalendarBuilder();
    }
}
