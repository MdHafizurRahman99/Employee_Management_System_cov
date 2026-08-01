<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerPayrollSummaryReportController;
use App\Models\User;
use App\Services\Odoo\OdooManagerPayrollSummaryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerPayrollSummaryReportControllerTest extends TestCase
{
    public function test_it_returns_the_payroll_summary_view(): void
    {
        $this->mock(OdooManagerPayrollSummaryReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->withArgs(function (User $user, $month, ?int $companyId): bool {
                    return $user->odoo_user_id === 27
                        && $month->format('Y-m') === '2026-06'
                        && $companyId === 2;
                })
                ->andReturn($this->reportData());
        });

        $request = Request::create('/manager/payroll-summary', 'GET', [
            'month' => '2026-06',
            'company_id' => '2',
        ]);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $view = (new ManagerPayrollSummaryReportController())->index(
            $request,
            app(OdooManagerPayrollSummaryReportService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-payroll-summary.index', $view->getName());
        $this->assertTrue($data['hasManagerPayrollIdentity']);
        $this->assertTrue($data['payrollAvailable']);
        $this->assertSame(2, $data['selectedCompanyId']);
        $this->assertSame('2026-06', $data['selectedMonth']->format('Y-m'));
        $this->assertCount(1, $data['companyBreakdown']);
    }

    public function test_it_exports_the_payroll_summary_as_excel(): void
    {
        $this->mock(OdooManagerPayrollSummaryReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->andReturn($this->reportData());
        });

        $request = Request::create('/manager/payroll-summary/export/excel', 'GET', ['month' => '2026-06']);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $response = (new ManagerPayrollSummaryReportController())->exportExcel(
            $request,
            app(OdooManagerPayrollSummaryReportService::class)
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('payroll-summary-report-2026-06.xls', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Payroll Summary Report', $response->getContent());
    }

    public function test_it_exports_the_payroll_summary_as_pdf(): void
    {
        $this->mock(OdooManagerPayrollSummaryReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->andReturn($this->reportData());
        });

        $pdfMock = Mockery::mock(DompdfWrapper::class);
        $pdfResponse = new Response('pdf-binary');

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data): bool {
                return $view === 'admin.manager-payroll-summary.export-pdf'
                    && isset($data['reportSummary'], $data['comparison'], $data['companyBreakdown'], $data['roleBreakdown'], $data['selectedMonth'], $data['generatedAt']);
            })
            ->andReturn($pdfMock);

        $pdfMock->shouldReceive('setPaper')
            ->once()
            ->with('a4', 'landscape')
            ->andReturnSelf();

        $pdfMock->shouldReceive('download')
            ->once()
            ->with('payroll-summary-report-2026-06.pdf')
            ->andReturn($pdfResponse);

        $request = Request::create('/manager/payroll-summary/export/pdf', 'GET', ['month' => '2026-06']);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $response = (new ManagerPayrollSummaryReportController())->exportPdf(
            $request,
            app(OdooManagerPayrollSummaryReportService::class)
        );

        $this->assertSame($pdfResponse, $response);
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
    private function reportData(): array
    {
        return [
            'payrollAvailable' => true,
            'payrollMessage' => null,
            'companies' => [['id' => 2, 'name' => 'Clinic']],
            'summary' => [
                'month_label' => 'June 2026',
                'payslip_count' => 2,
                'employees_count' => 2,
                'gross_total' => 6500.0,
                'gross_total_label' => '6,500.00',
                'deductions_total' => 800.0,
                'deductions_total_label' => '800.00',
                'net_total' => 5700.0,
                'net_total_label' => '5,700.00',
            ],
            'comparison' => [
                'current_month_label' => 'June 2026',
                'previous_month_label' => 'May 2026',
                'current_gross_total' => 6500.0,
                'current_gross_total_label' => '6,500.00',
                'previous_gross_total' => 3000.0,
                'previous_gross_total_label' => '3,000.00',
                'change_value' => 3500.0,
                'change_value_label' => '+3,500.00',
                'change_percent' => 116.67,
                'change_percent_label' => '116.67%',
                'direction_label' => 'Increase',
            ],
            'companyBreakdown' => [[
                'company' => 'Clinic',
                'payslip_count' => 1,
                'employees_count' => 1,
                'gross_total_label' => '3,200.00',
                'deductions_total_label' => '400.00',
                'net_total_label' => '2,800.00',
            ]],
            'roleBreakdown' => [[
                'role' => 'Nurse',
                'payslip_count' => 1,
                'employees_count' => 1,
                'gross_total_label' => '3,200.00',
                'deductions_total_label' => '400.00',
                'net_total_label' => '2,800.00',
            ]],
        ];
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
