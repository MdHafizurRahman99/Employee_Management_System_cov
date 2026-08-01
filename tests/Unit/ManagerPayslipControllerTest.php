<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerPayslipController;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPayrollService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerPayslipControllerTest extends TestCase
{
    public function test_it_returns_the_manager_payslip_view(): void
    {
        $this->mock(OdooManagerPayrollService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getPayslipGenerationPageData')
                ->once()
                ->andReturn([
                    'payrollAvailable' => true,
                    'payrollMessage' => null,
                    'employees' => [['id' => 35, 'name' => 'Odoo Employee', 'company' => 'Clinic']],
                    'recentPayslips' => [['id' => 91, 'employee' => 'Odoo Employee']],
                ]);
        });

        $view = (new ManagerPayslipController())->create(app(OdooManagerPayrollService::class));
        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-payslips.create', $view->getName());
        $this->assertTrue($data['payrollAvailable']);
        $this->assertCount(1, $data['employees']);
        $this->assertCount(1, $data['recentPayslips']);
    }

    public function test_it_redirects_with_success_after_generating_a_payslip(): void
    {
        $this->mock(OdooManagerPayrollService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createPayslip')
                ->once()
                ->withArgs(function (array $payload): bool {
                    return $payload['employee_id'] === '35'
                        && $payload['period_start'] === '2026-06-01'
                        && $payload['period_end'] === '2026-06-15';
                })
                ->andReturn([
                    'id' => 91,
                    'employee' => 'Odoo Employee',
                    'gross_pay' => 3200.50,
                    'deductions' => 420.25,
                    'net_pay' => 2780.25,
                ]);
        });

        $request = Request::create('/manager/payslips', 'POST', [
            'employee_id' => '35',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-15',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerPayslipController())->store($request, app(OdooManagerPayrollService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.payslips.create'), $response->getTargetUrl());
    }

    public function test_it_redirects_with_errors_when_payslip_generation_fails(): void
    {
        $this->mock(OdooManagerPayrollService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createPayslip')
                ->once()
                ->andThrow(new OdooException('The selected employee does not have an active payroll contract for the chosen pay period.'));
        });

        $request = Request::create('/manager/payslips', 'POST', [
            'employee_id' => '35',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-15',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerPayslipController())->store($request, app(OdooManagerPayrollService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.payslips.create'), $response->getTargetUrl());
    }
}
