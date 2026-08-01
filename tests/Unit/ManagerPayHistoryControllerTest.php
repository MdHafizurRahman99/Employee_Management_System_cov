<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerPayHistoryController;
use App\Models\User;
use App\Services\Odoo\OdooManagerPayrollService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerPayHistoryControllerTest extends TestCase
{
    public function test_it_returns_the_manager_team_pay_history_view(): void
    {
        $this->mock(OdooManagerPayrollService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getTeamPayHistoryPageData')
                ->once()
                ->withArgs(function (User $user, ?int $employeeId): bool {
                    return $user->odoo_user_id === 27 && $employeeId === 35;
                })
                ->andReturn([
                    'payrollAvailable' => true,
                    'payrollMessage' => null,
                    'employees' => [
                        ['id' => 35, 'name' => 'Alice Jones', 'company' => 'Clinic'],
                    ],
                    'payslips' => [
                        ['id' => 93, 'employee' => 'Alice Jones'],
                    ],
                    'summary' => [
                        'payslip_count' => 1,
                        'employees_count' => 1,
                        'gross_pay_total' => 3100.00,
                        'gross_pay_total_label' => '3100.00',
                        'deductions_total' => 400.00,
                        'deductions_total_label' => '400.00',
                        'net_pay_total' => 2700.00,
                        'net_pay_total_label' => '2700.00',
                        'latest_period_label' => '01 Jun 2026 - 15 Jun 2026',
                    ],
                ]);
        });

        $request = Request::create('/manager/pay-history', 'GET', ['employee_id' => '35']);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $view = (new ManagerPayHistoryController())->index(
            $request,
            app(OdooManagerPayrollService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-pay-history.index', $view->getName());
        $this->assertTrue($data['hasManagerPayrollIdentity']);
        $this->assertSame(35, $data['selectedEmployeeId']);
        $this->assertCount(1, $data['employees']);
        $this->assertCount(1, $data['payslips']);
        $this->assertSame('2700.00', $data['paySummary']['net_pay_total_label']);
    }

    private function managerUser(): User
    {
        $user = new User([
            'name' => 'Odoo Manager',
            'email' => 'manager@example.com',
            'odoo_user_id' => 27,
            'odoo_employee_id' => 11,
            'role' => 'manager',
            'auth_source' => 'odoo',
        ]);

        $user->setAttribute('id', 7);
        $user->exists = true;

        return $user;
    }
}
