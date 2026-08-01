<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooManagerWorkingHoursReportService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class OdooManagerWorkingHoursReportServiceTest extends TestCase
{
    public function test_it_builds_a_monthly_working_hours_report_for_managed_employees(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'resource_id' => ['type' => 'many2one'],
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
                    'resource_id' => [44, 'Alice Resource'],
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
                    'resource_id' => [45, 'Bob Resource'],
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
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'allocated_hours' => ['type' => 'float'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'planning.slot'
                    && $method === 'search_read'
                    && $args === [[
                        ['employee_id', 'in', [35, 36]],
                        ['start_datetime', '>=', '2026-06-01 00:00:00'],
                        ['start_datetime', '<=', '2026-06-30 23:59:59'],
                    ]]
                    && ($kwargs['order'] ?? null) === 'start_datetime asc';
            })
            ->andReturn([
                [
                    'id' => 71,
                    'employee_id' => [35, 'Alice Jones'],
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'allocated_hours' => 8.0,
                ],
                [
                    'id' => 72,
                    'employee_id' => [36, 'Bob Smith'],
                    'start_datetime' => '2026-06-11 09:00:00',
                    'end_datetime' => '2026-06-11 17:00:00',
                    'allocated_hours' => 8.0,
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.attendance', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'check_in' => ['type' => 'datetime'],
                'check_out' => ['type' => 'datetime'],
                'worked_hours' => ['type' => 'float'],
                'employee_id' => ['type' => 'many2one'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.attendance'
                    && $method === 'search_read'
                    && $args === [[
                        ['employee_id', 'in', [35, 36]],
                        ['check_in', '>=', '2026-06-01 00:00:00'],
                        ['check_in', '<=', '2026-06-30 23:59:59'],
                    ]]
                    && ($kwargs['order'] ?? null) === 'check_in asc';
            })
            ->andReturn([
                [
                    'id' => 91,
                    'employee_id' => [35, 'Alice Jones'],
                    'check_in' => '2026-06-10 09:00:00',
                    'check_out' => '2026-06-10 18:00:00',
                    'worked_hours' => 9.0,
                ],
                [
                    'id' => 92,
                    'employee_id' => [36, 'Bob Smith'],
                    'check_in' => '2026-06-11 09:00:00',
                    'check_out' => '2026-06-11 16:00:00',
                    'worked_hours' => 7.0,
                ],
            ]);

        $service = new OdooManagerWorkingHoursReportService($serviceAccount);
        $report = $service->getReportPageData(
            new User(['odoo_user_id' => 27]),
            Carbon::create(2026, 6, 1, 0, 0, 0)
        );

        $this->assertCount(2, $report['employees']);
        $this->assertCount(1, $report['companies']);
        $this->assertCount(2, $report['rows']);
        $this->assertSame('Alice Jones', $report['rows'][0]['employee']);
        $this->assertSame('9.00 hrs', $report['rows'][0]['actual_hours_label']);
        $this->assertSame('+1.00 hrs', $report['rows'][0]['variance_hours_label']);
        $this->assertSame('Overtime', $report['rows'][0]['status_label']);
        $this->assertSame('Bob Smith', $report['rows'][1]['employee']);
        $this->assertSame('1.00 hrs', $report['rows'][1]['undertime_hours_label']);
        $this->assertSame('June 2026', $report['summary']['month_label']);
        $this->assertSame('16.00 hrs', $report['summary']['planned_hours_total_label']);
        $this->assertSame('16.00 hrs', $report['summary']['actual_hours_total_label']);
        $this->assertSame('1.00 hrs', $report['summary']['overtime_total_label']);
        $this->assertSame('1.00 hrs', $report['summary']['undertime_total_label']);
    }
}
