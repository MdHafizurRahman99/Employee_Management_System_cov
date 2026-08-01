<?php

namespace App\Services\Odoo;

use App\Models\User;
use Illuminate\Support\Str;

class OdooWeeklyAvailabilityService
{
    private const DAY_META = [
        '0' => ['label' => 'Monday', 'short' => 'Mon'],
        '1' => ['label' => 'Tuesday', 'short' => 'Tue'],
        '2' => ['label' => 'Wednesday', 'short' => 'Wed'],
        '3' => ['label' => 'Thursday', 'short' => 'Thu'],
        '4' => ['label' => 'Friday', 'short' => 'Fri'],
        '5' => ['label' => 'Saturday', 'short' => 'Sat'],
        '6' => ['label' => 'Sunday', 'short' => 'Sun'],
    ];

    private const TYPE_META = [
        'available' => ['label' => 'Available', 'class' => 'success'],
        'unavailable' => ['label' => 'Unavailable', 'class' => 'danger'],
    ];

    private ?array $availabilityFields = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{
     *     days:array<int, array<string, mixed>>,
     *     summary:array<string, int>,
     *     entries:array<int, array<string, mixed>>
     * }
     */
    public function getAvailabilityPageData(User $user): array
    {
        $employeeId = $this->resolveEmployeeId($user);

        if (! $employeeId) {
            return $this->emptyPageData();
        }

        $entries = $this->fetchEntriesForEmployee($employeeId);

        return $this->buildPageData($entries);
    }

    public function createAvailability(User $user, array $data): int
    {
        $employeeId = $this->resolveEmployeeId($user);

        if (! $employeeId) {
            throw new OdooException('This account is not linked to an Odoo employee record yet.');
        }

        $availabilityId = $this->serviceAccount->executeKw(
            'hr.employee.weekly.availability',
            'create',
            [$this->buildWritePayload($employeeId, $data)]
        );

        if (! is_numeric($availabilityId) || (int) $availabilityId < 1) {
            throw new OdooException('Odoo did not confirm the weekly availability entry.');
        }

        return (int) $availabilityId;
    }

