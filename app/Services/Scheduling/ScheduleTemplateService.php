<?php

namespace App\Services\Scheduling;

use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Odoo\OdooScheduleRepository;
use Carbon\Carbon;

class ScheduleTemplateService
{
    public function __construct(private readonly ?OdooScheduleRepository $repository = null) {}
    /** @param array<int,array<string,mixed>> $shifts */
    public function saveVisibleWeek(array $shifts, Carbon $weekStart, array $data, ?User $creator): OdooScheduleRecord
    {
        $matching=array_values(array_filter($shifts,fn(array $s):bool=>(int)($s['company_id']??0)===(int)$data['company_id']));
        if(!$matching)throw new \RuntimeException('No shifts matched the selected template scope.');
        return ($this->repository ?? app(OdooScheduleRepository::class))->createTemplate($data,$matching,$weekStart);
    }

    /** @param array<int,array<string,mixed>> $existingShifts @return array<string,mixed> */
    public function preview(OdooScheduleRecord $template, Carbon $targetWeekStart, array $existingShifts): array
    {
        $rows = [];
        $conflictCount = 0;

        foreach ($template->items as $item) {
            $date = $targetWeekStart->copy()->addDays((int) $item->day_offset)->toDateString();
            $start = substr((string) $item->start_time, 0, 5);
            $end = substr((string) $item->end_time, 0, 5);
            $conflicts = array_values(array_filter($existingShifts, fn (array $shift): bool =>
                ! empty($item->employee_id)
                && (int) ($shift['employee_id'] ?? 0) === (int) $item->employee_id
                && ($shift['shift_date_value'] ?? '') === $date
                && ($shift['start_time_value'] ?? '') < $end
                && ($shift['end_time_value'] ?? '') > $start
            ));
            $hasConflict = $conflicts !== [];
            if ($hasConflict) $conflictCount++;
            $rows[] = ['item' => $item, 'date' => $date, 'start_time' => $start, 'end_time' => $end,
                'has_conflict' => $hasConflict, 'conflicts' => $conflicts];
        }

        return ['rows' => $rows, 'total' => count($rows), 'conflicts' => $conflictCount,
            'ready' => count($rows) - $conflictCount, 'target_week_start' => $targetWeekStart];
    }

    /** @return array{created:int,skipped:int,created_ids:array<int,int>} */
    public function apply(
        OdooScheduleRecord $template,
        Carbon $targetWeekStart,
        array $existingShifts,
        OdooManagerPlanningService $planningService,
        ?User $user,
        bool $skipConflicts
    ): array {
        $preview = $this->preview($template, $targetWeekStart, $existingShifts);
        if ($preview['conflicts'] > 0 && ! $skipConflicts) {
            throw new \RuntimeException('The template has conflicts. Review the preview or choose Skip conflicts.');
        }

        $createdIds = [];
        $skipped = 0;
        try {
            foreach ($preview['rows'] as $row) {
                if ($row['has_conflict']) { $skipped++; continue; }
                $item = $row['item'];
                $ids = $planningService->createShiftsReturningIds([
                    'employee_id' => $item->employee_id, 'role_id' => $item->role_id,
                    'company_id' => $item->company_id, 'shift_date' => $row['date'],
                    'work_location_id' => $item->work_location_id,
                    'start_time' => $row['start_time'], 'end_time' => $row['end_time'],
                    'title' => $item->title, 'note' => $item->note,
                ]);
                $createdIds = array_merge($createdIds, $ids);
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($createdIds) as $slotId) {
                try { $planningService->deleteShift((int) $slotId); } catch (\Throwable) { }
            }
            throw $exception instanceof OdooException ? $exception : new \RuntimeException($exception->getMessage(), 0, $exception);
        }

        ($this->repository ?? app(OdooScheduleRepository::class))->markTemplateApplied($template->id);
        return ['created' => count($createdIds), 'skipped' => $skipped, 'created_ids' => $createdIds];
    }
}
