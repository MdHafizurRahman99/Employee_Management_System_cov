<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerAttendanceService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerAttendanceController extends Controller
{
    /**
     * Display the manager team attendance page.
     */
    public function index(Request $request, OdooManagerAttendanceService $attendanceService): View
    {
        $fromDate = $this->resolveDate($request->query('from_date'), now()->startOfMonth());
        $toDate = $this->resolveDate($request->query('to_date'), now()->endOfDay());

        if ($toDate->lt($fromDate)) {
            [$fromDate, $toDate] = [$toDate->copy(), $fromDate->copy()];
        }

        $employeeId = $request->query('employee_id');
        $teamAttendance = [
            'employees' => [],
            'records' => [],
            'summary' => $this->emptySummary($fromDate, $toDate),
        ];
        $odooAttendanceError = null;
        $hasManagerAttendanceIdentity = filled($request->user()?->odoo_user_id);

        if ($hasManagerAttendanceIdentity) {
            try {
                $teamAttendance = $attendanceService->getTeamAttendancePageData(
                    $request->user(),
                    $fromDate,
                    $toDate,
                    is_numeric($employeeId) ? (int) $employeeId : null
                );
            } catch (OdooException $exception) {
                $odooAttendanceError = $exception->getMessage();
            }
        }

        return view('admin.manager-attendance.index', [
            'employees' => $teamAttendance['employees'],
            'attendanceRecords' => $teamAttendance['records'],
            'attendanceSummary' => $teamAttendance['summary'],
            'odooAttendanceError' => $odooAttendanceError,
            'hasManagerAttendanceIdentity' => $hasManagerAttendanceIdentity,
            'selectedFromDate' => $fromDate,
            'selectedToDate' => $toDate,
            'selectedEmployeeId' => is_numeric($employeeId) ? (int) $employeeId : null,
        ]);
    }

    /**
     * Submit a manual attendance correction to Odoo.
     */
    public function correct(
        Request $request,
        OdooManagerAttendanceService $attendanceService,
        int $attendance
    ): RedirectResponse {
        $validated = $request->validate([
            'check_in' => ['required', 'date_format:Y-m-d\TH:i'],
            'check_out' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'correction_note' => ['nullable', 'string', 'max:2000'],
            'last_known_write_date' => ['nullable', 'string', 'max:40'],
            'editing_attendance_id' => ['nullable', 'integer'],
        ]);

        try {
            $attendanceService->correctAttendanceRecord($request->user(), $attendance, $validated);
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.attendance.index', $this->preservedFilters($request))
                ->withErrors(['manager_attendance' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('manager.attendance.index', $this->preservedFilters($request))
            ->with('success', 'The attendance correction was submitted successfully.');
    }

    private function resolveDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback->copy();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(Carbon $fromDate, Carbon $toDate): array
    {
        return [
            'range_label' => $fromDate->format('d-m-Y').' - '.$toDate->format('d-m-Y'),
            'records_count' => 0,
            'employees_count' => 0,
            'missing_clock_out_count' => 0,
            'total_worked_hours' => 0.0,
            'total_worked_hours_label' => number_format(0, 2).' hrs',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preservedFilters(Request $request): array
    {
        return array_filter([
            'from_date' => $request->input('from_date', $request->query('from_date')),
            'to_date' => $request->input('to_date', $request->query('to_date')),
            'employee_id' => $request->input('employee_id', $request->query('employee_id')),
        ], fn (mixed $value) => $value !== null && $value !== '');
    }
}
