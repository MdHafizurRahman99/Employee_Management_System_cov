<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooPlanningService;
use App\Services\Odoo\OdooWeeklyAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function show(
        Request $request,
        OdooPlanningService $planningService,
        OdooWeeklyAvailabilityService $availabilityService
    ): View|RedirectResponse
    {
        if ($request->user()?->isManagerLike()) {
            return redirect()->route('manager.dashboard');
        }

        $todayShift = null;
        $upcomingShifts = [];
        $odooShiftError = null;
        $availabilityPageData = [
            'days' => [],
            'summary' => [
                'configured_days' => 0,
                'total_rules' => 0,
                'available_rules' => 0,
                'unavailable_rules' => 0,
                'full_day_rules' => 0,
            ],
            'entries' => [],
        ];
        $odooAvailabilityError = null;
        $hasAvailabilityIdentity = filled($request->user()?->odoo_employee_id);

        if ($request->user()?->odoo_employee_id || $request->user()?->odoo_resource_id) {
            try {
                $todayShift = $planningService->getTodayShiftForUser($request->user());
                $upcomingShifts = $planningService->getUpcomingShiftsForUser($request->user(), 5);
            } catch (OdooException $exception) {
                $odooShiftError = $exception->getMessage();
            }
        }

        if ($hasAvailabilityIdentity) {
            try {
                $availabilityPageData = $availabilityService->getAvailabilityPageData($request->user());
            } catch (OdooException $exception) {
                $odooAvailabilityError = $exception->getMessage();
            }
        }

        return view('admin.dashboard', [
            'todayShift' => $todayShift,
            'upcomingShifts' => $upcomingShifts,
            'odooShiftError' => $odooShiftError,
            'availabilityDays' => $availabilityPageData['days'],
            'availabilitySummary' => $availabilityPageData['summary'],
            'odooAvailabilityError' => $odooAvailabilityError,
            'hasAvailabilityIdentity' => $hasAvailabilityIdentity,
        ]);
    }
}
