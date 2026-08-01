<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerWorkingHoursReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ManagerWorkingHoursReportController extends Controller
{
    /**
     * Display the manager working hours report page.
     */
    public function index(Request $request, OdooManagerWorkingHoursReportService $reportService): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedEmployeeId = $this->resolveNullableInt($request->query('employee_id'));
        $selectedCompanyId = $this->resolveNullableInt($request->query('company_id'));
        $pageData = $this->emptyPageData($selectedMonth);
        $odooReportError = null;
        $hasManagerReportIdentity = filled($request->user()?->odoo_user_id);

        if ($hasManagerReportIdentity) {
            try {
                $pageData = $reportService->getReportPageData(
                    $request->user(),
                    $selectedMonth,
                    $selectedEmployeeId,
                    $selectedCompanyId
                );
            } catch (OdooException $exception) {
                $odooReportError = $exception->getMessage();
            }
        }

        return view('admin.manager-working-hours.index', [
            'employees' => $pageData['employees'],
            'companies' => $pageData['companies'],
            'reportRows' => $pageData['rows'],
            'reportSummary' => $pageData['summary'],
            'selectedMonth' => $selectedMonth,
            'selectedEmployeeId' => $selectedEmployeeId,
            'selectedCompanyId' => $selectedCompanyId,
            'odooReportError' => $odooReportError,
            'hasManagerReportIdentity' => $hasManagerReportIdentity,
        ]);
    }

    /**
     * Export the working hours report in an Excel-friendly format.
     */
    public function exportExcel(Request $request, OdooManagerWorkingHoursReportService $reportService): Response|RedirectResponse
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedEmployeeId = $this->resolveNullableInt($request->query('employee_id'));
        $selectedCompanyId = $this->resolveNullableInt($request->query('company_id'));

        try {
            $pageData = $reportService->getReportPageData(
                $request->user(),
                $selectedMonth,
                $selectedEmployeeId,
                $selectedCompanyId
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.working-hours.index', $this->preservedFilters($request))
                ->withErrors(['manager_working_hours' => $exception->getMessage()]);
        }

        $content = view('admin.manager-working-hours.export-excel', [
            'reportRows' => $pageData['rows'],
            'reportSummary' => $pageData['summary'],
            'selectedMonth' => $selectedMonth,
            'generatedAt' => now(),
        ])->render();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName('xls', $selectedMonth).'"',
        ]);
    }

    /**
     * Export the working hours report as a PDF document.
     */
    public function exportPdf(Request $request, OdooManagerWorkingHoursReportService $reportService)
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedEmployeeId = $this->resolveNullableInt($request->query('employee_id'));
        $selectedCompanyId = $this->resolveNullableInt($request->query('company_id'));

        try {
            $pageData = $reportService->getReportPageData(
                $request->user(),
                $selectedMonth,
                $selectedEmployeeId,
                $selectedCompanyId
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.working-hours.index', $this->preservedFilters($request))
                ->withErrors(['manager_working_hours' => $exception->getMessage()]);
        }

        return Pdf::loadView('admin.manager-working-hours.export-pdf', [
            'reportRows' => $pageData['rows'],
            'reportSummary' => $pageData['summary'],
            'selectedMonth' => $selectedMonth,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download($this->fileName('pdf', $selectedMonth));
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

    private function resolveNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array{
     *     employees:array<int, array<string, mixed>>,
     *     companies:array<int, array<string, mixed>>,
     *     rows:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    private function emptyPageData(Carbon $month): array
    {
        return [
            'employees' => [],
            'companies' => [],
            'rows' => [],
            'summary' => [
                'month_label' => $month->format('F Y'),
                'employees_count' => 0,
                'planned_hours_total' => 0.0,
                'planned_hours_total_label' => number_format(0, 2).' hrs',
                'actual_hours_total' => 0.0,
                'actual_hours_total_label' => number_format(0, 2).' hrs',
                'overtime_total' => 0.0,
                'overtime_total_label' => number_format(0, 2).' hrs',
                'undertime_total' => 0.0,
                'undertime_total_label' => number_format(0, 2).' hrs',
                'shift_count_total' => 0,
                'attendance_days_total' => 0,
                'missing_clock_out_total' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preservedFilters(Request $request): array
    {
        return array_filter([
            'month' => $request->input('month', $request->query('month')),
            'employee_id' => $request->input('employee_id', $request->query('employee_id')),
            'company_id' => $request->input('company_id', $request->query('company_id')),
        ], fn (mixed $value) => $value !== null && $value !== '');
    }

    private function fileName(string $extension, Carbon $month): string
    {
        return 'working-hours-report-'.$month->format('Y-m').'.'.$extension;
    }
}
