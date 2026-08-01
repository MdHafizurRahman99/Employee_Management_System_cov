<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooAttendanceService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class OdooAttendanceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_groups_attendance_records_by_day_and_flags_missing_clock_outs(): void
    {
        Carbon::setTestNow('2026-06-08 12:00:00');

        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.attendance',
                'fields_get',
                [],
                ['attributes' => ['string', 'type', 'relation']]
            )
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'check_in' => ['type' => 'datetime'],
                'check_out' => ['type' => 'datetime'],
                'worked_hours' => ['type' => 'float'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (
                string $model,
                string $method,
                array $args,
                array $kwargs
            ): bool {
                if ($model !== 'hr.attendance' || $method !== 'search_read') {
                    return false;
                }

                if (($kwargs['order'] ?? null) !== 'check_in asc') {
                    return false;
                }

                if (($kwargs['fields'] ?? []) !== ['id', 'check_in', 'check_out', 'worked_hours', 'employee_id']) {
                    return false;
                }

                $domain = $args[0] ?? [];

                return $domain === [
                    ['employee_id', '=', 35],
                    ['check_in', '>=', '2026-06-01 00:00:00'],
                    ['check_in', '<=', '2026-06-30 23:59:59'],
                ];
            })
            ->andReturn([
                [
                    'id' => 11,
                    'employee_id' => [35, 'Odoo Employee'],
                    'check_in' => '2026-06-02 09:00:00',
                    'check_out' => '2026-06-02 13:00:00',
                    'worked_hours' => 4.0,
                ],
                [
                    'id' => 12,
                    'employee_id' => [35, 'Odoo Employee'],
                    'check_in' => '2026-06-02 14:00:00',
                    'check_out' => '2026-06-02 18:30:00',
                    'worked_hours' => 4.5,
                ],
                [
                    'id' => 13,
                    'employee_id' => [35, 'Odoo Employee'],
                    'check_in' => '2026-06-03 09:15:00',
                    'check_out' => false,
                    'worked_hours' => 0.0,
                ],
            ]);

        $service = new OdooAttendanceService($serviceAccount);
        $result = $service->getAttendanceForMonth(
            new User(['odoo_employee_id' => 35]),
            Carbon::create(2026, 6, 1, 0, 0, 0, 'UTC')
        );

        $this->assertCount(2, $result['days']);
        $this->assertSame('2026-06-03', $result['days'][0]['date']);
        $this->assertSame('09:15 AM', $result['days'][0]['clock_in_label']);
        $this->assertSame('Missing clock-out', $result['days'][0]['clock_out_label']);
        $this->assertSame('Pending', $result['days'][0]['worked_hours_label']);
        $this->assertTrue($result['days'][0]['missing_clock_out']);

        $this->assertSame('2026-06-02', $result['days'][1]['date']);
        $this->assertSame('09:00 AM', $result['days'][1]['clock_in_label']);
        $this->assertSame('06:30 PM', $result['days'][1]['clock_out_label']);
        $this->assertSame('8.50 hrs', $result['days'][1]['worked_hours_label']);
        $this->assertSame(2, $result['days'][1]['session_count']);

        $this->assertSame('June 2026', $result['summary']['month_label']);
        $this->assertSame(2, $result['summary']['total_days']);
        $this->assertSame(1, $result['summary']['open_days']);
        $this->assertSame(3, $result['summary']['total_sessions']);
        $this->assertSame(8.5, $result['summary']['total_worked_hours']);
        $this->assertSame('8.50 hrs', $result['summary']['total_worked_hours_label']);
    }
}
