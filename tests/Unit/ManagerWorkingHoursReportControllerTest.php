<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerWorkingHoursReportController;
use App\Models\User;
use App\Services\Odoo\OdooManagerWorkingHoursReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerWorkingHoursReportControllerTest extends TestCase
{
    public function test_it_returns_the_working_hours_report_view(): void
    {
        $this->mock(OdooManagerWorkingHoursReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->withArgs(function (User $user, $month, ?int $employeeId, ?int $companyId): bool {
                    return $user->odoo_user_id === 27
                        && $month->format('Y-m') === '2026-06'
                        && $employeeId === 35
                        && $companyId === 2;
                })
                ->andReturn([
                    'employees' => [['id' => 35, 'name' => 'Alice Jones', 'company' => 'Clinic']],
                    'companies' => [['id' => 2, 'name' => 'Clinic']],
                    'rows' => [['employee' => 'Alice Jones', 'planned_hours_label' => '8.00 hrs']],
                    'summary' => [
                        'month_label' => 'June 2026',
                        'employees_count' => 1,
                        'planned_hours_total' => 8.0,
                        'planned_hours_total_label' => '8.00 hrs',
                        'actual_hours_total' => 9.0,
                        'actual_hours_total_label' => '9.00 hrs',
                        'overtime_total' => 1.0,
                        'overtime_total_label' => '1.00 hrs',
                        'undertime_total' => 0.0,
                        'undertime_total_label' => '0.00 hrs',
                        'shift_count_total' => 1,
                        'attendance_days_total' => 1,
                        'missing_clock_out_total' => 0,
                    ],
                ]);
        });

        $request = Request::create('/manager/working-hours', 'GET', [
            'month' => '2026-06',
            'employee_id' => '35',
            'company_id' => '2',
        ]);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $view = (new ManagerWorkingHoursReportController())->index(
            $request,
            app(OdooManagerWorkingHoursReportService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-working-hours.index', $view->getName());
        $this->assertTrue($data['hasManagerReportIdentity']);
        $this->assertSame('2026-06', $data['selectedMonth']->format('Y-m'));
        $this->assertSame(35, $data['selectedEmployeeId']);
        $this->assertSame(2, $data['selectedCompanyId']);
        $this->assertCount(1, $data['reportRows']);
    }

    public function test_it_exports_the_working_hours_report_as_excel(): void
    {
        $this->mock(OdooManagerWorkingHoursReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->andReturn($this->reportData());
        });

        $request = Request::create('/manager/working-hours/export/excel', 'GET', ['month' => '2026-06']);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $response = (new ManagerWorkingHoursReportController())->exportExcel(
            $request,
            app(OdooManagerWorkingHoursReportService::class)
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('working-hours-report-2026-06.xls', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Working Hours Report', $response->getContent());
    }

    public function test_it_exports_the_working_hours_report_as_pdf(): void
    {
        $this->mock(OdooManagerWorkingHoursReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->andReturn($this->reportData());
        });

        $pdfMock = Mockery::mock(DompdfWrapper::class);
        $pdfResponse = new Response('pdf-binary');

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data): bool {
                return $view === 'admin.manager-working-hours.export-pdf'
                    && isset($data['reportRows'], $data['reportSummary'], $data['selectedMonth'], $data['generatedAt']);
            })
            ->andReturn($pdfMock);

        $pdfMock->shouldReceive('setPaper')
            ->once()
            ->with('a4', 'landscape')
            ->andReturnSelf();

        $pdfMock->shouldReceive('download')
            ->once()
            ->with('working-hours-report-2026-06.pdf')
            ->andReturn($pdfResponse);

        $request = Request::create('/manager/working-hours/export/pdf', 'GET', ['month' => '2026-06']);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $response = (new ManagerWorkingHoursReportController())->exportPdf(
            $request,
            app(OdooManagerWorkingHoursReportService::class)
        );

        $this->assertSame($pdfResponse, $response);
    }

    /**
     * @return array{
     *     employees:array<int, array<string, mixed>>,
     *     companies:array<int, array<string, mixed>>,
     *     rows:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    private function reportData(): array
    {
        return [
            'employees' => [['id' => 35, 'name' => 'Alice Jones', 'company' => 'Clinic']],
            'companies' => [['id' => 2, 'name' => 'Clinic']],
            'rows' => [[
                'employee' => 'Alice Jones',
                'company' => 'Clinic',
                'planned_hours_label' => '8.00 hrs',
                'actual_hours_label' => '9.00 hrs',
                'variance_hours_label' => '+1.00 hrs',
                'overtime_hours_label' => '1.00 hrs',
                'undertime_hours_label' => '0.00 hrs',
                'shift_count' => 1,
                'attendance_days_count' => 1,
                'missing_clock_out_count' => 0,
                'status_label' => 'Overtime',
            ]],
            'summary' => [
                'month_label' => 'June 2026',
                'employees_count' => 1,
                'planned_hours_total' => 8.0,
                'planned_hours_total_label' => '8.00 hrs',
                'actual_hours_total' => 9.0,
                'actual_hours_total_label' => '9.00 hrs',
                'overtime_total' => 1.0,
                'overtime_total_label' => '1.00 hrs',
                'undertime_total' => 0.0,
                'undertime_total_label' => '0.00 hrs',
                'shift_count_total' => 1,
                'attendance_days_total' => 1,
                'missing_clock_out_total' => 0,
            ],
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
