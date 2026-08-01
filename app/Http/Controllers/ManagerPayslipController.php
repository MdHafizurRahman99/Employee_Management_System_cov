<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerPayslipController extends Controller
{
    /**
     * Display the manager payslip generation page.
     */
    public function create(OdooManagerPayrollService $payrollService): View
    {
        $pageData = [
            'payrollAvailable' => false,
            'payrollMessage' => null,
            'employees' => [],
            'recentPayslips' => [],
        ];
        $odooPayrollError = null;

        try {
            $pageData = $payrollService->getPayslipGenerationPageData();
        } catch (OdooException $exception) {
            $odooPayrollError = $exception->getMessage();
        }

        return view('admin.manager-payslips.create', [
            'payrollAvailable' => $pageData['payrollAvailable'],
            'payrollMessage' => $pageData['payrollMessage'],
            'employees' => $pageData['employees'],
            'recentPayslips' => $pageData['recentPayslips'],
            'odooPayrollError' => $odooPayrollError,
        ]);
    }

    /**
     * Create and compute a new Odoo payslip.
     */
    public function store(Request $request, OdooManagerPayrollService $payrollService): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $payslip = $payrollService->createPayslip($validated);
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.payslips.create')
                ->withErrors(['manager_payslip' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('manager.payslips.create')
            ->with('success', 'The Odoo payslip was generated successfully.')
            ->with('generated_payslip', $payslip);
    }
}
