<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooLeaveService;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TeamCalendarController extends Controller
{
    public function index(
        Request $request,
        OdooManagerPlanningService $planningService,
        OdooLeaveService $leaveService,
        ?OdooScheduleRepository $scheduleRepository = null
    ): View {
        $month = $this->resolveMonth($request->query('month'));
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $selectedCalendarDate = $this->resolveCalendarDate($request->query('day'), $month);
        $calendarData = ['employees' => [], 'shifts' => [], 'approved_leave' => [], 'birthdays' => [], 'events' => []];
        $leaveTypes = [];
        $leaveRequests = [];
        $calendarError = null;
        $leaveError = null;
        $hasLeaveIdentity = filled($request->user()?->odoo_employee_id);

        try {
            $calendarData = $planningService->getTeamCalendarDataForRange($gridStart, $gridEnd);
            $scheduleRepository ??= app(OdooScheduleRepository::class);
            $calendarData['events'] = [];
            try {
                $calendarData['events'] = $scheduleRepository->teamCalendarEvents($gridStart, $gridEnd)->all();
            } catch (OdooException) {
                // Keep legacy schedule-day events available if Odoo Calendar is unavailable.
            }
            try {
                $calendarData['events'] = array_merge(
                    $calendarData['events'],
                    $scheduleRepository->dayEntries($gridStart, $gridEnd)->all()
                );
            } catch (OdooException) {
                // New calendar events remain available if legacy day metadata is unavailable.
            }
        } catch (OdooException $exception) {
            $calendarError = $exception->getMessage();
        }

        if ($hasLeaveIdentity) {
            try {
                $leavePageData = $leaveService->getLeaveRequestPageData($request->user());
                $leaveTypes = $leavePageData['leaveTypes'] ?? [];
                $leaveRequests = $leavePageData['leaveRequests'] ?? [];
            } catch (OdooException $exception) {
                $leaveError = $exception->getMessage();
            }
        }

        $eventsByDate = $this->buildEventsByDate($calendarData, (int) ($request->user()?->odoo_employee_id ?? 0));
        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($day = 0; $day < 7; $day++) {
                $dateValue = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'date_value' => $dateValue,
                    'is_current_month' => $cursor->isSameMonth($month),
                    'is_today' => $cursor->isToday(),
                    'events' => $eventsByDate[$dateValue] ?? [],
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $employees = collect($calendarData['employees'] ?? [])->sortBy('name')->values()->all();
        $companies = collect($employees)
            ->filter(fn (array $employee): bool => ! empty($employee['company_id']))
            ->map(fn (array $employee): array => ['id' => (int) $employee['company_id'], 'name' => (string) ($employee['company'] ?? 'Company')])
            ->unique('id')->sortBy('name')->values()->all();
        $allEvents = collect($eventsByDate)->flatten(1);
        $monthEvents = $allEvents->filter(
            fn (array $event): bool => Carbon::parse($event['date'])->betweenIncluded(
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth()
            )
        );
        $upcomingFrom = now()->isSameMonth($month) ? now()->startOfDay() : $month->copy()->startOfMonth();
        $teamOnLeave = collect($this->buildTeamLeaveRanges($allEvents))
            ->filter(fn (array $range): bool => Carbon::parse($range['end_date'])->endOfDay()->gte($upcomingFrom)
                && Carbon::parse($range['date'])->startOfDay()->lte($gridEnd))
            ->map(fn (array $range): array => $this->addLeaveTiming($range, $upcomingFrom))
            ->sortBy('date')->take(4)->values()->all();
        $upcomingMoments = $allEvents
            ->filter(fn (array $event): bool => in_array($event['type'], ['birthday', 'event'], true)
                && Carbon::parse($event['date'])->betweenIncluded($gridStart, $gridEnd))
            ->unique(fn (array $event): string => $event['type'].'-'.($event['id'] ?? $event['employee_id']).'-'.$event['date'].'-'.$event['title'])
            ->sortBy('date')
            ->map(fn (array $event): array => $this->addMomentTiming($event, $upcomingFrom))
            ->values()->all();
        $myUpcomingShifts = $allEvents
            ->where('type', 'shift')->where('is_mine', true)
            ->filter(fn (array $event): bool => Carbon::parse($event['date'])->gte($upcomingFrom))
            ->sortBy('date')->take(4)->values()->all();
        $upcomingLeaveRequests = collect($leaveRequests)
            ->filter(fn (array $request): bool => filled($request['end_date'] ?? null)
                && Carbon::parse($request['end_date'])->endOfDay()->gte(now()))
            ->sortBy('start_date')->take(4)->values()->all();
        $leaveRequestSummary = [
            'pending' => collect($leaveRequests)->whereIn('state', ['confirm', 'validate1'])->count(),
            'approved' => collect($leaveRequests)->where('state', 'validate')->count(),
            'other' => collect($leaveRequests)->whereNotIn('state', ['confirm', 'validate1', 'validate'])->count(),
        ];

        return view('admin.team-calendar.index', [
            'selectedMonth' => $month,
            'selectedCalendarDate' => $selectedCalendarDate,
            'previousMonth' => $month->copy()->subMonthNoOverflow(),
            'nextMonth' => $month->copy()->addMonthNoOverflow(),
            'weeks' => $weeks,
            'eventsByDate' => $eventsByDate,
            'employees' => $employees,
            'companies' => $companies,
            'leaveTypes' => $leaveTypes,
            'hasLeaveIdentity' => $hasLeaveIdentity,
            'calendarError' => $calendarError,
            'leaveError' => $leaveError,
            'teamOnLeave' => $teamOnLeave,
            'upcomingMoments' => $upcomingMoments,
            'myUpcomingShifts' => $myUpcomingShifts,
            'upcomingLeaveRequests' => $upcomingLeaveRequests,
            'leaveRequestSummary' => $leaveRequestSummary,
            'canManageCalendar' => (bool) $request->user()?->can('access-manager-tools'),
            'summary' => [
                'shifts' => $monthEvents->where('type', 'shift')->count(),
                'people_on_leave' => $monthEvents->where('type', 'leave')->pluck('employee_id')->unique()->count(),
                'birthdays' => $monthEvents->where('type', 'birthday')->count(),
                'team_events' => $monthEvents->where('type', 'event')->count(),
                'team_members' => count($employees),
            ],
        ]);
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    private function buildEventsByDate(array $data, int $currentEmployeeId): array
    {
        $events = [];
        $employeesById = collect($data['employees'] ?? [])->keyBy(fn (array $employee): int => (int) ($employee['id'] ?? 0));

        foreach ($data['shifts'] ?? [] as $shift) {
            if (($shift['publish_state'] ?? 'published') === 'unpublished') continue;
            $date = (string) ($shift['shift_date_value'] ?? $shift['date_value'] ?? '');
            if ($date === '') continue;
            $events[$date][] = [
                'type' => 'shift', 'icon' => 'fa-briefcase',
                'date' => $date,
                'employee_id' => (int) ($shift['employee_id'] ?? 0),
                'employee' => (string) ($shift['employee'] ?? 'Employee'),
                'company_id' => (int) ($shift['company_id'] ?? 0),
                'company' => (string) ($shift['company'] ?? 'N/A'),
                'calendar_title' => (int) ($shift['employee_id'] ?? 0) === $currentEmployeeId
                    ? (string) ($shift['role'] ?? 'My shift')
                    : (string) ($shift['employee'] ?? 'Team shift'),
                'title' => (string) ($shift['employee'] ?? 'Employee').' · '.(string) ($shift['role'] ?? 'Shift'),
                'time' => (string) ($shift['time_label'] ?? ''),
                'start_time' => (string) ($shift['start_time_value'] ?? ''),
                'end_time' => (string) ($shift['end_time_value'] ?? ''),
                'detail' => (string) ($shift['work_location'] ?? 'No work location'),
                'is_mine' => (int) ($shift['employee_id'] ?? 0) === $currentEmployeeId,
            ];
        }

        $leaveSequenceMeta = $this->buildLeaveSequenceMeta($data['approved_leave'] ?? []);
        foreach ($data['approved_leave'] ?? [] as $leave) {
            $date = (string) ($leave['date_value'] ?? '');
            if ($date === '') continue;
            $employee = (string) ($leave['employee'] ?? 'Employee');
            $employeeId = (int) ($leave['employee_id'] ?? 0);
            $employeeRecord = $employeesById->get($employeeId, []);
            $timeLabel = trim((string) ($leave['time_label'] ?? ''));
            if ($timeLabel === '' || preg_match('/^24(?:[.:]00)?h?$/i', $timeLabel)) {
                $timeLabel = 'All day';
            }
            $sequence = $leaveSequenceMeta[$employeeId.'|'.$employee.'|'.$date] ?? ['index' => 0, 'days' => 1];
            $events[$date][] = [
                'type' => 'leave', 'icon' => 'fa-plane-departure',
                'date' => $date,
                'employee_id' => $employeeId, 'employee' => $employee,
                'company_id' => (int) ($employeeRecord['company_id'] ?? 0),
                'company' => (string) ($employeeRecord['company'] ?? ''),
                'calendar_title' => $sequence['index'] === 0 ? $employee : 'On leave',
                'calendar_subtitle' => $sequence['days'] > 1
                    ? ($sequence['index'] === 0 ? 'On leave · '.$sequence['days'].' days' : 'Continues')
                    : 'On leave · '.$timeLabel,
                'title' => $employee.' · On leave',
                'time' => $timeLabel,
                'detail' => 'Approved leave',
                'is_mine' => $employeeId === $currentEmployeeId,
            ];
        }

        foreach ($data['birthdays'] ?? [] as $birthday) {
            $date = (string) ($birthday['date_value'] ?? '');
            if ($date === '') continue;
            $employee = (string) ($birthday['employee'] ?? 'Employee');
            $employeeId = (int) ($birthday['employee_id'] ?? 0);
            $events[$date][] = [
                'type' => 'birthday', 'icon' => 'fa-birthday-cake',
                'date' => $date,
                'employee_id' => $employeeId, 'employee' => $employee,
                'company_id' => (int) ($birthday['company_id'] ?? 0),
                'company' => (string) ($birthday['company'] ?? ''),
                'calendar_title' => $employee,
                'title' => $employee.'\'s birthday', 'time' => 'All day',
                'start_time' => '', 'end_time' => '',
                'detail' => 'Team celebration', 'is_mine' => $employeeId === $currentEmployeeId,
            ];
        }

        foreach ($data['events'] ?? [] as $teamEvent) {
            $label = trim((string) ($teamEvent->holiday_name ?? ''));
            if ($label === '') continue;
            $date = $teamEvent->schedule_date instanceof Carbon ? $teamEvent->schedule_date->toDateString() : '';
            if ($date === '') continue;
            $companyId = (int) ($teamEvent->company_id ?? 0);
            $companyName = (string) (collect($data['employees'] ?? [])->firstWhere('company_id', $companyId)['company'] ?? 'Company event');
            $events[$date][] = [
                'id' => (int) ($teamEvent->id ?? 0),
                'source' => (string) ($teamEvent->source ?? 'day_meta'),
                'type' => 'event', 'icon' => 'fa-star', 'employee_id' => 0, 'employee' => '',
                'date' => $date,
                'company_id' => $companyId, 'company' => $companyName,
                'calendar_title' => $label,
                'title' => $label,
                'time' => filled($teamEvent->blocked_start ?? null) && filled($teamEvent->blocked_end ?? null)
                    ? $teamEvent->blocked_start.' - '.$teamEvent->blocked_end
                    : 'All day',
                'start_time' => (string) ($teamEvent->blocked_start ?? ''),
                'end_time' => (string) ($teamEvent->blocked_end ?? ''),
                'detail' => (string) ($teamEvent->note ?? 'Team event'),
                'is_mine' => false,
            ];
        }

        foreach ($events as &$dayEvents) {
            usort($dayEvents, function (array $left, array $right): int {
                $order = ['event' => 0, 'birthday' => 1, 'leave' => 2, 'shift' => 3];
                return [$order[$left['type']] ?? 9, $left['time'], $left['employee']]
                    <=> [$order[$right['type']] ?? 9, $right['time'], $right['employee']];
            });
        }
        unset($dayEvents);

        return $events;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildTeamLeaveRanges(\Illuminate\Support\Collection $monthEvents): array
    {
        $ranges = [];

        foreach ($monthEvents->where('type', 'leave')->groupBy('employee_id') as $leaveDays) {
            $currentRange = [];

            foreach ($leaveDays->sortBy('date') as $leaveDay) {
                $previous = $currentRange ? end($currentRange) : null;
                if ($previous && Carbon::parse($previous['date'])->addDay()->toDateString() !== $leaveDay['date']) {
                    $ranges[] = $this->summarizeLeaveRange($currentRange);
                    $currentRange = [];
                }
                $currentRange[] = $leaveDay;
            }

            if ($currentRange) {
                $ranges[] = $this->summarizeLeaveRange($currentRange);
            }
        }

        return collect($ranges)->sortBy('date')->values()->all();
    }

    /** @param array<string, mixed> $range
     *  @return array<string, mixed>
     */
    private function addLeaveTiming(array $range, Carbon $anchor): array
    {
        $start = Carbon::parse($range['date'])->startOfDay();
        $end = Carbon::parse($range['end_date'])->endOfDay();

        if ($anchor->betweenIncluded($start, $end)) {
            $range['timing_label'] = now()->isSameDay($anchor) ? 'Away now' : 'In progress';
            $range['timing_class'] = 'is-live';
        } elseif ($start->isSameDay($anchor->copy()->addDay())) {
            $range['timing_label'] = 'Starts tomorrow';
            $range['timing_class'] = 'is-soon';
        } else {
            $range['timing_label'] = 'Starts '.$start->format('d M');
            $range['timing_class'] = 'is-upcoming';
        }

        return $range;
    }

    /** @param array<string, mixed> $event
     *  @return array<string, mixed>
     */
    private function addMomentTiming(array $event, Carbon $anchor): array
    {
        $date = Carbon::parse($event['date'])->startOfDay();
        $event['relative_date_label'] = $date->isSameDay($anchor)
            ? 'Today'
            : ($date->isSameDay($anchor->copy()->addDay()) ? 'Tomorrow' : $date->format('D, d M'));
        $event['timing_label'] = $event['relative_date_label'];
        $event['timing_class'] = $date->isSameDay($anchor)
            ? 'is-today'
            : ($date->lt($anchor) ? 'is-past' : 'is-upcoming');

        if ($date->isToday() && filled($event['start_time'] ?? null) && filled($event['end_time'] ?? null)) {
            $start = Carbon::parse($event['date'].' '.$event['start_time']);
            $end = Carbon::parse($event['date'].' '.$event['end_time']);
            if (now()->betweenIncluded($start, $end)) {
                $event['timing_label'] = 'Happening now';
                $event['timing_class'] = 'is-live';
            } elseif (now()->gt($end)) {
                $event['timing_label'] = 'Earlier today';
                $event['timing_class'] = 'is-past-today';
            }
        }

        return $event;
    }

    /** @param array<int, array<string, mixed>> $leaveDays
     *  @return array<string, array{index:int,days:int}>
     */
    private function buildLeaveSequenceMeta(array $leaveDays): array
    {
        $meta = [];

        foreach (collect($leaveDays)->groupBy(fn (array $leave): string => (int) ($leave['employee_id'] ?? 0).'|'.(string) ($leave['employee'] ?? 'Employee')) as $employeeKey => $employeeLeaveDays) {
            $sequence = [];
            $flush = function () use (&$sequence, &$meta, $employeeKey): void {
                $count = count($sequence);
                foreach ($sequence as $index => $leaveDay) {
                    $meta[$employeeKey.'|'.$leaveDay['date_value']] = ['index' => $index, 'days' => $count];
                }
                $sequence = [];
            };

            foreach ($employeeLeaveDays->filter(fn (array $leave): bool => filled($leave['date_value'] ?? null))->sortBy('date_value') as $leaveDay) {
                $previous = $sequence ? end($sequence) : null;
                if ($previous && Carbon::parse($previous['date_value'])->addDay()->toDateString() !== $leaveDay['date_value']) {
                    $flush();
                }
                $sequence[] = $leaveDay;
            }

            if ($sequence) {
                $flush();
            }
        }

        return $meta;
    }

    /** @param array<int, array<string, mixed>> $leaveDays
     *  @return array<string, mixed>
     */
    private function summarizeLeaveRange(array $leaveDays): array
    {
        $summary = $leaveDays[0];
        $start = Carbon::parse($summary['date']);
        $end = Carbon::parse($leaveDays[array_key_last($leaveDays)]['date']);
        $summary['end_date'] = $end->toDateString();
        $summary['date_range_label'] = $start->isSameDay($end)
            ? $start->format('d M')
            : ($start->isSameMonth($end)
                ? $start->format('d').'–'.$end->format('d M')
                : $start->format('d M').' – '.$end->format('d M'));

        return $summary;
    }

    private function resolveMonth(mixed $value): Carbon
    {
        try {
            return filled($value) ? Carbon::createFromFormat('Y-m', (string) $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function resolveCalendarDate(mixed $value, Carbon $month): Carbon
    {
        try {
            return filled($value)
                ? Carbon::createFromFormat('Y-m-d', (string) $value)->startOfDay()
                : (now()->isSameMonth($month) ? now()->startOfDay() : $month->copy()->startOfMonth());
        } catch (\Throwable) {
            return now()->isSameMonth($month) ? now()->startOfDay() : $month->copy()->startOfMonth();
        }
    }
}
