<?php

namespace App\Services\Scheduling;

use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooScheduleRecord;
use Carbon\Carbon;

class ScheduleBudgetService
{
    public function __construct(private readonly ?OdooScheduleRepository $repository = null) {}
    /** @param array<int,array<string,mixed>> $shifts @param iterable<int,OdooScheduleRecord> $rates @param iterable<int,OdooScheduleRecord> $breaks @param iterable<int,OdooScheduleRecord> $budgets @return array<string,mixed> */
    public function project(array $shifts, iterable $rates, iterable $breaks, iterable $budgets): array
    {
        $rateList = collect($rates);
        $breakMap = collect($breaks)->groupBy('odoo_slot_id');
        $budgetMap = collect($budgets)->keyBy('company_id');
        $rows = [];
        $companies = [];

        foreach ($shifts as $shift) {
            $date = ($shift['start_at'] ?? null) instanceof Carbon ? $shift['start_at']->toDateString() : (string) ($shift['date_value'] ?? '');
            $companyId = (int) ($shift['company_id'] ?? 0);
            $employeeId = (int) ($shift['employee_id'] ?? 0);
            $duration = (int) ($shift['duration_minutes'] ?? 0);
            $unpaidBreak = (int) $breakMap->get((int) ($shift['id'] ?? 0), collect())->where('is_paid', false)->sum('duration_minutes');
            $payableMinutes = max(0, $duration - $unpaidBreak);
            $rate = $rateList->filter(fn (object $item): bool => (int)$item->company_id===$companyId && (int)$item->employee_id===$employeeId && $item->effective_from->toDateString() <= $date && (!$item->effective_to || $item->effective_to->toDateString() >= $date))->sortByDesc('effective_from')->first();
            $cost = $rate ? round(((float) $rate->hourly_rate) * $payableMinutes / 60, 2) : null;
            $rows[] = array_merge($shift, ['cost_rate'=>$rate,'payable_minutes'=>$payableMinutes,'unpaid_break_minutes'=>$unpaidBreak,'projected_cost'=>$cost,'cost_known'=>$cost!==null]);
            $company = $companies[$companyId] ?? ['company_id'=>$companyId,'company'=>$shift['company']??'Location','projected_cost'=>0.0,'known_shifts'=>0,'unknown_shifts'=>0,'scheduled_minutes'=>0,'currency'=>$rate?->currency ?? ($budgetMap->get($companyId)?->currency ?? 'AUD')];
            $company['scheduled_minutes'] += $duration;
            if ($cost === null) $company['unknown_shifts']++; else { $company['known_shifts']++; $company['projected_cost'] += $cost; }
            $companies[$companyId] = $company;
        }

        foreach ($companies as $companyId => &$company) {
            $budget = $budgetMap->get($companyId);
            $company['projected_cost'] = round($company['projected_cost'], 2);
            $company['budget'] = $budget ? (float) $budget->amount : null;
            $currencyMatches = ! $budget || $budget->currency === $company['currency'];
            $company['variance'] = $budget && $currencyMatches ? round((float)$budget->amount - $company['projected_cost'], 2) : null;
            $company['budget_status'] = !$budget ? 'unset' : (! $currencyMatches ? 'currency-mismatch' : ($company['projected_cost'] > (float)$budget->amount ? 'over' : 'within'));
        }
        unset($company);
        foreach ($budgetMap as $companyId => $budget) {
            if (!isset($companies[$companyId])) $companies[$companyId]=['company_id'=>(int)$companyId,'company'=>'Location #'.$companyId,'projected_cost'=>0.0,'known_shifts'=>0,'unknown_shifts'=>0,'scheduled_minutes'=>0,'currency'=>$budget->currency,'budget'=>(float)$budget->amount,'variance'=>(float)$budget->amount,'budget_status'=>'within'];
        }
        $knownCost = round(array_sum(array_column($companies, 'projected_cost')), 2);
        $totalBudget = round(array_sum(array_map(fn(array $c): float => (float)($c['budget']??0), $companies)), 2);
        $currencies=collect($companies)->pluck('currency')->filter()->unique();
        $totalsComparable=$currencies->count()<=1;

        return ['shifts'=>$rows,'companies'=>array_values($companies),'summary'=>['projected_cost'=>$knownCost,'total_budget'=>$totalBudget,'variance'=>$totalsComparable?round($totalBudget-$knownCost,2):null,'known_shifts'=>count(array_filter($rows,fn(array $r):bool=>$r['cost_known'])),'unknown_shifts'=>count(array_filter($rows,fn(array $r):bool=>!$r['cost_known'])),'open_shifts'=>count(array_filter($rows,fn(array $r):bool=>empty($r['employee_id']))),'currency'=>$totalsComparable?($currencies->first()??'AUD'):'MIXED','totals_comparable'=>$totalsComparable]];
    }

    /** @param array<int,array<string,mixed>> $shifts @return array<string,mixed> */
    public function projectFromStorage(array $shifts, Carbon $weekStart, ?Carbon $rangeEnd = null): array
    {
        $repository=$this->repository ?? app(OdooScheduleRepository::class);
        $rangeEnd = ($rangeEnd ?? $weekStart->copy()->endOfWeek())->copy()->endOfWeek();
        try {
            $budgets = collect();
            for ($cursor = $weekStart->copy()->startOfWeek(); $cursor->lte($rangeEnd); $cursor->addWeek()) {
                $budgets = $budgets->concat($repository->weekBudgets($cursor));
            }
            $rangeBudgets = $budgets->groupBy('company_id')->map(function ($companyBudgets): OdooScheduleRecord {
                $first = $companyBudgets->first();
                $currencies = $companyBudgets->pluck('currency')->filter()->unique();
                return new OdooScheduleRecord([
                    'company_id' => $first->company_id,
                    'amount' => (float) $companyBudgets->sum('amount'),
                    'currency' => $currencies->count() === 1 ? $currencies->first() : 'MIXED',
                ]);
            })->values();
            return $this->project(
                $shifts,
                $repository->costRates(collect($shifts)->pluck('employee_id')->filter()->unique()->values()->all(), $weekStart, $rangeEnd),
                $repository->breaks(collect($shifts)->pluck('id')->filter()->values()->all()),
                $rangeBudgets
            );
        } catch(OdooException) {
            return $this->project($shifts,[],[],[]);
        }
    }
}
