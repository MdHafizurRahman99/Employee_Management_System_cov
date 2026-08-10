<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OdooManagerAttendanceService
{
    private ?array $attendanceFields = null;

    private ?array $employeeFields = null;

    /**
     * Team relationships based on Odoo 19 HR/Attendance rules.
     *
     * @var array<int, string>
     */
    private const TEAM_RELATION_FIELDS = [
        'attendance_manager_id',
        'parent_id.user_id',
        'leave_manager_id',
    ];

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{
     *     employees:array<int, array<string, mixed>>,
     *     records:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public function getTeamAttendancePageData(
        User $manager,
        Carbon $fromDate,
        Carbon $toDate,
        ?int $employeeId = null
    ): array {
        $employees = $this->getManagedEmployees($manager);

        if ($employees === []) {
            return [
                'employees' => [],
                'records' => [],
                'summary' => $this->emptySummary($fromDate, $toDate),
            ];
        }

        $allowedEmployeeIds = array_column($employees, 'id');

        if ($employeeId !== null && ! in_array($employeeId, $allowedEmployeeIds, true)) {
            throw new OdooException('The selected employee is not available in your attendance team.');
        }

        $records = $this->getAttendanceRecords(
            $employeeId !== null ? [$employeeId] : $allowedEmployeeIds,
            $fromDate,
            $toDate
        );

        return [
            'employees' => $employees,
            'records' => $records,
            'summary' => $this->summarizeRecords($records, $fromDate, $toDate),
        ];
    }

    public function correctAttendanceRecord(User $manager, int $attendanceId, array $data): void
    {
        $employees = $this->getManagedEmployees($manager);
        $allowedEmployeeIds = array_column($employees, 'id');

        if ($allowedEmployeeIds === []) {
            throw new OdooException('Team attendance corrections require an Odoo manager account linked to employees.');
        }

        $attendance = $this->getAttendanceRecordById($attendanceId);

        if (! $attendance) {
            throw new OdooException('The selected attendance record could not be found.');
        }

        if (! in_array((int) $attendance['employee_id'], $allowedEmployeeIds, true)) {
            throw new OdooException('You do not have access to correct this attendance record.');
        }

        $lastKnownWriteDate = trim((string) ($data['last_known_write_date'] ?? ''));
        $currentWriteDate = (string) ($attendance['write_date_value'] ?? '');

        if ($lastKnownWriteDate !== '' && $currentWriteDate !== '' && $lastKnownWriteDate !== $currentWriteDate) {
            throw new OdooException('This attendance record was updated by someone else. Please reload the page before trying again.');
        }

        $checkInAt = $this->parseLocalDateTime((string) $data['check_in']);
        $checkOutAt = $this->parseLocalDateTime((string) ($data['check_out'] ?? ''));

        if (! $checkInAt) {
            throw new OdooException('Please provide a valid corrected check-in time.');
        }

        if ($checkOutAt && ! $checkOutAt->gt($checkInAt)) {
            throw new OdooException('The corrected check-out time must be later than check-in.');
        }

        $payload = [
            'check_in' => $this->toOdooDateTime($checkInAt),
            'check_out' => $checkOutAt ? $this->toOdooDateTime($checkOutAt) : false,
        ];

        $result = $this->serviceAccount->executeKw('hr.attendance', 'write', [
            [$attendanceId],
            $payload,
        ]);

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the attendance correction.');
        }

        Log::info('Manager attendance correction submitted.', [
            'manager_local_user_id' => $manager->getKey(),
            'manager_odoo_user_id' => $manager->odoo_user_id,
            'attendance_id' => $attendanceId,
            'employee_id' => $attendance['employee_id'],
            'employee_name' => $attendance['employee'],
            'before' => [
                'check_in' => $attendance['check_in_value'],
                'check_out' => $attendance['check_out_value'],
                'worked_hours' => $attendance['worked_hours'],
            ],
            'after' => [
                'check_in' => $payload['check_in'],
                'check_out' => $payload['check_out'],
            ],
            'note' => trim((string) ($data['correction_note'] ?? '')),
            'corrected_at' => now()->toIso8601String(),
        ]);
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
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function getAttendanceRecords(array $employeeIds, Carbon $fromDate, Carbon $toDate): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $fields = $this->attendanceFields();
        $checkInField = $this->resolveField($fields, ['check_in']);
        $checkOutField = $this->resolveField($fields, ['check_out']);
        $workedHoursField = $this->resolveField($fields, ['worked_hours']);

        if (! $checkInField || ! $checkOutField || ! $workedHoursField) {
            throw new OdooException('The Odoo attendance model does not expose the expected attendance fields.');
        }

        $records = $this->serviceAccount->executeKw(
            'hr.attendance',
            'search_read',
            [[
                ['employee_id', 'in', $employeeIds],
                [$checkInField, '>=', $fromDate->copy()->startOfDay()->toDateTimeString()],
                [$checkInField, '<=', $toDate->copy()->endOfDay()->toDateTimeString()],
            ]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $checkInField,
                    $checkOutField,
                    $workedHoursField,
                    'write_date',
                    $this->resolveField($fields, ['employee_id']),
                ]))),
                'order' => $checkInField.' desc',
                'limit' => 200,
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $record) use ($checkInField, $checkOutField, $workedHoursField): ?array {
            if (! is_array($record) || empty($record['id'])) {
                return null;
            }

            $checkInAt = $this->parseDateTime($record[$checkInField] ?? null);
            $checkOutAt = $this->parseDateTime($record[$checkOutField] ?? null);

            if (! $checkInAt) {
                return null;
            }

            $employee = $this->extractManyToOne($record['employee_id'] ?? null);
            $workedHours = $this->normalizeWorkedHours($record[$workedHoursField] ?? null, $checkInAt, $checkOutAt);
            $missingClockOut = ! $checkOutAt;
            $writeDate = isset($record['write_date']) && is_string($record['write_date']) ? (string) $record['write_date'] : '';

            return [
                'id' => (int) $record['id'],
                'employee_id' => $employee['id'],
                'employee' => $employee['name'] ?? 'Employee',
                'check_in_label' => $checkInAt->format('d-m-Y h:i A'),
                'check_out_label' => $checkOutAt ? $checkOutAt->format('d-m-Y h:i A') : 'Missing clock-out',
                'worked_hours' => $workedHours,
                'worked_hours_label' => $missingClockOut && $workedHours <= 0
                    ? 'Pending'
                    : number_format($workedHours, 2).' hrs',
                'status_label' => $missingClockOut ? 'Missing Clock-out' : 'Complete',
                'status_class' => $missingClockOut ? 'warning' : 'success',
                'missing_clock_out' => $missingClockOut,
                'check_in_value' => $this->toOdooDateTime($checkInAt),
                'check_out_value' => $checkOutAt ? $this->toOdooDateTime($checkOutAt) : '',
                'check_in_form_value' => $checkInAt->format('Y-m-d\TH:i'),
                'check_out_form_value' => $checkOutAt ? $checkOutAt->format('Y-m-d\TH:i') : '',
                'write_date_value' => $writeDate,
                'updated_label' => $writeDate !== '' ? ($this->parseDateTime($writeDate)?->format('d-m-Y h:i A') ?? 'N/A') : 'N/A',
            ];
        }, $records)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getAttendanceRecordById(int $attendanceId): ?array
    {
        $fields = $this->attendanceFields();
        $checkInField = $this->resolveField($fields, ['check_in']);
        $checkOutField = $this->resolveField($fields, ['check_out']);
        $workedHoursField = $this->resolveField($fields, ['worked_hours']);

        if (! $checkInField || ! $checkOutField || ! $workedHoursField) {
            throw new OdooException('The Odoo attendance model does not expose the expected attendance fields.');
        }

        $records = $this->serviceAccount->executeKw(
            'hr.attendance',
            'search_read',
            [[['id', '=', $attendanceId]]],
            [
                'fields' => array_values(array_filter(array_unique([
                    'id',
                    $checkInField,
                    $checkOutField,
                    $workedHoursField,
                    'write_date',
                    $this->resolveField($fields, ['employee_id']),
                ]))),
                'limit' => 1,
            ]
        );

        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return $this->normalizeSingleRecord($records[0], $checkInField, $checkOutField, $workedHoursField);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeSingleRecord(
        array $record,
        string $checkInField,
        string $checkOutField,
        string $workedHoursField
    ): ?array {
        $checkInAt = $this->parseDateTime($record[$checkInField] ?? null);
        $checkOutAt = $this->parseDateTime($record[$checkOutField] ?? null);

        if (! $checkInAt) {
            return null;
        }

        $employee = $this->extractManyToOne($record['employee_id'] ?? null);
        $workedHours = $this->normalizeWorkedHours($record[$workedHoursField] ?? null, $checkInAt, $checkOutAt);
        $missingClockOut = ! $checkOutAt;
        $writeDate = isset($record['write_date']) && is_string($record['write_date']) ? (string) $record['write_date'] : '';

        return [
            'id' => (int) $record['id'],
            'employee_id' => $employee['id'],
            'employee' => $employee['name'] ?? 'Employee',
            'check_in_label' => $checkInAt->format('d-m-Y h:i A'),
            'check_out_label' => $checkOutAt ? $checkOutAt->format('d-m-Y h:i A') : 'Missing clock-out',
            'worked_hours' => $workedHours,
            'worked_hours_label' => $missingClockOut && $workedHours <= 0
                ? 'Pending'
                : number_format($workedHours, 2).' hrs',
            'status_label' => $missingClockOut ? 'Missing Clock-out' : 'Complete',
            'status_class' => $missingClockOut ? 'warning' : 'success',
            'missing_clock_out' => $missingClockOut,
            'check_in_value' => $this->toOdooDateTime($checkInAt),
            'check_out_value' => $checkOutAt ? $this->toOdooDateTime($checkOutAt) : '',
            'check_in_form_value' => $checkInAt->format('Y-m-d\TH:i'),
            'check_out_form_value' => $checkOutAt ? $checkOutAt->format('Y-m-d\TH:i') : '',
            'write_date_value' => $writeDate,
            'updated_label' => $writeDate !== '' ? ($this->parseDateTime($writeDate)?->format('d-m-Y h:i A') ?? 'N/A') : 'N/A',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function summarizeRecords(array $records, Carbon $fromDate, Carbon $toDate): array
    {
        return [
            'range_label' => $fromDate->format('d-m-Y').' - '.$toDate->format('d-m-Y'),
            'records_count' => count($records),
            'employees_count' => count(array_unique(array_map(fn (array $record) => (int) ($record['employee_id'] ?? 0), $records))),
            'missing_clock_out_count' => count(array_filter($records, fn (array $record) => $record['missing_clock_out'])),
            'total_worked_hours' => round(array_reduce(
                $records,
                fn (float $carry, array $record) => $carry + (float) $record['worked_hours'],
                0.0
            ), 2),
            'total_worked_hours_label' => number_format(array_reduce(
                $records,
                fn (float $carry, array $record) => $carry + (float) $record['worked_hours'],
                0.0
            ), 2).' hrs',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(Carbon $fromDate, Carbon $toDate): array
    {
        return [
            'range_label' => $fromDate->format('d-m-Y').' - '.$toDate->format('d-m-Y'),
            'records_count' => 0,
            'employees_count' => 0,
            'missing_clock_out_count' => 0,
            'total_worked_hours' => 0.0,
            'total_worked_hours_label' => number_format(0, 2).' hrs',
        ];
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

    private function parseLocalDateTime(string $value): ?Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d\TH:i', $value, config('app.timezone'));
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

        if (! $checkOutAt) {
            return 0.0;
        }

        return round($checkInAt->diffInMinutes($checkOutAt) / 60, 2);
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
