<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPayrollService;
use App\Services\Odoo\OdooServiceAccount;
use App\Models\User;
use Mockery;
use Tests\TestCase;

class OdooManagerPayrollServiceTest extends TestCase
{
    public function test_it_returns_unavailable_page_data_when_payroll_is_not_installed(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.payslip']]])
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'ir.module.module',
                'search_read',
                [[['name', '=', 'hr_payroll']]],
                ['fields' => ['state'], 'limit' => 1]
            )
            ->andReturn([
                ['state' => 'uninstalled'],
            ]);

        $service = new OdooManagerPayrollService($serviceAccount);
        $pageData = $service->getPayslipGenerationPageData();

        $this->assertFalse($pageData['payrollAvailable']);
        $this->assertSame([], $pageData['employees']);
        $this->assertSame([], $pageData['recentPayslips']);
        $this->assertStringContainsString('not installed', (string) $pageData['payrollMessage']);
    }

    public function test_it_creates_and_computes_a_payslip_when_payroll_is_available(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.payslip']]])
            ->andReturn(1);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'work_email' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.employee'
                    && $method === 'search_read'
                    && $args === [[['active', '=', true]]]
                    && ($kwargs['order'] ?? null) === 'name asc';
            })
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Odoo Employee',
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'employee@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.payslip', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'name' => ['type' => 'char'],
                'number' => ['type' => 'char'],
                'state' => ['type' => 'selection'],
                'date_from' => ['type' => 'date'],
                'date_to' => ['type' => 'date'],
                'gross_wage' => ['type' => 'float'],
                'net_wage' => ['type' => 'float'],
                'line_ids' => ['type' => 'one2many'],
                'write_date' => ['type' => 'datetime'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.contract']]])
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.payslip',
                'create',
                [[
                    'employee_id' => 35,
                    'date_from' => '2026-06-01',
                    'date_to' => '2026-06-15',
                    'company_id' => 2,
                    'name' => 'Odoo Employee Payslip 01 Jun 2026 - 15 Jun 2026',
                ]]
            )
            ->andReturn(91);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.payslip', 'compute_sheet', [[91]])
            ->andThrow(new OdooException("type object 'hr.payslip' has no attribute 'compute_sheet'"));

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.payslip', 'action_compute_sheet', [[91]])
            ->andReturn(true);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.payslip'
                    && $method === 'search_read'
                    && $args === [[['id', '=', 91]]]
                    && ($kwargs['limit'] ?? null) === 1;
            })
            ->andReturn([
                [
                    'id' => 91,
                    'employee_id' => [35, 'Odoo Employee'],
                    'company_id' => [2, 'Clinic'],
                    'name' => 'Odoo Employee Payslip 01 Jun 2026 - 15 Jun 2026',
                    'number' => 'PSLIP/2026/0001',
                    'state' => 'done',
                    'date_from' => '2026-06-01',
                    'date_to' => '2026-06-15',
                    'gross_wage' => 3200.50,
                    'net_wage' => 2780.25,
                    'line_ids' => [],
                    'write_date' => '2026-06-08 10:00:00',
                ],
            ]);

        $service = new OdooManagerPayrollService($serviceAccount);
        $payslip = $service->createPayslip([
            'employee_id' => 35,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-15',
        ]);

        $this->assertSame(91, $payslip['id']);
        $this->assertSame('Odoo Employee', $payslip['employee']);
        $this->assertSame('01 Jun 2026 - 15 Jun 2026', $payslip['period_label']);
        $this->assertSame(3200.5, $payslip['gross_pay']);
        $this->assertSame(420.25, $payslip['deductions']);
        $this->assertSame(2780.25, $payslip['net_pay']);
        $this->assertSame('Done', $payslip['state_label']);
    }

    public function test_it_returns_employee_pay_history_sorted_by_most_recent_first(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.payslip']]])
            ->andReturn(1);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.payslip', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'name' => ['type' => 'char'],
                'number' => ['type' => 'char'],
                'state' => ['type' => 'selection'],
                'date_from' => ['type' => 'date'],
                'date_to' => ['type' => 'date'],
                'gross_wage' => ['type' => 'float'],
                'net_wage' => ['type' => 'float'],
                'line_ids' => ['type' => 'one2many'],
                'write_date' => ['type' => 'datetime'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.payslip'
                    && $method === 'search_read'
                    && $args === [[['employee_id', 'in', [35]]]]
                    && ($kwargs['order'] ?? null) === 'date_to desc, id desc'
                    && ($kwargs['limit'] ?? null) === 100;
            })
            ->andReturn([
                [
                    'id' => 92,
                    'employee_id' => [35, 'Odoo Employee'],
                    'company_id' => [2, 'Clinic'],
                    'name' => 'June Payslip',
                    'number' => 'PSLIP/2026/0002',
                    'state' => 'done',
                    'date_from' => '2026-06-16',
                    'date_to' => '2026-06-30',
                    'gross_wage' => 3300.00,
                    'net_wage' => 2890.00,
                    'line_ids' => [],
                    'write_date' => '2026-07-01 09:00:00',
                ],
                [
                    'id' => 91,
                    'employee_id' => [35, 'Odoo Employee'],
                    'company_id' => [2, 'Clinic'],
                    'name' => 'May Payslip',
                    'number' => 'PSLIP/2026/0001',
                    'state' => 'done',
                    'date_from' => '2026-05-16',
                    'date_to' => '2026-05-31',
                    'gross_wage' => 3200.00,
                    'net_wage' => 2800.00,
                    'line_ids' => [],
                    'write_date' => '2026-06-01 09:00:00',
                ],
            ]);

        $service = new OdooManagerPayrollService($serviceAccount);
        $data = $service->getEmployeePayHistoryPageData(new User(['odoo_employee_id' => 35]));

        $this->assertTrue($data['payrollAvailable']);
        $this->assertCount(2, $data['payslips']);
        $this->assertSame(92, $data['payslips'][0]['id']);
        $this->assertSame('16-06-2026 - 30-06-2026', $data['payslips'][0]['period_label']);
        $this->assertSame(2, $data['summary']['payslip_count']);
        $this->assertSame('6,500.00', $data['summary']['gross_pay_total_label']);
        $this->assertSame('810.00', $data['summary']['deductions_total_label']);
        $this->assertSame('5,690.00', $data['summary']['net_pay_total_label']);
    }

    public function test_it_returns_team_pay_history_for_managed_employees_only(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.payslip']]])
            ->andReturn(1);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'work_email' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.employee'
                    && $method === 'search_read'
                    && $args === [[
                        ['attendance_manager_id', '=', 27],
                        ['active', '=', true],
                    ]];
            })
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Alice Jones',
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'alice@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.employee'
                    && $method === 'search_read'
                    && $args === [[
                        ['parent_id.user_id', '=', 27],
                        ['active', '=', true],
                    ]];
            })
            ->andReturn([
                [
                    'id' => 36,
                    'name' => 'Bob Smith',
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'bob@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.employee'
                    && $method === 'search_read'
                    && $args === [[
                        ['leave_manager_id', '=', 27],
                        ['active', '=', true],
                    ]];
            })
            ->andReturn([]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.payslip', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'name' => ['type' => 'char'],
                'number' => ['type' => 'char'],
                'state' => ['type' => 'selection'],
                'date_from' => ['type' => 'date'],
                'date_to' => ['type' => 'date'],
                'gross_wage' => ['type' => 'float'],
                'net_wage' => ['type' => 'float'],
                'line_ids' => ['type' => 'one2many'],
                'write_date' => ['type' => 'datetime'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.payslip'
                    && $method === 'search_read'
                    && $args === [[['employee_id', 'in', [35]]]]
                    && ($kwargs['limit'] ?? null) === 150;
            })
            ->andReturn([
                [
                    'id' => 93,
                    'employee_id' => [35, 'Alice Jones'],
                    'company_id' => [2, 'Clinic'],
                    'name' => 'Alice June Payslip',
                    'number' => 'PSLIP/2026/0003',
                    'state' => 'done',
                    'date_from' => '2026-06-01',
                    'date_to' => '2026-06-15',
                    'gross_wage' => 3100.00,
                    'net_wage' => 2700.00,
                    'line_ids' => [],
                    'write_date' => '2026-06-16 09:00:00',
                ],
            ]);

        $service = new OdooManagerPayrollService($serviceAccount);
        $data = $service->getTeamPayHistoryPageData(
            new User(['odoo_user_id' => 27]),
            35
        );

        $this->assertTrue($data['payrollAvailable']);
        $this->assertCount(2, $data['employees']);
        $this->assertCount(1, $data['payslips']);
        $this->assertSame('Alice Jones', $data['payslips'][0]['employee']);
        $this->assertSame(1, $data['summary']['employees_count']);
        $this->assertSame('3,100.00', $data['summary']['gross_pay_total_label']);
        $this->assertSame('400.00', $data['summary']['deductions_total_label']);
        $this->assertSame('2,700.00', $data['summary']['net_pay_total_label']);
    }
}
