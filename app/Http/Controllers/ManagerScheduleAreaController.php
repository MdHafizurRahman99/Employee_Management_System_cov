<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerScheduleAreaController extends Controller
{
    public function index(OdooManagerPlanningService $planningService, OdooScheduleRepository $repository): View
    {
        $roles = [];
        $companies = [];
        $odooPlanningError = null;

        try {
            $data = $planningService->getShiftCreationPageData();
            $roles = $data['roles'];
            $companies = $data['companies'];
        } catch (OdooException $exception) {
            $odooPlanningError = $exception->getMessage();
        }

        try {$areas=$repository->areas(false);} catch(OdooException $exception){$areas=collect();$odooPlanningError=$odooPlanningError?:$exception->getMessage();}

        return view('admin.manager-shifts.areas', compact('areas', 'roles', 'companies', 'odooPlanningError'));
    }

    public function store(Request $request, OdooManagerPlanningService $planningService, OdooScheduleRepository $repository): RedirectResponse
    {
        $validated = $this->validateArea($request);

        try {
            $data = $planningService->getShiftCreationPageData();
            $role = collect($data['roles'])->firstWhere('id', (int) $validated['odoo_role_id']);
            $company = collect($data['companies'])->firstWhere('id', (int) $validated['company_id']);
            if (! $role || ! $company || (! empty($role['company_id']) && (int) $role['company_id'] !== (int) $company['id'])) {
                throw new OdooException('Choose an Odoo role and location that belong together.');
            }
        } catch (OdooException $exception) {
            return back()->withErrors(['schedule_area' => $exception->getMessage()])->withInput();
        }

        try{$repository->upsertArea($validated);}catch(OdooException $exception){return back()->withErrors(['schedule_area'=>$exception->getMessage()])->withInput();}

        return back()->with('success', 'Scheduling area and coverage targets saved.');
    }

    public function update(Request $request, OdooScheduleRepository $repository, int $area): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'coverage' => ['required', 'array', 'size:7'],
            'coverage.*' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        try{$repository->updateArea($area,$validated);}catch(OdooException $exception){return back()->withErrors(['schedule_area'=>$exception->getMessage()]);}

        return back()->with('success', 'Area coverage targets updated.');
    }

    public function destroy(OdooScheduleRepository $repository, int $area): RedirectResponse
    {
        try{$repository->archiveArea($area);}catch(OdooException $exception){return back()->withErrors(['schedule_area'=>$exception->getMessage()]);}

        return back()->with('success', 'Scheduling area hidden. Odoo shifts were not changed.');
    }

    /** @return array<string,mixed> */
    private function validateArea(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'integer'],
            'odoo_role_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'coverage' => ['required', 'array', 'size:7'],
            'coverage.*' => ['required', 'integer', 'min:0', 'max:999'],
        ]);
    }

}
