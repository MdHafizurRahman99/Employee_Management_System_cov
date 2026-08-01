<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;

class OdooManagerPayrollService
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

    private ?array $contractFields = null;

    private array $modelAvailability = [];

    private ?array $payrollAvailability = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{
     *     payrollAvailable:bool,
     *     payrollMessage:?string,
     *     employees:array<int, array<string, mixed>>,
     *     recentPayslips:array<int, array<string, mixed>>
     * }
     */
    public function getPayslipGenerationPageData(): array
    {
        $availability = $this->payrollAvailability();

        if (! $availability['available']) {
            return [
                'payrollAvailable' => false,
                'payrollMessage' => $availability['message'],
                'employees' => [],
                'recentPayslips' => [],
            ];
        }

        return [
            'payrollAvailable' => true,
            'payrollMessage' => $availability['message'],
            'employees' => $this->getSelectableEmployees(),
            'recentPayslips' => $this->getRecentPayslips(),
        ];
    }

    /**
     * @return array{
     *     payrollAvailable:bool,
     *     payrollMessage:?string,
     *     payslips:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public function getEmployeePayHistoryPageData(User $user): array
    {
        $availability = $this->payrollAvailability();

        if (! $availability['available']) {
            return [
                'payrollAvailable' => false,
                'payrollMessage' => $availability['message'],
                'payslips' => [],
                'summary' => $this->emptyPayslipSummary(),
            ];
        }

        $employeeId = (int) ($user->odoo_employee_id ?? 0);

        if ($employeeId < 1) {
            return [
                'payrollAvailable' => true,
                'payrollMessage' => null,
                'payslips' => [],
                'summary' => $this->emptyPayslipSummary(),
            ];
        }

        $payslips = $this->getPayslipsForEmployees([$employeeId], 100);

        return [
            'payrollAvailable' => true,
            'payrollMessage' => null,
            'payslips' => $payslips,
            'summary' => $this->summarizePayslips($payslips),
        ];
    }

    /**
     * @return array{
     *     payrollAvailable:bool,
     *     payrollMessage:?string,
     *     employees:array<int, array<string, mixed>>,
     *     payslips:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public function getTeamPayHistoryPageData(User $manager, ?int $employeeId = null): array
    {
        $availability = $this->payrollAvailability();

        if (! $availability['available']) {
            return [
                'payrollAvailable' => false,
                'payrollMessage' => $availability['message'],
                'employees' => [],
                'payslips' => [],
                'summary' => $this->emptyPayslipSummary(),
            ];
        }

        $employees = $this->getManagedEmployees($manager);

        if ($employees === []) {
            return [
                'payrollAvailable' => true,
                'payrollMessage' => null,
                'employees' => [],
                'payslips' => [],
                'summary' => $this->emptyPayslipSummary(),
            ];
        }

        $allowedEmployeeIds = array_column($employees, 'id');

        if ($employeeId !== null && ! in_array($employeeId, $allowedEmployeeIds, true)) {
            throw new OdooException('The selected employee is not available in your payroll team.');
        }

        $payslips = $this->getPayslipsForEmployees(
            $employeeId !== null ? [$employeeId] : $allowedEmployeeIds,
            150
        );

        return [
            'payrollAvailable' => true,
            'payrollMessage' => null,
            'employees' => $employees,
            'payslips' => $payslips,
            'summary' => $this->summarizePayslips($payslips),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPayslip(array $data): array
    {
        $availability = $this->payrollAvailability();

        if (! $availability['available']) {
            throw new OdooException($availability['message'] ?? 'Payroll is unavailable right now.');
        }

        $employee = $this->findById($this->getSelectableEmployees(), (int) $data['employee_id']);

        if (! $employee) {
            throw new OdooException('Please choose a valid employee.');
        }

        $periodStart = $this->parsePeriodDate((string) $data['period_start']);
        $periodEnd = $this->parsePeriodDate((string) $data['period_end']);

        if (! $periodStart || ! $periodEnd) {
            throw new OdooException('Please provide a valid pay period.');
        }

        if ($periodEnd->lt($periodStart)) {
            throw new OdooException('The pay period end date must be the same as or later than the start date.');
        }

        $fields = $this->payslipFields();
        $employeeField = $this->resolveField($fields, ['employee_id']);
        $dateFromField = $this->resolveField($fields, ['date_from', 'date_start']);
        $dateToField = $this->resolveField($fields, ['date_to', 'date_end']);

        if (! $employeeField || ! $dateFromField || ! $dateToField) {
            throw new OdooException('The connected Odoo payroll model is missing the required employee or pay period fields.');
        }

        $payload = [
            $employeeField => $employee['id'],
            $dateFromField => $periodStart->format('Y-m-d'),
            $dateToField => $periodEnd->format('Y-m-d'),
        ];

        if (isset($fields['company_id']) && $employee['company_id']) {
            $payload['company_id'] = $employee['company_id'];
        }

        if (isset($fields['name'])) {
            $payload['name'] = sprintf(
                '%s Payslip %s - %s',
                $employee['name'],
                $periodStart->format('d M Y'),
                $periodEnd->format('d M Y')
            );
        }

        $contract = $this->findActiveContractForPeriod($employee['id'], $periodStart, $periodEnd);

        if ($this->modelExists('hr.contract') && isset($fields['contract_id']) && ! $contract) {
            throw new OdooException(
                'The selected employee does not have an active payroll contract for the chosen pay period.'
            );
        }

        if ($contract) {
            if (isset($fields['contract_id'])) {
                $payload['contract_id'] = $contract['id'];
            }

            if (isset($fields['struct_id']) && $contract['struct_id']) {
                $payload['struct_id'] = $contract['struct_id'];
            }

            if (isset($fields['salary_structure_id']) && $contract['salary_structure_id']) {
                $payload['salary_structure_id'] = $contract['salary_structure_id'];
            }

            if (isset($fields['struct_type_id']) && $contract['structure_type_id']) {
                $payload['struct_type_id'] = $contract['structure_type_id'];
            }

            if (isset($fields['salary_structure_type_id']) && $contract['structure_type_id']) {
                $payload['salary_structure_type_id'] = $contract['structure_type_id'];
            }
        }

        $payslipId = $this->serviceAccount->executeKw('hr.payslip', 'create', [$payload]);

        if (! is_numeric($payslipId) || (int) $payslipId < 1) {
            throw new OdooException('Odoo did not confirm the payslip creation.');
        }

        $this->computePayslip((int) $payslipId);

        $payslip = $this->getPayslipById((int) $payslipId);

        if (! $payslip) {
            throw new OdooException('The created payslip could not be reloaded from Odoo.');
        }

        return $payslip;
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
    private function getRecentPayslips(): array
    {
        return $this->getPayslipRecords([], 20);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPayslipById(int $payslipId): ?array
    {
        $records = $this->getPayslipRecords([['id', '=', $payslipId]], 1);

        if (! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return $records[0];
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function getPayslipsForEmployees(array $employeeIds, int $limit = 100): array
    {
        $employeeIds = array_values(array_unique(array_filter(
            $employeeIds,
            fn (mixed $employeeId) => is_int($employeeId) && $employeeId > 0
        )));

        if ($employeeIds === []) {
            return [];
        }

        return $this->getPayslipRecords([
            ['employee_id', 'in', $employeeIds],
        ], $limit);
    }

    /**
     * @param  array<int, array<int|string, mixed>>  $domain
     * @return array<int, array<string, mixed>>
     */
    private function getPayslipRecords(array $domain, int $limit = 20): array
    {
        $fields = $this->payslipFields();
        $dateToField = $this->resolveField($fields, ['date_to', 'date_end']);

        $records = $this->serviceAccount->executeKw(
            'hr.payslip',
            'search_read',
            [$domain],
            [
                'fields' => $this->requestedPayslipFields($fields),
                'order' => $dateToField ? $dateToField.' desc, id desc' : 'id desc',
                'limit' => $limit,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return $this->normalizePayslips($records);
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function normalizePayslips(array $records): array
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
        $dateFromField = $this->resolveField($this->payslipFields(), ['date_from', 'date_start']);
        $dateToField = $this->resolveField($this->payslipFields(), ['date_to', 'date_end']);

        return array_values(array_filter(array_map(function (mixed $record) use ($lineMap, $dateFromField, $dateToField): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $employee = $this->extractManyToOne($record['employee_id'] ?? null);
            $company = $this->extractManyToOne($record['company_id'] ?? null);
            $currency = $this->extractManyToOne($record['currency_id'] ?? null);
            $periodStart = $dateFromField ? $this->parseDate($record[$dateFromField] ?? null) : null;
            $periodEnd = $dateToField ? $this->parseDate($record[$dateToField] ?? null) : null;
            $writeDate = is_string($record['write_date'] ?? null)
                ? (string) $record['write_date']
                : (is_string($record['create_date'] ?? null) ? (string) $record['create_date'] : '');
            $totals = $this->extractPayslipTotals(
                $record,
                $lineMap[(int) $record['id']] ?? []
            );

            return [
                'id' => (int) $record['id'],
                'employee' => $employee['name'] ?? 'Employee',
                'employee_id' => $employee['id'],
                'company' => $company['name'] ?? 'N/A',
                'company_id' => $company['id'],
                'currency' => $currency['name'],
                'state' => (string) ($record['state'] ?? 'draft'),
                'state_label' => $this->formatStateLabel((string) ($record['state'] ?? 'draft')),
                'number' => trim((string) ($record['number'] ?? $record['name'] ?? '')),
                'title' => trim((string) ($record['name'] ?? 'Payslip')),
                'period_start_value' => $periodStart?->format('Y-m-d'),
                'period_end_value' => $periodEnd?->format('Y-m-d'),
                'period_label' => $periodStart && $periodEnd
                    ? $periodStart->format('d M Y').' - '.$periodEnd->format('d M Y')
                    : 'N/A',
                'gross_pay' => $totals['gross_pay'],
                'deductions' => $totals['deductions'],
                'net_pay' => $totals['net_pay'],
                'write_date_value' => $writeDate,
                'updated_label' => $writeDate !== ''
                    ? ($this->parseDateTime($writeDate)?->format('d M Y h:i A') ?? 'N/A')
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

    private function computePayslip(int $payslipId): void
    {
        $supportedMethods = ['compute_sheet', 'action_compute_sheet'];
        $unsupportedMethodError = null;

        foreach ($supportedMethods as $method) {
            try {
                $result = $this->serviceAccount->executeKw('hr.payslip', $method, [[$payslipId]]);

                if ($result === false) {
                    throw new OdooException('Odoo did not confirm the payslip computation.');
                }

                return;
            } catch (OdooException $exception) {
                if ($this->isUnsupportedMethodMessage($exception->getMessage())) {
                    $unsupportedMethodError = $exception;

                    continue;
                }

                throw $exception;
            }
        }

        if ($unsupportedMethodError) {
            throw new OdooException(
                'The connected Odoo payroll version does not expose a supported payslip computation action.'
            );
        }
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
                'message' => 'The configured Odoo account cannot access the payroll models required to generate payslips.',
            ];
        }

        return $this->payrollAvailability = [
            'available' => false,
            'message' => 'Odoo payroll is unavailable because the Payroll module is not installed in the connected database.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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

    /**
     * @return array<string, array<string, mixed>>
     */
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

    /**
     * @return array<string, array<string, mixed>>
     */
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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function contractFields(): array
    {
        if ($this->contractFields !== null) {
            return $this->contractFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.contract',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->contractFields = is_array($fields) ? $fields : [];

        return $this->contractFields;
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

        uasort($employeesById, fn (array $left, array $right) => strcmp($left['name'], $right['name']));

        return array_values($employeesById);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findActiveContractForPeriod(int $employeeId, Carbon $periodStart, Carbon $periodEnd): ?array
    {
        if (! $this->modelExists('hr.contract')) {
            return null;
        }

        $fields = $this->contractFields();
        $records = $this->serviceAccount->executeKw(
            'hr.contract',
            'search_read',
            [[['employee_id', '=', $employeeId]]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $this->resolveField($fields, ['name']),
                    $this->resolveField($fields, ['state']),
                    $this->resolveField($fields, ['date_start']),
                    $this->resolveField($fields, ['date_end']),
                    $this->resolveField($fields, ['company_id']),
                    $this->resolveField($fields, ['struct_id']),
                    $this->resolveField($fields, ['salary_structure_id']),
                    $this->resolveField($fields, ['structure_type_id', 'salary_structure_type_id']),
                ]))),
                'order' => 'date_start desc, id desc',
                'limit' => 25,
            ]
        );

        if (! is_array($records)) {
            return null;
        }

        foreach ($records as $record) {
            if (! is_array($record) || empty($record['id'])) {
                continue;
            }

            $state = strtolower(trim((string) ($record['state'] ?? '')));

            if ($state !== '' && ! in_array($state, ['open', 'close'], true)) {
                continue;
            }

            $contractStart = $this->parseDate($record['date_start'] ?? null);
            $contractEnd = $this->parseDate($record['date_end'] ?? null);

            if ($contractStart && $contractStart->gt($periodEnd)) {
                continue;
            }

            if ($contractEnd && $contractEnd->lt($periodStart)) {
                continue;
            }

            $company = $this->extractManyToOne($record['company_id'] ?? null);
            $structure = $this->extractManyToOne($record['struct_id'] ?? null);
            $salaryStructure = $this->extractManyToOne($record['salary_structure_id'] ?? null);
            $structureType = $this->extractManyToOne(
                $record['structure_type_id'] ?? $record['salary_structure_type_id'] ?? null
            );

            return [
                'id' => (int) $record['id'],
                'company_id' => $company['id'],
                'struct_id' => $structure['id'],
                'salary_structure_id' => $salaryStructure['id'],
                'structure_type_id' => $structureType['id'],
            ];
        }

        return null;
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
            isset($fields['currency_id']) ? 'currency_id' : null,
            isset($fields['state']) ? 'state' : null,
            isset($fields['write_date']) ? 'write_date' : null,
            isset($fields['create_date']) ? 'create_date' : null,
            isset($fields['line_ids']) ? 'line_ids' : null,
            $this->resolveField($fields, ['name']),
            $this->resolveField($fields, ['number']),
            $this->resolveField($fields, ['date_from', 'date_start']),
            $this->resolveField($fields, ['date_to', 'date_end']),
            $this->resolveField($fields, ['gross_wage']),
            $this->resolveField($fields, ['net_wage']),
        ])));
    }

    /**
     * @param  array<int, array<string, mixed>>  $payslips
     * @return array<string, mixed>
     */
    private function summarizePayslips(array $payslips): array
    {
        $grossPayTotal = round(array_reduce(
            $payslips,
            fn (float $carry, array $payslip) => $carry + (float) ($payslip['gross_pay'] ?? 0.0),
            0.0
        ), 2);

        $deductionsTotal = round(array_reduce(
            $payslips,
            fn (float $carry, array $payslip) => $carry + (float) ($payslip['deductions'] ?? 0.0),
            0.0
        ), 2);

        $netPayTotal = round(array_reduce(
            $payslips,
            fn (float $carry, array $payslip) => $carry + (float) ($payslip['net_pay'] ?? 0.0),
            0.0
        ), 2);

        return [
            'payslip_count' => count($payslips),
            'employees_count' => count(array_unique(array_filter(array_map(
                fn (array $payslip) => (int) ($payslip['employee_id'] ?? 0),
                $payslips
            )))),
            'gross_pay_total' => $grossPayTotal,
            'gross_pay_total_label' => number_format($grossPayTotal, 2),
            'deductions_total' => $deductionsTotal,
            'deductions_total_label' => number_format($deductionsTotal, 2),
            'net_pay_total' => $netPayTotal,
            'net_pay_total_label' => number_format($netPayTotal, 2),
            'latest_period_label' => $payslips[0]['period_label'] ?? 'N/A',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayslipSummary(): array
    {
        return [
            'payslip_count' => 0,
            'employees_count' => 0,
            'gross_pay_total' => 0.0,
            'gross_pay_total_label' => number_format(0, 2),
            'deductions_total' => 0.0,
            'deductions_total_label' => number_format(0, 2),
            'net_pay_total' => 0.0,
            'net_pay_total_label' => number_format(0, 2),
            'latest_period_label' => 'N/A',
        ];
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

    private function parsePeriodDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value), config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
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

    private function formatStateLabel(string $state): string
    {
        return ucwords(str_replace('_', ' ', trim($state)));
    }

    private function isUnsupportedMethodMessage(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'has no attribute')
            || str_contains($message, 'unknown method')
            || str_contains($message, 'object hr.payslip doesn\'t exist')
            || str_contains($message, 'not a valid')
            || str_contains($message, 'unsupported');
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
