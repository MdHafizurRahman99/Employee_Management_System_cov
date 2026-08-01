<?php

namespace App\Services\Scheduling;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use Carbon\Carbon;

class AutoScheduleService
{
    /**
     * Build a deterministic, reviewable coverage plan. This method never writes data.
     *
     * @param array<string,mixed> $pageData
     * @param iterable<int,object> $areas
     * @param iterable<int,object> $dayEntries
     * @param array{company_id:int,work_location_id:int,start_time:string,end_time:string,max_weekly_hours:int,create_open_shifts:bool,allow_diary_override:bool} $options
     * @return array<string,mixed>
     */
    public function preview(Carbon $weekStart, array $pageData, iterable $areas, iterable $dayEntries, array $options): array
    {
        $weekStart = $weekStart->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $companyId = (int) $options['company_id'];
        $workLocationId = (int) $options['work_location_id'];
        $startTime = substr($options['start_time'], 0, 5);
        $endTime = substr($options['end_time'], 0, 5);
        $duration = $this->minutes($startTime, $endTime);
        $maximumMinutes = max(1, (int) $options['max_weekly_hours']) * 60;
        $allowOpen = (bool) $options['create_open_shifts'];
        $allowDiaryOverride = (bool) ($options['allow_diary_override'] ?? false);
        $employees = array_values(array_filter($pageData['employees'] ?? [], fn (array $employee): bool =>
            (int) ($employee['company_id'] ?? 0) === $companyId
            && (int) ($employee['work_location_id'] ?? 0) === $workLocationId
        ));
        $existing = array_values(array_filter($pageData['recentShifts'] ?? [], function (array $shift) use ($companyId, $workLocationId, $weekStart, $weekEnd): bool {
            $date = $shift['shift_date_value'] ?? $shift['date_value'] ?? null;
            return (int) ($shift['company_id'] ?? 0) === $companyId
                && (int) ($shift['work_location_id'] ?? 0) === $workLocationId
                && is_string($date)
                && Carbon::parse($date)->betweenIncluded($weekStart, $weekEnd);
        }));
        $rosterRows = collect($pageData['weeklyRoster']['rows'] ?? [])->keyBy('employee_id');
        $employeeDiary = $pageData['employeeDiary']['by_employee_date'] ?? [];
        $areaList = collect($areas)->filter(fn (object $area): bool => (int) $area->company_id === $companyId && (bool) $area->is_active)->sortBy('sort_order')->values();
        $dayMetadata = collect($dayEntries);
        $employeeMinutes = [];

        foreach ($employees as $employee) {
            $employeeMinutes[(int) $employee['id']] = (int) ($rosterRows->get((int) $employee['id'])['scheduled_minutes'] ?? 0);
        }

        $rows = [];
        $proposed = [];
        $summary = ['coverage_cells' => 0, 'positions_needed' => 0, 'assigned' => 0, 'diary_overrides' => 0, 'open' => 0, 'unfilled' => 0, 'blocked' => 0];

        foreach ($areaList as $area) {
            $coverage = $area->coverageRequirements->keyBy('weekday');
            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $date = $weekStart->copy()->addDays($dayOffset);
                $required = (int) ($coverage->get($dayOffset)?->minimum_people ?? 0);
                if ($required < 1) {
                    continue;
                }

                $summary['coverage_cells']++;
                $dateValue = $date->toDateString();
                $matching = array_values(array_filter($existing, fn (array $shift): bool =>
                    (int) ($shift['role_id'] ?? 0) === (int) $area->odoo_role_id
                    && ($shift['shift_date_value'] ?? $shift['date_value'] ?? '') === $dateValue
                ));
                $occupiedPositions = count($matching);
                $needed = max(0, $required - $occupiedPositions);
                $summary['positions_needed'] += $needed;

                for ($position = 1; $position <= $needed; $position++) {
                    $base = [
                        'date' => $dateValue,
                        'weekday' => $date->format('D'),
                        'area_id' => (int) $area->id,
                        'area' => (string) $area->name,
                        'area_color' => (string) $area->color,
                        'company_id' => $companyId,
                        'work_location_id' => $workLocationId,
                        'role_id' => (int) $area->odoo_role_id,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'duration_minutes' => $duration,
                        'required' => $required,
                        'existing_positions' => $occupiedPositions,
                    ];

                    if ($this->isBlocked($dayMetadata, $base)) {
                        $rows[] = $base + ['status' => 'blocked', 'employee_id' => null, 'employee' => null, 'reason' => 'The proposed hours overlap blocked time.'];
                        $summary['blocked']++;
                        continue;
                    }

                    $candidate = collect($employees)
                        ->filter(fn (array $employee): bool => $this->isEligible(
                            $employee, $base, $existing, $proposed, $rosterRows->get((int) $employee['id']),
                            $employeeMinutes[(int) $employee['id']] ?? 0, $maximumMinutes,
                            $employeeDiary[(int) $employee['id']][$dateValue] ?? [],
                            $allowDiaryOverride
                        ))
                        ->sortBy(fn (array $employee): string => sprintf('%01d:%010d:%s:%010d',
                            $this->diaryPreferenceRank(
                                $employeeDiary[(int) $employee['id']][$dateValue] ?? [],
                                $base
                            ),
                            $employeeMinutes[(int) $employee['id']] ?? 0,
                            mb_strtolower((string) ($employee['name'] ?? '')),
                            (int) $employee['id']
                        ))
                        ->first();

                    if (is_array($candidate)) {
                        $employeeId = (int) $candidate['id'];
                        $diaryOverride = $allowDiaryOverride && $this->hasDiaryConflict(
                            $employeeDiary[$employeeId][$dateValue] ?? [],
                            $base
                        );
                        $row = $base + [
                            'status' => 'assigned',
                            'employee_id' => $employeeId,
                            'employee' => (string) $candidate['name'],
                            'diary_override' => $diaryOverride,
                            'reason' => $diaryOverride ? 'Diary unavailability preference overridden.' : null,
                        ];
                        $employeeMinutes[$employeeId] = ($employeeMinutes[$employeeId] ?? 0) + $duration;
                        $proposed[] = $row;
                        $rows[] = $row;
                        $summary['assigned']++;
                        if ($diaryOverride) {
                            $summary['diary_overrides']++;
                        }
                    } elseif ($allowOpen) {
                        $row = $base + ['status' => 'open', 'employee_id' => null, 'employee' => 'Open shift', 'reason' => 'No eligible employee was available within the weekly-hours limit.'];
                        $proposed[] = $row;
                        $rows[] = $row;
                        $summary['open']++;
                    } else {
                        $rows[] = $base + ['status' => 'unfilled', 'employee_id' => null, 'employee' => null, 'reason' => 'No eligible employee was available.'];
                        $summary['unfilled']++;
                    }
                }
            }
        }

