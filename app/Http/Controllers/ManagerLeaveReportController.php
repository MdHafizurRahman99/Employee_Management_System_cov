<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerLeaveReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ManagerLeaveReportController extends Controller
{
    /**
     * Display the manager leave report page.
     */
    public function index(Request $request, OdooManagerLeaveReportService $reportService): View
    {
        $selectedFromDate = $this->resolveDate($request->query('from_date'), now()->startOfMonth());
        $selectedToDate = $this->resolveDate($request->query('to_date'), now()->endOfMonth());

        if ($selectedToDate->lt($selectedFromDate)) {
            [$selectedFromDate, $selectedToDate] = [$selectedToDate->copy(), $selectedFromDate->copy()];
        }

        $selectedEmployeeId = $this->resolveNullableInt($request->query('employee_id'));
        $selectedLeaveTypeId = $this->resolveNullableInt($request->query('leave_type_id'));
        $pageData = $this->emptyPageData($selectedFromDate, $selectedToDate);
        $odooLeaveReportError = null;
        $hasManagerLeaveIdentity = filled($request->user()?->odoo_user_id);

        if ($hasManagerLeaveIdentity) {
            try {
                $pageData = $reportService->getReportPageData(
                    $request->user(),
                    $selectedFromDate,
                    $selectedToDate,
                    $selectedEmployeeId,
                    $selectedLeaveTypeId
                );
            } catch (OdooException $exception) {
                $odooLeaveReportError = $exception->getMessage();
            }
        }

        return view('admin.manager-leave-report.index', [
            'leaveAvailable' => $pageData['leaveAvailable'],
            'leaveMessage' => $pageData['leaveMessage'],
            'employees' => $pageData['employees'],
            'leaveTypes' => $pageData['leaveTypes'],
            'reportRows' => $pageData['rows'],
            'reportSummary' => $pageData['summary'],
            'selectedFromDate' => $selectedFromDate,
            'selectedToDate' => $selectedToDate,
            'selectedEmployeeId' => $selectedEmployeeId,
            'selectedLeaveTypeId' => $selectedLeaveTypeId,
            'odooLeaveReportError' => $odooLeaveReportError,
            'hasManagerLeaveIdentity' => $hasManagerLeaveIdentity,
        ]);
    }

    /**
     * Export the leave report in an Excel-friendly format.
     */
    public function exportExcel(Request $request, OdooManagerLeaveReportService $reportService): Response|RedirectResponse
    {
        [$selectedFromDate, $selectedToDate, $selectedEmployeeId, $selectedLeaveTypeId] = $this->resolvedFilters($request);

        try {
            $pageData = $reportService->getReportPageData(
                $request->user(),
                $selectedFromDate,
                $selectedToDate,
                $selectedEmployeeId,
                $selectedLeaveTypeId
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.leave-report.index', $this->preservedFilters($request))
                ->withErrors(['manager_leave_report' => $exception->getMessage()]);
        }

        $content = view('admin.manager-leave-report.export-excel', [
            'reportRows' => $pageData['rows'],
            'reportSummary' => $pageData['summary'],
            'selectedFromDate' => $selectedFromDate,
            'selectedToDate' => $selectedToDate,
            'generatedAt' => now(),
        ])->render();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName('xls', $selectedFromDate, $selectedToDate).'"',
        ]);
    }

    /**
     * Export the leave report as a PDF document.
     */
    public function exportPdf(Request $request, OdooManagerLeaveReportService $reportService)
    {
        [$selectedFromDate, $selectedToDate, $selectedEmployeeId, $selectedLeaveTypeId] = $this->resolvedFilters($request);

        try {
            $pageData = $reportService->getReportPageData(
                $request->user(),
                $selectedFromDate,
                $selectedToDate,
                $selectedEmployeeId,
                $selectedLeaveTypeId
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.leave-report.index', $this->preservedFilters($request))
                ->withErrors(['manager_leave_report' => $exception->getMessage()]);
        }

        return Pdf::loadView('admin.manager-leave-report.export-pdf', [
            'reportRows' => $pageData['rows'],
            'reportSummary' => $pageData['summary'],
            'selectedFromDate' => $selectedFromDate,
            'selectedToDate' => $selectedToDate,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')
            ->download($this->fileName('pdf', $selectedFromDate, $selectedToDate));
    }

    private function resolveDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback->copy()->startOfDay();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return $fallback->copy()->startOfDay();
        }
    }

    private function resolveNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array{0:Carbon,1:Carbon,2:?int,3:?int}
     */
    private function resolvedFilters(Request $request): array
    {
        $selectedFromDate = $this->resolveDate($request->query('from_date'), now()->startOfMonth());
        $selectedToDate = $this->resolveDate($request->query('to_date'), now()->endOfMonth());

        if ($selectedToDate->lt($selectedFromDate)) {
            [$selectedFromDate, $selectedToDate] = [$selectedToDate->copy(), $selectedFromDate->copy()];
        }

        return [
            $selectedFromDate,
            $selectedToDate,
            $this->resolveNullableInt($request->query('employee_id')),
            $this->resolveNullableInt($request->query('leave_type_id')),
        ];
    }

    /**
     * @return array{
     *     leaveAvailable:bool,
     *     leaveMessage:?string,
     *     employees:array<int, array<string, mixed>>,
     *     leaveTypes:array<int, array<string, mixed>>,
     *     rows:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    private function emptyPageData(Carbon $fromDate, Carbon $toDate): array
    {
        return [
            'leaveAvailable' => false,
            'leaveMessage' => null,
            'employees' => [],
            'leaveTypes' => [],
            'rows' => [],
            'summary' => [
                'range_label' => $fromDate->format('d M Y').' - '.$toDate->format('d M Y'),
                'row_count' => 0,
                'employees_count' => 0,
                'leave_types_count' => 0,
                'day_based_total' => 0.0,
                'day_based_total_label' => number_format(0, 2).' days',
                'hour_based_total' => 0.0,
                'hour_based_total_label' => number_format(0, 2).' hours',
                'request_count_total' => 0,
                'balance_rows_count' => 0,
            ],
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
            'leave_type_id' => $request->input('leave_type_id', $request->query('leave_type_id')),
        ], fn (mixed $value) => $value !== null && $value !== '');
    }

    private function fileName(string $extension, Carbon $fromDate, Carbon $toDate): string
    {
        return 'leave-report-'.$fromDate->format('Ymd').'-'.$toDate->format('Ymd').'.'.$extension;
    }
}