    public function updateAvailability(User $user, int $availabilityId, array $data): void
    {
        $employeeId = $this->resolveEmployeeId($user);

        if (! $employeeId) {
            throw new OdooException('This account is not linked to an Odoo employee record yet.');
        }

        $entry = $this->findAvailabilityEntry($employeeId, $availabilityId);

        if (! $entry) {
            throw new OdooException('The selected weekly availability entry could not be found.');
        }

        $result = $this->serviceAccount->executeKw(
            'hr.employee.weekly.availability',
            'write',
            [
                [$availabilityId],
                $this->buildWritePayload($employeeId, $data),
            ]
        );

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the weekly availability update.');
        }
    }

    public function deleteAvailability(User $user, int $availabilityId): void
    {
        $employeeId = $this->resolveEmployeeId($user);

        if (! $employeeId) {
            throw new OdooException('This account is not linked to an Odoo employee record yet.');
        }

        $entry = $this->findAvailabilityEntry($employeeId, $availabilityId);

        if (! $entry) {
            throw new OdooException('The selected weekly availability entry could not be found.');
        }

        $result = $this->serviceAccount->executeKw(
            'hr.employee.weekly.availability',
            'unlink',
            [[$availabilityId]]
        );

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the weekly availability deletion.');
        }
    }

    private function resolveEmployeeId(User $user): ?int
    {
        return filled($user->odoo_employee_id) ? (int) $user->odoo_employee_id : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchEntriesForEmployee(int $employeeId): array
    {
        $fields = $this->availabilityFields();
        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $this->resolveField($fields, ['employee_id']),
            $this->resolveField($fields, ['sequence']),
            $this->resolveField($fields, ['day_of_week']),
            $this->resolveField($fields, ['availability_type']),
            $this->resolveField($fields, ['is_full_day']),
            $this->resolveField($fields, ['start_time']),
            $this->resolveField($fields, ['end_time']),
            $this->resolveField($fields, ['time_range_display']),
            $this->resolveField($fields, ['summary']),
            $this->resolveField($fields, ['write_date']),
        ])));

        $records = $this->serviceAccount->executeKw(
            'hr.employee.weekly.availability',
            'search_read',
            [[['employee_id', '=', $employeeId]]],
            [
                'fields' => $requestedFields,
                'order' => 'day_of_week asc, sequence asc, start_time asc, end_time asc, id asc',
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $record) => is_array($record) ? $this->normalizeEntry($record) : null,
            $records
        )));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAvailabilityEntry(int $employeeId, int $availabilityId): ?array
    {
        $records = $this->serviceAccount->executeKw(
            'hr.employee.weekly.availability',
            'search_read',
            [[
                ['id', '=', $availabilityId],
                ['employee_id', '=', $employeeId],
            ]],
            [
                'fields' => ['id', 'employee_id'],
                'limit' => 1,
            ]
        );

        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            return null;
        }

        return $records[0];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeEntry(array $record): ?array
    {
        $dayKey = isset($record['day_of_week']) ? (string) $record['day_of_week'] : null;
        $typeKey = isset($record['availability_type']) ? (string) $record['availability_type'] : null;

        if ($dayKey === null || $dayKey === '' || ! isset(self::DAY_META[$dayKey]) || ! $typeKey || ! isset(self::TYPE_META[$typeKey])) {
            return null;
        }

        $isFullDay = (bool) ($record['is_full_day'] ?? false);
        $startTime = isset($record['start_time']) && is_numeric($record['start_time']) ? (float) $record['start_time'] : null;
        $endTime = isset($record['end_time']) && is_numeric($record['end_time']) ? (float) $record['end_time'] : null;

        return [
            'id' => isset($record['id']) ? (int) $record['id'] : 0,
            'day_of_week' => $dayKey,
            'day_label' => self::DAY_META[$dayKey]['label'],
            'day_short_label' => self::DAY_META[$dayKey]['short'],
            'availability_type' => $typeKey,
            'availability_label' => self::TYPE_META[$typeKey]['label'],
            'availability_class' => self::TYPE_META[$typeKey]['class'],
            'is_full_day' => $isFullDay,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'time_label' => $this->resolveTimeLabel($record, $isFullDay, $startTime, $endTime),
            'summary' => isset($record['summary']) ? (string) $record['summary'] : '',
        ];
    }

    private function resolveTimeLabel(array $record, bool $isFullDay, ?float $startTime, ?float $endTime): string
    {
        if (isset($record['time_range_display']) && is_string($record['time_range_display']) && trim($record['time_range_display']) !== '') {
            return trim($record['time_range_display']);
        }

        if ($isFullDay) {
            return 'Full day';
        }

        if ($startTime === null || $endTime === null) {
            return 'Time not set';
        }

        return sprintf('%s - %s', $this->formatFloatHour($startTime), $this->formatFloatHour($endTime));
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
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{
     *     days:array<int, array<string, mixed>>,
     *     summary:array<string, int>,
     *     entries:array<int, array<string, mixed>>
     * }
     */
    private function buildPageData(array $entries): array
    {
        $entriesByDay = [];

        foreach ($entries as $entry) {
            $entriesByDay[$entry['day_of_week']][] = $entry;
        }

        $days = [];
        $summary = [
            'configured_days' => 0,
            'total_rules' => count($entries),
            'available_rules' => count(array_filter($entries, fn (array $entry): bool => $entry['availability_type'] === 'available')),
            'unavailable_rules' => count(array_filter($entries, fn (array $entry): bool => $entry['availability_type'] === 'unavailable')),
            'full_day_rules' => count(array_filter($entries, fn (array $entry): bool => $entry['is_full_day'] === true)),
        ];

        foreach (self::DAY_META as $dayKey => $meta) {
            $dayEntries = $entriesByDay[$dayKey] ?? [];

            if ($dayEntries !== []) {
                $summary['configured_days']++;
            }

            $days[] = [
                'key' => $dayKey,
                'label' => $meta['label'],
                'short_label' => $meta['short'],
                'entries' => $dayEntries,
                'entry_count' => count($dayEntries),
                'has_rules' => $dayEntries !== [],
                'status_label' => $this->resolveDayStatusLabel($dayEntries),
                'status_class' => $this->resolveDayStatusClass($dayEntries),
            ];
        }

        return [
            'days' => $days,
            'summary' => $summary,
            'entries' => $entries,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function resolveDayStatusLabel(array $entries): string
    {
        if ($entries === []) {
            return 'Not configured';
        }

        $fullDayAvailable = collect($entries)->contains(fn (array $entry): bool => $entry['availability_type'] === 'available' && $entry['is_full_day']);
        $fullDayUnavailable = collect($entries)->contains(fn (array $entry): bool => $entry['availability_type'] === 'unavailable' && $entry['is_full_day']);

        if ($fullDayAvailable) {
            return 'Open all day';
        }

        if ($fullDayUnavailable) {
            return 'Blocked all day';
        }

        return count($entries) === 1 ? '1 custom rule' : count($entries) . ' custom rules';
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function resolveDayStatusClass(array $entries): string
    {
        if ($entries === []) {
            return 'muted';
        }

        $availableCount = count(array_filter($entries, fn (array $entry): bool => $entry['availability_type'] === 'available'));
        $unavailableCount = count(array_filter($entries, fn (array $entry): bool => $entry['availability_type'] === 'unavailable'));

        if ($availableCount > 0 && $unavailableCount === 0) {
            return 'success';
        }

        if ($unavailableCount > 0 && $availableCount === 0) {
            return 'danger';
        }

        return 'primary';
    }

    /**
     * @return array{
     *     days:array<int, array<string, mixed>>,
     *     summary:array<string, int>,
     *     entries:array<int, array<string, mixed>>
     * }
     */
    private function emptyPageData(): array
    {
        return $this->buildPageData([]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildWritePayload(int $employeeId, array $data): array
    {
        $isFullDay = (bool) ($data['is_full_day'] ?? false);

        return [
            'employee_id' => $employeeId,
            'day_of_week' => (string) $data['day_of_week'],
            'availability_type' => (string) $data['availability_type'],
            'is_full_day' => $isFullDay,
            'start_time' => $isFullDay ? false : (float) $data['start_time'],
            'end_time' => $isFullDay ? false : (float) $data['end_time'],
        ];
    }

    private function availabilityFields(): array
    {
        if ($this->availabilityFields !== null) {
            return $this->availabilityFields;
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
        } catch (OdooException $exception) {
            if (Str::contains(Str::lower($exception->getMessage()), [
                'hr.employee.weekly.availability',
                'object',
                'model',
                'not exist',
            ])) {
                throw new OdooException('Weekly availability is not enabled in Odoo for this environment yet.', 0, $exception);
            }

            throw $exception;
        }

        $this->availabilityFields = is_array($fields) ? $fields : [];

        return $this->availabilityFields;
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
}
