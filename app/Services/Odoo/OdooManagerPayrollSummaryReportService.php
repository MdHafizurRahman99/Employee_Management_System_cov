<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;

class OdooManagerPayrollSummaryReportService
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

    private ?array $payslipFields = null;

    private ?array $payslipLineFields = null;

    private ?array $payrollAvailability = null;

    private array $modelAvailability = [];

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{
     *     payrollAvailable:bool,
     *     payrollMessage:?string,
     *     companies:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>,
     *     comparison:array<string, mixed>,
     *     companyBreakdown:array<int, array<string, mixed>>,
     *     roleBreakdown:array<int, array<string, mixed>>
     * }
     */
    public function getReportPageData(User $manager, Carbon $month, ?int $companyId = null): array
    {
        $month = $month->copy()->startOfMonth();
        $availability = $this->payrollAvailability();

        if (! $availability['available']) {
            return [
                'payrollAvailable' => false,
                'payrollMessage' => $availability['message'],
                'companies' => [],
                'summary' => $this->emptySummary($month),
                'comparison' => $this->emptyComparison($month),
                'companyBreakdown' => [],
                'roleBreakdown' => [],
            ];
        }

        $employees = $this->getManagedEmployees($manager);
        $companies = $this->extractCompanies($employees);

        if ($companyId !== null && ! in_array($companyId, array_column($companies, 'id'), true)) {
            throw new OdooException('The selected company is not available in your payroll summary team.');
        }

        $filteredEmployees = $employees;

        if ($companyId !== null) {
            $filteredEmployees = array_values(array_filter(
                $filteredEmployees,
                fn (array $employee) => (int) ($employee['company_id'] ?? 0) === $companyId
            ));
        }

        if ($filteredEmployees === []) {
            return [
                'payrollAvailable' => true,
                'payrollMessage' => null,
                'companies' => $companies,
                'summary' => $this->emptySummary($month),
                'comparison' => $this->emptyComparison($month),
                'companyBreakdown' => [],
                'roleBreakdown' => [],
            ];
        }

        $currentPayslips = $this->getPayslipsForMonth($filteredEmployees, $month);
        $previousMonth = $month->copy()->subMonthNoOverflow()->startOfMonth();
        $previousPayslips = $this->getPayslipsForMonth($filteredEmployees, $previousMonth);

        return [
            'payrollAvailable' => true,
            'payrollMessage' => null,
            'companies' => $companies,
            'summary' => $this->summarizePayslips($currentPayslips, $month),
            'comparison' => $this->buildComparison($currentPayslips, $previousPayslips, $month, $previousMonth),
            'companyBreakdown' => $this->buildCompanyBreakdown($currentPayslips),
            'roleBreakdown' => $this->buildRoleBreakdown($currentPayslips),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $employees
     * @return array<int, array<string, mixed>>
     */
    private function getPayslipsForMonth(array $employees, Carbon $month): array
    {
        $employeeIds = array_values(array_unique(array_filter(array_map(
            fn (array $employee) => (int) ($employee['id'] ?? 0),
            $employees
        ))));

        if ($employeeIds === []) {
            return [];
        }

        $employeeMap = [];

        foreach ($employees as $employee) {
            $employeeMap[(int) $employee['id']] = $employee;
        }

        $fields = $this->payslipFields();
        $dateField = $this->resolveField($fields, ['date_to', 'date_end', 'date_from', 'date_start']);

        if (! $dateField) {
            throw new OdooException('The connected Odoo payroll model is missing the required payslip date fields.');
        }

        $records = $this->serviceAccount->executeKw(
            'hr.payslip',
            'search_read',
            [[
                ['employee_id', 'in', $employeeIds],
                [$dateField, '>=', $month->copy()->startOfMonth()->format('Y-m-d')],
                [$dateField, '<=', $month->copy()->endOfMonth()->format('Y-m-d')],
            ]],
            [
                'fields' => $this->requestedPayslipFields($fields),
                'order' => $dateField.' desc, id desc',
                'limit' => 500,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return $this->normalizePayslips($records, $employeeMap);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $employeeMap
     * @return array<int, array<string, mixed>>
     */
    private function normalizePayslips(array $records, array $employeeMap): array
    {
        $lineIds = [];

        foreach ($records as $record) {
            if (isset($record['line_ids']) && is_array($record['line_ids'])) {
                foreach ($record['line_ids'] as $lineId) {
                    if (is_numeric($lineId)) {
                        $lineIds[] = (int) $lineId;
                    }
                }
            }
        }

        $lineMap = $this->getPayslipLineMap($lineIds);
        $fields = $this->payslipFields();
        $dateFromField = $this->resolveField($fields, ['date_from', 'date_start']);
        $dateToField = $this->resolveField($fields, ['date_to', 'date_end']);

        return array_values(array_filter(array_map(function (mixed $record) use ($employeeMap, $lineMap, $dateFromField, $dateToField): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $employee = $this->extractManyToOne($record['employee_id'] ?? null);
            $employeeData = $employee['id'] ? ($employeeMap[$employee['id']] ?? null) : null;

            if (! $employeeData) {
                return null;
            }

            $company = $this->extractManyToOne($record['company_id'] ?? null);
            $periodStart = $dateFromField ? $this->parseDate($record[$dateFromField] ?? null) : null;
            $periodEnd = $dateToField ? $this->parseDate($record[$dateToField] ?? null) : null;
            $totals = $this->extractPayslipTotals($record, $lineMap[(int) $record['id']] ?? []);

            return [
                'id' => (int) $record['id'],
                'employee_id' => (int) $employeeData['id'],
                'employee' => (string) $employeeData['name'],
                'company_id' => $company['id'] ?? $employeeData['company_id'],
                'company' => $company['name'] ?? $employeeData['company'],
                'role' => (string) ($employeeData['role'] ?? 'Unassigned Role'),
                'gross_pay' => $totals['gross_pay'],
                'deductions' => $totals['deductions'],
                'net_pay' => $totals['net_pay'],
                'period_label' => $periodStart && $periodEnd
                    ? $periodStart->format('d-m-Y').' - '.$periodEnd->format('d-m-Y')
                    : 'N/A',
            ];
        }, $records)));
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function getPayslipLineMap(array $lineIds): array
    {
        $lineIds = array_values(array_unique(array_filter($lineIds, fn (mixed $id) => is_int($id) && $id > 0)));

        if ($lineIds === [] || ! $this->modelExists('hr.payslip.line')) {
            return [];
        }

        $fields = $this->payslipLineFields();
        $records = $this->serviceAccount->executeKw(
            'hr.payslip.line',
            'search_read',
            [[['id', 'in', $lineIds]]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $this->resolveField($fields, ['slip_id']),
                    $this->resolveField($fields, ['name']),
                    $this->resolveField($fields, ['code']),
                    $this->resolveField($fields, ['total']),
                    $this->resolveField($fields, ['amount']),
                    $this->resolveField($fields, ['category_id']),
                ]))),
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        $grouped = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $slip = $this->extractManyToOne($record['slip_id'] ?? null);

            if (! $slip['id']) {
                continue;
            }

            $category = $this->extractManyToOne($record['category_id'] ?? null);
            $grouped[$slip['id']][] = [
                'name' => (string) ($record['name'] ?? ''),
                'code' => (string) ($record['code'] ?? ''),
                'category' => (string) ($category['name'] ?? ''),
                'total' => $this->toFloat($record['total'] ?? $record['amount'] ?? null),
            ];
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{gross_pay:float,deductions:float,net_pay:float}
     */
    private function extractPayslipTotals(array $record, array $lines): array
    {
        $grossPay = $this->toFloat($record['gross_wage'] ?? null);
        $netPay = $this->toFloat($record['net_wage'] ?? null);
        $deductions = 0.0;

        foreach ($lines as $line) {
            $lineTotal = $this->toFloat($line['total'] ?? null);

            if ($lineTotal === null) {
                continue;
            }

            $code = strtoupper(trim((string) ($line['code'] ?? '')));
            $category = strtoupper(trim((string) ($line['category'] ?? '')));
            $name = strtoupper(trim((string) ($line['name'] ?? '')));

            if ($grossPay === null && ($code === 'GROSS' || str_contains($category, 'GROSS') || str_contains($name, 'GROSS'))) {
                $grossPay = $lineTotal;
            }

            if ($netPay === null && ($code === 'NET' || str_contains($category, 'NET') || $name === 'NET')) {
                $netPay = $lineTotal;
            }

            if ($lineTotal < 0) {
                $deductions += abs($lineTotal);
            }
        }

        if ($deductions === 0.0 && $grossPay !== null && $netPay !== null) {
            $deductions = max($grossPay - $netPay, 0.0);
        }

        if ($grossPay === null && $netPay !== null && $deductions > 0) {
            $grossPay = $netPay + $deductions;
        }

        return [
            'gross_pay' => round($grossPay ?? 0.0, 2),
            'deductions' => round($deductions, 2),
            'net_pay' => round($netPay ?? max(($grossPay ?? 0.0) - $deductions, 0.0), 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $payslips
     * @return array<string, mixed>
     */
    private function summarizePayslips(array $payslips, Carbon $month): array
    {
        $grossTotal = round(array_reduce(
            $payslips,
            fn (float $carry, array $payslip) => $carry + (float) ($payslip['gross_pay'] ?? 0.0),
            0.0
        ), 2);

        $deductionsTotal = round(array_reduce(
            $payslips,
            fn (float $carry, array $payslip) => $carry + (float) ($payslip['deductions'] ?? 0.0),
            0.0
        ), 2);

        $netTotal = round(array_reduce(
            $payslips,
            fn (float $carry, array $payslip) => $carry + (float) ($payslip['net_pay'] ?? 0.0),
            0.0
        ), 2);

        return [
            'month_label' => $month->format('F Y'),
            'payslip_count' => count($payslips),
            'employees_count' => count(array_unique(array_filter(array_map(
                fn (array $payslip) => (int) ($payslip['employee_id'] ?? 0),
                $payslips
            )))),
            'gross_total' => $grossTotal,
            'gross_total_label' => number_format($grossTotal, 2),
            'deductions_total' => $deductionsTotal,
            'deductions_total_label' => number_format($deductionsTotal, 2),
            'net_total' => $netTotal,
            'net_total_label' => number_format($netTotal, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $currentPayslips
     * @param  array<int, array<string, mixed>>  $previousPayslips
     * @return array<string, mixed>
     */
    private function buildComparison(array $currentPayslips, array $previousPayslips, Carbon $currentMonth, Carbon $previousMonth): array
    {
        $currentSummary = $this->summarizePayslips($currentPayslips, $currentMonth);
        $previousSummary = $this->summarizePayslips($previousPayslips, $previousMonth);
        $changeValue = round((float) $currentSummary['gross_total'] - (float) $previousSummary['gross_total'], 2);
        $changePercent = (float) $previousSummary['gross_total'] > 0
            ? round(($changeValue / (float) $previousSummary['gross_total']) * 100, 2)
            : null;

        return [
            'current_month_label' => $currentMonth->format('F Y'),
            'previous_month_label' => $previousMonth->format('F Y'),
            'current_gross_total' => $currentSummary['gross_total'],
            'current_gross_total_label' => $currentSummary['gross_total_label'],
            'previous_gross_total' => $previousSummary['gross_total'],
            'previous_gross_total_label' => $previousSummary['gross_total_label'],
            'change_value' => $changeValue,
            'change_value_label' => $this->formatDeltaCurrency($changeValue),
            'change_percent' => $changePercent,
            'change_percent_label' => $changePercent === null ? 'N/A' : number_format($changePercent, 2).'%',
            'direction_label' => $changeValue > 0 ? 'Increase' : ($changeValue < 0 ? 'Decrease' : 'No Change'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $payslips
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanyBreakdown(array $payslips): array
    {
        $breakdown = [];

        foreach ($payslips as $payslip) {
            $key = (string) ($payslip['company_id'] ?? '0');
            $breakdown[$key] ??= [
                'company_id' => $payslip['company_id'] ?? null,
                'company' => $payslip['company'] ?? 'N/A',
                'payslip_count' => 0,
                'employee_ids' => [],
                'gross_total' => 0.0,
                'deductions_total' => 0.0,
                'net_total' => 0.0,
            ];

            $breakdown[$key]['payslip_count']++;
            $breakdown[$key]['employee_ids'][(int) ($payslip['employee_id'] ?? 0)] = true;
            $breakdown[$key]['gross_total'] += (float) ($payslip['gross_pay'] ?? 0.0);
            $breakdown[$key]['deductions_total'] += (float) ($payslip['deductions'] ?? 0.0);
            $breakdown[$key]['net_total'] += (float) ($payslip['net_pay'] ?? 0.0);
        }

        $rows = array_values(array_map(function (array $row): array {
            $row['employees_count'] = count($row['employee_ids']);
            $row['gross_total'] = round((float) $row['gross_total'], 2);
            $row['gross_total_label'] = number_format((float) $row['gross_total'], 2);
            $row['deductions_total'] = round((float) $row['deductions_total'], 2);
            $row['deductions_total_label'] = number_format((float) $row['deductions_total'], 2);
            $row['net_total'] = round((float) $row['net_total'], 2);
            $row['net_total_label'] = number_format((float) $row['net_total'], 2);
            unset($row['employee_ids']);

            return $row;
        }, $breakdown));

        usort($rows, fn (array $left, array $right) => strcmp($left['company'], $right['company']));

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payslips
     * @return array<int, array<string, mixed>>
     */
    private function buildRoleBreakdown(array $payslips): array
    {
        $breakdown = [];

        foreach ($payslips as $payslip) {
            $role = (string) ($payslip['role'] ?? 'Unassigned Role');
            $breakdown[$role] ??= [
                'role' => $role,
                'payslip_count' => 0,
                'employee_ids' => [],
                'gross_total' => 0.0,
                'deductions_total' => 0.0,
                'net_total' => 0.0,
            ];

            $breakdown[$role]['payslip_count']++;
            $breakdown[$role]['employee_ids'][(int) ($payslip['employee_id'] ?? 0)] = true;
            $breakdown[$role]['gross_total'] += (float) ($payslip['gross_pay'] ?? 0.0);
            $breakdown[$role]['deductions_total'] += (float) ($payslip['deductions'] ?? 0.0);
            $breakdown[$role]['net_total'] += (float) ($payslip['net_pay'] ?? 0.0);
        }

        $rows = array_values(array_map(function (array $row): array {
            $row['employees_count'] = count($row['employee_ids']);
            $row['gross_total'] = round((float) $row['gross_total'], 2);
            $row['gross_total_label'] = number_format((float) $row['gross_total'], 2);
            $row['deductions_total'] = round((float) $row['deductions_total'], 2);
            $row['deductions_total_label'] = number_format((float) $row['deductions_total'], 2);
            $row['net_total'] = round((float) $row['net_total'], 2);
            $row['net_total_label'] = number_format((float) $row['net_total'], 2);
            unset($row['employee_ids']);

            return $row;
        }, $breakdown));

        usort($rows, fn (array $left, array $right) => strcmp($left['role'], $right['role']));

        return $rows;
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
            $this->resolveField($fields, ['job_title']),
            $this->resolveField($fields, ['job_id']),
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
                $job = $this->extractManyToOne($record['job_id'] ?? null);
                $role = trim((string) ($record['job_title'] ?? ''));

                if ($role === '') {
                    $role = $job['name'] ?? 'Unassigned Role';
                }

                $employeesById[(int) $record['id']] = [
                    'id' => (int) $record['id'],
                    'name' => (string) ($record['name'] ?? 'Employee'),
                    'company_id' => $company['id'],
                    'company' => $company['name'] ?? 'N/A',
                    'role' => $role,
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
     * @return array<string, mixed>
     */
    private function emptySummary(Carbon $month): array
    {
        return [
            'month_label' => $month->format('F Y'),
            'payslip_count' => 0,
            'employees_count' => 0,
            'gross_total' => 0.0,
            'gross_total_label' => number_format(0, 2),
            'deductions_total' => 0.0,
            'deductions_total_label' => number_format(0, 2),
            'net_total' => 0.0,
            'net_total_label' => number_format(0, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyComparison(Carbon $month): array
    {
        $previousMonth = $month->copy()->subMonthNoOverflow()->startOfMonth();

        return [
            'current_month_label' => $month->format('F Y'),
            'previous_month_label' => $previousMonth->format('F Y'),
            'current_gross_total' => 0.0,
            'current_gross_total_label' => number_format(0, 2),
            'previous_gross_total' => 0.0,
            'previous_gross_total_label' => number_format(0, 2),
            'change_value' => 0.0,
            'change_value_label' => $this->formatDeltaCurrency(0.0),
            'change_percent' => null,
            'change_percent_label' => 'N/A',
            'direction_label' => 'No Change',
        ];
    }

    /**
     * @return array{available:bool,message:?string}
     */
    private function payrollAvailability(): array
    {
        if ($this->payrollAvailability !== null) {
            return $this->payrollAvailability;
        }

        if ($this->modelExists('hr.payslip')) {
            return $this->payrollAvailability = [
                'available' => true,
                'message' => null,
            ];
        }

        $payrollModuleState = $this->getModuleState('hr_payroll');

        if ($payrollModuleState === 'installed') {
            return $this->payrollAvailability = [
                'available' => false,
                'message' => 'The configured Odoo account cannot access the payroll models required for payroll summary reporting.',
            ];
        }

        return $this->payrollAvailability = [
            'available' => false,
            'message' => 'Odoo payroll is unavailable because the Payroll module is not installed in the connected database.',
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

    private function payslipFields(): array
    {
        if ($this->payslipFields !== null) {
            return $this->payslipFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.payslip',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->payslipFields = is_array($fields) ? $fields : [];

        return $this->payslipFields;
    }

    private function payslipLineFields(): array
    {
        if ($this->payslipLineFields !== null) {
            return $this->payslipLineFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.payslip.line',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->payslipLineFields = is_array($fields) ? $fields : [];

        return $this->payslipLineFields;
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
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    private function requestedPayslipFields(array $fields): array
    {
        return array_values(array_filter(array_unique([
            'id',
            isset($fields['employee_id']) ? 'employee_id' : null,
            isset($fields['company_id']) ? 'company_id' : null,
            isset($fields['line_ids']) ? 'line_ids' : null,
            $this->resolveField($fields, ['date_from', 'date_start']),
            $this->resolveField($fields, ['date_to', 'date_end']),
            $this->resolveField($fields, ['gross_wage']),
            $this->resolveField($fields, ['net_wage']),
        ])));
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

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, config('app.timezone'))->startOfDay();
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

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function formatDeltaCurrency(float $value): string
    {
        if ($value > 0) {
            return '+'.number_format($value, 2);
        }

        if ($value < 0) {
            return '-'.number_format(abs($value), 2);
        }

        return number_format(0, 2);
    }
}
