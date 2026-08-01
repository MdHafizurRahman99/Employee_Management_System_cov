<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPayrollSummaryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ManagerPayrollSummaryReportController extends Controller
{
    /**
     * Display the manager payroll summary report page.
     */
    public function index(Request $request, OdooManagerPayrollSummaryReportService $reportService): View
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedCompanyId = $this->resolveNullableInt($request->query('company_id'));
        $pageData = $this->emptyPageData($selectedMonth);
        $odooPayrollSummaryError = null;
        $hasManagerPayrollIdentity = filled($request->user()?->odoo_user_id);

        if ($hasManagerPayrollIdentity) {
            try {
                $pageData = $reportService->getReportPageData(
                    $request->user(),
                    $selectedMonth,
                    $selectedCompanyId
                );
            } catch (OdooException $exception) {
                $odooPayrollSummaryError = $exception->getMessage();
            }
        }

        return view('admin.manager-payroll-summary.index', [
            'payrollAvailable' => $pageData['payrollAvailable'],
            'payrollMessage' => $pageData['payrollMessage'],
            'companies' => $pageData['companies'],
            'reportSummary' => $pageData['summary'],
            'comparison' => $pageData['comparison'],
            'companyBreakdown' => $pageData['companyBreakdown'],
            'roleBreakdown' => $pageData['roleBreakdown'],
            'selectedMonth' => $selectedMonth,
            'selectedCompanyId' => $selectedCompanyId,
            'odooPayrollSummaryError' => $odooPayrollSummaryError,
            'hasManagerPayrollIdentity' => $hasManagerPayrollIdentity,
        ]);
    }

    /**
     * Export the payroll summary report in an Excel-friendly format.
     */
    public function exportExcel(Request $request, OdooManagerPayrollSummaryReportService $reportService): Response|RedirectResponse
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedCompanyId = $this->resolveNullableInt($request->query('company_id'));

        try {
            $pageData = $reportService->getReportPageData(
                $request->user(),
                $selectedMonth,
                $selectedCompanyId
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.payroll-summary.index', $this->preservedFilters($request))
                ->withErrors(['manager_payroll_summary' => $exception->getMessage()]);
        }

        $content = view('admin.manager-payroll-summary.export-excel', [
            'reportSummary' => $pageData['summary'],
            'comparison' => $pageData['comparison'],
            'companyBreakdown' => $pageData['companyBreakdown'],
            'roleBreakdown' => $pageData['roleBreakdown'],
            'selectedMonth' => $selectedMonth,
            'generatedAt' => now(),
        ])->render();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName('xls', $selectedMonth).'"',
        ]);
    }

    /**
     * Export the payroll summary report as a PDF document.
     */
    public function exportPdf(Request $request, OdooManagerPayrollSummaryReportService $reportService)
    {
        $selectedMonth = $this->resolveMonth($request->query('month'));
        $selectedCompanyId = $this->resolveNullableInt($request->query('company_id'));

        try {
            $pageData = $reportService->getReportPageData(
                $request->user(),
                $selectedMonth,
                $selectedCompanyId
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.payroll-summary.index', $this->preservedFilters($request))
                ->withErrors(['manager_payroll_summary' => $exception->getMessage()]);
        }

        return Pdf::loadView('admin.manager-payroll-summary.export-pdf', [
            'reportSummary' => $pageData['summary'],
            'comparison' => $pageData['comparison'],
            'companyBreakdown' => $pageData['companyBreakdown'],
            'roleBreakdown' => $pageData['roleBreakdown'],
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
     *     payrollAvailable:bool,
     *     payrollMessage:?string,
     *     companies:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>,
     *     comparison:array<string, mixed>,
     *     companyBreakdown:array<int, array<string, mixed>>,
     *     roleBreakdown:array<int, array<string, mixed>>
     * }
     */
    private function emptyPageData(Carbon $month): array
    {
        $previousMonth = $month->copy()->subMonthNoOverflow()->startOfMonth();

        return [
            'payrollAvailable' => false,
            'payrollMessage' => null,
            'companies' => [],
            'summary' => [
                'month_label' => $month->format('F Y'),
                'payslip_count' => 0,
                'employees_count' => 0,
                'gross_total' => 0.0,
                'gross_total_label' => number_format(0, 2),
                'deductions_total' => 0.0,
                'deductions_total_label' => number_format(0, 2),
                'net_total' => 0.0,
                'net_total_label' => number_format(0, 2),
            ],
            'comparison' => [
                'current_month_label' => $month->format('F Y'),
                'previous_month_label' => $previousMonth->format('F Y'),
                'current_gross_total' => 0.0,
                'current_gross_total_label' => number_format(0, 2),
                'previous_gross_total' => 0.0,
                'previous_gross_total_label' => number_format(0, 2),
                'change_value' => 0.0,
                'change_value_label' => number_format(0, 2),
                'change_percent' => null,
                'change_percent_label' => 'N/A',
                'direction_label' => 'No Change',
            ],
            'companyBreakdown' => [],
            'roleBreakdown' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preservedFilters(Request $request): array
    {
        return array_filter([
            'month' => $request->input('month', $request->query('month')),
            'company_id' => $request->input('company_id', $request->query('company_id')),
        ], fn (mixed $value) => $value !== null && $value !== '');
    }

    private function fileName(string $extension, Carbon $month): string
    {
        return 'payroll-summary-report-'.$month->format('Y-m').'.'.$extension;
    }
}
