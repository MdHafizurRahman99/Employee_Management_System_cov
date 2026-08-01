<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooManagerLeaveReportService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class OdooManagerLeaveReportServiceTest extends TestCase
{
    public function test_it_returns_unavailable_report_data_when_time_off_is_not_installed(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.leave']]])
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'ir.module.module',
                'search_read',
                [[['name', '=', 'hr_holidays']]],
                ['fields' => ['state'], 'limit' => 1]
            )
            ->andReturn([
                ['state' => 'uninstalled'],
            ]);

        $service = new OdooManagerLeaveReportService($serviceAccount);
        $report = $service->getReportPageData(
            new User(['odoo_user_id' => 27]),
            Carbon::create(2026, 6, 1, 0, 0, 0),
            Carbon::create(2026, 6, 30, 0, 0, 0)
        );

        $this->assertFalse($report['leaveAvailable']);
        $this->assertSame([], $report['rows']);
        $this->assertStringContainsString('not installed', (string) $report['leaveMessage']);
    }

    public function test_it_builds_a_leave_report_with_remaining_balances(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.leave']]])
            ->andReturn(1);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('ir.model', 'search_count', [[['model', '=', 'hr.leave.type']]])
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
                        ['leave_manager_id', '=', 27],
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
            ->andReturn([]);

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
            ->andReturn([]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.users',
                'search_read',
                [[['id', '=', 27]]],
                ['fields' => ['group_ids'], 'limit' => 1]
            )
            ->andReturn([
                ['group_ids' => []],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave.type', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
                'request_unit' => ['type' => 'selection'],
                'sequence' => ['type' => 'integer'],
                'virtual_remaining_leaves' => ['type' => 'float'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.leave.type'
                    && $method === 'search_read'
                    && $args === [[['active', '=', true]]]
                    && ($kwargs['order'] ?? null) === 'sequence asc, name asc';
            })
            ->andReturn([
                [
                    'id' => 7,
                    'name' => 'Sick Leave',
                    'request_unit' => 'day',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'holiday_status_id' => ['type' => 'many2one'],
                'request_date_from' => ['type' => 'date'],
                'request_date_to' => ['type' => 'date'],
                'number_of_days' => ['type' => 'float'],
                'number_of_hours' => ['type' => 'float'],
                'leave_type_request_unit' => ['type' => 'selection'],
                'state' => ['type' => 'selection'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.leave'
                    && $method === 'search_read'
                    && $args === [[
                        ['employee_id', 'in', [35]],
                        ['state', '=', 'validate'],
                        ['request_date_from', '<=', '2026-06-30'],
                        ['request_date_to', '>=', '2026-06-01'],
                    ]]
                    && ($kwargs['order'] ?? null) === 'request_date_from desc, request_date_to desc';
            })
            ->andReturn([
                [
                    'id' => 91,
                    'employee_id' => [35, 'Alice Jones'],
                    'holiday_status_id' => [7, 'Sick Leave'],
                    'request_date_from' => '2026-06-10',
                    'request_date_to' => '2026-06-11',
                    'number_of_days' => 2.0,
                    'number_of_hours' => 0.0,
                    'leave_type_request_unit' => 'day',
                    'state' => 'validate',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.leave.type'
                    && $method === 'search_read'
                    && $args === [[['id', 'in', [7]]]]
                    && ($kwargs['context']['employee_id'] ?? null) === 35;
            })
            ->andReturn([
                [
                    'id' => 7,
                    'name' => 'Sick Leave',
                    'request_unit' => 'day',
                    'virtual_remaining_leaves' => 8.5,
                ],
            ]);

        $service = new OdooManagerLeaveReportService($serviceAccount);
        $report = $service->getReportPageData(
            new User(['odoo_user_id' => 27]),
            Carbon::create(2026, 6, 1, 0, 0, 0),
            Carbon::create(2026, 6, 30, 0, 0, 0)
        );

        $this->assertTrue($report['leaveAvailable']);
        $this->assertCount(1, $report['employees']);
        $this->assertCount(1, $report['leaveTypes']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame('Alice Jones', $report['rows'][0]['employee']);
        $this->assertSame('Sick Leave', $report['rows'][0]['leave_type']);
        $this->assertSame('2.00 days', $report['rows'][0]['taken_label']);
        $this->assertSame('8.50 days', $report['rows'][0]['remaining_balance_label']);
        $this->assertSame('01 Jun 2026 - 30 Jun 2026', $report['summary']['range_label']);
        $this->assertSame('2.00 days', $report['summary']['day_based_total_label']);
        $this->assertSame(1, $report['summary']['row_count']);
        $this->assertSame(1, $report['summary']['balance_rows_count']);
    }
}
