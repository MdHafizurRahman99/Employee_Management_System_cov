<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Scheduling\ScheduleBudgetService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerScheduleBudgetController extends Controller
{
    public function index(Request $request, OdooManagerPlanningService $planning, ScheduleBudgetService $budgetService, OdooScheduleRepository $repository): View
    {
        $weekStart=$this->weekStart($request->query('week')); $shifts=[]; $companies=[]; $odooPlanningError=null;
        try { $shifts=$planning->getWeeklyShiftsForDate($weekStart); $companies=$planning->getShiftCreationPageDataForMonth($weekStart,$weekStart)['companies']; }
        catch(OdooException $exception){$odooPlanningError=$exception->getMessage();}
        $forecast=$budgetService->projectFromStorage($shifts,$weekStart);
        $employees=collect($shifts)->filter(fn(array $s):bool=>!empty($s['employee_id']))->unique('employee_id')->sortBy('employee')->values();
        try{$currentRates=$repository->costRates($employees->pluck('employee_id')->all(),$weekStart,$weekStart->copy()->endOfWeek())->unique(fn(object $r):string=>$r->company_id.':'.$r->employee_id);$weekBudgets=$repository->weekBudgets($weekStart)->keyBy('company_id');}catch(OdooException $exception){$currentRates=collect();$weekBudgets=collect();$odooPlanningError=$odooPlanningError?:$exception->getMessage();}
        return view('admin.manager-shifts.budget',compact('weekStart','companies','employees','currentRates','weekBudgets','forecast','odooPlanningError'));
    }

    public function storeRate(Request $request, OdooManagerPlanningService $planning, OdooScheduleRepository $repository): RedirectResponse
    {
        $data=$request->validate(['week'=>['required','date_format:Y-m-d'],'company_id'=>['required','integer'],'employee_id'=>['required','integer'],'hourly_rate'=>['required','numeric','min:0','max:100000'],'currency'=>['required','string','size:3'],'effective_from'=>['required','date_format:Y-m-d'],'effective_to'=>['nullable','date_format:Y-m-d','after_or_equal:effective_from']]);
        try {
            $valid=collect($planning->getWeeklyShiftsForDate($this->weekStart($data['week'])))->contains(fn(array $shift):bool=>(int)($shift['company_id']??0)===(int)$data['company_id']&&(int)($shift['employee_id']??0)===(int)$data['employee_id']);
            if(!$valid) throw new OdooException('Choose an employee and location from the visible Odoo schedule week.');
        } catch(OdooException $exception){return back()->withErrors(['schedule_budget'=>$exception->getMessage()])->withInput();}
        try{$repository->upsertRate($data);}catch(OdooException $exception){return back()->withErrors(['schedule_budget'=>$exception->getMessage()])->withInput();}
        return back()->with('success','Confirmed hourly rate saved.');
    }

    public function storeBudget(Request $request, OdooManagerPlanningService $planning, OdooScheduleRepository $repository): RedirectResponse
    {
        $data=$request->validate(['week'=>['required','date_format:Y-m-d'],'company_id'=>['required','integer'],'amount'=>['required','numeric','min:0','max:999999999'],'currency'=>['required','string','size:3']]);
        $week=$this->weekStart($data['week']);
        try {
            $companies=$planning->getShiftCreationPageDataForMonth($week,$week)['companies'];
            if(!collect($companies)->contains(fn(array $company):bool=>(int)$company['id']===(int)$data['company_id'])) throw new OdooException('Choose a location returned by Odoo.');
        } catch(OdooException $exception){return back()->withErrors(['schedule_budget'=>$exception->getMessage()])->withInput();}
        try{$repository->upsertBudget($data,$week);}catch(OdooException $exception){return back()->withErrors(['schedule_budget'=>$exception->getMessage()])->withInput();}
        return back()->with('success','Weekly schedule budget saved.');
    }

    private function weekStart(mixed $value): Carbon
    {
        try{return filled($value)?Carbon::createFromFormat('Y-m-d',(string)$value)->startOfWeek():now()->startOfWeek();}catch(\Throwable){return now()->startOfWeek();}
    }
}
