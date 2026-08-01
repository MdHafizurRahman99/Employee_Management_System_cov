<?php

namespace App\Http\Controllers;

use App\Services\Attendance\AttendanceTrackerException;
use App\Services\Attendance\AttendanceTrackerService;
use App\Services\Odoo\OdooAttendanceService;
use App\Services\Odoo\OdooException;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeAttendanceController extends Controller
{
    /**
     * Display the logged-in employee's attendance hub with local tracking and Odoo history.
     */
    public function index(
        Request $request,
        AttendanceTrackerService $attendanceTrackerService,
        OdooAttendanceService $attendanceService
    ): View {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $trackedAttendance = $attendanceTrackerService->getPageData($request->user(), $selectedMonth);
        $currentMonth = now()->startOfMonth();
        $attendanceData = [
            'days' => [],
            'summary' => $this->emptyOdooSummary($selectedMonth),
        ];
        $currentMonthSummary = $this->emptyOdooSummary($currentMonth);
        $odooAttendanceError = null;
        $hasAttendanceIdentity = filled($request->user()?->odoo_employee_id);

        if ($hasAttendanceIdentity) {
            try {
                $attendanceData = $attendanceService->getAttendanceForMonth($request->user(), $selectedMonth);
            } catch (OdooException $exception) {
                $odooAttendanceError = $exception->getMessage();
            }

            if (! $odooAttendanceError) {
                if ($selectedMonth->isSameMonth($currentMonth)) {
                    $currentMonthSummary = $attendanceData['summary'];
                } else {
                    try {
                        $currentMonthSummary = $attendanceService->getAttendanceSummaryForMonth(
                            $request->user(),
                            $currentMonth
                        );
                    } catch (OdooException $exception) {
                        $odooAttendanceError = $exception->getMessage();
                    }
                }
            }
        }

        return view('admin.employee-attendance.index', [
            'selectedMonth' => $selectedMonth,
            'currentMonth' => $currentMonth,
            'previousMonth' => $selectedMonth->copy()->subMonthNoOverflow(),
            'nextMonth' => $selectedMonth->copy()->addMonthNoOverflow(),
            'canViewNextMonth' => $selectedMonth->lt($currentMonth),
            'attendanceTracker' => $trackedAttendance['tracker'],
            'trackedAttendanceDays' => $trackedAttendance['days'],
            'trackedAttendanceSummary' => $trackedAttendance['summary'],
            'attendanceDays' => $attendanceData['days'],
            'selectedMonthSummary' => $attendanceData['summary'],
            'currentMonthSummary' => $currentMonthSummary,
            'odooAttendanceError' => $odooAttendanceError,
            'hasAttendanceIdentity' => $hasAttendanceIdentity,
        ]);
    }

    public function checkIn(Request $request, AttendanceTrackerService $attendanceTrackerService): RedirectResponse
    {
        try {
            $attendanceTrackerService->checkIn($request->user());
        } catch (AttendanceTrackerException $exception) {
            return redirect()
                ->route('employee.attendance.index', $this->preservedFilters($request))
                ->withErrors(['attendance_tracker' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.attendance.index', $this->preservedFilters($request))
            ->with('success', 'You have checked in successfully.');
    }

    public function startBreak(Request $request, AttendanceTrackerService $attendanceTrackerService): RedirectResponse
    {
        try {
            $attendanceTrackerService->startBreak($request->user());
        } catch (AttendanceTrackerException $exception) {
            return redirect()
                ->route('employee.attendance.index', $this->preservedFilters($request))
                ->withErrors(['attendance_tracker' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.attendance.index', $this->preservedFilters($request))
            ->with('success', 'Your break has started.');
    }

    public function endBreak(Request $request, AttendanceTrackerService $attendanceTrackerService): RedirectResponse
    {
        try {
            $attendanceTrackerService->endBreak($request->user());
        } catch (AttendanceTrackerException $exception) {
            return redirect()
                ->route('employee.attendance.index', $this->preservedFilters($request))
                ->withErrors(['attendance_tracker' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.attendance.index', $this->preservedFilters($request))
            ->with('success', 'Your break has ended.');
    }

    public function checkOut(Request $request, AttendanceTrackerService $attendanceTrackerService): RedirectResponse
    {
        try {
            $attendanceTrackerService->checkOut($request->user());
        } catch (AttendanceTrackerException $exception) {
            return redirect()
                ->route('employee.attendance.index', $this->preservedFilters($request))
                ->withErrors(['attendance_tracker' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.attendance.index', $this->preservedFilters($request))
            ->with('success', 'You have checked out successfully.');
    }

    private function resolveMonth(?string $month): Carbon
    {
        $currentMonth = now()->startOfMonth();

        if (! $month) {
            return $currentMonth->copy();
        }

        try {
            $selectedMonth = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            return $currentMonth->copy();
        }

        if ($selectedMonth->greaterThan($currentMonth)) {
            return $currentMonth->copy();
        }

        return $selectedMonth;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOdooSummary(Carbon $month): array
    {
        return [
            'month_label' => $month->format('F Y'),
            'total_days' => 0,
            'complete_days' => 0,
            'open_days' => 0,
            'total_sessions' => 0,
            'total_worked_hours' => 0.0,
            'total_worked_hours_label' => number_format(0, 2).' hrs',
            'average_hours_per_day' => 0.0,
            'average_hours_per_day_label' => number_format(0, 2).' hrs',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function preservedFilters(Request $request): array
    {
        $month = (string) $request->input('month', $request->query('month', ''));

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return ['month' => $month];
        }

        return [];
    }
}
