<?php

namespace App\Services\Scheduling;

use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Odoo\OdooException;
use Carbon\Carbon;

class ScheduleDayService
{
    public function __construct(private readonly ?OdooScheduleRepository $repository = null) {}
    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    public function decorateViews(array $roster, array $areaBoard): array
    {
        $dates = collect($areaBoard['days'] ?? $roster['days'] ?? [])->pluck('date_value')->filter()->values();

        if ($dates->isEmpty()) {
            return [$roster, $areaBoard];
        }
        try{$records = ($this->repository ?? app(OdooScheduleRepository::class))->dayEntries(Carbon::parse($dates->first()), Carbon::parse($dates->last()));}catch(OdooException){return [$roster,$areaBoard];}

        return $this->applyMetadata($roster, $areaBoard, $records);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    public function applyMetadata(array $roster, array $areaBoard, iterable $records): array
    {
        $metadata = collect($records)->groupBy(fn (object $item): string => $item->schedule_date->toDateString());
        $roster['days'] = $this->decorateDays($roster['days'] ?? [], $metadata);
        $areaBoard['days'] = $this->decorateDays($areaBoard['days'] ?? [], $metadata);

        $areaBoard['rows'] = $areaBoard['rows'] ?? [];
        foreach ($areaBoard['rows'] as &$row) {
            $row['cells'] = $row['cells'] ?? [];
            foreach ($row['cells'] as &$cell) {
                $matching = $metadata->get($cell['date_value'], collect())->filter(function (object $item) use ($row): bool {
                    return (int) $item->company_id === (int) ($row['company_id'] ?? 0)
                        && ($item->schedule_area_id === null || (int) $item->schedule_area_id === (int) ($row['schedule_area_id'] ?? 0));
                });
                $blockedCount = 0;
                foreach ($matching as $item) {
                    if (! $item->blocked_start || ! $item->blocked_end) {
                        continue;
                    }
                    foreach ($cell['shifts'] ?? [] as $shift) {
                        if ($this->shiftOverlapsBlockedTime($shift, $cell['date_value'], $item->blocked_start, $item->blocked_end)) {
                            $blockedCount++;
                        }
                    }
                }
                $cell['day_notes'] = $matching->pluck('note')->filter()->unique()->values()->all();
                $cell['holiday_labels'] = $matching->pluck('holiday_name')->filter()->unique()->values()->all();
                $cell['blocked_labels'] = $matching->filter(fn (object $item): bool => (bool) ($item->blocked_start && $item->blocked_end))
                    ->map(fn (object $item): string => substr($item->blocked_start, 0, 5).'–'.substr($item->blocked_end, 0, 5))->unique()->values()->all();
                $cell['blocked_shift_count'] = $blockedCount;
            }
            unset($cell);
        }
        unset($row);

        return [$roster, $areaBoard];
    }

    /** @param array<int,array<string,mixed>> $days @return array<int,array<string,mixed>> */
    private function decorateDays(array $days, $metadata): array
    {
        foreach ($days as &$day) {
            $items = $metadata->get($day['date_value'], collect());
            $day['holiday_labels'] = $items->pluck('holiday_name')->filter()->unique()->values()->all();
            $day['has_day_note'] = $items->contains(fn (object $item): bool => filled($item->note));
            $day['blocked_labels'] = $items->filter(fn (object $item): bool => (bool) ($item->blocked_start && $item->blocked_end))
                ->map(fn (object $item): string => substr($item->blocked_start, 0, 5).'–'.substr($item->blocked_end, 0, 5))->unique()->values()->all();
        }
        unset($day);

        return $days;
    }

    /** @param array<string,mixed> $shift */
    private function shiftOverlapsBlockedTime(array $shift, string $date, string $blockedStart, string $blockedEnd): bool
    {
        $start = $shift['start_at'] ?? null;
        $end = $shift['end_at'] ?? null;
        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            return false;
        }
        $blockedFrom = Carbon::parse($date.' '.$blockedStart);
        $blockedTo = Carbon::parse($date.' '.$blockedEnd);

        return $start->lt($blockedTo) && $end->gt($blockedFrom);
    }
}
