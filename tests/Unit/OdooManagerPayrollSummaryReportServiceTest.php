<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooManagerPayrollSummaryReportService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class OdooManagerPayrollSummaryReportServiceTest extends TestCase
{
    public function test_it_returns_unavailable_report_data_when_payroll_is_not_installed(): void
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

        $service = new OdooManagerPayrollSummaryReportService($serviceAccount);
        $report = $service->getReportPageData(
            new User(['odoo_user_id' => 27]),
            Carbon::create(2026, 6, 1, 0, 0, 0)
        );

        $this->assertFalse($report['payrollAvailable']);
        $this->assertSame([], $report['companyBreakdown']);
        $this->assertSame([], $report['roleBreakdown']);
        $this->assertStringContainsString('not installed', (string) $report['payrollMessage']);
    }

    public function test_it_builds_a_monthly_payroll_summary_report(): void
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
                'job_title' => ['type' => 'char'],
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
                    'job_title' => 'Nurse',
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
                    'company_id' => [3, 'Pharmacy'],
                    'job_title' => 'Pharmacist',
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
                'date_from' => ['type' => 'date'],
                'date_to' => ['type' => 'date'],
                'gross_wage' => ['type' => 'float'],
                'net_wage' => ['type' => 'float'],
                'line_ids' => ['type' => 'one2many'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.payslip'
                    && $method === 'search_read'
                    && $args === [[
                        ['employee_id', 'in', [35, 36]],
                        ['date_to', '>=', '2026-06-01'],
                        ['date_to', '<=', '2026-06-30'],
                    ]]
                    && ($kwargs['order'] ?? null) === 'date_to desc, id desc';
            })
            ->andReturn([
                [
                    'id' => 101,
                    'employee_id' => [35, 'Alice Jones'],
                    'company_id' => [2, 'Clinic'],
                    'date_from' => '2026-06-01',
                    'date_to' => '2026-06-15',
                    'gross_wage' => 3200.00,
                    'net_wage' => 2800.00,
                    'line_ids' => [],
                ],
                [
                    'id' => 102,
                    'employee_id' => [36, 'Bob Smith'],
                    'company_id' => [3, 'Pharmacy'],
                    'date_from' => '2026-06-16',
                    'date_to' => '2026-06-30',
                    'gross_wage' => 3300.00,
                    'net_wage' => 2900.00,
                    'line_ids' => [],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.payslip'
                    && $method === 'search_read'
                    && $args === [[
                        ['employee_id', 'in', [35, 36]],
                        ['date_to', '>=', '2026-05-01'],
                        ['date_to', '<=', '2026-05-31'],
                    ]]
                    && ($kwargs['order'] ?? null) === 'date_to desc, id desc';
            })
            ->andReturn([
                [
                    'id' => 99,
                    'employee_id' => [35, 'Alice Jones'],
                    'company_id' => [2, 'Clinic'],
                    'date_from' => '2026-05-01',
                    'date_to' => '2026-05-15',
                    'gross_wage' => 3000.00,
                    'net_wage' => 2600.00,
                    'line_ids' => [],
                ],
            ]);

        $service = new OdooManagerPayrollSummaryReportService($serviceAccount);
        $report = $service->getReportPageData(
            new User(['odoo_user_id' => 27]),
            Carbon::create(2026, 6, 1, 0, 0, 0)
        );

        $this->assertTrue($report['payrollAvailable']);
        $this->assertCount(2, $report['companies']);
        $this->assertSame('June 2026', $report['summary']['month_label']);
        $this->assertSame(2, $report['summary']['payslip_count']);
        $this->assertSame('6,500.00', $report['summary']['gross_total_label']);
        $this->assertSame('800.00', $report['summary']['deductions_total_label']);
        $this->assertSame('5,700.00', $report['summary']['net_total_label']);
        $this->assertSame('May 2026', $report['comparison']['previous_month_label']);
        $this->assertSame('+3,500.00', $report['comparison']['change_value_label']);
        $this->assertSame('116.67%', $report['comparison']['change_percent_label']);
        $this->assertCount(2, $report['companyBreakdown']);
        $this->assertSame('Clinic', $report['companyBreakdown'][0]['company']);
        $this->assertCount(2, $report['roleBreakdown']);
        $this->assertSame('Nurse', $report['roleBreakdown'][0]['role']);
    }
}
