<?php

namespace App\Services\Scheduling;

use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooScheduleRecord;
use Carbon\Carbon;

class ScheduleComplianceService
{
    public function __construct(private readonly ?OdooScheduleRepository $repository = null) {}
    /** @param array<int,array<string,mixed>> $shifts @param iterable<int,OdooScheduleRecord> $rules @param iterable<int,OdooScheduleRecord> $breaks @return array{shifts:array<int,array<string,mixed>>,summary:array<string,int>} */
    public function evaluateShiftList(array $shifts, iterable $rules, iterable $breaks): array
    {
        $view = ['rows' => [['cells' => [['shifts' => $shifts]]]]];
        [$decorated, , $summary] = $this->applyCompliance($view, ['rows' => []], $rules, $breaks);

        return ['shifts' => $decorated['rows'][0]['cells'][0]['shifts'], 'summary' => $summary];
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,int>} */
    public function decorateViews(array $roster, array $areaBoard): array
    {
        $repository=$this->repository ?? app(OdooScheduleRepository::class);
        try{$rules=$repository->complianceRules()->where('is_enabled',true);$breaks=$repository->breaks($this->shiftIds($roster,$areaBoard));}catch(OdooException){return [$roster,$areaBoard,$this->emptySummary()];}

        return $this->applyCompliance($roster, $areaBoard, $rules, $breaks);
    }

    /** @param iterable<int,OdooScheduleRecord> $rules @param iterable<int,OdooScheduleRecord> $breaks @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,int>} */
    public function applyCompliance(array $roster, array $areaBoard, iterable $rules, iterable $breaks): array
    {
        $ruleMap = collect($rules)->keyBy('company_id');
        $breakMap = collect($breaks)->groupBy('odoo_slot_id');
        $shifts = $this->uniqueShifts($roster, $areaBoard);
        $decorations = [];
        $summary = $this->emptySummary();

        foreach ($shifts as $id => $shift) {
            $rule = $ruleMap->get((int) ($shift['company_id'] ?? 0));
            $planned = $breakMap->get($id, collect());
            $breakMinutes = (int) $planned->sum('duration_minutes');
            $warnings = [];
            if ($rule) {
                $duration = (int) ($shift['duration_minutes'] ?? 0);
                if ($duration >= $rule->break_required_after_minutes && $breakMinutes < $rule->minimum_break_minutes) {
                    $warnings['missing_break'] = 'Needs '.$rule->minimum_break_minutes.' min break';
                    $summary['missing_breaks']++;
                }
                if ($duration > $rule->maximum_shift_minutes) {
                    $warnings['long_shift'] = 'Exceeds '.round($rule->maximum_shift_minutes / 60, 1).'h limit';
                    $summary['long_shifts']++;
                }
            }
            $decorations[$id] = ['planned_breaks' => $planned->values()->all(), 'planned_break_minutes' => $breakMinutes, 'compliance_warnings' => $warnings];
            $summary['planned_breaks'] += $planned->count();
        }

        $byEmployee = collect($shifts)->filter(fn (array $shift): bool => ! empty($shift['employee_id']) && ($shift['start_at'] ?? null) instanceof Carbon)
            ->groupBy('employee_id');
        foreach ($byEmployee as $employeeShifts) {
            $ordered = $employeeShifts->sortBy('start_at')->values();
            for ($i = 1; $i < $ordered->count(); $i++) {
                $previous = $ordered[$i - 1];
                $current = $ordered[$i];
                $rule = $ruleMap->get((int) ($current['company_id'] ?? 0));
                if (! $rule || ! (($previous['end_at'] ?? null) instanceof Carbon)) {
                    continue;
                }
                $rest = $previous['end_at']->diffInMinutes($current['start_at'], false);
                if ($rest < $rule->minimum_rest_minutes) {
                    $decorations[(int) $current['id']]['compliance_warnings']['short_rest'] = 'Only '.max(0, round($rest / 60, 1)).'h rest';
                    $summary['short_rest']++;
                }
            }
        }

        $summary['violation_shifts'] = count(array_filter($decorations, fn (array $item): bool => $item['compliance_warnings'] !== []));
        $this->decorateShiftCopies($roster, $decorations);
        $this->decorateShiftCopies($areaBoard, $decorations);

        return [$roster, $areaBoard, $summary];
    }

    /** @param array<int,array<string,mixed>> $decorations */
    private function decorateShiftCopies(array &$view, array $decorations): void
    {
        $view['rows'] = $view['rows'] ?? [];
        foreach ($view['rows'] as &$row) {
            $row['cells'] = $row['cells'] ?? [];
            foreach ($row['cells'] as &$cell) {
                $cell['shifts'] = $cell['shifts'] ?? [];
                foreach ($cell['shifts'] as &$shift) {
                    $shift = array_merge($shift, $decorations[(int) ($shift['id'] ?? 0)] ?? ['planned_breaks' => [], 'planned_break_minutes' => 0, 'compliance_warnings' => []]);
                }
                unset($shift);
            }
            unset($cell);
        }
        unset($row);
    }

    /** @return array<int,array<string,mixed>> */
    private function uniqueShifts(array $roster, array $areaBoard): array
    {
        $result = [];
        foreach ([$roster, $areaBoard] as $view) {
            foreach ($view['rows'] ?? [] as $row) {
                foreach ($row['cells'] ?? [] as $cell) {
                    foreach ($cell['shifts'] ?? [] as $shift) {
                        if (! empty($shift['id'])) $result[(int) $shift['id']] = $shift;
                    }
                }
            }
        }
        return $result;
    }

    /** @return array<int,int> */
    private function shiftIds(array $roster, array $areaBoard): array { return array_keys($this->uniqueShifts($roster, $areaBoard)); }

    /** @return array<string,int> */
    private function emptySummary(): array { return ['violation_shifts'=>0,'missing_breaks'=>0,'long_shifts'=>0,'short_rest'=>0,'planned_breaks'=>0]; }
}
