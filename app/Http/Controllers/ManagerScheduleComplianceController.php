<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Scheduling\ScheduleComplianceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerScheduleComplianceController extends Controller
{
    public function index(Request $request, OdooManagerPlanningService $planningService, ScheduleComplianceService $compliance, OdooScheduleRepository $repository): View
    {
        $weekStart = $this->weekStart($request->query('week'));
        $shifts = [];
        $companies = [];
        $odooPlanningError = null;
        try {
            $shifts = $planningService->getWeeklyShiftsForDate($weekStart);
            $companies = $planningService->getShiftCreationPageDataForMonth($weekStart, $weekStart)['companies'];
        } catch (OdooException $exception) {
            $odooPlanningError = $exception->getMessage();
        }
        try{$rules=$repository->complianceRules();$breaks=$repository->breaks(collect($shifts)->pluck('id')->all());}catch(OdooException $exception){$rules=collect();$breaks=collect();$odooPlanningError=$odooPlanningError?:$exception->getMessage();}
        $audit = $compliance->evaluateShiftList($shifts, $rules->where('is_enabled', true), $breaks);

        return view('admin.manager-shifts.compliance', compact('weekStart', 'companies', 'rules', 'audit', 'odooPlanningError'));
    }

    public function storeRule(Request $request, OdooScheduleRepository $repository): RedirectResponse
    {
        $data = $request->validate([
            'week' => ['required', 'date_format:Y-m-d'], 'company_id' => ['required', 'integer'],
            'break_required_after_minutes' => ['required', 'integer', 'min:60', 'max:1440'],
            'minimum_break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'maximum_shift_minutes' => ['required', 'integer', 'min:60', 'max:1440'],
            'minimum_rest_minutes' => ['required', 'integer', 'min:0', 'max:2880'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
        try{$repository->upsertComplianceRule($data);}catch(OdooException $exception){return back()->withErrors(['schedule_compliance'=>$exception->getMessage()])->withInput();}

        return back()->with('success', 'Compliance rules saved.');
    }

    public function storeBreak(Request $request, OdooManagerPlanningService $planningService, OdooScheduleRepository $repository): RedirectResponse
    {
        $data = $request->validate([
            'week' => ['required', 'date_format:Y-m-d'], 'odoo_slot_id' => ['required', 'integer'],
            'start_time' => ['required', 'date_format:H:i'], 'duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'is_paid' => ['nullable', 'boolean'], 'note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $shift = collect($planningService->getWeeklyShiftsForDate($this->weekStart($data['week'])))->firstWhere('id', (int) $data['odoo_slot_id']);
            if (! $shift || ! ($shift['start_at'] instanceof Carbon) || ! ($shift['end_at'] instanceof Carbon)) throw new OdooException('The selected Odoo shift is no longer in this week.');
            $breakStart = Carbon::parse($shift['start_at']->toDateString().' '.$data['start_time']);
            $breakEnd = $breakStart->copy()->addMinutes((int) $data['duration_minutes']);
            if ($breakStart->lt($shift['start_at']) || $breakEnd->gt($shift['end_at'])) {
                throw new OdooException('The planned break must fit completely inside the shift.');
            }
            $overlaps = $repository->breaks([(int)$data['odoo_slot_id']])->contains(function (object $existing) use ($shift, $breakStart, $breakEnd): bool {
                $existingStart = Carbon::parse($shift['start_at']->toDateString().' '.$existing->start_time);
                $existingEnd = $existingStart->copy()->addMinutes($existing->duration_minutes);
                return $breakStart->lt($existingEnd) && $breakEnd->gt($existingStart);
            });
            if ($overlaps) throw new OdooException('This break overlaps another planned break on the shift.');
        } catch (OdooException $exception) {
            return back()->withErrors(['schedule_compliance' => $exception->getMessage()])->withInput();
        }
        try{$repository->createBreak($data);}catch(OdooException $exception){return back()->withErrors(['schedule_compliance'=>$exception->getMessage()])->withInput();}

        return back()->with('success', 'Break added to the shift plan.');
    }

    public function destroyBreak(Request $request, OdooScheduleRepository $repository, int $shiftBreak): RedirectResponse
    {
        try{$repository->deleteBreak($shiftBreak);}catch(OdooException $exception){return back()->withErrors(['schedule_compliance'=>$exception->getMessage()]);}
        return redirect()->route('manager.schedule-compliance.index', ['week' => $this->weekStart($request->input('week'))->toDateString()])->with('success', 'Planned break removed.');
    }

    private function weekStart(mixed $value): Carbon
    {
        try { return filled($value) ? Carbon::createFromFormat('Y-m-d', (string) $value)->startOfWeek() : now()->startOfWeek(); }
        catch (\Throwable) { return now()->startOfWeek(); }
    }
}
