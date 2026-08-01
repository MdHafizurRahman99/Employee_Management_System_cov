<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Scheduling\AutoScheduleService;
use App\Services\Scheduling\ScheduleUndoService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManagerAutoScheduleController extends Controller
{
    public function index(
        Request $request,
        OdooManagerPlanningService $planning,
        OdooScheduleRepository $repository,
        AutoScheduleService $autoSchedule,
        ?ScheduleUndoService $undo = null
    ): View {
        $weekStart = $this->date($request->query('week'))->startOfWeek();
        $options = $this->options($request, false);
        $pageData = ['companies' => [], 'workLocations' => [], 'employees' => [], 'recentShifts' => [], 'weeklyRoster' => ['rows' => []]];
        $areas = collect();
        $preview = null;
        $odooPlanningError = null;

        try {
            $pageData = $planning->getShiftCreationPageDataForMonth($weekStart, $weekStart);
            $areas = $repository->areas();
            if ($request->boolean('preview')) {
                $options = $this->options($request, true);
                $preview = $autoSchedule->preview(
                    $weekStart,
                    $pageData,
                    $areas,
                    $repository->dayEntries($weekStart, $weekStart->copy()->endOfWeek()),
                    $options
                );
            }
        } catch (OdooException|\RuntimeException $exception) {
            $odooPlanningError = $exception->getMessage();
        }

        return view('admin.manager-shifts.auto-schedule', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->endOfWeek(),
            'companies' => $pageData['companies'],
            'workLocations' => $pageData['workLocations'] ?? [],
            'areas' => $areas,
            'options' => $options,
            'preview' => $preview,
            'odooPlanningError' => $odooPlanningError,
        ]);
    }

    public function apply(
        Request $request,
        OdooManagerPlanningService $planning,
        OdooScheduleRepository $repository,
        AutoScheduleService $autoSchedule
    ): RedirectResponse {
        $options = $this->options($request, true);
        $weekStart = $this->date($request->input('week'))->startOfWeek();

        try {
            $pageData = $planning->getShiftCreationPageDataForMonth($weekStart, $weekStart);
            $preview = $autoSchedule->preview(
                $weekStart,
                $pageData,
                $repository->areas(),
                $repository->dayEntries($weekStart, $weekStart->copy()->endOfWeek()),
                $options
            );
            if ($preview['proposals'] === []) {
                throw new \RuntimeException('There are no safe auto-schedule proposals left to create. Refresh the preview.');
            }
            $result = $autoSchedule->apply($preview, $planning);
        } catch (OdooException|\RuntimeException $exception) {
            return redirect()->route('manager.auto-schedule.index', $this->query($weekStart, $options))
                ->withErrors(['auto_schedule' => $exception->getMessage()]);
        }

        $redirect = redirect()->route('manager.shifts.create', [
            'month' => $weekStart->format('Y-m'),
            'day' => $weekStart->toDateString(),
            'view' => 'area',
        ])->with('success', $result['created'].' Odoo shift(s) created: '.$result['assigned'].' assigned and '.$result['open'].' open.');
        if($undo && $result['created_ids']){
            try{$redirect->with('schedule_undo',$undo->recordCreatedSlots($result['created_ids'],'Auto schedule coverage',$planning,$request->user(),$options['company_id']));}catch(OdooException|\RuntimeException){}
        }
        return $redirect;
    }

    /** @return array{company_id:int,work_location_id:int,start_time:string,end_time:string,max_weekly_hours:int,create_open_shifts:bool,allow_diary_override:bool} */
    private function options(Request $request, bool $validate): array
    {
        $defaults = [
            'company_id' => (int) $request->input('company_id', 0),
            'work_location_id' => (int) $request->input('work_location_id', 0),
            'start_time' => (string) $request->input('start_time', '09:00'),
            'end_time' => (string) $request->input('end_time', '17:00'),
            'max_weekly_hours' => (int) $request->input('max_weekly_hours', 38),
            'create_open_shifts' => $request->has('preview') || $request->isMethod('post')
                ? $request->boolean('create_open_shifts')
                : true,
            'allow_diary_override' => $request->boolean('allow_diary_override'),
        ];
        if (! $validate) {
            return $defaults;
        }

        $data = $request->validate([
            'week' => ['required', 'date_format:Y-m-d'],
            'company_id' => ['required', 'integer', 'min:1'],
            'work_location_id' => ['required', 'integer', 'min:1'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'max_weekly_hours' => ['required', 'integer', 'min:1', 'max:80'],
            'create_open_shifts' => ['nullable', 'boolean'],
            'allow_diary_override' => ['nullable', 'boolean'],
        ]);
        if ($data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages(['end_time' => 'The end time must be later than the start time.']);
        }

        return [
            'company_id' => (int) $data['company_id'],
            'work_location_id' => (int) $data['work_location_id'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'max_weekly_hours' => (int) $data['max_weekly_hours'],
            'create_open_shifts' => (bool) ($data['create_open_shifts'] ?? false),
            'allow_diary_override' => (bool) ($data['allow_diary_override'] ?? false),
        ];
    }

    /** @return array<string,mixed> */
    private function query(Carbon $weekStart, array $options): array
    {
        return [
            'preview' => 1,
            'week' => $weekStart->toDateString(),
            'company_id' => $options['company_id'],
            'work_location_id' => $options['work_location_id'],
            'start_time' => $options['start_time'],
            'end_time' => $options['end_time'],
            'max_weekly_hours' => $options['max_weekly_hours'],
            'create_open_shifts' => $options['create_open_shifts'] ? 1 : 0,
            'allow_diary_override' => $options['allow_diary_override'] ? 1 : 0,
        ];
    }

    private function date(?string $value): Carbon
    {
        try { return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : now()->startOfDay(); }
        catch (\Throwable) { return now()->startOfDay(); }
    }
}
