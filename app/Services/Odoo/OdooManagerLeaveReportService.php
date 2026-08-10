<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;

class OdooManagerLeaveReportService
{
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

    private ?array $leaveFields = null;

    private ?array $leaveTypeFields = null;

    private ?array $employeeFields = null;

    private ?array $leaveTypesCache = null;

    private ?array $availability = null;

    private array $modelAvailability = [];

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{
     *     leaveAvailable:bool,
     *     leaveMessage:?string,
     *     employees:array<int, array<string, mixed>>,
     *     leaveTypes:array<int, array<string, mixed>>,
     *     rows:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public function getReportPageData(
        User $manager,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $employeeId = null,
        ?int $leaveTypeId = null
    ): array {
        $fromDate = $fromDate->copy()->startOfDay();
        $toDate = $toDate->copy()->startOfDay();

        if ($toDate->lt($fromDate)) {
            [$fromDate, $toDate] = [$toDate->copy(), $fromDate->copy()];
        }

        $availability = $this->leaveAvailability();

        if (! $availability['available']) {
            return [
                'leaveAvailable' => false,
                'leaveMessage' => $availability['message'],
                'employees' => [],
                'leaveTypes' => [],
                'rows' => [],
                'summary' => $this->emptySummary($fromDate, $toDate),
            ];
        }

        $employees = $this->getManagedEmployees($manager);
        $leaveTypes = $this->getLeaveTypes();

        if ($employees === []) {
            return [
                'leaveAvailable' => true,
                'leaveMessage' => null,
                'employees' => [],
                'leaveTypes' => $leaveTypes,
                'rows' => [],
                'summary' => $this->emptySummary($fromDate, $toDate),
            ];
        }

        $allowedEmployeeIds = array_column($employees, 'id');

        if ($employeeId !== null && ! in_array($employeeId, $allowedEmployeeIds, true)) {
            throw new OdooException('The selected employee is not available in your leave report team.');
        }

        if ($leaveTypeId !== null && ! in_array($leaveTypeId, array_column($leaveTypes, 'id'), true)) {
            throw new OdooException('The selected leave type is not available.');
        }

        $leaveRows = $this->buildLeaveRows(
            $employees,
            $fromDate,
            $toDate,
            $employeeId,
            $leaveTypeId
        );

        return [
            'leaveAvailable' => true,
            'leaveMessage' => null,
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'rows' => $leaveRows,
            'summary' => $this->summarizeRows($leaveRows, $fromDate, $toDate),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array<int, array<string, mixed>>
     */
    private function buildLeaveRows(
        array $employees,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $employeeId = null,
        ?int $leaveTypeId = null
    ): array {
        $filteredEmployees = $employees;

        if ($employeeId !== null) {
            $filteredEmployees = array_values(array_filter(
                $filteredEmployees,
                fn (array $employee) => (int) ($employee['id'] ?? 0) === $employeeId
            ));
        }

        $employeeIds = array_values(array_unique(array_filter(array_map(
            fn (array $employee) => (int) ($employee['id'] ?? 0),
            $filteredEmployees
        ))));

        if ($employeeIds === []) {
            return [];
        }

        $records = $this->getApprovedLeaveRecords($employeeIds, $fromDate, $toDate, $leaveTypeId);
        $balances = $this->getLeaveBalancesForEmployees($employeeIds, $leaveTypeId);
        $employeeMap = [];

        foreach ($employees as $employee) {
            $employeeMap[(int) $employee['id']] = $employee;
        }

        $rowsByKey = [];

        foreach ($records as $record) {
            $employeeRecord = $employeeMap[(int) ($record['employee_id'] ?? 0)] ?? null;

            if (! $employeeRecord) {
                continue;
            }

            $key = $record['employee_id'].'-'.$record['leave_type_id'];
            $existing = $rowsByKey[$key] ?? [
                'employee_id' => (int) $record['employee_id'],
                'employee' => $employeeRecord['name'],
                'company' => $employeeRecord['company'],
                'leave_type_id' => (int) $record['leave_type_id'],
                'leave_type' => $record['leave_type'],
                'request_unit' => $record['request_unit'],
                'taken_value' => 0.0,
                'request_count' => 0,
                'last_end_date' => null,
                'remaining_balance' => $balances[(int) $record['employee_id']][(int) $record['leave_type_id']]['remaining_balance'] ?? null,
                'remaining_balance_unit' => $balances[(int) $record['employee_id']][(int) $record['leave_type_id']]['request_unit']
                    ?? $record['request_unit'],
            ];

            $existing['taken_value'] = round((float) $existing['taken_value'] + (float) $record['taken_value'], 2);
            $existing['request_count'] = (int) $existing['request_count'] + 1;

            if (
                ! $existing['last_end_date']
                || ($record['end_date'] instanceof Carbon && $record['end_date']->gt($existing['last_end_date']))
            ) {
                $existing['last_end_date'] = $record['end_date'];
            }

            $rowsByKey[$key] = $existing;
        }

        $rows = array_values(array_map(function (array $row): array {
            $row['taken_label'] = $this->formatDuration((float) $row['taken_value'], (string) $row['request_unit']);
            $row['remaining_balance_label'] = $this->formatRemainingBalance(
                $row['remaining_balance'],
                (string) $row['remaining_balance_unit']
            );
            $row['last_leave_label'] = $row['last_end_date'] instanceof Carbon
                ? $row['last_end_date']->format('d-m-Y')
                : 'N/A';
            $row['has_balance'] = $row['remaining_balance'] !== null;
            unset($row['last_end_date']);

            return $row;
        }, $rowsByKey));

        usort($rows, function (array $left, array $right): int {
            $employeeComparison = strcmp($left['employee'], $right['employee']);

            if ($employeeComparison !== 0) {
                return $employeeComparison;
            }

            return strcmp($left['leave_type'], $right['leave_type']);
        });

        return $rows;
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function getApprovedLeaveRecords(
        array $employeeIds,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $leaveTypeId = null
    ): array {
        if ($employeeIds === []) {
            return [];
        }

        $fields = $this->leaveFields();
        $domain = [
            ['employee_id', 'in', $employeeIds],
            ['state', '=', 'validate'],
            ['request_date_from', '<=', $toDate->toDateString()],
            ['request_date_to', '>=', $fromDate->toDateString()],
        ];

        if ($leaveTypeId !== null) {
            $domain[] = ['holiday_status_id', '=', $leaveTypeId];
        }

        $records = $this->serviceAccount->executeKw(
            'hr.leave',
            'search_read',
            [$domain],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $this->resolveField($fields, ['employee_id']),
                    $this->resolveField($fields, ['holiday_status_id']),
                    $this->resolveField($fields, ['request_date_from']),
                    $this->resolveField($fields, ['request_date_to']),
                    $this->resolveField($fields, ['number_of_days']),
                    $this->resolveField($fields, ['number_of_hours']),
                    $this->resolveField($fields, ['leave_type_request_unit']),
                    $this->resolveField($fields, ['state']),
                ]))),
                'order' => 'request_date_from desc, request_date_to desc',
                'limit' => 1000,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $employee = $this->extractManyToOne($record['employee_id'] ?? null);
            $leaveType = $this->extractManyToOne($record['holiday_status_id'] ?? null);
            $requestUnit = (string) ($record['leave_type_request_unit'] ?? 'day');
            $endDate = $this->parseDateValue($record['request_date_to'] ?? null);

            return [
                'id' => (int) $record['id'],
                'employee_id' => $employee['id'],
                'leave_type_id' => $leaveType['id'],
                'leave_type' => $leaveType['name'] ?? 'Time Off',
                'request_unit' => $requestUnit,
                'taken_value' => $requestUnit === 'hour'
                    ? round((float) ($record['number_of_hours'] ?? 0), 2)
                    : round((float) ($record['number_of_days'] ?? 0), 2),
                'end_date' => $endDate,
            ];
        }, $records)));
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<int, array{remaining_balance:float|null,request_unit:string}>>
     */
    private function getLeaveBalancesForEmployees(array $employeeIds, ?int $leaveTypeId = null): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $leaveTypes = $this->getLeaveTypes();
        $activeLeaveTypeIds = array_values(array_unique(array_filter(array_map(
            fn (array $leaveType) => (int) ($leaveType['id'] ?? 0),
            $leaveTypes
        ))));

        if ($leaveTypeId !== null) {
            $activeLeaveTypeIds = array_values(array_filter(
                $activeLeaveTypeIds,
                fn (int $id) => $id === $leaveTypeId
            ));
        }

        if ($activeLeaveTypeIds === []) {
            return [];
        }

        $fields = $this->leaveTypeFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['request_unit']),
            isset($fields['virtual_remaining_leaves']) ? 'virtual_remaining_leaves' : null,
            isset($fields['remaining_leaves']) ? 'remaining_leaves' : null,
            isset($fields['max_leaves']) ? 'max_leaves' : null,
            isset($fields['leaves_taken']) ? 'leaves_taken' : null,
        ])));

        $balances = [];

        foreach ($employeeIds as $employeeId) {
            $records = $this->serviceAccount->executeKw(
                'hr.leave.type',
                'search_read',
                [[
                    ['id', 'in', $activeLeaveTypeIds],
                ]],
                [
                    'fields' => $requestedFields,
                    'order' => 'name asc',
                    'limit' => max(count($activeLeaveTypeIds), 1),
                    'context' => ['employee_id' => $employeeId],
                ]
            );

            if (! is_array($records)) {
                continue;
            }

            foreach ($records as $record) {
                if (! is_array($record) || empty($record['id'])) {
                    continue;
                }

                $remainingBalance = $this->resolveRemainingBalance($record);

                $balances[$employeeId][(int) $record['id']] = [
                    'remaining_balance' => $remainingBalance,
                    'request_unit' => (string) ($record['request_unit'] ?? 'day'),
                ];
            }
        }

        return $balances;
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

        if ($this->hasGlobalLeaveAccess($manager)) {
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getLeaveTypes(): array
    {
        if ($this->leaveTypesCache !== null) {
            return $this->leaveTypesCache;
        }

        $fields = $this->leaveTypeFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['active']),
            $this->resolveField($fields, ['request_unit']),
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
            return $this->leaveTypesCache = [];
        }

        return $this->leaveTypesCache = array_values(array_filter(array_map(function (mixed $record): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            return [
                'id' => (int) $record['id'],
                'name' => (string) ($record['name'] ?? 'Time Off'),
                'request_unit' => (string) ($record['request_unit'] ?? 'day'),
            ];
        }, $records)));
    }

    /**
     * @return array{available:bool,message:?string}
     */
    private function leaveAvailability(): array
    {
        if ($this->availability !== null) {
            return $this->availability;
        }

        if ($this->modelExists('hr.leave') && $this->modelExists('hr.leave.type')) {
            return $this->availability = [
                'available' => true,
                'message' => null,
            ];
        }

        $moduleState = $this->getModuleState('hr_holidays');

        if ($moduleState === 'installed') {
            return $this->availability = [
                'available' => false,
                'message' => 'The configured Odoo account cannot access the Time Off models required for leave reporting.',
            ];
        }

        return $this->availability = [
            'available' => false,
            'message' => 'Odoo Time Off is unavailable because the Time Off module is not installed in the connected database.',
        ];
    }

    private function hasGlobalLeaveAccess(User $manager): bool
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

    private function modelExists(string $model): bool
    {
        if (array_key_exists($model, $this->modelAvailability)) {
            return $this->modelAvailability[$model];
        }

        $count = $this->serviceAccount->executeKw(
            'ir.model',
            'search_count',
            [[['model', '=', $model]]]
        );

        $this->modelAvailability[$model] = is_numeric($count) && (int) $count > 0;

        return $this->modelAvailability[$model];
    }

    private function getModuleState(string $moduleName): ?string
    {
        $records = $this->serviceAccount->executeKw(
            'ir.module.module',
            'search_read',
            [[['name', '=', $moduleName]]],
            [
                'fields' => ['state'],
                'limit' => 1,
            ]
        );

        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return isset($records[0]['state']) ? (string) $records[0]['state'] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarizeRows(array $rows, Carbon $fromDate, Carbon $toDate): array
    {
        return [
            'range_label' => $fromDate->format('d-m-Y').' - '.$toDate->format('d-m-Y'),
            'row_count' => count($rows),
            'employees_count' => count(array_unique(array_filter(array_map(
                fn (array $row) => (int) ($row['employee_id'] ?? 0),
                $rows
            )))),
            'leave_types_count' => count(array_unique(array_filter(array_map(
                fn (array $row) => (int) ($row['leave_type_id'] ?? 0),
                $rows
            )))),
            'day_based_total' => round(array_reduce(
                $rows,
                fn (float $carry, array $row) => $carry + ((string) $row['request_unit'] === 'hour' ? 0.0 : (float) ($row['taken_value'] ?? 0.0)),
                0.0
            ), 2),
            'day_based_total_label' => number_format(array_reduce(
                $rows,
                fn (float $carry, array $row) => $carry + ((string) $row['request_unit'] === 'hour' ? 0.0 : (float) ($row['taken_value'] ?? 0.0)),
                0.0
            ), 2).' day'.($this->pluralSuffix((float) array_reduce(
                $rows,
                fn (float $carry, array $row) => $carry + ((string) $row['request_unit'] === 'hour' ? 0.0 : (float) ($row['taken_value'] ?? 0.0)),
                0.0
            ))),
            'hour_based_total' => round(array_reduce(
                $rows,
                fn (float $carry, array $row) => $carry + ((string) $row['request_unit'] === 'hour' ? (float) ($row['taken_value'] ?? 0.0) : 0.0),
                0.0
            ), 2),
            'hour_based_total_label' => number_format(array_reduce(
                $rows,
                fn (float $carry, array $row) => $carry + ((string) $row['request_unit'] === 'hour' ? (float) ($row['taken_value'] ?? 0.0) : 0.0),
                0.0
            ), 2).' hour'.($this->pluralSuffix((float) array_reduce(
                $rows,
                fn (float $carry, array $row) => $carry + ((string) $row['request_unit'] === 'hour' ? (float) ($row['taken_value'] ?? 0.0) : 0.0),
                0.0
            ))),
            'request_count_total' => array_reduce(
                $rows,
                fn (int $carry, array $row) => $carry + (int) ($row['request_count'] ?? 0),
                0
            ),
            'balance_rows_count' => count(array_filter($rows, fn (array $row) => (bool) ($row['has_balance'] ?? false))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(Carbon $fromDate, Carbon $toDate): array
    {
        return [
            'range_label' => $fromDate->format('d-m-Y').' - '.$toDate->format('d-m-Y'),
            'row_count' => 0,
            'employees_count' => 0,
            'leave_types_count' => 0,
            'day_based_total' => 0.0,
            'day_based_total_label' => number_format(0, 2).' days',
            'hour_based_total' => 0.0,
            'hour_based_total_label' => number_format(0, 2).' hours',
            'request_count_total' => 0,
            'balance_rows_count' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveRemainingBalance(array $record): ?float
    {
        foreach (['virtual_remaining_leaves', 'remaining_leaves'] as $field) {
            if (isset($record[$field]) && is_numeric($record[$field])) {
                return round((float) $record[$field], 2);
            }
        }

        if (isset($record['max_leaves'], $record['leaves_taken']) && is_numeric($record['max_leaves']) && is_numeric($record['leaves_taken'])) {
            return round((float) $record['max_leaves'] - (float) $record['leaves_taken'], 2);
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

    private function formatDuration(float $value, string $requestUnit): string
    {
        return number_format($value, 2).' '.match ($requestUnit) {
            'hour' => 'hour'.$this->pluralSuffix($value),
            default => 'day'.$this->pluralSuffix($value),
        };
    }

    private function formatRemainingBalance(?float $value, string $requestUnit): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return number_format($value, 2).' '.match ($requestUnit) {
            'hour' => 'hour'.$this->pluralSuffix($value),
            default => 'day'.$this->pluralSuffix($value),
        };
    }

    private function pluralSuffix(float $value): string
    {
        return abs($value - 1.0) < 0.0001 ? '' : 's';
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
}
