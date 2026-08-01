<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class EmployeePayHistoryController extends Controller
{
    /**
     * Display the logged-in employee's Odoo pay history.
     */
    public function index(Request $request, OdooManagerPayrollService $payrollService): View
    {
        $pageData = [
            'payrollAvailable' => false,
            'payrollMessage' => null,
            'payslips' => [],
            'summary' => $this->emptySummary(),
        ];
        $odooPayrollError = null;
        $hasPayrollIdentity = filled($request->user()?->odoo_employee_id);

        if ($request->user()) {
            try {
                $pageData = $payrollService->getEmployeePayHistoryPageData($request->user());
            } catch (OdooException $exception) {
                $odooPayrollError = $exception->getMessage();
            }
        }

        return view('admin.employee-pay-history.index', [
            'payrollAvailable' => $pageData['payrollAvailable'],
            'payrollMessage' => $pageData['payrollMessage'],
            'payslips' => $pageData['payslips'],
            'paySummary' => $pageData['summary'],
            'odooPayrollError' => $odooPayrollError,
            'hasPayrollIdentity' => $hasPayrollIdentity,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'payslip_count' => 0,
            'employees_count' => 0,
            'gross_pay_total' => 0.0,
            'gross_pay_total_label' => number_format(0, 2),
            'deductions_total' => 0.0,
            'deductions_total_label' => number_format(0, 2),
            'net_pay_total' => 0.0,
            'net_pay_total_label' => number_format(0, 2),
            'latest_period_label' => 'N/A',
        ];
    }
}
