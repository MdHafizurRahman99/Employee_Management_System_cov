<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ManagerPayHistoryController extends Controller
{
    /**
     * Display the manager team pay history page.
     */
    public function index(Request $request, OdooManagerPayrollService $payrollService): View
    {
        $employeeId = $request->query('employee_id');
        $pageData = [
            'payrollAvailable' => false,
            'payrollMessage' => null,
            'employees' => [],
            'payslips' => [],
            'summary' => $this->emptySummary(),
        ];
        $odooPayrollError = null;
        $hasManagerPayrollIdentity = filled($request->user()?->odoo_user_id);

        if ($request->user()) {
            try {
                $pageData = $payrollService->getTeamPayHistoryPageData(
                    $request->user(),
                    is_numeric($employeeId) ? (int) $employeeId : null
                );
            } catch (OdooException $exception) {
                $odooPayrollError = $exception->getMessage();
            }
        }

        return view('admin.manager-pay-history.index', [
            'payrollAvailable' => $pageData['payrollAvailable'],
            'payrollMessage' => $pageData['payrollMessage'],
            'employees' => $pageData['employees'],
            'payslips' => $pageData['payslips'],
            'paySummary' => $pageData['summary'],
            'odooPayrollError' => $odooPayrollError,
            'hasManagerPayrollIdentity' => $hasManagerPayrollIdentity,
            'selectedEmployeeId' => is_numeric($employeeId) ? (int) $employeeId : null,
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
