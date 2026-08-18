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
        $calendarData = ['employees' => [], 'shifts' => [], 'approved_leave' => [], 'birthdays' => [], 'events' => []];
        $leaveTypes = [];
        $leaveRequests = [];
        $calendarError = null;
        $leaveError = null;
        $hasLeaveIdentity = filled($request->user()?->odoo_employee_id);

        try {
            $calendarData = $planningService->getTeamCalendarDataForRange($gridStart, $gridEnd);
            $scheduleRepository ??= app(OdooScheduleRepository::class);
            try {
                $calendarData['events'] = $scheduleRepository->dayEntries($gridStart, $gridEnd)->all();
            } catch (OdooException) {
                $calendarData['events'] = [];
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
        $upcomingFrom = now()->isSameMonth($month) ? now()->startOfDay() : $month->copy()->startOfMonth();
        $upcomingUntil = $upcomingFrom->copy()->addDays(7)->endOfDay();
        $teamOnLeave = $allEvents
            ->where('type', 'leave')
            ->filter(fn (array $event): bool => Carbon::parse($event['date'])->betweenIncluded($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->unique('employee_id')->take(4)->values()->all();
        $upcomingMoments = $allEvents
            ->filter(fn (array $event): bool => in_array($event['type'], ['leave', 'birthday', 'event'], true)
                && Carbon::parse($event['date'])->betweenIncluded($upcomingFrom, $upcomingUntil))
            ->unique(fn (array $event): string => $event['type'].'-'.$event['employee_id'].'-'.$event['title'])
            ->sortBy('date')->take(4)->values()->all();
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
                'shifts' => $allEvents->where('type', 'shift')->count(),
                'people_on_leave' => $allEvents->where('type', 'leave')->pluck('employee_id')->unique()->count(),
                'birthdays' => $allEvents->where('type', 'birthday')->count(),
                'team_events' => $allEvents->where('type', 'event')->count(),
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

        foreach ($data['approved_leave'] ?? [] as $leave) {
            $date = (string) ($leave['date_value'] ?? '');
            if ($date === '') continue;
            $employee = (string) ($leave['employee'] ?? 'Employee');
            $employeeId = (int) ($leave['employee_id'] ?? 0);
            $employeeRecord = $employeesById->get($employeeId, []);
            $events[$date][] = [
                'type' => 'leave', 'icon' => 'fa-plane-departure',
                'date' => $date,
                'employee_id' => $employeeId, 'employee' => $employee,
                'company_id' => (int) ($employeeRecord['company_id'] ?? 0),
                'company' => (string) ($employeeRecord['company'] ?? ''),
                'calendar_title' => $employee.' · On leave',
                'title' => $employee.' · On leave',
                'time' => (string) ($leave['time_label'] ?? 'Full day'),
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

    private function resolveMonth(mixed $value): Carbon
    {
        try {
            return filled($value) ? Carbon::createFromFormat('Y-m', (string) $value)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }
}
