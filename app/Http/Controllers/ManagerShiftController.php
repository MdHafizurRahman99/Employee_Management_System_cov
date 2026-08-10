<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Scheduling\SchedulingAreaService;
use App\Services\Scheduling\ScheduleDayService;
use App\Services\Scheduling\ScheduleComplianceService;
use App\Services\Scheduling\ScheduleBudgetService;
use App\Services\Scheduling\SchedulePublishService;
use App\Services\Scheduling\ScheduleUndoService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerShiftController extends Controller
{
    private const VISIBLE_PERIOD_DAYS = 14;
    private const MAX_VISIBLE_WEEKS = 12;

    /**
     * Display the manager Odoo shift creation page.
     */
    public function create(
        Request $request,
        OdooManagerPlanningService $planningService
    ): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedDay = $this->resolveSelectedDay($request->query('day'), $selectedMonth);
        $selectedView = $this->resolveView($request->query('view'));
        $rangeAnchor = $selectedDay
            ?? (now()->isSameMonth($selectedMonth) ? now()->startOfDay() : $selectedMonth->copy()->startOfMonth());
        [$scheduleRangeStart, $scheduleRangeEnd, $scheduleRangeWasLimited] = $this->resolveScheduleRange($request, $rangeAnchor);
        $pageData = [
            'employees' => [],
            'roles' => [],
            'companies' => [],
            'workLocations' => [],
            'recentShifts' => [],
            'shiftCalendar' => [],
            'selectedCalendarDate' => $selectedMonth->copy()->startOfMonth(),
            'selectedCalendarDateLabel' => $selectedMonth->format('d-m-Y'),
            'selectedCalendarDateValue' => $selectedMonth->format('Y-m-d'),
            'selectedCalendarShifts' => [],
            'weeklyRoster' => $this->emptyWeeklyRoster($rangeAnchor, $scheduleRangeStart, $scheduleRangeEnd),
            'weeklyAreaBoard' => $this->emptyWeeklyAreaBoard($rangeAnchor, $scheduleRangeStart, $scheduleRangeEnd),
            'employeeDiary' => [
                'entries' => [],
                'by_employee_date' => [],
                'by_date' => [],
                'count' => 0,
            ],
            'employeeDiaryError' => null,
        ];
        $odooPlanningError = null;

        try {
            $pageData = $planningService->getShiftCreationPageDataForMonth(
                $selectedMonth,
                $selectedDay,
                $scheduleRangeStart,
                $scheduleRangeEnd
            );
            $odooPlanningError = $pageData['employeeDiaryError'] ?? null;
        } catch (OdooException $exception) {
            $odooPlanningError = $exception->getMessage();
        }

        $weeklyAreaBoard = app(SchedulingAreaService::class)->decorateBoard(
            $pageData['weeklyAreaBoard'] ?? $this->emptyWeeklyAreaBoard($rangeAnchor, $scheduleRangeStart, $scheduleRangeEnd)
        );
        [$weeklyRoster, $weeklyAreaBoard] = app(ScheduleDayService::class)->decorateViews(
            $pageData['weeklyRoster'],
            $weeklyAreaBoard
        );
        [$weeklyRoster, $weeklyAreaBoard, $complianceSummary] = app(ScheduleComplianceService::class)->decorateViews($weeklyRoster, $weeklyAreaBoard);
        $budgetShifts = collect($weeklyRoster['rows'] ?? [])->flatMap(fn (array $row) => collect($row['cells'] ?? [])->flatMap(fn (array $cell) => $cell['shifts'] ?? []))->unique('id')->values()->all();
        $budgetForecast = app(ScheduleBudgetService::class)->projectFromStorage(
            $budgetShifts,
            $weeklyRoster['week_start'],
            $weeklyRoster['week_end']
        );
        return view('admin.manager-shifts.create', [
            'selectedMonth' => $selectedMonth,
            'previousMonth' => $selectedMonth->copy()->subMonthNoOverflow(),
            'nextMonth' => $selectedMonth->copy()->addMonthNoOverflow(),
            'employees' => $pageData['employees'],
            'roles' => $pageData['roles'],
            'companies' => $pageData['companies'],
            'workLocations' => $pageData['workLocations'] ?? [],
            'recentShifts' => $pageData['recentShifts'],
            'shiftCalendar' => $pageData['shiftCalendar'],
            'selectedCalendarDate' => $pageData['selectedCalendarDate'],
            'selectedCalendarDateLabel' => $pageData['selectedCalendarDateLabel'],
            'selectedCalendarDateValue' => $pageData['selectedCalendarDateValue'],
            'selectedCalendarShifts' => $pageData['selectedCalendarShifts'],
            'weeklyRoster' => $weeklyRoster,
            'weeklyAreaBoard' => $weeklyAreaBoard,
            'complianceSummary' => $complianceSummary,
            'budgetForecast' => $budgetForecast,
            'employeeDiary' => $pageData['employeeDiary'] ?? [
                'entries' => [],
                'by_employee_date' => [],
                'by_date' => [],
                'count' => 0,
            ],
            'selectedView' => $selectedView,
            'scheduleRangeStart' => $scheduleRangeStart,
            'scheduleRangeEnd' => $scheduleRangeEnd,
            'scheduleRangeDays' => $scheduleRangeStart->diffInDays($scheduleRangeEnd) + 1,
            'scheduleRangeWasLimited' => $scheduleRangeWasLimited,
            'showScheduleScopeModal' => $request->boolean('setup')
                || ! $request->filled('start_date')
                || ! $request->filled('end_date'),
            'odooPlanningError' => $odooPlanningError,
        ]);
    }

    /** Display employee responses for confirmation-required shifts. */
    public function confirmations(Request $request, OdooManagerPlanningService $planningService): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedDay = $this->resolveSelectedDay($request->query('day'), $selectedMonth)
            ?? $selectedMonth->copy()->startOfMonth();
        $status = in_array($request->query('status'), ['pending', 'accepted', 'declined', 'updated'], true)
            ? (string) $request->query('status')
            : 'all';
        $search = trim((string) $request->query('search', ''));
        $hasRange = $request->filled('start_date') && $request->filled('end_date');
        [$weekStart, $weekEnd] = $hasRange
            ? array_slice($this->resolveScheduleRange($request, $selectedDay), 0, 2)
            : [$selectedDay->copy()->startOfWeek(), $selectedDay->copy()->endOfWeek()];
        $shifts = [];
        $odooPlanningError = null;

        try {
            $shifts = array_values(array_filter(
                $hasRange
                    ? $planningService->getShiftsForRange($weekStart, $weekEnd)
                    : $planningService->getWeeklyShiftsForDate($selectedDay),
                fn (array $shift): bool => (bool) ($shift['requires_confirmation'] ?? false)
            ));
        } catch (OdooException $exception) {
            $odooPlanningError = $exception->getMessage();
        }

        $summary = [
            'all' => count($shifts),
            'pending' => count(array_filter($shifts, fn (array $shift): bool => ($shift['confirmation_status'] ?? 'pending') === 'pending')),
            'accepted' => count(array_filter($shifts, fn (array $shift): bool => ($shift['confirmation_status'] ?? null) === 'accepted')),
            'declined' => count(array_filter($shifts, fn (array $shift): bool => ($shift['confirmation_status'] ?? null) === 'declined')),
            'updated' => count(array_filter($shifts, fn (array $shift): bool => ($shift['publish_state'] ?? null) === 'updated')),
        ];

        $filteredShifts = array_values(array_filter($shifts, function (array $shift) use ($status, $search): bool {
            $matchesStatus = $status === 'all'
                || ($status === 'updated' && ($shift['publish_state'] ?? null) === 'updated')
                || ($status !== 'updated' && ($shift['confirmation_status'] ?? 'pending') === $status);
            $haystack = mb_strtolower(implode(' ', [
                $shift['employee'] ?? '', $shift['role'] ?? '', $shift['company'] ?? '',
                $shift['title'] ?? '', $shift['confirmation_note'] ?? '',
            ]));

            return $matchesStatus && ($search === '' || str_contains($haystack, mb_strtolower($search)));
        }));

        return view('admin.manager-shifts.confirmations', compact(
            'selectedMonth', 'selectedDay', 'weekStart', 'weekEnd', 'status', 'search',
            'filteredShifts', 'summary', 'odooPlanningError'
        ));
    }

    public function remindConfirmation(
        Request $request,
        OdooManagerPlanningService $planningService,
        SchedulePublishService $publishService,
        int $shift
    ): RedirectResponse {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'day' => ['required', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $selectedDay = Carbon::createFromFormat('Y-m-d', $validated['day'])->startOfDay();

        try {
            $candidateShifts = ! empty($validated['start_date']) && ! empty($validated['end_date'])
                ? $planningService->getShiftsForRange(
                    Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfWeek(),
                    Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfWeek()
                )
                : $planningService->getWeeklyShiftsForDate($selectedDay);
            $candidate = collect($candidateShifts)
                ->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $shift);

            if (! is_array($candidate)) {
                throw new OdooException('The selected shift is no longer in this schedule week.');
            }

            $sent = $publishService->sendConfirmationReminder($candidate, $request->user());
        } catch (OdooException|\RuntimeException $exception) {
            return redirect()->route('manager.shifts.confirmations', $validated)
                ->withErrors(['manager_shift' => $exception->getMessage()]);
        }

        return redirect()->route('manager.shifts.confirmations', $validated)
            ->with('success', $sent > 0 ? 'Confirmation reminder delivered.' : 'No matching local employee account was available for delivery.');
    }

    /**
     * Store a new Odoo planning slot for the team.
     */
    public function store(Request $request, OdooManagerPlanningService $planningService, ?ScheduleUndoService $undo = null): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'role_id' => ['required', 'integer'],
            'company_id' => ['required', 'integer'],
            'work_location_id' => ['nullable', 'integer'],
            'shift_date' => ['required', 'date_format:Y-m-d'],
            'shift_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:shift_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $createdIds = $planningService->createShiftsReturningIds($validated);
            $createdCount = count($createdIds);
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => $exception->getMessage()])
                ->withInput();
        }

        $redirect = redirect()
            ->route('manager.shifts.create', $this->preservedFilters($request))
            ->with('success', $createdCount === 1
                ? 'The Odoo shift was created successfully.'
                : $createdCount.' Odoo shifts were created successfully.');
        if($undo && $createdIds){try{$redirect->with('schedule_undo',$undo->recordCreatedSlots($createdIds,'Create shift',$planningService,$request->user(),(int)$validated['company_id']));}catch(OdooException|\RuntimeException){}}
        return $redirect;
    }

    /**
     * Update an existing Odoo planning slot for the team.
     */
    public function update(Request $request, OdooManagerPlanningService $planningService, int $shift): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer'],
            'role_id' => ['required', 'integer'],
            'company_id' => ['required', 'integer'],
            'work_location_id' => ['required', 'integer'],
            'shift_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
            'last_known_write_date' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $planningService->updateShift($shift, $validated);
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('manager.shifts.create', $this->preservedFilters($request))
            ->with('success', 'The Odoo shift was updated successfully.');
    }

    /**
     * Delete an existing Odoo planning slot for the team.
     */
    public function destroy(Request $request, OdooManagerPlanningService $planningService, int $shift): RedirectResponse
    {
        $validated = $request->validate([
            'last_known_write_date' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $planningService->deleteShift($shift, (string) ($validated['last_known_write_date'] ?? ''));
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => $exception->getMessage()]);
        }

        return redirect()
            ->route('manager.shifts.create', $this->preservedFilters($request))
            ->with('success', 'The Odoo shift was deleted successfully.');
    }

    /**
     * Mark the currently visible date range as published on Odoo planning slots.
     */
    public function publishWeek(
        Request $request,
        OdooManagerPlanningService $planningService,
        SchedulePublishService $publishService
    ): RedirectResponse {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'day' => ['required', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'requires_confirmation' => ['nullable', 'boolean'],
            'notification_mode' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            if (! empty($validated['start_date']) && ! empty($validated['end_date'])) {
                $periodStart = Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfWeek();
                $periodEnd = Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfWeek();
                if ($periodStart->diffInDays($periodEnd) + 1 > self::MAX_VISIBLE_WEEKS * 7) {
                    return back()->withErrors(['manager_shift' => 'The schedule range cannot exceed 12 full weeks.']);
                }
                $weekShifts = $planningService->getShiftsForRange($periodStart, $periodEnd);
            } else {
                $selectedMonth = $this->resolveMonth((string) $validated['month']);
                $selectedDay = $this->resolveSelectedDay((string) $validated['day'], $selectedMonth)
                    ?? $selectedMonth->copy()->startOfWeek();
                $weekShifts = $planningService->getWeeklyShiftsForDate($selectedDay);
            }
            $publishedCount = $publishService->publishShifts(
                $weekShifts,
                $request->user(),
                (bool) ($validated['requires_confirmation'] ?? false),
                (string) ($validated['notification_mode'] ?? '')
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => $exception->getMessage()]);
        }

        return redirect()
            ->route('manager.shifts.create', $this->preservedFilters($request))
            ->with('success', $publishedCount === 0
                ? 'No Odoo shifts were available to publish for the visible date range.'
                : $publishedCount.' Odoo shift'.($publishedCount === 1 ? ' was' : 's were').' marked as published for this date range.');
    }

    /**
     * Delete multiple selected Odoo planning slots.
     */
    public function bulkDelete(Request $request, OdooManagerPlanningService $planningService): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'day' => ['required', 'date_format:Y-m-d'],
            'shifts' => ['required', 'array', 'min:1'],
            'shifts.*.id' => ['required', 'integer'],
            'shifts.*.last_known_write_date' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            foreach ($validated['shifts'] as $shiftData) {
                $planningService->deleteShift(
                    (int) $shiftData['id'],
                    (string) ($shiftData['last_known_write_date'] ?? '')
                );
            }
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => $exception->getMessage()]);
        }

        $count = count($validated['shifts']);

        return redirect()
            ->route('manager.shifts.create', $this->preservedFilters($request))
            ->with('success', $count === 1
                ? 'The selected Odoo shift was deleted successfully.'
                : $count.' Odoo shifts were deleted successfully.');
    }

    /**
     * Convert multiple selected shifts into open shifts.
     */
    public function bulkOpen(Request $request, OdooManagerPlanningService $planningService): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'day' => ['required', 'date_format:Y-m-d'],
            'shifts' => ['required', 'array', 'min:1'],
            'shifts.*.id' => ['required', 'integer'],
            'shifts.*.role_id' => ['required', 'integer'],
            'shifts.*.company_id' => ['required', 'integer'],
            'shifts.*.work_location_id' => ['required', 'integer'],
            'shifts.*.shift_date' => ['required', 'date_format:Y-m-d'],
            'shifts.*.start_time' => ['required', 'date_format:H:i'],
            'shifts.*.end_time' => ['required', 'date_format:H:i'],
            'shifts.*.title' => ['nullable', 'string', 'max:120'],
            'shifts.*.note' => ['nullable', 'string', 'max:2000'],
            'shifts.*.last_known_write_date' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            foreach ($validated['shifts'] as $shiftData) {
                $planningService->updateShift((int) $shiftData['id'], [
                    'employee_id' => null,
                    'role_id' => (int) $shiftData['role_id'],
                    'company_id' => (int) $shiftData['company_id'],
                    'work_location_id' => (int) $shiftData['work_location_id'],
                    'shift_date' => (string) $shiftData['shift_date'],
                    'start_time' => (string) $shiftData['start_time'],
                    'end_time' => (string) $shiftData['end_time'],
                    'title' => $shiftData['title'] ?? null,
                    'note' => $shiftData['note'] ?? null,
                    'last_known_write_date' => (string) ($shiftData['last_known_write_date'] ?? ''),
                ]);
            }
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => $exception->getMessage()]);
        }

        $count = count($validated['shifts']);

        return redirect()
            ->route('manager.shifts.create', $this->preservedFilters($request))
            ->with('success', $count === 1
                ? 'The selected shift is now an open shift.'
                : $count.' selected shifts were converted to open shifts.');
    }

    /** Update common fields across multiple selected shifts. */
    public function bulkUpdate(Request $request, OdooManagerPlanningService $planningService): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'day' => ['required', 'date_format:Y-m-d'],
            'shifts' => ['required', 'array', 'min:1'],
            'shifts.*.id' => ['required', 'integer'],
            'shifts.*.employee_id' => ['nullable', 'integer'],
            'shifts.*.role_id' => ['required', 'integer'],
            'shifts.*.company_id' => ['required', 'integer'],
            'shifts.*.work_location_id' => ['required', 'integer'],
            'shifts.*.shift_date' => ['required', 'date_format:Y-m-d'],
            'shifts.*.start_time' => ['required', 'date_format:H:i'],
            'shifts.*.end_time' => ['required', 'date_format:H:i'],
            'shifts.*.title' => ['nullable', 'string', 'max:120'],
            'shifts.*.note' => ['nullable', 'string', 'max:2000'],
            'shifts.*.last_known_write_date' => ['nullable', 'string', 'max:40'],
            'employee_id' => ['nullable', 'integer'],
            'role_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'work_location_id' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $overrides = array_filter([
            'employee_id' => $request->input('employee_id'),
            'role_id' => $request->input('role_id'),
            'company_id' => $request->input('company_id'),
            'work_location_id' => $request->input('work_location_id'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'title' => $request->input('title'),
            'note' => $request->input('note'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($overrides === []) {
            return back()->withErrors(['manager_shift' => 'Choose at least one field to update.']);
        }

        try {
            foreach ($validated['shifts'] as $shiftData) {
                $planningService->updateShift((int) $shiftData['id'], array_merge($shiftData, $overrides, [
                    'last_known_write_date' => (string) ($shiftData['last_known_write_date'] ?? ''),
                ]));
            }
        } catch (OdooException $exception) {
            return redirect()->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => $exception->getMessage()]);
        }

        return redirect()->route('manager.shifts.create', $this->preservedFilters($request))
            ->with('success', count($validated['shifts']).' selected shift(s) were updated.');
    }

    /** Copy one day or the complete visible schedule period to a new date range. */
    public function copyPeriod(Request $request, OdooManagerPlanningService $planningService, ?ScheduleUndoService $undo = null): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'day' => ['required', 'date_format:Y-m-d'],
            'source_date' => ['required', 'date_format:Y-m-d'],
            'target_date' => ['required', 'date_format:Y-m-d', 'different:source_date'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'period' => ['required', 'in:day,week,two_weeks,range'],
        ]);

        $sourceDate = Carbon::createFromFormat('Y-m-d', $validated['source_date'])->startOfDay();
        $targetDate = Carbon::createFromFormat('Y-m-d', $validated['target_date'])->startOfDay();
        $visibleStart = ! empty($validated['start_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfWeek()
            : $sourceDate->copy()->startOfWeek();
        $visibleEnd = ! empty($validated['end_date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfWeek()
            : $visibleStart->copy()->addDays(self::VISIBLE_PERIOD_DAYS - 1);
        $rangeDays = $visibleStart->diffInDays($visibleEnd) + 1;
        if ($rangeDays > self::MAX_VISIBLE_WEEKS * 7) {
            return back()->withErrors(['manager_shift' => 'The schedule range cannot exceed 12 full weeks.']);
        }
        if ($validated['period'] === 'day') {
            $shifts = $planningService->getWeeklyShiftsForDate($sourceDate);
        } elseif ($validated['period'] !== 'range' && empty($validated['start_date'])) {
            $shifts = $planningService->getVisiblePeriodShiftsForDate($sourceDate);
        } else {
            $shifts = $planningService->getShiftsForRange(
                $sourceDate->copy()->startOfWeek(),
                $sourceDate->copy()->startOfWeek()->addDays($rangeDays - 1)
            );
        }

        if ($validated['period'] === 'day') {
            $shifts = array_values(array_filter($shifts, fn (array $shift): bool =>
                ($shift['shift_date_value'] ?? '') === $sourceDate->toDateString()));
        } else {
            $sourceDate = $sourceDate->copy()->startOfWeek();
            $targetDate = $targetDate->copy()->startOfWeek();
        }

        if ($shifts === []) {
            return back()->withErrors(['manager_shift' => 'There are no shifts in the selected source period to copy.']);
        }

        $createdIds = [];
        try {
            foreach ($shifts as $shift) {
                $shiftDate = Carbon::createFromFormat('Y-m-d', (string) $shift['shift_date_value']);
                $newDate = $targetDate->copy()->addDays($sourceDate->diffInDays($shiftDate, false));
                $createdIds = array_merge($createdIds, $planningService->createShiftsReturningIds([
                    'employee_id' => $shift['employee_id'] ?? null,
                    'role_id' => $shift['role_id'],
                    'company_id' => $shift['company_id'],
                    'work_location_id' => $shift['work_location_id'],
                    'shift_date' => $newDate->toDateString(),
                    'start_time' => $shift['start_time_value'],
                    'end_time' => $shift['end_time_value'],
                    'title' => $shift['title_value'] ?? null,
                    'note' => $shift['note'] ?? null,
                    '_copy_existing_shift' => true,
                ]));
            }
        } catch (OdooException $exception) {
            foreach (array_reverse($createdIds) as $createdId) {
                try {
                    $planningService->deleteShift((int) $createdId);
                } catch (\Throwable) {
                    // Continue compensating the rest of this copy batch.
                }
            }

            return redirect()->route('manager.shifts.create', $this->preservedFilters($request))
                ->withErrors(['manager_shift' => 'Copy failed. Shifts created by this attempt were rolled back: '.$exception->getMessage()]);
        }

        $redirectRangeStart = $targetDate->copy()->startOfWeek();
        $redirectFilters = [
            'month' => $targetDate->format('Y-m'),
            'day' => $targetDate->toDateString(),
        ];
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $redirectFilters['start_date'] = $redirectRangeStart->toDateString();
            $redirectFilters['end_date'] = $redirectRangeStart->copy()->addDays($rangeDays - 1)->toDateString();
        }
        $redirect=redirect()->route('manager.shifts.create', array_merge($this->preservedFilters($request), $redirectFilters))
            ->with('success', count($createdIds).' shift(s) were copied successfully.');
        if($undo && $createdIds){try{$redirect->with('schedule_undo',$undo->recordCreatedSlots($createdIds,'Copy schedule period',$planningService,$request->user(),(int)($shifts[0]['company_id']??0)));}catch(OdooException|\RuntimeException){}}
        return $redirect;
    }

    private function resolveMonth(?string $month): Carbon
    {
        if (! $month) {
            return now()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function resolveSelectedDay(?string $day, Carbon $selectedMonth): ?Carbon
    {
        if (! $day) {
            return null;
        }

        try {
            $selectedDay = Carbon::createFromFormat('Y-m-d', $day)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $selectedDay->isSameMonth($selectedMonth) ? $selectedDay : null;
    }

    private function resolveView(?string $view): string
    {
        return in_array($view, ['team', 'area'], true) ? $view : 'team';
    }

    /** @return array{0:Carbon,1:Carbon,2:bool} */
    private function resolveScheduleRange(Request $request, Carbon $anchor): array
    {
        try {
            $start = $request->filled('start_date')
                ? Carbon::createFromFormat('Y-m-d', (string) $request->query('start_date'))->startOfWeek()
                : $anchor->copy()->startOfWeek();
            $end = $request->filled('end_date')
                ? Carbon::createFromFormat('Y-m-d', (string) $request->query('end_date'))->endOfWeek()
                : $start->copy()->addDays(self::VISIBLE_PERIOD_DAYS - 1)->endOfWeek();
        } catch (\Throwable) {
            $start = $anchor->copy()->startOfWeek();
            $end = $start->copy()->addDays(self::VISIBLE_PERIOD_DAYS - 1)->endOfWeek();
        }

        if ($end->lt($start)) {
            $end = $start->copy()->endOfWeek();
        }

        $maximumEnd = $start->copy()->addWeeks(self::MAX_VISIBLE_WEEKS)->subDay()->endOfDay();
        $wasLimited = $end->gt($maximumEnd);

        return [$start, $wasLimited ? $maximumEnd : $end, $wasLimited];
    }

    /**
     * @return array<string, string>
     */
    private function preservedFilters(Request $request): array
    {
        $filters = [];
        $month = (string) $request->input('month', $request->query('month', ''));
        $day = (string) $request->input('day', $request->query('day', ''));

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $filters['month'] = $month;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
            $filters['day'] = $day;
        }

        foreach (['start_date', 'end_date'] as $rangeField) {
            $value = (string) $request->input($rangeField, $request->query($rangeField, ''));

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $filters[$rangeField] = $value;
            }
        }

        $view = (string) $request->input('view', $request->query('view', ''));

        if (in_array($view, ['team', 'area'], true)) {
            $filters['view'] = $view;
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyWeeklyRoster(
        Carbon $selectedDate,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null
    ): array
    {
        $weekStart = ($periodStart ?? $selectedDate)->copy()->startOfWeek();
        $weekEnd = ($periodEnd ?? $this->visiblePeriodEnd($selectedDate))->copy()->endOfWeek();
        $days = [];
        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $days[] = [
                'date' => $cursor->copy(),
                'date_value' => $cursor->toDateString(),
                'weekday' => $cursor->format('D'),
                'day_number' => $cursor->format('d'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $cursor->isSameDay($selectedDate),
                'shift_count' => 0,
                'hours_label' => '0h',
            ];
            $cursor->addDay();
        }

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_label' => $weekStart->format('d-m-Y').' - '.$weekEnd->format('d-m-Y'),
            'previous_week_day' => $weekStart->copy()->subDays($weekStart->diffInDays($weekEnd) + 1),
            'next_week_day' => $weekStart->copy()->addDays($weekStart->diffInDays($weekEnd) + 1),
            'period_days' => $weekStart->diffInDays($weekEnd) + 1,
            'days' => $days,
            'rows' => [],
            'summary' => [
                'shift_count' => 0,
                'scheduled_hours' => '0h',
                'people_scheduled' => 0,
                'open_shifts' => 0,
                'published_shifts' => 0,
                'unpublished_shifts' => 0,
                'updated_shifts' => 0,
                'confirmation_shifts' => 0,
                'approved_leave' => 0,
                'pending_leave' => 0,
                'unavailable_people' => 0,
                'coverage_days' => 0,
                'average_shift' => '0h',
                'busiest_day' => 'No shifts',
                'unscheduled_people' => 0,
                'overtime_risks' => 0,
                'long_shifts' => 0,
            ],
            'alerts' => [
                [
                    'type' => 'info',
                    'icon' => 'fa-info-circle',
                    'title' => 'No roster data',
                    'message' => 'Odoo schedule data is not available for this date range yet.',
                ],
            ],
            'role_breakdown' => [],
            'company_breakdown' => [],
            'shift_templates' => [],
            'time_off_days' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyWeeklyAreaBoard(
        Carbon $selectedDate,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null
    ): array
    {
        $weekStart = ($periodStart ?? $selectedDate)->copy()->startOfWeek();
        $weekEnd = ($periodEnd ?? $this->visiblePeriodEnd($selectedDate))->copy()->endOfWeek();
        $days = [];
        $cursor = $weekStart->copy();

        while ($cursor->lte($weekEnd)) {
            $days[] = [
                'date' => $cursor->copy(),
                'date_value' => $cursor->toDateString(),
                'weekday' => $cursor->format('D'),
                'day_number' => $cursor->format('d'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $cursor->isSameDay($selectedDate),
                'shift_count' => 0,
                'hours_label' => '0h',
            ];
            $cursor->addDay();
        }

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'week_label' => $weekStart->format('d-m-Y').' - '.$weekEnd->format('d-m-Y'),
            'previous_week_day' => $weekStart->copy()->subDays($weekStart->diffInDays($weekEnd) + 1),
            'next_week_day' => $weekStart->copy()->addDays($weekStart->diffInDays($weekEnd) + 1),
            'period_days' => $weekStart->diffInDays($weekEnd) + 1,
            'days' => $days,
            'rows' => [],
        ];
    }

    private function visiblePeriodEnd(Carbon $selectedDate): Carbon
    {
        return $selectedDate->copy()->startOfWeek()->addDays(self::VISIBLE_PERIOD_DAYS - 1);
    }
}