        return [
            'rows' => $rows,
            'proposals' => $proposed,
            'summary' => $summary,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'options' => $options,
            'area_count' => $areaList->count(),
            'employee_count' => count($employees),
        ];
    }

    /** @return array{created:int,assigned:int,open:int,created_ids:array<int,int>} */
    public function apply(array $preview, OdooManagerPlanningService $planning): array
    {
        $createdIds = [];
        $assigned = 0;
        $open = 0;

        try {
            foreach ($preview['proposals'] as $proposal) {
                $ids = $planning->createShiftsReturningIds([
                    'employee_id' => $proposal['employee_id'],
                    'role_id' => $proposal['role_id'],
                    'company_id' => $proposal['company_id'],
                    'work_location_id' => $proposal['work_location_id'],
                    'shift_date' => $proposal['date'],
                    'start_time' => $proposal['start_time'],
                    'end_time' => $proposal['end_time'],
                    'title' => 'Auto schedule · '.$proposal['area'],
                    'note' => ($proposal['diary_override'] ?? false)
                        ? 'Generated from Odoo coverage requirements; employee diary unavailability was explicitly overridden during manager review.'
                        : 'Generated from Odoo coverage requirements; reviewed before creation.',
                ]);
                $createdIds = array_merge($createdIds, $ids);
                $proposal['status'] === 'assigned' ? $assigned++ : $open++;
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($createdIds) as $slotId) {
                try { $planning->deleteShift((int) $slotId); } catch (\Throwable) { }
            }
            throw $exception instanceof OdooException
                ? $exception
                : new \RuntimeException($exception->getMessage(), 0, $exception);
        }

        return ['created' => count($createdIds), 'assigned' => $assigned, 'open' => $open, 'created_ids' => $createdIds];
    }

    /** @param array<string,mixed> $employee @param array<string,mixed> $slot @param array<string,mixed>|null $rosterRow */
    private function isEligible(
        array $employee,
        array $slot,
        array $existing,
        array $proposed,
        ?array $rosterRow,
        int $scheduledMinutes,
        int $maximumMinutes,
        array $diaryEntries = [],
        bool $allowDiaryOverride = false
    ): bool
    {
        $employeeId = (int) ($employee['id'] ?? 0);
        $roleIds = array_map('intval', $employee['planning_role_ids'] ?? []);
        if ($employeeId < 1 || ($roleIds !== [] && ! in_array((int) $slot['role_id'], $roleIds, true))) {
            return false;
        }
        if ($scheduledMinutes + (int) $slot['duration_minutes'] > $maximumMinutes) {
            return false;
        }
        $timeOff = $rosterRow['cells'][$slot['date']]['time_off'] ?? [];
        if (collect($timeOff)->contains(fn (array $entry): bool => in_array($entry['kind'] ?? '', ['leave-approved', 'unavailable'], true))) {
            return false;
        }
        if (! $allowDiaryOverride && $this->hasDiaryConflict($diaryEntries, $slot)) {
            return false;
        }
        foreach (array_merge($existing, $proposed) as $shift) {
            if ((int) ($shift['employee_id'] ?? 0) !== $employeeId || ($shift['shift_date_value'] ?? $shift['date_value'] ?? $shift['date'] ?? '') !== $slot['date']) {
                continue;
            }
            $start = substr((string) ($shift['start_time_value'] ?? $shift['start_time'] ?? ''), 0, 5);
            $end = substr((string) ($shift['end_time_value'] ?? $shift['end_time'] ?? ''), 0, 5);
            if ($start < $slot['end_time'] && $end > $slot['start_time']) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,array<string,mixed>> $entries @param array<string,mixed> $slot */
    private function hasDiaryConflict(array $entries, array $slot): bool
    {
        foreach ($entries as $entry) {
            if (($entry['entry_type'] ?? '') === 'unavailable' && $this->diaryEntryOverlaps($entry, $slot)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int,array<string,mixed>> $entries @param array<string,mixed> $slot */
    private function diaryPreferenceRank(array $entries, array $slot): int
    {
        if ($this->hasDiaryConflict($entries, $slot)) {
            return 2;
        }

        foreach ($entries as $entry) {
            if (($entry['entry_type'] ?? '') === 'available' && $this->diaryEntryOverlaps($entry, $slot)) {
                return 0;
            }
        }

        return 1;
    }

    /** @param array<string,mixed> $entry @param array<string,mixed> $slot */
    private function diaryEntryOverlaps(array $entry, array $slot): bool
    {
        if (($entry['is_all_day'] ?? false) === true) {
            return true;
        }

        $entryStart = substr((string) ($entry['start_time_value'] ?? ''), 0, 5);
        $entryEnd = substr((string) ($entry['end_time_value'] ?? ''), 0, 5);
        $slotStart = substr((string) ($slot['start_time'] ?? ''), 0, 5);
        $slotEnd = substr((string) ($slot['end_time'] ?? ''), 0, 5);

        return $entryStart !== ''
            && $entryEnd !== ''
            && $slotStart !== ''
            && $slotEnd !== ''
            && $entryStart < $slotEnd
            && $entryEnd > $slotStart;
    }

    /** @param iterable<int,object> $entries @param array<string,mixed> $slot */
    private function isBlocked(iterable $entries, array $slot): bool
    {
        foreach ($entries as $entry) {
            if ((int) $entry->company_id !== (int) $slot['company_id'] || $entry->schedule_date->toDateString() !== $slot['date']) {
                continue;
            }
            if ($entry->schedule_area_id !== null && (int) $entry->schedule_area_id !== (int) $slot['area_id']) {
                continue;
            }
            if ($entry->blocked_start && $entry->blocked_end && $entry->blocked_start < $slot['end_time'] && $entry->blocked_end > $slot['start_time']) {
                return true;
            }
        }
        return false;
    }

    private function minutes(string $start, string $end): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));
        return max(1, ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute));
    }
}
