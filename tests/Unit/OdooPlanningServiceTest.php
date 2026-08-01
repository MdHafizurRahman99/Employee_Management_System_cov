<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooPlanningService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class OdooPlanningServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_builds_shift_page_data_with_a_calendar_and_selected_day_details(): void
    {
        Carbon::setTestNow('2026-06-10 08:00:00');

        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'name' => ['type' => 'char'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'employee_id' => ['type' => 'many2one'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'planning.slot'
                    && $method === 'search_read'
                    && $args === [[
                        ['employee_id', '=', 35],
                        ['start_datetime', '>=', '2026-06-01 00:00:00'],
                        ['start_datetime', '<=', '2026-06-30 23:59:59'],
                    ]]
                    && ($kwargs['order'] ?? null) === 'start_datetime asc';
            })
            ->andReturn([
                [
                    'id' => 71,
                    'name' => 'Morning Shift',
                    'start_datetime' => '2026-06-10 01:00:00',
                    'end_datetime' => '2026-06-10 09:00:00',
                    'role_id' => [9, 'Receptionist'],
                    'company_id' => [2, 'Clinic'],
                    'employee_id' => [35, 'Odoo Employee'],
                ],
                [
                    'id' => 72,
                    'name' => 'Afternoon Shift',
                    'start_datetime' => '2026-06-10 10:00:00',
                    'end_datetime' => '2026-06-10 15:00:00',
                    'role_id' => [9, 'Receptionist'],
                    'company_id' => [2, 'Clinic'],
                    'employee_id' => [35, 'Odoo Employee'],
                ],
            ]);

        $service = new OdooPlanningService($serviceAccount);
        $pageData = $service->getShiftPageData(
            new User(['odoo_employee_id' => 35]),
            Carbon::createFromFormat('Y-m', '2026-06', config('app.timezone')),
            Carbon::createFromFormat('Y-m-d', '2026-06-10', config('app.timezone'))
        );

        $this->assertCount(2, $pageData['shifts']);
        $this->assertSame('Morning Shift', $pageData['todayShift']['title']);
        $this->assertSame('2026-06-10', $pageData['selectedCalendarDateValue']);
        $this->assertCount(2, $pageData['selectedCalendarShifts']);
        $this->assertNotEmpty($pageData['shiftCalendar']);
    }
}
