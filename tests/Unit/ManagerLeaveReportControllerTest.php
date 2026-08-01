<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerLeaveReportController;
use App\Models\User;
use App\Services\Odoo\OdooManagerLeaveReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerLeaveReportControllerTest extends TestCase
{
    public function test_it_returns_the_leave_report_view(): void
    {
        $this->mock(OdooManagerLeaveReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->withArgs(function (User $user, $fromDate, $toDate, ?int $employeeId, ?int $leaveTypeId): bool {
                    return $user->odoo_user_id === 27
                        && $fromDate->format('Y-m-d') === '2026-06-01'
                        && $toDate->format('Y-m-d') === '2026-06-30'
                        && $employeeId === 35
                        && $leaveTypeId === 7;
                })
                ->andReturn($this->reportData());
        });

        $request = Request::create('/manager/leave-report', 'GET', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
            'employee_id' => '35',
            'leave_type_id' => '7',
        ]);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $view = (new ManagerLeaveReportController())->index(
            $request,
            app(OdooManagerLeaveReportService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-leave-report.index', $view->getName());
        $this->assertTrue($data['hasManagerLeaveIdentity']);
        $this->assertTrue($data['leaveAvailable']);
        $this->assertSame(35, $data['selectedEmployeeId']);
        $this->assertSame(7, $data['selectedLeaveTypeId']);
        $this->assertCount(1, $data['reportRows']);
    }

    public function test_it_exports_the_leave_report_as_excel(): void
    {
        $this->mock(OdooManagerLeaveReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->andReturn($this->reportData());
        });

        $request = Request::create('/manager/leave-report/export/excel', 'GET', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $response = (new ManagerLeaveReportController())->exportExcel(
            $request,
            app(OdooManagerLeaveReportService::class)
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('application/vnd.ms-excel', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('leave-report-20260601-20260630.xls', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Leave Report', $response->getContent());
    }

    public function test_it_exports_the_leave_report_as_pdf(): void
    {
        $this->mock(OdooManagerLeaveReportService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getReportPageData')
                ->once()
                ->andReturn($this->reportData());
        });

        $pdfMock = Mockery::mock(DompdfWrapper::class);
        $pdfResponse = new Response('pdf-binary');

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function (string $view, array $data): bool {
                return $view === 'admin.manager-leave-report.export-pdf'
                    && isset($data['reportRows'], $data['reportSummary'], $data['selectedFromDate'], $data['selectedToDate'], $data['generatedAt']);
            })
            ->andReturn($pdfMock);

        $pdfMock->shouldReceive('setPaper')
            ->once()
            ->with('a4', 'landscape')
            ->andReturnSelf();

        $pdfMock->shouldReceive('download')
            ->once()
            ->with('leave-report-20260601-20260630.pdf')
            ->andReturn($pdfResponse);

        $request = Request::create('/manager/leave-report/export/pdf', 'GET', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]);
        $request->setUserResolver(fn (): User => $this->managerUser());

        $response = (new ManagerLeaveReportController())->exportPdf(
            $request,
            app(OdooManagerLeaveReportService::class)
        );

        $this->assertSame($pdfResponse, $response);
    }

    /**
     * @return array{
     *     leaveAvailable:bool,
     *     leaveMessage:?string,
     *     employees:array<int, array<string, mixed>>,
     *     leaveTypes:array<int, array<string, mixed>>,
     *     rows:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    private function reportData(): array
    {
        return [
            'leaveAvailable' => true,
            'leaveMessage' => null,
            'employees' => [['id' => 35, 'name' => 'Alice Jones', 'company' => 'Clinic']],
            'leaveTypes' => [['id' => 7, 'name' => 'Sick Leave']],
            'rows' => [[
                'employee_id' => 35,
                'employee' => 'Alice Jones',
                'company' => 'Clinic',
                'leave_type_id' => 7,
                'leave_type' => 'Sick Leave',
                'request_unit' => 'day',
                'taken_label' => '2.00 days',
                'remaining_balance_label' => '8.50 days',
                'request_count' => 1,
                'last_leave_label' => '11 Jun 2026',
            ]],
            'summary' => [
                'range_label' => '01 Jun 2026 - 30 Jun 2026',
                'row_count' => 1,
                'employees_count' => 1,
                'leave_types_count' => 1,
                'day_based_total' => 2.0,
                'day_based_total_label' => '2.00 days',
                'hour_based_total' => 0.0,
                'hour_based_total_label' => '0.00 hours',
                'request_count_total' => 1,
                'balance_rows_count' => 1,
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
