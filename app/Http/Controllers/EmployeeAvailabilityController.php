<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooWeeklyAvailabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeAvailabilityController extends Controller
{
    public function index(Request $request, OdooWeeklyAvailabilityService $availabilityService): View
    {
        $pageData = [
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

        if ($hasAvailabilityIdentity) {
            try {
                $pageData = $availabilityService->getAvailabilityPageData($request->user());
            } catch (OdooException $exception) {
                $odooAvailabilityError = $exception->getMessage();
            }
        }

        return view('admin.employee-availability.index', [
            'availabilityDays' => $pageData['days'],
            'availabilitySummary' => $pageData['summary'],
            'availabilityEntries' => $pageData['entries'],
            'odooAvailabilityError' => $odooAvailabilityError,
            'hasAvailabilityIdentity' => $hasAvailabilityIdentity,
        ]);
    }

    public function store(Request $request, OdooWeeklyAvailabilityService $availabilityService): RedirectResponse
    {
        $validated = $this->validateAvailabilityRequest($request);

        try {
            $availabilityService->createAvailability($request->user(), $validated);
        } catch (OdooException $exception) {
            return redirect()
                ->route('employee.availability.index')
                ->withErrors(['availability' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('employee.availability.index')
            ->with('success', 'Your weekly availability rule was saved successfully.');
    }

    public function update(
        Request $request,
        OdooWeeklyAvailabilityService $availabilityService,
        int $availability
    ): RedirectResponse {
        $validated = $this->validateAvailabilityRequest($request);

        try {
            $availabilityService->updateAvailability($request->user(), $availability, $validated);
        } catch (OdooException $exception) {
            return redirect()
                ->route('employee.availability.index')
                ->withErrors(['availability' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('employee.availability.index')
            ->with('success', 'Your weekly availability rule was updated successfully.');
    }

    public function destroy(
        Request $request,
        OdooWeeklyAvailabilityService $availabilityService,
        int $availability
    ): RedirectResponse {
        try {
            $availabilityService->deleteAvailability($request->user(), $availability);
        } catch (OdooException $exception) {
            return redirect()
                ->route('employee.availability.index')
                ->withErrors(['availability' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.availability.index')
            ->with('success', 'The weekly availability rule was removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAvailabilityRequest(Request $request): array
    {
        $validated = $request->validate([
            'day_of_week' => ['required', 'in:0,1,2,3,4,5,6'],
            'availability_type' => ['required', 'in:available,unavailable'],
            'is_full_day' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $isFullDay = (bool) ($validated['is_full_day'] ?? false);

        if (! $isFullDay) {
            if (empty($validated['start_time']) || empty($validated['end_time'])) {
                throw ValidationException::withMessages([
                    'availability' => 'Please provide both a start time and an end time.',
                ]);
            }

            $validated['start_time'] = $this->convertTimeToFloat($validated['start_time']);
            $validated['end_time'] = $this->convertTimeToFloat($validated['end_time']);

            if ($validated['end_time'] <= $validated['start_time']) {
                throw ValidationException::withMessages([
                    'availability' => 'The end time must be later than the start time.',
                ]);
            }
        } else {
            $validated['start_time'] = null;
            $validated['end_time'] = null;
        }

        $validated['is_full_day'] = $isFullDay;

        return $validated;
    }

    private function convertTimeToFloat(string $time): float
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return round($hours + ($minutes / 60), 2);
    }
}
