<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerAttendanceService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class OdooManagerAttendanceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_builds_team_attendance_data_for_the_manager(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

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
                    && ($kwargs['order'] ?? null) === 'check_in desc';
            })
            ->andReturn([
                [
                    'id' => 91,
                    'employee_id' => [35, 'Alice Jones'],
                    'check_in' => '2026-06-10 09:00:00',
                    'check_out' => '2026-06-10 17:00:00',
                    'worked_hours' => 8.0,
                    'write_date' => '2026-06-10 17:05:00',
                ],
                [
                    'id' => 92,
                    'employee_id' => [36, 'Bob Smith'],
                    'check_in' => '2026-06-11 09:15:00',
                    'check_out' => false,
                    'worked_hours' => 0.0,
                    'write_date' => '2026-06-11 09:16:00',
                ],
            ]);

        $service = new OdooManagerAttendanceService($serviceAccount);
        $data = $service->getTeamAttendancePageData(
            new User(['odoo_user_id' => 27]),
            Carbon::create(2026, 6, 1, 0, 0, 0),
            Carbon::create(2026, 6, 30, 0, 0, 0)
        );

        $this->assertCount(2, $data['employees']);
        $this->assertSame('Alice Jones', $data['employees'][0]['name']);
        $this->assertSame('Bob Smith', $data['employees'][1]['name']);

        $this->assertCount(2, $data['records']);
        $this->assertSame('Alice Jones', $data['records'][0]['employee']);
        $this->assertSame('8.00 hrs', $data['records'][0]['worked_hours_label']);
        $this->assertTrue($data['records'][1]['missing_clock_out']);
        $this->assertSame('Missing Clock-out', $data['records'][1]['status_label']);

        $this->assertSame('01 Jun 2026 - 30 Jun 2026', $data['summary']['range_label']);
        $this->assertSame(2, $data['summary']['records_count']);
        $this->assertSame(2, $data['summary']['employees_count']);
        $this->assertSame(1, $data['summary']['missing_clock_out_count']);
        $this->assertSame('8.00 hrs', $data['summary']['total_worked_hours_label']);
    }

    public function test_it_submits_a_manual_attendance_correction_and_logs_it(): void
    {
        Log::spy();

        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'work_email' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
            ]);

        foreach (['attendance_manager_id', 'parent_id.user_id', 'leave_manager_id'] as $index => $field) {
            $serviceAccount->shouldReceive('executeKw')
                ->once()
                ->withArgs(function (string $model, string $method, array $args) use ($field): bool {
                    return $model === 'hr.employee'
                        && $method === 'search_read'
                        && $args === [[
                            [$field, '=', 27],
                            ['active', '=', true],
                        ]];
                })
                ->andReturn($index === 0 ? [[
                    'id' => 35,
                    'name' => 'Alice Jones',
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'alice@example.com',
                ]] : []);
        }

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
                    && $args === [[['id', '=', 91]]]
                    && ($kwargs['limit'] ?? null) === 1;
            })
            ->andReturn([
                [
                    'id' => 91,
                    'employee_id' => [35, 'Alice Jones'],
                    'check_in' => '2026-06-10 09:00:00',
                    'check_out' => false,
                    'worked_hours' => 0.0,
                    'write_date' => '2026-06-10 09:05:00',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.attendance',
                'write',
                [[91], [
                    'check_in' => '2026-06-10 09:00:00',
                    'check_out' => '2026-06-10 17:00:00',
                ]]
            )
            ->andReturn(true);

        $service = new OdooManagerAttendanceService($serviceAccount);
        $service->correctAttendanceRecord(
            new User(['id' => 7, 'odoo_user_id' => 27]),
            91,
            [
                'check_in' => '2026-06-10T09:00',
                'check_out' => '2026-06-10T17:00',
                'correction_note' => 'Added missing clock-out.',
                'last_known_write_date' => '2026-06-10 09:05:00',
            ]
        );

        Log::shouldHaveReceived('info')->once();
    }

    public function test_it_rejects_stale_team_attendance_corrections(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'work_email' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
            ]);

        foreach (['attendance_manager_id', 'parent_id.user_id', 'leave_manager_id'] as $index => $field) {
            $serviceAccount->shouldReceive('executeKw')
                ->once()
                ->withArgs(function (string $model, string $method, array $args) use ($field): bool {
                    return $model === 'hr.employee'
                        && $method === 'search_read'
                        && $args === [[
                            [$field, '=', 27],
                            ['active', '=', true],
                        ]];
                })
                ->andReturn($index === 0 ? [[
                    'id' => 35,
                    'name' => 'Alice Jones',
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'alice@example.com',
                ]] : []);
        }

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
            ->withArgs(fn (string $model, string $method): bool => $model === 'hr.attendance' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 91,
                    'employee_id' => [35, 'Alice Jones'],
                    'check_in' => '2026-06-10 09:00:00',
                    'check_out' => false,
                    'worked_hours' => 0.0,
                    'write_date' => '2026-06-10 09:10:00',
                ],
            ]);

        $service = new OdooManagerAttendanceService($serviceAccount);

        $this->expectException(OdooException::class);
        $this->expectExceptionMessage('This attendance record was updated by someone else. Please reload the page before trying again.');

        $service->correctAttendanceRecord(
            new User(['id' => 7, 'odoo_user_id' => 27]),
            91,
            [
                'check_in' => '2026-06-10T09:00',
                'check_out' => '2026-06-10T17:00',
                'correction_note' => 'Added missing clock-out.',
                'last_known_write_date' => '2026-06-10 09:05:00',
            ]
        );
    }
}
