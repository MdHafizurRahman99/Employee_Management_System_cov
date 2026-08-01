<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OdooEmployeeScheduleEntryService
{
    private const MODEL = 'hr.employee.schedule.entry';

    private ?array $fields = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function getForUserMonth(User $user, Carbon $month): array
    {
        $employeeId = $this->employeeId($user);

        if (! $employeeId) {
            return [];
        }

        return $this->fetchEntries([
            ['employee_id', '=', $employeeId],
            ['entry_date', '>=', $month->copy()->startOfMonth()->toDateString()],
            ['entry_date', '<=', $month->copy()->endOfMonth()->toDateString()],
            ['active', '=', true],
        ]);
    }

    /**
     * @return array{
     *   entries:array<int,array<string,mixed>>,
     *   by_employee_date:array<int,array<string,array<int,array<string,mixed>>>>,
     *   by_date:array<string,array<int,array<string,mixed>>>,
     *   count:int
     * }
     */
    public function getForManagerRange(Carbon $start, Carbon $end, ?array $employeeIds = null): array
    {
        if ($employeeIds === []) {
            return [
                'entries' => [],
                'by_employee_date' => [],
                'by_date' => [],
                'count' => 0,
            ];
        }

        $domain = [
            ['entry_date', '>=', $start->toDateString()],
            ['entry_date', '<=', $end->toDateString()],
            ['active', '=', true],
        ];

        if ($employeeIds !== null) {
            $domain[] = ['employee_id', 'in', array_values(array_unique(array_map('intval', $employeeIds)))];
        }

        $entries = $this->fetchEntries($domain);
        $byEmployeeDate = [];
        $byDate = [];

        foreach ($entries as $entry) {
            if (! $entry['employee_id']) {
                continue;
            }

            $byEmployeeDate[$entry['employee_id']][$entry['date_value']][] = $entry;
            $byDate[$entry['date_value']][] = $entry;
        }

        return [
            'entries' => $entries,
            'by_employee_date' => $byEmployeeDate,
            'by_date' => $byDate,
            'count' => count($entries),
        ];
    }

    public function createEntry(User $user, array $data): int
    {
        $this->entryFields();
        $employeeId = $this->requireEmployeeId($user);
        $entryId = $this->serviceAccount->executeKw(
            self::MODEL,
            'create',
            [$this->writePayload($employeeId, $data)]
        );

        if (! is_numeric($entryId) || (int) $entryId < 1) {
            throw new OdooException('Odoo did not confirm the schedule diary entry.');
        }

        return (int) $entryId;
    }

    public function updateEntry(User $user, int $entryId, array $data): void
    {
        $this->entryFields();
        $employeeId = $this->requireEmployeeId($user);
        $this->requireOwnedEntry($employeeId, $entryId);
        $result = $this->serviceAccount->executeKw(
            self::MODEL,
            'write',
            [[$entryId], $this->writePayload($employeeId, $data)]
        );

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the schedule diary update.');
        }
    }

    public function deleteEntry(User $user, int $entryId): string
    {
        $this->entryFields();
        $employeeId = $this->requireEmployeeId($user);
        $entry = $this->requireOwnedEntry($employeeId, $entryId);
        $result = $this->serviceAccount->executeKw(self::MODEL, 'unlink', [[$entryId]]);

        if ($result !== true) {
            throw new OdooException('Odoo did not confirm the schedule diary deletion.');
        }

        return (string) $entry['entry_date'];
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $weeks
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function addEntriesToCalendar(array $weeks, array $entries): array
    {
        $byDate = collect($entries)->groupBy('date_value');

        return array_map(function (array $week) use ($byDate): array {
            return array_map(function (array $day) use ($byDate): array {
                $dayEntries = $byDate->get($day['date_value'], collect())->values()->all();
                $day['diary_entries'] = $dayEntries;
                $day['diary_count'] = count($dayEntries);

                return $day;
            }, $week);
        }, $weeks);
    }

    /** @param  array<int, array<int|string, mixed>>  $domain */
    private function fetchEntries(array $domain): array
    {
        $fields = $this->entryFields();
        $requestedFields = array_values(array_filter([
            'id',
            $this->resolveField($fields, ['employee_id']),
            $this->resolveField($fields, ['entry_date']),
            $this->resolveField($fields, ['entry_type']),
            $this->resolveField($fields, ['title']),
            $this->resolveField($fields, ['note']),
            $this->resolveField($fields, ['is_full_day']),
            $this->resolveField($fields, ['start_time']),
            $this->resolveField($fields, ['end_time']),
            $this->resolveField($fields, ['time_range_display']),
            $this->resolveField($fields, ['active']),
            $this->resolveField($fields, ['write_date']),
        ]));
        $records = $this->serviceAccount->executeKw(
            self::MODEL,
            'search_read',
            [$domain],
            [
                'fields' => $requestedFields,
                'order' => 'entry_date asc, is_full_day desc, start_time asc, employee_id asc, id asc',
            ]
        );

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $record): ?array => is_array($record) ? $this->normalizeEntry($record) : null,
            $records
        )));
    }

    /** @return array<string, mixed> */
    private function requireOwnedEntry(int $employeeId, int $entryId): array
    {
        $records = $this->serviceAccount->executeKw(
            self::MODEL,
            'search_read',
            [[
                ['id', '=', $entryId],
                ['employee_id', '=', $employeeId],
            ]],
            ['fields' => ['id', 'entry_date'], 'limit' => 1]
        );

        if (! is_array($records) || ! isset($records[0]) || ! is_array($records[0])) {
            throw new OdooException('The selected schedule diary entry could not be found.');
        }

        return $records[0];
    }

    /** @return array<string, mixed>|null */
    private function normalizeEntry(array $record): ?array
    {
        if (empty($record['entry_date']) || empty($record['entry_type'])) {
            return null;
        }

        $employee = $this->manyToOne($record['employee_id'] ?? null);
        $type = (string) $record['entry_type'];
        $meta = match ($type) {
            'available' => ['label' => 'Available', 'class' => 'available', 'icon' => 'fa-check-circle'],
            'unavailable' => ['label' => 'Unavailable', 'class' => 'unavailable', 'icon' => 'fa-ban'],
            default => ['label' => 'Note', 'class' => 'note', 'icon' => 'fa-sticky-note'],
        };
        $isAllDay = (bool) ($record['is_full_day'] ?? false);
        $start = isset($record['start_time']) && is_numeric($record['start_time'])
            ? (float) $record['start_time']
            : null;
        $end = isset($record['end_time']) && is_numeric($record['end_time'])
            ? (float) $record['end_time']
            : null;

        return [
            'id' => (int) ($record['id'] ?? 0),
            'employee_id' => $employee['id'],
            'employee_name' => $employee['name'],
            'date_value' => (string) $record['entry_date'],
            'date_label' => Carbon::parse((string) $record['entry_date'])->format('D, d M Y'),
            'entry_type' => $type,
            'type_label' => $meta['label'],
            'type_class' => $meta['class'],
            'icon' => $meta['icon'],
            'title' => trim((string) ($record['title'] ?? '')) ?: $meta['label'],
            'notes' => trim((string) ($record['note'] ?? '')) ?: null,
            'is_all_day' => $isAllDay,
            'start_time' => $start,
            'end_time' => $end,
            'start_time_value' => $start !== null ? $this->floatToTime($start) : null,
            'end_time_value' => $end !== null ? $this->floatToTime($end) : null,
            'time_label' => $isAllDay || $start === null || $end === null
                ? 'All day'
                : $this->formatFloatHour($start).' – '.$this->formatFloatHour($end),
            'write_date_value' => is_string($record['write_date'] ?? null) ? $record['write_date'] : '',
        ];
    }

    /** @return array<string, mixed> */
    private function writePayload(int $employeeId, array $data): array
    {
        $isAllDay = (bool) ($data['is_all_day'] ?? false);

        return [
            'employee_id' => $employeeId,
            'entry_date' => (string) $data['entry_date'],
            'entry_type' => (string) $data['entry_type'],
            'title' => $data['title'] ?: false,
            'note' => $data['notes'] ?: false,
            'is_full_day' => $isAllDay,
            'start_time' => $isAllDay ? false : $this->timeToFloat((string) $data['start_time']),
            'end_time' => $isAllDay ? false : $this->timeToFloat((string) $data['end_time']),
            'active' => true,
        ];
    }

    private function employeeId(User $user): ?int
    {
        return filled($user->odoo_employee_id) ? (int) $user->odoo_employee_id : null;
    }

    private function requireEmployeeId(User $user): int
    {
        $employeeId = $this->employeeId($user);

        if (! $employeeId) {
            throw new OdooException('This account is not linked to an Odoo employee record yet.');
        }

        return $employeeId;
    }

    private function entryFields(): array
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        try {
            $fields = $this->serviceAccount->executeKw(
                self::MODEL,
                'fields_get',
                [],
                ['attributes' => ['string', 'type', 'relation']]
            );
        } catch (OdooException $exception) {
            if (Str::contains(Str::lower($exception->getMessage()), [
                self::MODEL,
                'object',
                'model',
                'not exist',
            ])) {
                throw new OdooException(
                    'Schedule diary is not enabled in Odoo yet. Upgrade hr_employee_weekly_availability.',
                    0,
                    $exception
                );
            }

            throw $exception;
        }

        $this->fields = is_array($fields) ? $fields : [];

        return $this->fields;
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

    /** @return array{id:?int,name:?string} */
    private function manyToOne(mixed $value): array
    {
        return is_array($value)
            ? [
                'id' => isset($value[0]) && is_numeric($value[0]) ? (int) $value[0] : null,
                'name' => isset($value[1]) ? (string) $value[1] : null,
            ]
            : ['id' => null, 'name' => null];
    }

    private function timeToFloat(string $time): float
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return round($hours + ($minutes / 60), 4);
    }

    private function floatToTime(float $value): string
    {
        $minutes = (int) round($value * 60);

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatFloatHour(float $value): string
    {
        return Carbon::createFromFormat('H:i', $this->floatToTime($value))->format('h:i A');
    }

}
