<?php

namespace App\Support;

use Carbon\Carbon;

class ShiftCalendarBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $shifts
     * @return array{
     *     weeks:array<int, array<int, array<string, mixed>>>,
     *     selected_date:Carbon,
     *     selected_date_label:string,
     *     selected_date_value:string,
     *     selected_date_shifts:array<int, array<string, mixed>>
     * }
     */
    public function build(Carbon $month, array $shifts, ?Carbon $selectedDay = null): array
    {
        $month = $month->copy()->startOfMonth();
        $shiftsByDate = $this->groupShiftsByDate($shifts);
        $selectedDate = $this->resolveSelectedDay($month, $shiftsByDate, $selectedDay);
        $calendarStart = $month->copy()->startOfWeek();
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek();
        $weeks = [];
        $week = [];
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $dateValue = $cursor->toDateString();

            $week[] = [
                'date' => $cursor->copy(),
                'date_value' => $dateValue,
                'day_number' => $cursor->day,
                'is_current_month' => $cursor->isSameMonth($month),
                'is_today' => $cursor->isToday(),
                'is_selected' => $cursor->isSameDay($selectedDate),
                'shift_count' => count($shiftsByDate[$dateValue] ?? []),
                'shifts' => $shiftsByDate[$dateValue] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor->addDay();
        }

        return [
            'weeks' => $weeks,
            'selected_date' => $selectedDate,
            'selected_date_label' => $selectedDate->format('d-m-Y'),
            'selected_date_value' => $selectedDate->toDateString(),
            'selected_date_shifts' => $shiftsByDate[$selectedDate->toDateString()] ?? [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $shifts
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupShiftsByDate(array $shifts): array
    {
        $grouped = [];

        foreach ($shifts as $shift) {
            $dateValue = isset($shift['date_value']) ? (string) $shift['date_value'] : '';

            if ($dateValue === '') {
                continue;
            }

            $grouped[$dateValue][] = $shift;
        }

        return $grouped;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $shiftsByDate
     */
    private function resolveSelectedDay(Carbon $month, array $shiftsByDate, ?Carbon $selectedDay): Carbon
    {
        if ($selectedDay && $selectedDay->isSameMonth($month)) {
            return $selectedDay->copy()->startOfDay();
        }

        $today = now()->startOfDay();

        if ($today->isSameMonth($month)) {
            return $today;
        }

        $availableDates = array_keys($shiftsByDate);
        sort($availableDates);

        if ($availableDates !== []) {
            return Carbon::parse($availableDates[0], config('app.timezone'))->startOfDay();
        }

        return $month->copy()->startOfDay();
    }
}
