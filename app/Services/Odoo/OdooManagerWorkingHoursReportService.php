<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;

class OdooManagerWorkingHoursReportService
{
    /**
     * Team relationships based on Odoo 19 HR manager access.
     *
     * @var array<int, string>
     */
    private const TEAM_RELATION_FIELDS = [
        'attendance_manager_id',
        'parent_id.user_id',
        'leave_manager_id',
    ];

    private ?array $employeeFields = null;

    private ?array $planningFields = null;

    private ?array $attendanceFields = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{
     *     employees:array<int, array<string, mixed>>,
     *     companies:array<int, array<string, mixed>>,
     *     rows:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public function getReportPageData(
        User $manager,
        Carbon $month,
        ?int $employeeId = null,
        ?int $companyId = null
    ): array {
        $month = $month->copy()->startOfMonth();
        $employees = $this->getManagedEmployees($manager);
        $companies = $this->extractCompanies($employees);

        if ($employees === []) {
            return [
                'employees' => [],
                'companies' => [],
                'rows' => [],
                'summary' => $this->emptySummary($month),
            ];
        }

        if ($companyId !== null && ! in_array($companyId, array_column($companies, 'id'), true)) {
            throw new OdooException('The selected company is not available in your working hours team.');
        }

        if ($employeeId !== null && ! in_array($employeeId, array_column($employees, 'id'), true)) {
            throw new OdooException('The selected employee is not available in your working hours team.');
        }

        $filteredEmployees = $employees;

        if ($companyId !== null) {
            $filteredEmployees = array_values(array_filter(
                $filteredEmployees,
                fn (array $employee) => (int) ($employee['company_id'] ?? 0) === $companyId
            ));
        }

        if ($employeeId !== null) {
            $filteredEmployees = array_values(array_filter(
                $filteredEmployees,
                fn (array $employee) => (int) ($employee['id'] ?? 0) === $employeeId
            ));

            if ($filteredEmployees === []) {
                throw new OdooException('The selected employee does not belong to the selected company filter.');
            }
        }

        $rows = $this->buildReportRows($filteredEmployees, $month);

        return [
            'employees' => $employees,
            'companies' => $companies,
            'rows' => $rows,
            'summary' => $this->summarizeRows($rows, $month),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array<int, array<string, mixed>>
     */
    private function buildReportRows(array $employees, Carbon $month): array
    {
        if ($employees === []) {
            return [];
        }

        $plannedByEmployee = $this->getPlannedHoursByEmployee($employees, $month);
        $actualByEmployee = $this->getActualHoursByEmployee($employees, $month);

        $rows = [];

        foreach ($employees as $employee) {
            $employeeId = (int) ($employee['id'] ?? 0);
            $planned = $plannedByEmployee[$employeeId] ?? [
                'planned_hours' => 0.0,
                'shift_count' => 0,
            ];
            $actual = $actualByEmployee[$employeeId] ?? [
                'actual_hours' => 0.0,
                'attendance_days_count' => 0,
                'missing_clock_out_count' => 0,
            ];

            $plannedHours = round((float) ($planned['planned_hours'] ?? 0.0), 2);
            $actualHours = round((float) ($actual['actual_hours'] ?? 0.0), 2);
            $varianceHours = round($actualHours - $plannedHours, 2);
            $overtimeHours = $varianceHours > 0 ? $varianceHours : 0.0;
            $undertimeHours = $varianceHours < 0 ? abs($varianceHours) : 0.0;

            $rows[] = [
                'employee_id' => $employeeId,
                'employee' => $employee['name'],
                'company_id' => $employee['company_id'],
                'company' => $employee['company'],
                'planned_hours' => $plannedHours,
                'planned_hours_label' => $this->formatHours($plannedHours),
                'actual_hours' => $actualHours,
                'actual_hours_label' => $this->formatHours($actualHours),
                'variance_hours' => $varianceHours,
                'variance_hours_label' => $this->formatVarianceHours($varianceHours),
                'overtime_hours' => round($overtimeHours, 2),
                'overtime_hours_label' => $this->formatHours($overtimeHours),
                'undertime_hours' => round($undertimeHours, 2),
                'undertime_hours_label' => $this->formatHours($undertimeHours),
                'shift_count' => (int) ($planned['shift_count'] ?? 0),
                'attendance_days_count' => (int) ($actual['attendance_days_count'] ?? 0),
                'missing_clock_out_count' => (int) ($actual['missing_clock_out_count'] ?? 0),
                'status_label' => $this->statusLabel($varianceHours),
                'status_class' => $this->statusClass($varianceHours),
            ];
        }

        usort($rows, fn (array $left, array $right) => strcmp($left['employee'], $right['employee']));

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array<int, array{planned_hours:float,shift_count:int}>
     */
    private function getPlannedHoursByEmployee(array $employees, Carbon $month): array
    {
        $fields = $this->planningFields();
        $startField = $this->resolveField($fields, ['start_datetime', 'start_dt', 'start_date']);
        $endField = $this->resolveField($fields, ['end_datetime', 'end_dt', 'end_date']);

        if (! $startField || ! $endField) {
            throw new OdooException('The Odoo planning model does not expose the expected shift datetime fields.');
        }

        $useEmployeeField = isset($fields['employee_id']);
        $useResourceField = ! $useEmployeeField && isset($fields['resource_id']);

        if (! $useEmployeeField && ! $useResourceField) {
            throw new OdooException('The Odoo planning model does not expose an employee or resource link for reporting.');
        }

        $resourceMap = [];
        $identityValues = [];

        foreach ($employees as $employee) {
            if ($useEmployeeField) {
                $identityValues[] = (int) $employee['id'];
                continue;
            }

            $resourceId = (int) ($employee['resource_id'] ?? 0);

            if ($resourceId > 0) {
                $identityValues[] = $resourceId;
                $resourceMap[$resourceId] = (int) $employee['id'];
            }
        }

        $identityValues = array_values(array_unique(array_filter($identityValues)));

        if ($identityValues === []) {
            return [];
        }

        $records = $this->serviceAccount->executeKw(
            'planning.slot',
            'search_read',
            [[
                [$useEmployeeField ? 'employee_id' : 'resource_id', 'in', $identityValues],
                [$startField, '>=', $this->toOdooDateTime($month->copy()->startOfMonth())],
                [$startField, '<=', $this->toOdooDateTime($month->copy()->endOfMonth()->endOfDay())],
            ]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $startField,
                    $endField,
                    isset($fields['allocated_hours']) ? 'allocated_hours' : null,
                    $useEmployeeField ? 'employee_id' : null,
                    $useResourceField ? 'resource_id' : null,
                ]))),
                'order' => $startField.' asc',
                'limit' => 1000,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        $summary = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $employeeKey = null;

            if ($useEmployeeField) {
                $employeeKey = $this->extractManyToOne($record['employee_id'] ?? null)['id'];
            } elseif ($useResourceField) {
                $resourceId = $this->extractManyToOne($record['resource_id'] ?? null)['id'];
                $employeeKey = $resourceId ? ($resourceMap[$resourceId] ?? null) : null;
            }

            if (! $employeeKey) {
                continue;
            }

            $plannedHours = $this->resolvePlannedHours($record, $startField, $endField);

            $summary[$employeeKey] = [
                'planned_hours' => round(($summary[$employeeKey]['planned_hours'] ?? 0.0) + $plannedHours, 2),
                'shift_count' => (int) ($summary[$employeeKey]['shift_count'] ?? 0) + 1,
            ];
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array<int, array{actual_hours:float,attendance_days_count:int,missing_clock_out_count:int}>
     */
    private function getActualHoursByEmployee(array $employees, Carbon $month): array
    {
        $fields = $this->attendanceFields();
        $checkInField = $this->resolveField($fields, ['check_in']);
        $checkOutField = $this->resolveField($fields, ['check_out']);
        $workedHoursField = $this->resolveField($fields, ['worked_hours']);

        if (! $checkInField || ! $checkOutField || ! $workedHoursField) {
            throw new OdooException('The Odoo attendance model does not expose the expected attendance fields.');
        }

        $employeeIds = array_values(array_unique(array_filter(array_map(
            fn (array $employee) => (int) ($employee['id'] ?? 0),
            $employees
        ))));

        if ($employeeIds === []) {
            return [];
        }

        $records = $this->serviceAccount->executeKw(
            'hr.attendance',
            'search_read',
            [[
                ['employee_id', 'in', $employeeIds],
                [$checkInField, '>=', $this->toOdooDateTime($month->copy()->startOfMonth())],
                [$checkInField, '<=', $this->toOdooDateTime($month->copy()->endOfMonth()->endOfDay())],
            ]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $checkInField,
                    $checkOutField,
                    $workedHoursField,
                    'employee_id',
                ]))),
                'order' => $checkInField.' asc',
                'limit' => 2000,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        $summary = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $employeeId = $this->extractManyToOne($record['employee_id'] ?? null)['id'];
            $checkInAt = $this->parseDateTime($record[$checkInField] ?? null);
            $checkOutAt = $this->parseDateTime($record[$checkOutField] ?? null);

            if (! $employeeId || ! $checkInAt) {
                continue;
            }

            $workedHours = $this->normalizeWorkedHours($record[$workedHoursField] ?? null, $checkInAt, $checkOutAt);

            $summary[$employeeId]['actual_hours'] = round(($summary[$employeeId]['actual_hours'] ?? 0.0) + $workedHours, 2);
            $summary[$employeeId]['attendance_days'][$checkInAt->toDateString()] = true;

            if (! $checkOutAt) {
                $summary[$employeeId]['missing_clock_out_count'] = (int) ($summary[$employeeId]['missing_clock_out_count'] ?? 0) + 1;
            }
        }

        foreach ($summary as $employeeId => $employeeSummary) {
            $summary[$employeeId] = [
                'actual_hours' => round((float) ($employeeSummary['actual_hours'] ?? 0.0), 2),
                'attendance_days_count' => count($employeeSummary['attendance_days'] ?? []),
                'missing_clock_out_count' => (int) ($employeeSummary['missing_clock_out_count'] ?? 0),
            ];
        }

        return $summary;
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
            $this->resolveField($fields, ['resource_id']),
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

                $resource = $this->extractManyToOne($record['resource_id'] ?? null);
                $company = $this->extractManyToOne($record['company_id'] ?? null);

                $employeesById[(int) $record['id']] = [
                    'id' => (int) $record['id'],
                    'name' => (string) ($record['name'] ?? 'Employee'),
                    'resource_id' => $resource['id'],
                    'company_id' => $company['id'],
                    'company' => $company['name'] ?? 'N/A',
                    'work_email' => (string) ($record['work_email'] ?? ''),
                ];
            }
        }

        uasort($employeesById, fn (array $left, array $right) => strcmp($left['name'], $right['name']));

        return array_values($employeesById);
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array<int, array<string, mixed>>
     */
    private function extractCompanies(array $employees): array
    {
        $companiesById = [];

        foreach ($employees as $employee) {
            $companyId = (int) ($employee['company_id'] ?? 0);

            if ($companyId < 1) {
                continue;
            }

            $companiesById[$companyId] = [
                'id' => $companyId,
                'name' => (string) ($employee['company'] ?? 'Company'),
            ];
        }

        uasort($companiesById, fn (array $left, array $right) => strcmp($left['name'], $right['name']));

        return array_values($companiesById);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolvePlannedHours(array $record, string $startField, string $endField): float
    {
        if (isset($record['allocated_hours']) && is_numeric($record['allocated_hours'])) {
            return round((float) $record['allocated_hours'], 2);
        }

        $startAt = $this->parseDateTime($record[$startField] ?? null);
        $endAt = $this->parseDateTime($record[$endField] ?? null);

        if (! $startAt || ! $endAt || ! $endAt->gt($startAt)) {
            return 0.0;
        }

        return round($startAt->diffInMinutes($endAt) / 60, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarizeRows(array $rows, Carbon $month): array
    {
        $plannedHoursTotal = round(array_reduce(
            $rows,
            fn (float $carry, array $row) => $carry + (float) ($row['planned_hours'] ?? 0.0),
            0.0
        ), 2);

        $actualHoursTotal = round(array_reduce(
            $rows,
            fn (float $carry, array $row) => $carry + (float) ($row['actual_hours'] ?? 0.0),
            0.0
        ), 2);

        $overtimeTotal = round(array_reduce(
            $rows,
            fn (float $carry, array $row) => $carry + (float) ($row['overtime_hours'] ?? 0.0),
            0.0
        ), 2);

        $undertimeTotal = round(array_reduce(
            $rows,
            fn (float $carry, array $row) => $carry + (float) ($row['undertime_hours'] ?? 0.0),
            0.0
        ), 2);

        return [
            'month_label' => $month->format('F Y'),
            'employees_count' => count($rows),
            'planned_hours_total' => $plannedHoursTotal,
            'planned_hours_total_label' => $this->formatHours($plannedHoursTotal),
            'actual_hours_total' => $actualHoursTotal,
            'actual_hours_total_label' => $this->formatHours($actualHoursTotal),
            'overtime_total' => $overtimeTotal,
            'overtime_total_label' => $this->formatHours($overtimeTotal),
            'undertime_total' => $undertimeTotal,
            'undertime_total_label' => $this->formatHours($undertimeTotal),
            'shift_count_total' => array_reduce(
                $rows,
                fn (int $carry, array $row) => $carry + (int) ($row['shift_count'] ?? 0),
                0
            ),
            'attendance_days_total' => array_reduce(
                $rows,
                fn (int $carry, array $row) => $carry + (int) ($row['attendance_days_count'] ?? 0),
                0
            ),
            'missing_clock_out_total' => array_reduce(
                $rows,
                fn (int $carry, array $row) => $carry + (int) ($row['missing_clock_out_count'] ?? 0),
                0
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(Carbon $month): array
    {
        return [
            'month_label' => $month->format('F Y'),
            'employees_count' => 0,
            'planned_hours_total' => 0.0,
            'planned_hours_total_label' => $this->formatHours(0),
            'actual_hours_total' => 0.0,
            'actual_hours_total_label' => $this->formatHours(0),
            'overtime_total' => 0.0,
            'overtime_total_label' => $this->formatHours(0),
            'undertime_total' => 0.0,
            'undertime_total_label' => $this->formatHours(0),
            'shift_count_total' => 0,
            'attendance_days_total' => 0,
            'missing_clock_out_total' => 0,
        ];
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

    private function attendanceFields(): array
    {
        if ($this->attendanceFields !== null) {
            return $this->attendanceFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.attendance',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->attendanceFields = is_array($fields) ? $fields : [];

        return $this->attendanceFields;
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

    private function toOdooDateTime(Carbon $dateTime): string
    {
        return $dateTime->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    private function normalizeWorkedHours(mixed $value, Carbon $checkInAt, ?Carbon $checkOutAt): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (! $checkOutAt || ! $checkOutAt->gt($checkInAt)) {
            return 0.0;
        }

        return round($checkInAt->diffInMinutes($checkOutAt) / 60, 2);
    }

    private function formatHours(float|int $hours): string
    {
        return number_format((float) $hours, 2).' hrs';
    }

    private function formatVarianceHours(float $hours): string
    {
        if ($hours > 0) {
            return '+'.number_format($hours, 2).' hrs';
        }

        if ($hours < 0) {
            return '-'.number_format(abs($hours), 2).' hrs';
        }

        return number_format(0, 2).' hrs';
    }

    private function statusLabel(float $varianceHours): string
    {
        if ($varianceHours > 0) {
            return 'Overtime';
        }

        if ($varianceHours < 0) {
            return 'Undertime';
        }

        return 'On Target';
    }

    private function statusClass(float $varianceHours): string
    {
        if ($varianceHours > 0) {
            return 'success';
        }

        if ($varianceHours < 0) {
            return 'warning';
        }

        return 'secondary';
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
