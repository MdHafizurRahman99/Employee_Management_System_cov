<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OdooLeaveService
{
    private ?array $leaveFields = null;

    private ?array $leaveTypeFields = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{leaveTypes:array<int, array<string, mixed>>, leaveRequests:array<int, array<string, mixed>>}
     */
    public function getLeaveRequestPageData(User $user): array
    {
        $employeeId = $this->resolveEmployeeId($user);

        if (! $employeeId) {
            return [
                'leaveTypes' => [],
                'leaveRequests' => [],
            ];
        }

        return [
            'leaveTypes' => $this->getLeaveTypes(),
            'leaveRequests' => $this->getLeaveRequests($employeeId),
        ];
    }

    public function submitLeaveRequest(User $user, array $data): int
    {
        $employeeId = $this->resolveEmployeeId($user);

        if (! $employeeId) {
            throw new OdooException('Leave requests are unavailable until this account is linked to an Odoo employee.');
        }

        $leaveType = $this->findLeaveType((int) $data['leave_type_id']);
        $startDate = $this->parseDateInput((string) $data['start_date']);
        $endDate = $this->parseDateInput((string) $data['end_date']);

        if (! $startDate || ! $endDate) {
            throw new OdooException('Please provide a valid leave date range.');
        }

        if ($endDate->lt($startDate)) {
            throw new OdooException('The leave end date must be on or after the start date.');
        }

        $requestUnit = $leaveType['request_unit'];
        $startPeriod = $data['start_period'] ?? null;
        $endPeriod = $data['end_period'] ?? null;
        $startHour = $data['start_hour'] ?? null;
        $endHour = $data['end_hour'] ?? null;

        $this->validateLeaveTypeAvailability($leaveType);
        $this->validateRequestShape($requestUnit, $startDate, $endDate, $startPeriod, $endPeriod, $startHour, $endHour);

        if ($requestUnit === 'day' && $this->hasOverlappingDayRequest($employeeId, $startDate, $endDate)) {
            throw new OdooException('This leave request overlaps with an existing request for the selected dates.');
        }

        $payload = [
            'employee_id' => $employeeId,
            'holiday_status_id' => $leaveType['id'],
            'request_date_from' => $startDate->toDateString(),
            'request_date_to' => $endDate->toDateString(),
        ];

        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason !== '') {
            $payload['notes'] = $reason;

            if (isset($this->leaveFields()['name'])) {
                $payload['name'] = Str::limit($reason, 80, '');
            }
        }

        if ($requestUnit === 'half_day') {
            $payload['request_date_from_period'] = $startPeriod;
            $payload['request_date_to_period'] = $endPeriod;
        }

        if ($requestUnit === 'hour') {
            $payload['request_hour_from'] = round((float) $startHour, 2);
            $payload['request_hour_to'] = round((float) $endHour, 2);
        }

        $this->appendPlanningBridgePayload($payload, $data);

        $leaveId = $this->serviceAccount->executeKw('hr.leave', 'create', [$payload]);

        if (! is_numeric($leaveId) || (int) $leaveId < 1) {
            throw new OdooException('Odoo did not confirm the leave request submission.');
        }

        return (int) $leaveId;
    }

    public function cancelLeaveRequest(User $user, int $leaveRequestId): void
    {
        $employeeId = $this->resolveEmployeeId($user);

        if (! $employeeId) {
            throw new OdooException('Leave requests are unavailable until this account is linked to an Odoo employee.');
        }

        $leaveRequest = $this->findLeaveRequestForEmployee($employeeId, $leaveRequestId);

        if (! $leaveRequest) {
            throw new OdooException('The selected leave request could not be found.');
        }

        if (! $this->isPendingState($leaveRequest['state'])) {
            throw new OdooException('Only pending leave requests can be cancelled.');
        }

        $result = $this->serviceAccount->executeKw('hr.leave', 'write', [
            [$leaveRequestId],
            ['state' => 'cancel'],
        ]);

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the leave cancellation.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getLeaveTypes(): array
    {
        $fields = $this->leaveTypeFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['active']),
            $this->resolveField($fields, ['request_unit']),
            $this->resolveField($fields, ['requires_allocation']),
            $this->resolveField($fields, ['has_valid_allocation']),
            $this->resolveField($fields, ['leave_validation_type']),
            $this->resolveField($fields, ['sequence']),
        ])));

        $domain = [];

        if (isset($fields['active'])) {
            $domain[] = ['active', '=', true];
        }

        $order = isset($fields['sequence']) ? 'sequence asc, name asc' : 'name asc';

        $records = $this->serviceAccount->executeKw(
            'hr.leave.type',
            'search_read',
            [$domain],
            [
                'fields' => $requestedFields,
                'order' => $order,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $requestUnit = (string) ($record['request_unit'] ?? 'day');
            $requiresAllocation = (bool) ($record['requires_allocation'] ?? false);
            $hasValidAllocation = array_key_exists('has_valid_allocation', $record)
                ? (bool) $record['has_valid_allocation']
                : null;
            return [
                'id' => (int) $record['id'],
                'name' => (string) ($record['name'] ?? 'Time Off'),
                'request_unit' => $requestUnit,
                'request_unit_label' => $this->requestUnitLabel($requestUnit),
                'requires_allocation' => $requiresAllocation,
                'has_valid_allocation' => $hasValidAllocation,
                'validation_type' => (string) ($record['leave_validation_type'] ?? ''),
                'can_request' => true,
                'availability_note' => $requiresAllocation && $hasValidAllocation === false
                    ? 'This leave type needs available balance or an approved allocation in Odoo. Without that, the request may be rejected.'
                    : null,
            ];
        }, $records)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getLeaveRequests(int $employeeId): array
    {
        $fields = $this->leaveFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['holiday_status_id']),
            $this->resolveField($fields, ['state']),
            $this->resolveField($fields, ['request_date_from']),
            $this->resolveField($fields, ['request_date_to']),
            $this->resolveField($fields, ['number_of_days']),
            $this->resolveField($fields, ['number_of_hours']),
            $this->resolveField($fields, ['notes']),
            $this->resolveField($fields, ['create_date']),
            $this->resolveField($fields, ['can_cancel']),
            $this->resolveField($fields, ['leave_type_request_unit']),
            $this->resolveField($fields, ['planning_slot_id']),
            $this->resolveField($fields, ['planning_slot_title']),
            $this->resolveField($fields, ['planning_role_name']),
            $this->resolveField($fields, ['planning_company_name']),
            $this->resolveField($fields, ['planning_start_datetime']),
            $this->resolveField($fields, ['planning_end_datetime']),
        ])));

        $records = $this->serviceAccount->executeKw(
            'hr.leave',
            'search_read',
            [[['employee_id', '=', $employeeId]]],
            [
                'fields' => $requestedFields,
                'order' => 'request_date_from desc, create_date desc',
                'limit' => 50,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $status = $this->statusMeta((string) ($record['state'] ?? ''));
            $startDate = $this->parseDateValue($record['request_date_from'] ?? null);
            $endDate = $this->parseDateValue($record['request_date_to'] ?? null);
            $createdAt = $this->parseDateTimeValue($record['create_date'] ?? null);
            $requestUnit = (string) ($record['leave_type_request_unit'] ?? 'day');
            $numberOfDays = isset($record['number_of_days']) ? round((float) $record['number_of_days'], 2) : 0.0;
            $numberOfHours = isset($record['number_of_hours']) ? round((float) $record['number_of_hours'], 2) : 0.0;
            $canCancel = array_key_exists('can_cancel', $record)
                ? (bool) $record['can_cancel']
                : $this->isPendingState((string) ($record['state'] ?? ''));
            $planningSlot = $this->extractManyToOne($record['planning_slot_id'] ?? null);
            $planningStartAt = $this->parseDateTimeValue($record['planning_start_datetime'] ?? null);
            $planningEndAt = $this->parseDateTimeValue($record['planning_end_datetime'] ?? null);
            $planningSlotTitle = trim((string) ($record['planning_slot_title'] ?? ''));
            $planningRoleName = trim((string) ($record['planning_role_name'] ?? ''));
            $planningCompanyName = trim((string) ($record['planning_company_name'] ?? ''));

            return [
                'id' => (int) $record['id'],
                'type' => $this->extractManyToOne($record['holiday_status_id'] ?? null)['name'] ?? 'Time Off',
                'request_unit' => $requestUnit,
                'request_unit_label' => $this->requestUnitLabel($requestUnit),
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'start_date_label' => $startDate?->format('d M Y') ?? 'N/A',
                'end_date_label' => $endDate?->format('d M Y') ?? 'N/A',
                'duration_label' => $requestUnit === 'hour'
                    ? number_format($numberOfHours, 2).' hour'.($numberOfHours === 1.0 ? '' : 's')
                    : number_format($numberOfDays, 2).' day'.($numberOfDays === 1.0 ? '' : 's'),
                'reason' => (string) ($record['notes'] ?? ''),
                'state' => (string) ($record['state'] ?? ''),
                'status_label' => $status['label'],
                'status_class' => $status['class'],
                'can_cancel' => $canCancel && $this->isPendingState((string) ($record['state'] ?? '')),
                'submitted_at' => $createdAt,
                'submitted_at_label' => $createdAt?->format('d M Y h:i A') ?? 'N/A',
                'planning_slot_id' => $planningSlot['id'],
                'planning_slot' => $planningSlot['name'] ?? null,
                'planning_slot_title' => $planningSlotTitle !== '' ? $planningSlotTitle : ($planningSlot['name'] ?? null),
                'planning_role_name' => $planningRoleName !== '' ? $planningRoleName : null,
                'planning_company_name' => $planningCompanyName !== '' ? $planningCompanyName : null,
                'planning_start_at' => $planningStartAt,
                'planning_end_at' => $planningEndAt,
                'planning_start_label' => $planningStartAt?->format('d M Y h:i A'),
                'planning_end_label' => $planningEndAt?->format('d M Y h:i A'),
            ];
        }, $records)));
    }

    /**
     * @return array<string, mixed>
     */
    private function findLeaveType(int $leaveTypeId): array
    {
        $leaveType = collect($this->getLeaveTypes())->firstWhere('id', $leaveTypeId);

        if (! $leaveType) {
            throw new OdooException('The selected leave type is not available.');
        }

        return $leaveType;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findLeaveRequestForEmployee(int $employeeId, int $leaveRequestId): ?array
    {
        $records = $this->serviceAccount->executeKw(
            'hr.leave',
            'search_read',
            [[
                ['id', '=', $leaveRequestId],
                ['employee_id', '=', $employeeId],
            ]],
            [
                'fields' => ['id', 'state', 'can_cancel'],
                'limit' => 1,
            ]
        );

        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return $records[0];
    }

    private function validateLeaveTypeAvailability(array $leaveType): void
    {
        if (empty($leaveType['id'])) {
            throw new OdooException('The selected leave type is not available.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function appendPlanningBridgePayload(array &$payload, array $data): void
    {
        $fields = $this->leaveFields();

        if (($field = $this->resolveField($fields, ['planning_slot_id'])) && is_numeric($data['source_shift_id'] ?? null)) {
            $planningSlotId = (int) $data['source_shift_id'];

            if ($this->planningSlotExists($planningSlotId)) {
                $payload[$field] = $planningSlotId;
            }
        }

        if (($field = $this->resolveField($fields, ['planning_slot_title'])) && ($value = $this->normalizeOptionalText($data['source_shift_title'] ?? null)) !== null) {
            $payload[$field] = $value;
        }

        if (($field = $this->resolveField($fields, ['planning_role_name'])) && ($value = $this->normalizeOptionalText($data['source_shift_role'] ?? null)) !== null) {
            $payload[$field] = $value;
        }

        if (($field = $this->resolveField($fields, ['planning_company_name'])) && ($value = $this->normalizeOptionalText($data['source_shift_company'] ?? null)) !== null) {
            $payload[$field] = $value;
        }

        if (($field = $this->resolveField($fields, ['planning_start_datetime'])) && ($value = $this->normalizeUtcDateTimeInput($data['source_shift_start_at'] ?? null)) !== null) {
            $payload[$field] = $value;
        }

        if (($field = $this->resolveField($fields, ['planning_end_datetime'])) && ($value = $this->normalizeUtcDateTimeInput($data['source_shift_end_at'] ?? null)) !== null) {
            $payload[$field] = $value;
        }
    }

    private function planningSlotExists(int $planningSlotId): bool
    {
        $count = $this->serviceAccount->executeKw(
            'planning.slot',
            'search_count',
            [[['id', '=', $planningSlotId]]]
        );

        return is_numeric($count) && (int) $count > 0;
    }

    private function validateRequestShape(
        string $requestUnit,
        Carbon $startDate,
        Carbon $endDate,
        mixed $startPeriod,
        mixed $endPeriod,
        mixed $startHour,
        mixed $endHour
    ): void {
        if ($requestUnit === 'half_day') {
            if (! in_array($startPeriod, ['am', 'pm'], true) || ! in_array($endPeriod, ['am', 'pm'], true)) {
                throw new OdooException('Please choose valid half-day periods for the selected leave type.');
            }

            return;
        }

        if ($requestUnit === 'hour') {
            if ($startDate->ne($endDate)) {
                throw new OdooException('Hourly leave requests must start and end on the same day.');
            }

            if (! is_numeric($startHour) || ! is_numeric($endHour)) {
                throw new OdooException('Please provide both start and end hours for this leave type.');
            }

            $normalizedStartHour = round((float) $startHour, 2);
            $normalizedEndHour = round((float) $endHour, 2);

            if ($normalizedStartHour < 0 || $normalizedStartHour > 23.99) {
                throw new OdooException('The leave start hour must be between 0 and 23.99.');
            }

            if ($normalizedEndHour <= $normalizedStartHour || $normalizedEndHour > 24) {
                throw new OdooException('The leave end hour must be greater than the start hour and no later than 24.00.');
            }
        }
    }

    private function hasOverlappingDayRequest(int $employeeId, Carbon $startDate, Carbon $endDate): bool
    {
        $overlapCount = $this->serviceAccount->executeKw(
            'hr.leave',
            'search_count',
            [[
                ['employee_id', '=', $employeeId],
                ['state', 'not in', ['refuse', 'cancel']],
                ['request_date_from', '<=', $endDate->toDateString()],
                ['request_date_to', '>=', $startDate->toDateString()],
            ]]
        );

        return is_numeric($overlapCount) && (int) $overlapCount > 0;
    }

    private function resolveEmployeeId(User $user): ?int
    {
        return $user->odoo_employee_id ? (int) $user->odoo_employee_id : null;
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

    private function leaveTypeFields(): array
    {
        if ($this->leaveTypeFields !== null) {
            return $this->leaveTypeFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.leave.type',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->leaveTypeFields = is_array($fields) ? $fields : [];

        return $this->leaveTypeFields;
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

    private function parseDateInput(string $value): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeUtcDateTimeInput(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', trim($value), 'UTC')->format('Y-m-d H:i:s');
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
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function parseDateTimeValue(mixed $value): ?Carbon
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

    private function normalizeOptionalText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
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
     * @return array{label:string,class:string}
     */
    private function statusMeta(string $state): array
    {
        return match ($state) {
            'draft' => ['label' => 'Draft', 'class' => 'secondary'],
            'confirm', 'validate1' => ['label' => 'Pending', 'class' => 'warning'],
            'validate' => ['label' => 'Approved', 'class' => 'success'],
            'refuse' => ['label' => 'Rejected', 'class' => 'danger'],
            'cancel' => ['label' => 'Cancelled', 'class' => 'dark'],
            default => ['label' => Str::headline($state !== '' ? $state : 'Unknown'), 'class' => 'secondary'],
        };
    }

    private function requestUnitLabel(string $requestUnit): string
    {
        return match ($requestUnit) {
            'hour' => 'Hourly',
            'half_day' => 'Half Day',
            default => 'Day Based',
        };
    }

    private function isPendingState(string $state): bool
    {
        return in_array($state, ['draft', 'confirm', 'validate1'], true);
    }
}
