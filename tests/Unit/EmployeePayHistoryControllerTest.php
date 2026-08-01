<?php

namespace Tests\Unit;

use App\Http\Controllers\EmployeePayHistoryController;
use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPayrollService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class EmployeePayHistoryControllerTest extends TestCase
{
    public function test_it_returns_the_employee_pay_history_view(): void
    {
        $this->mock(OdooManagerPayrollService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getEmployeePayHistoryPageData')
                ->once()
                ->andReturn([
                    'payrollAvailable' => true,
                    'payrollMessage' => null,
                    'payslips' => [
                        ['id' => 91, 'period_label' => '01 Jun 2026 - 15 Jun 2026'],
                    ],
                    'summary' => [
                        'payslip_count' => 1,
                        'employees_count' => 1,
                        'gross_pay_total' => 3200.50,
                        'gross_pay_total_label' => '3200.50',
                        'deductions_total' => 420.25,
                        'deductions_total_label' => '420.25',
                        'net_pay_total' => 2780.25,
                        'net_pay_total_label' => '2780.25',
                        'latest_period_label' => '01 Jun 2026 - 15 Jun 2026',
                    ],
                ]);
        });

        $view = (new EmployeePayHistoryController())->index(
            $this->requestWithUser($this->employeeUser()),
            app(OdooManagerPayrollService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.employee-pay-history.index', $view->getName());
        $this->assertTrue($data['hasPayrollIdentity']);
        $this->assertTrue($data['payrollAvailable']);
        $this->assertCount(1, $data['payslips']);
        $this->assertSame('2780.25', $data['paySummary']['net_pay_total_label']);
    }

    public function test_it_exposes_payroll_errors_without_throwing_from_the_controller(): void
    {
        $this->mock(OdooManagerPayrollService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getEmployeePayHistoryPageData')
                ->once()
                ->andThrow(new OdooException('Payroll is temporarily unavailable.'));
        });

        $view = (new EmployeePayHistoryController())->index(
            $this->requestWithUser($this->employeeUser()),
            app(OdooManagerPayrollService::class)
        );

        $data = $view->getData();

        $this->assertSame('Payroll is temporarily unavailable.', $data['odooPayrollError']);
        $this->assertSame([], $data['payslips']);
    }

    private function employeeUser(): User
    {
        $user = new User([
            'name' => 'Odoo Employee',
            'email' => 'employee@example.com',
            'odoo_user_id' => 27,
            'odoo_employee_id' => 35,
        ]);

        $user->setAttribute('id', 1);
        $user->exists = true;

        return $user;
    }

    private function requestWithUser(User $user): Request
    {
        $request = Request::create('/employee/pay-history', 'GET');
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
