<?php

namespace App\Services\Odoo;

use App\Models\User;
use App\Notifications\LeaveRequestStatusChangedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OdooManagerLeaveService
{
    private ?array $leaveFields = null;

    private ?array $employeeFields = null;

    /**
     * Team relationships based on Odoo 19 HR/Leave manager access.
     *
     * @var array<int, string>
     */
    private const TEAM_RELATION_FIELDS = [
        'leave_manager_id',
        'parent_id.user_id',
        'attendance_manager_id',
    ];

    /**
     * @var array<int, array{name:string,privilege:string}>
     */
    private const GLOBAL_TIME_OFF_GROUPS = [
        ['name' => 'Officer: Manage all requests', 'privilege' => 'Time Off'],
        ['name' => 'Administrator', 'privilege' => 'Time Off'],
    ];

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{
     *     employees:array<int, array<string, mixed>>,
     *     leaveRequests:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public function getLeaveApprovalPageData(User $manager, ?int $employeeId = null): array
    {
        $employees = $this->getManagedEmployees($manager);

        if ($employees === []) {
            return [
                'employees' => [],
                'leaveRequests' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $allowedEmployeeIds = array_column($employees, 'id');

        if ($employeeId !== null && ! in_array($employeeId, $allowedEmployeeIds, true)) {
            throw new OdooException('The selected employee is not available in your leave approval team.');
        }

        $leaveRequests = $this->getPendingLeaveRequests(
            $employeeId !== null ? [$employeeId] : $allowedEmployeeIds
        );

        return [
            'employees' => $employees,
            'leaveRequests' => $leaveRequests,
            'summary' => $this->summarizeRequests($leaveRequests),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function approveLeaveRequest(User $manager, int $leaveRequestId, string $lastKnownWriteDate = ''): array
    {
        $leaveRequest = $this->guardManagedLeaveRequest($manager, $leaveRequestId, $lastKnownWriteDate);
        $beforeState = $leaveRequest['state'];

        try {
            $result = $this->serviceAccount->executeKw('hr.leave', 'action_approve', [[$leaveRequestId]]);
        } catch (OdooException $exception) {
            Log::warning('Manager leave approval failed.', [
                'manager_local_user_id' => $manager->getKey(),
                'manager_odoo_user_id' => $manager->odoo_user_id,
                'leave_request_id' => $leaveRequestId,
                'before_state' => $beforeState,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($result !== true && $result !== null) {
            throw new OdooException('Odoo did not confirm the leave approval.');
        }

        $updatedLeave = $this->getLeaveRequestById($leaveRequestId);

        if (! $updatedLeave) {
            throw new OdooException('The leave request could not be reloaded after approval.');
        }

        $this->notifyLocalEmployee($updatedLeave, $manager, null);

        Log::info('Manager leave approval submitted.', [
            'manager_local_user_id' => $manager->getKey(),
            'manager_odoo_user_id' => $manager->odoo_user_id,
            'leave_request_id' => $leaveRequestId,
            'employee_id' => $updatedLeave['employee_id'],
            'before_state' => $beforeState,
            'after_state' => $updatedLeave['state'],
        ]);

        return $updatedLeave;
    }

    /**
     * @return array<string, mixed>
     */
    public function refuseLeaveRequest(
        User $manager,
        int $leaveRequestId,
        string $lastKnownWriteDate = '',
        ?string $managerNote = null
    ): array {
        $leaveRequest = $this->guardManagedLeaveRequest($manager, $leaveRequestId, $lastKnownWriteDate);
        $beforeState = $leaveRequest['state'];
        $note = trim((string) $managerNote);

        try {
            $result = $this->serviceAccount->executeKw('hr.leave', 'action_refuse', [[$leaveRequestId]]);
        } catch (OdooException $exception) {
            Log::warning('Manager leave refusal failed.', [
                'manager_local_user_id' => $manager->getKey(),
                'manager_odoo_user_id' => $manager->odoo_user_id,
                'leave_request_id' => $leaveRequestId,
                'before_state' => $beforeState,
                'manager_note' => $note,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($result !== true && $result !== null) {
            throw new OdooException('Odoo did not confirm the leave refusal.');
        }

        $updatedLeave = $this->getLeaveRequestById($leaveRequestId);

        if (! $updatedLeave) {
            throw new OdooException('The leave request could not be reloaded after refusal.');
        }

        $this->notifyLocalEmployee($updatedLeave, $manager, $note !== '' ? $note : null);

        Log::info('Manager leave refusal submitted.', [
            'manager_local_user_id' => $manager->getKey(),
            'manager_odoo_user_id' => $manager->odoo_user_id,
            'leave_request_id' => $leaveRequestId,
            'employee_id' => $updatedLeave['employee_id'],
            'before_state' => $beforeState,
            'after_state' => $updatedLeave['state'],
            'manager_note' => $note,
        ]);

        return $updatedLeave;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getManagedEmployees(User $manager): array
    {
        if (! $manager->odoo_user_id) {
            return [];
        }

        $fields = $this->employeeFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['company_id']),
            $this->resolveField($fields, ['work_email']),
            $this->resolveField($fields, ['active']),
        ])));

        $employeesById = [];

        foreach (self::TEAM_RELATION_FIELDS as $relationField) {
            $domain = [
                [$relationField, '=', (int) $manager->odoo_user_id],
            ];

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
                continue;
            }

            foreach ($records as $record) {
                if (! is_array($record) || empty($record['id'])) {
                    continue;
                }

                $company = $this->extractManyToOne($record['company_id'] ?? null);
                $employeesById[(int) $record['id']] = [
                    'id' => (int) $record['id'],
                    'name' => (string) ($record['name'] ?? 'Employee'),
                    'company_id' => $company['id'],
                    'company' => $company['name'] ?? 'N/A',
                    'work_email' => (string) ($record['work_email'] ?? ''),
                ];
            }
        }

        if ($this->hasGlobalLeaveApprovalAccess($manager)) {
            foreach ($this->getAllActiveEmployees() as $employee) {
                $employeesById[$employee['id']] = $employee;
            }
        }

        uasort($employeesById, fn (array $left, array $right) => strcmp($left['name'], $right['name']));

        return array_values($employeesById);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAllActiveEmployees(): array
    {
        $fields = $this->employeeFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['company_id']),
            $this->resolveField($fields, ['work_email']),
            $this->resolveField($fields, ['active']),
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

            $company = $this->extractManyToOne($record['company_id'] ?? null);

            return [
                'id' => (int) $record['id'],
                'name' => (string) ($record['name'] ?? 'Employee'),
                'company_id' => $company['id'],
                'company' => $company['name'] ?? 'N/A',
                'work_email' => (string) ($record['work_email'] ?? ''),
            ];
        }, $records)));
    }

    private function hasGlobalLeaveApprovalAccess(User $manager): bool
    {
        if (! $manager->odoo_user_id) {
            return false;
        }

        $user = $this->serviceAccount->executeKw(
            'res.users',
            'search_read',
            [[['id', '=', (int) $manager->odoo_user_id]]],
            [
                'fields' => ['group_ids'],
                'limit' => 1,
            ]
        );

        if (! is_array($user) || ! isset($user[0]) || ! is_array($user[0])) {
            return false;
        }

        $groupIds = array_values(array_filter(
            is_array($user[0]['group_ids'] ?? null) ? $user[0]['group_ids'] : [],
            fn (mixed $groupId) => is_numeric($groupId)
        ));

        if ($groupIds === []) {
            return false;
        }

        $groups = $this->serviceAccount->executeKw(
            'res.groups',
            'search_read',
            [[['id', 'in', $groupIds]]],
            [
                'fields' => ['name', 'privilege_id'],
            ]
        );

        if (! is_array($groups)) {
            return false;
        }

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $privilege = $this->extractManyToOne($group['privilege_id'] ?? null)['name'];
            $name = isset($group['name']) ? (string) $group['name'] : null;

            foreach (self::GLOBAL_TIME_OFF_GROUPS as $globalGroup) {
                if ($name === $globalGroup['name'] && $privilege === $globalGroup['privilege']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function getPendingLeaveRequests(array $employeeIds): array
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
                ['state', 'in', ['confirm', 'validate1']],
            ]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    'write_date',
                    $this->resolveField($fields, ['employee_id']),
                    $this->resolveField($fields, ['holiday_status_id']),
                    $this->resolveField($fields, ['state']),
                    $this->resolveField($fields, ['validation_type']),
                    $this->resolveField($fields, ['request_date_from']),
                    $this->resolveField($fields, ['request_date_to']),
                    $this->resolveField($fields, ['number_of_days']),
                    $this->resolveField($fields, ['number_of_hours']),
                    $this->resolveField($fields, ['notes']),
                    $this->resolveField($fields, ['create_date']),
                    $this->resolveField($fields, ['leave_type_request_unit']),
                    $this->resolveField($fields, ['planning_slot_id']),
                    $this->resolveField($fields, ['planning_slot_title']),
                    $this->resolveField($fields, ['planning_role_name']),
                    $this->resolveField($fields, ['planning_company_name']),
                    $this->resolveField($fields, ['planning_start_datetime']),
                    $this->resolveField($fields, ['planning_end_datetime']),
                    $this->resolveField($fields, ['can_approve']),
                    $this->resolveField($fields, ['can_validate']),
                    $this->resolveField($fields, ['can_refuse']),
                ]))),
                'order' => 'request_date_from asc, create_date asc',
                'limit' => 100,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $record) => is_array($record) ? $this->normalizeLeaveRequest($record) : null,
            $records
        )));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getLeaveRequestById(int $leaveRequestId): ?array
    {
        $fields = $this->leaveFields();
        $records = $this->serviceAccount->executeKw(
            'hr.leave',
            'search_read',
            [[['id', '=', $leaveRequestId]]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    'write_date',
                    $this->resolveField($fields, ['employee_id']),
                    $this->resolveField($fields, ['holiday_status_id']),
                    $this->resolveField($fields, ['state']),
                    $this->resolveField($fields, ['validation_type']),
                    $this->resolveField($fields, ['request_date_from']),
                    $this->resolveField($fields, ['request_date_to']),
                    $this->resolveField($fields, ['number_of_days']),
                    $this->resolveField($fields, ['number_of_hours']),
                    $this->resolveField($fields, ['notes']),
                    $this->resolveField($fields, ['create_date']),
                    $this->resolveField($fields, ['leave_type_request_unit']),
                    $this->resolveField($fields, ['planning_slot_id']),
                    $this->resolveField($fields, ['planning_slot_title']),
                    $this->resolveField($fields, ['planning_role_name']),
                    $this->resolveField($fields, ['planning_company_name']),
                    $this->resolveField($fields, ['planning_start_datetime']),
                    $this->resolveField($fields, ['planning_end_datetime']),
                    $this->resolveField($fields, ['can_approve']),
                    $this->resolveField($fields, ['can_validate']),
                    $this->resolveField($fields, ['can_refuse']),
                ]))),
                'limit' => 1,
            ]
        );

        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return $this->normalizeLeaveRequest($records[0]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function guardManagedLeaveRequest(
        User $manager,
        int $leaveRequestId,
        string $lastKnownWriteDate = ''
    ): ?array {
        $leaveRequest = $this->getLeaveRequestById($leaveRequestId);

        if (! $leaveRequest) {
            throw new OdooException('The selected leave request could not be found.');
        }

        $allowedEmployeeIds = array_column($this->getManagedEmployees($manager), 'id');

        if (! in_array((int) $leaveRequest['employee_id'], $allowedEmployeeIds, true)) {
            throw new OdooException('You do not have access to this leave request.');
        }

        $currentWriteDate = (string) ($leaveRequest['write_date_value'] ?? '');
        $lastKnownWriteDate = trim($lastKnownWriteDate);

        if ($lastKnownWriteDate !== '' && $currentWriteDate !== '' && $currentWriteDate !== $lastKnownWriteDate) {
            throw new OdooException('This leave request was updated by someone else. Please reload the page before trying again.');
        }

        if (! $leaveRequest['can_approve_action'] && ! $leaveRequest['can_refuse_action']) {
            throw new OdooException('This leave request is no longer actionable.');
        }

        return $leaveRequest;
    }

    /**
     * @param  array<string, mixed>  $leaveRequest
     */
    private function notifyLocalEmployee(array $leaveRequest, User $manager, ?string $managerNote): void
    {
        $employeeUsers = $this->getLocalEmployeeUsers((int) $leaveRequest['employee_id']);

        if ($employeeUsers->isEmpty()) {
            Log::info('No local Laravel user matched the approved/refused Odoo employee for notification.', [
                'leave_request_id' => $leaveRequest['id'],
                'employee_id' => $leaveRequest['employee_id'],
            ]);

            return;
        }

        foreach ($employeeUsers as $employeeUser) {
            try {
                $employeeUser->notify(new LeaveRequestStatusChangedNotification(
                    $leaveRequest,
                    $leaveRequest['status_label'],
                    (string) ($manager->name ?? 'Manager'),
                    $managerNote
                ));
            } catch (\Throwable $exception) {
                Log::warning('Leave status notification could not be delivered.', [
                    'leave_request_id' => $leaveRequest['id'],
                    'employee_id' => $leaveRequest['employee_id'],
                    'local_user_id' => $employeeUser->getKey(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    protected function getLocalEmployeeUsers(int $employeeId)
    {
        return User::query()
            ->where('odoo_employee_id', $employeeId)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>|null
     */
    private function normalizeLeaveRequest(array $record): ?array
    {
        if (empty($record['id'])) {
            return null;
        }

        $employee = $this->extractManyToOne($record['employee_id'] ?? null);
        $leaveType = $this->extractManyToOne($record['holiday_status_id'] ?? null);
        $status = $this->statusMeta((string) ($record['state'] ?? ''));
        $startDate = $this->parseDateValue($record['request_date_from'] ?? null);
        $endDate = $this->parseDateValue($record['request_date_to'] ?? null);
        $createdAt = $this->parseDateTimeValue($record['create_date'] ?? null);
        $requestUnit = (string) ($record['leave_type_request_unit'] ?? 'day');
        $numberOfDays = isset($record['number_of_days']) ? round((float) $record['number_of_days'], 2) : 0.0;
        $numberOfHours = isset($record['number_of_hours']) ? round((float) $record['number_of_hours'], 2) : 0.0;
        $writeDate = isset($record['write_date']) && is_string($record['write_date']) ? (string) $record['write_date'] : '';
        $planningSlot = $this->extractManyToOne($record['planning_slot_id'] ?? null);
        $planningStartAt = $this->parseDateTimeValue($record['planning_start_datetime'] ?? null);
        $planningEndAt = $this->parseDateTimeValue($record['planning_end_datetime'] ?? null);
        $planningSlotTitle = trim((string) ($record['planning_slot_title'] ?? ''));
        $planningRoleName = trim((string) ($record['planning_role_name'] ?? ''));
        $planningCompanyName = trim((string) ($record['planning_company_name'] ?? ''));

        return [
            'id' => (int) $record['id'],
            'employee_id' => $employee['id'],
            'employee' => $employee['name'] ?? 'Employee',
            'type' => $leaveType['name'] ?? 'Time Off',
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
            'validation_type' => (string) ($record['validation_type'] ?? ''),
            'status_label' => $status['label'],
            'status_class' => $status['class'],
            'can_approve_action' => (bool) ($record['can_approve'] ?? false) || (bool) ($record['can_validate'] ?? false),
            'can_refuse_action' => (bool) ($record['can_refuse'] ?? false),
            'submitted_at_label' => $createdAt?->format('d M Y h:i A') ?? 'N/A',
            'write_date_value' => $writeDate,
            'updated_label' => $writeDate !== '' ? ($this->parseDateTimeValue($writeDate)?->format('d M Y h:i A') ?? 'N/A') : 'N/A',
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
    }

    /**
     * @param  array<int, array<string, mixed>>  $leaveRequests
     * @return array<string, mixed>
     */
    private function summarizeRequests(array $leaveRequests): array
    {
        return [
            'pending_count' => count($leaveRequests),
            'employees_count' => count(array_unique(array_map(fn (array $leaveRequest) => (int) ($leaveRequest['employee_id'] ?? 0), $leaveRequests))),
            'double_approval_count' => count(array_filter($leaveRequests, fn (array $leaveRequest) => $leaveRequest['state'] === 'validate1')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'pending_count' => 0,
            'employees_count' => 0,
            'double_approval_count' => 0,
        ];
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

    private function resolveField(array $fields, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($fields[$candidate])) {
                return $candidate;
            }
        }

        return null;
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
            'confirm' => ['label' => 'Pending Approval', 'class' => 'warning'],
            'validate1' => ['label' => 'Pending Second Approval', 'class' => 'info'],
            'validate' => ['label' => 'Approved', 'class' => 'success'],
            'refuse' => ['label' => 'Rejected', 'class' => 'danger'],
            'cancel' => ['label' => 'Cancelled', 'class' => 'dark'],
            'draft' => ['label' => 'Draft', 'class' => 'secondary'],
            default => ['label' => ucfirst($state !== '' ? $state : 'Unknown'), 'class' => 'secondary'],
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
}
