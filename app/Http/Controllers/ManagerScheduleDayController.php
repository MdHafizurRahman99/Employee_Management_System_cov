<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerScheduleDayController extends Controller
{
    public function index(Request $request, OdooManagerPlanningService $planningService, OdooScheduleRepository $repository): View
    {
        $weekStart = $this->weekStart($request->query('week'));
        $weekEnd = $weekStart->copy()->endOfWeek();
        $companies = [];
        $odooPlanningError = null;
        try {
            $companies = $planningService->getShiftCreationPageDataForMonth($weekStart, $weekStart)['companies'];
        } catch (OdooException $exception) {
            $odooPlanningError = $exception->getMessage();
        }
        try{$areas=$repository->areas();$entries=$repository->dayEntries($weekStart,$weekEnd)->groupBy(fn(object $item):string=>$item->schedule_date->toDateString());}catch(OdooException $exception){$areas=collect();$entries=collect();$odooPlanningError=$odooPlanningError?:$exception->getMessage();}
        $days = collect(range(0, 6))->map(fn (int $offset): Carbon => $weekStart->copy()->addDays($offset));

        return view('admin.manager-shifts.days', compact('weekStart', 'weekEnd', 'companies', 'areas', 'entries', 'days', 'odooPlanningError'));
    }

    public function store(Request $request, OdooScheduleRepository $repository): RedirectResponse
    {
        $validated = $request->validate([
            'week' => ['required', 'date_format:Y-m-d'],
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'company_id' => ['required', 'integer'],
            'schedule_area_id' => ['nullable', 'integer'],
            'holiday_name' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
            'blocked_start' => ['nullable', 'date_format:H:i', 'required_with:blocked_end'],
            'blocked_end' => ['nullable', 'date_format:H:i', 'required_with:blocked_start', 'after:blocked_start'],
        ]);
        $area = ! empty($validated['schedule_area_id']) ? $repository->areas()->firstWhere('id',(int)$validated['schedule_area_id']) : null;
        if ($area && (int) $area->company_id !== (int) $validated['company_id']) {
            return back()->withErrors(['schedule_day' => 'The selected area belongs to another location.'])->withInput();
        }
        if (blank($validated['holiday_name'] ?? null) && blank($validated['note'] ?? null) && blank($validated['blocked_start'] ?? null)) {
            return back()->withErrors(['schedule_day' => 'Add a note, holiday label, or blocked time before saving.'])->withInput();
        }

        try{$repository->upsertDay($validated);}catch(OdooException $exception){return back()->withErrors(['schedule_day'=>$exception->getMessage()])->withInput();}

        return redirect()->route('manager.schedule-days.index', ['week' => $validated['week']])->with('success', 'Schedule day details saved.');
    }

    public function destroy(Request $request, OdooScheduleRepository $repository, int $dayMeta): RedirectResponse
    {
        $week = $this->weekStart($request->input('week'))->toDateString();
        try{$repository->deleteDay($dayMeta);}catch(OdooException $exception){return back()->withErrors(['schedule_day'=>$exception->getMessage()]);}

        return redirect()->route('manager.schedule-days.index', ['week' => $week])->with('success', 'Schedule day details removed.');
    }

    private function weekStart(mixed $value): Carbon
    {
        try {
            return filled($value) ? Carbon::createFromFormat('Y-m-d', (string) $value)->startOfWeek() : now()->startOfWeek();
        } catch (\Throwable) {
            return now()->startOfWeek();
        }
    }
}
