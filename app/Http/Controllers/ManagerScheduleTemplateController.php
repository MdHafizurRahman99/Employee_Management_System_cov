<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Scheduling\ScheduleTemplateService;
use App\Services\Scheduling\ScheduleUndoService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerScheduleTemplateController extends Controller
{
    public function index(Request $request, OdooManagerPlanningService $planning, ScheduleTemplateService $templates, OdooScheduleRepository $repository): View
    {
        $target = $this->date($request->query('target_week'))->startOfWeek();
        $selected = $request->query('template') ? $repository->template((int)$request->query('template')) : null;
        $preview = null;
        $error = null;
        $companies=[];
        try{$companies=$planning->getShiftCreationPageDataForMonth($target,$target)['companies'];}catch(OdooException $exception){$error=$exception->getMessage();}
        if ($selected) {
            try { $preview = $templates->preview($selected, $target, $planning->getWeeklyShiftsForDate($target)); }
            catch (OdooException $exception) { $error = $exception->getMessage(); }
        }

        return view('admin.manager-shifts.templates', [
            'templates' => $repository->templates(),
            'selectedTemplate' => $selected, 'preview' => $preview, 'targetWeek' => $target, 'odooPlanningError' => $error, 'companies'=>$companies,
        ]);
    }

    public function store(Request $request, OdooManagerPlanningService $planning, ScheduleTemplateService $templates): RedirectResponse
    {
        $data = $request->validate(['name' => ['required','string','max:120'], 'description' => ['nullable','string','max:500'],
            'company_id' => ['required','integer'], 'source_day' => ['required','date_format:Y-m-d']]);
        $weekStart = $this->date($data['source_day'])->startOfWeek();
        try { $template = $templates->saveVisibleWeek($planning->getWeeklyShiftsForDate($weekStart), $weekStart, $data, $request->user()); }
        catch (OdooException|\RuntimeException $exception) { return back()->withErrors(['schedule_template' => $exception->getMessage()])->withInput(); }

        return redirect()->route('manager.schedule-templates.index', ['template' => $template->id, 'target_week' => $weekStart->copy()->addWeek()->toDateString()])
            ->with('success', 'Schedule template saved.');
    }

    public function apply(Request $request, OdooManagerPlanningService $planning, ScheduleTemplateService $templates, OdooScheduleRepository $repository, int $template, ?ScheduleUndoService $undo = null): RedirectResponse
    {
        $data = $request->validate(['target_week' => ['required','date_format:Y-m-d'], 'skip_conflicts' => ['nullable','boolean']]);
        $templateRecord=$repository->template($template);
        if(!$templateRecord)return back()->withErrors(['schedule_template'=>'The Odoo template is unavailable.']);
        $target = $this->date($data['target_week'])->startOfWeek();
        try {
            $result = $templates->apply($templateRecord, $target, $planning->getWeeklyShiftsForDate($target), $planning, $request->user(), (bool) ($data['skip_conflicts'] ?? false));
        } catch (OdooException|\RuntimeException $exception) {
            return redirect()->route('manager.schedule-templates.index', ['template'=>$template,'target_week'=>$target->toDateString()])
                ->withErrors(['schedule_template' => $exception->getMessage()]);
        }

        $redirect=redirect()->route('manager.shifts.create', ['month'=>$target->format('Y-m'),'day'=>$target->toDateString()])
            ->with('success', $result['created'].' template shift(s) created; '.$result['skipped'].' conflict(s) skipped.');
        if($undo && $result['created_ids']){try{$redirect->with('schedule_undo',$undo->recordCreatedSlots($result['created_ids'],'Apply schedule template',$planning,$request->user(),$templateRecord->company_id));}catch(OdooException|\RuntimeException){}}
        return $redirect;
    }

    public function archive(OdooScheduleRepository $repository, int $template): RedirectResponse
    {
        $repository->archiveTemplate($template);
        return redirect()->route('manager.schedule-templates.index')->with('success', 'Schedule template archived.');
    }

    private function date(?string $value): Carbon
    {
        try { return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : now()->startOfDay(); }
        catch (\Throwable) { return now()->startOfDay(); }
    }
}
