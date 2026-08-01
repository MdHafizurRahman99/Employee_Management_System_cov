<?php

namespace App\Services\Scheduling;

use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Odoo\OdooException;

class SchedulingAreaService
{
    public function __construct(private readonly ?OdooScheduleRepository $repository = null) {}
    /** @param array<string,mixed> $board @return array<string,mixed> */
    public function decorateBoard(array $board): array
    {
        try{return $this->applyCoverage($board, ($this->repository ?? app(OdooScheduleRepository::class))->areas());}catch(OdooException){return $board+['coverage_summary'=>$this->emptySummary()];}
    }

    /** @param iterable<int|string,OdooScheduleRecord> $areaRecords @param array<string,mixed> $board @return array<string,mixed> */
    public function applyCoverage(array $board, iterable $areaRecords): array
    {
        $areas = collect($areaRecords)->keyBy(fn (object $area): string => $area->company_id.':'.$area->odoo_role_id);
        $summary = $this->emptySummary();

        $board['rows'] = $board['rows'] ?? [];

        foreach ($board['rows'] as &$row) {
            $key = ((int) ($row['company_id'] ?? 0)).':'.((int) ($row['role_id'] ?? 0));
            /** @var OdooScheduleRecord|null $area */
            $area = $areas->get($key);
            $row['schedule_area_id'] = $area?->id;
            $row['area_name'] = $area?->name ?? $row['role'];
            $row['area_color'] = $area?->color ?? '#64748b';
            $targets = $area?->coverageRequirements->keyBy('weekday') ?? collect();

            $row['cells'] = $row['cells'] ?? [];
            foreach ($row['cells'] as &$cell) {
                $weekday = (int) date('N', strtotime((string) $cell['date_value'])) - 1;
                $target = $targets->get($weekday);
                $required = (int) ($target?->minimum_people ?? 0);
                $assigned = (int) ($cell['assigned_count'] ?? 0);
                $cell['coverage_required'] = $required;
                $cell['coverage_gap'] = max(0, $required - $assigned);
                $cell['coverage_status'] = $required === 0 ? 'unconfigured' : ($assigned < $required ? 'under' : ($assigned === $required ? 'met' : 'over'));

                if ($required > 0) {
                    $summary['configured_cells']++;
                    $summary[$cell['coverage_status'].'_cells']++;
                    $summary['missing_people'] += $cell['coverage_gap'];
                }
            }
            unset($cell);
        }
        unset($row);

        usort($board['rows'], fn (array $a, array $b): int => ($areas->get(((int) ($a['company_id'] ?? 0)).':'.((int) ($a['role_id'] ?? 0)))?->sort_order ?? 9999)
            <=> ($areas->get(((int) ($b['company_id'] ?? 0)).':'.((int) ($b['role_id'] ?? 0)))?->sort_order ?? 9999));
        $board['coverage_summary'] = $summary;

        return $board;
    }

    /** @return array<string,int> */
    private function emptySummary(): array
    {
        return ['configured_cells' => 0, 'under_cells' => 0, 'met_cells' => 0, 'over_cells' => 0, 'missing_people' => 0];
    }
}
