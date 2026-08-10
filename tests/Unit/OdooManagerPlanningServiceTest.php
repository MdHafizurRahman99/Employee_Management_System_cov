<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooEmployeeScheduleEntryService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class OdooManagerPlanningServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_hidden_open_shifts_do_not_inflate_team_roster_day_totals(): void
    {
        $service = new OdooManagerPlanningService(Mockery::mock(OdooServiceAccount::class));
        $method = new \ReflectionMethod($service, 'buildWeeklyRoster');
        $method->setAccessible(true);
        $start = Carbon::parse('2026-08-17');
        $assignedShift = [
            'id' => 1,
            'employee_id' => 35,
            'employee' => 'Administrator',
            'company' => 'Clinic',
            'company_id' => 2,
            'date_value' => '2026-08-20',
            'duration_minutes' => 720,
            'is_published' => false,
        ];
        $hiddenOpenShift = [
            'id' => 2,
            'employee_id' => null,
            'employee' => 'Unassigned',
            'company' => 'Clinic',
            'company_id' => 2,
            'date_value' => '2026-08-20',
            'duration_minutes' => 37800,
            'is_published' => false,
        ];

        $roster = $method->invoke(
            $service,
            $start,
            [['id' => 35, 'name' => 'Administrator', 'company' => 'Clinic', 'company_id' => 2]],
            [$assignedShift, $hiddenOpenShift],
            [],
            $start,
            $start->copy()->endOfWeek()
        );
        $thursday = collect($roster['days'])->firstWhere('date_value', '2026-08-20');

        $this->assertSame(1, $thursday['shift_count']);
        $this->assertSame('12h', $thursday['hours_label']);
        $this->assertSame(1, $roster['summary']['shift_count']);
        $this->assertSame('12h', $roster['summary']['scheduled_hours']);
        $this->assertSame(1, $roster['summary']['open_shifts']);
    }

    public function test_it_builds_shift_creation_page_data_from_odoo(): void
    {
        Carbon::setTestNow('2026-06-08 09:00:00');

        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $this->allowWorkLocations($serviceAccount);

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
                    'resource_id' => [44, 'Resource'],
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'employee@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'planning.role'
                    && $method === 'search_read'
                    && $args === [[['active', '=', true]]]
                    && ($kwargs['order'] ?? null) === 'name asc';
            })
            ->andReturn([
                [
                    'id' => 9,
                    'name' => 'Front Desk',
                    'company_id' => [2, 'Clinic'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.company',
                'search_read',
                [[]],
                ['fields' => ['id', 'name'], 'order' => 'name asc']
            )
            ->andReturn([
                ['id' => 2, 'name' => 'Clinic'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'name' => ['type' => 'char'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'employee_id' => ['type' => 'many2one'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'planning.slot'
                    && $method === 'search_read'
                    && ($kwargs['limit'] ?? null) === 500
                    && ($kwargs['order'] ?? null) === 'start_datetime asc'
                    && $args === [[
                        ['start_datetime', '>=', '2026-06-01 00:00:00'],
                        ['start_datetime', '<=', '2026-06-30 23:59:59'],
                    ]];
            })
            ->andReturn([
                [
                    'id' => 71,
                    'name' => 'Front Desk - Odoo Employee',
                    'note' => 'Morning cover',
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'write_date' => '2026-06-08 08:00:00',
                    'role_id' => [9, 'Front Desk'],
                    'company_id' => [2, 'Clinic'],
                    'employee_id' => [35, 'Odoo Employee'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'holiday_status_id' => ['type' => 'many2one'],
                'state' => ['type' => 'selection'],
                'request_date_from' => ['type' => 'date'],
                'request_date_to' => ['type' => 'date'],
                'leave_type_request_unit' => ['type' => 'selection'],
                'number_of_hours' => ['type' => 'float'],
                'planning_start_datetime' => ['type' => 'datetime'],
                'planning_end_datetime' => ['type' => 'datetime'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.leave'
                    && $method === 'search_read'
                    && ($kwargs['limit'] ?? null) === 300
                    && $args === [[
                        ['employee_id', 'in', [35]],
                        ['state', 'in', ['confirm', 'validate1', 'validate']],
                        ['request_date_from', '<=', '2026-06-21'],
                        ['request_date_to', '>=', '2026-06-08'],
                    ]];
            })
            ->andReturn([]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee.weekly.availability', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'day_of_week' => ['type' => 'selection'],
                'availability_type' => ['type' => 'selection'],
                'is_full_day' => ['type' => 'boolean'],
                'start_time' => ['type' => 'float'],
                'end_time' => ['type' => 'float'],
                'time_range_display' => ['type' => 'char'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.employee.weekly.availability'
                    && $method === 'search_read'
                    && ($kwargs['limit'] ?? null) === 500
                    && $args === [[
                        ['employee_id', 'in', [35]],
                        ['availability_type', '=', 'unavailable'],
                    ]];
            })
            ->andReturn([]);

        $scheduleEntries = Mockery::mock(OdooEmployeeScheduleEntryService::class);
        $scheduleEntries->shouldReceive('getForManagerRange')
            ->once()
            ->withArgs(fn (Carbon $start, Carbon $end, array $employeeIds): bool =>
                $start->toDateString() === '2026-06-08'
                && $end->toDateString() === '2026-06-21'
                && $employeeIds === [35]
            )
            ->andThrow(new OdooException(
                'Schedule diary is not enabled in Odoo yet. Upgrade hr_employee_weekly_availability.'
            ));
        $service = new OdooManagerPlanningService($serviceAccount, null, $scheduleEntries);
        $pageData = $service->getShiftCreationPageData();

        $this->assertCount(1, $pageData['employees']);
        $this->assertSame('Odoo Employee', $pageData['employees'][0]['name']);
        $this->assertSame(44, $pageData['employees'][0]['resource_id']);

        $this->assertCount(1, $pageData['roles']);
        $this->assertSame('Front Desk', $pageData['roles'][0]['name']);

        $this->assertCount(1, $pageData['companies']);
        $this->assertSame('Clinic', $pageData['companies'][0]['name']);

        $this->assertCount(1, $pageData['recentShifts']);
        $this->assertSame('Odoo Employee', $pageData['recentShifts'][0]['employee']);
        $this->assertSame('09:00 AM - 05:00 PM', $pageData['recentShifts'][0]['time_label']);
        $this->assertSame(1, $pageData['weeklyRoster']['summary']['shift_count']);
        $this->assertSame('8h', $pageData['weeklyRoster']['summary']['scheduled_hours']);
        $this->assertSame('Odoo Employee', $pageData['weeklyRoster']['rows'][0]['employee']);
        $this->assertSame(1, $pageData['weeklyRoster']['rows'][0]['shift_count']);
        $this->assertSame('10-06-2026 (1)', $pageData['weeklyRoster']['summary']['busiest_day']);
        $this->assertSame('Front Desk', $pageData['weeklyRoster']['role_breakdown'][0]['name']);
        $this->assertSame('Front Desk', $pageData['weeklyRoster']['shift_templates'][0]['title']);
        $this->assertCount(14, $pageData['weeklyRoster']['days']);
        $this->assertSame('2026-06-21', $pageData['weeklyRoster']['week_end']->toDateString());
        $this->assertSame('2026-06-22', $pageData['weeklyRoster']['next_week_day']->toDateString());
        $this->assertSame(0, $pageData['employeeDiary']['count']);
        $this->assertSame(
            'Schedule diary is not enabled in Odoo yet. Upgrade hr_employee_weekly_availability.',
            $pageData['employeeDiaryError']
        );
    }

    public function test_it_creates_an_odoo_shift_when_no_conflict_exists(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $this->allowWorkLocations($serviceAccount);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'ems_work_location_id' => ['type' => 'many2one'],
                'allocated_hours' => ['type' => 'float'],
                'name' => ['type' => 'char'],
                'note' => ['type' => 'text'],
            ]);

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
            ->withArgs(fn (string $model, string $method): bool => $model === 'hr.employee' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Odoo Employee',
                    'resource_id' => [44, 'Resource'],
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'employee@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.role' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 9,
                    'name' => 'Front Desk',
                    'company_id' => [2, 'Clinic'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.company',
                'search_read',
                [[]],
                ['fields' => ['id', 'name'], 'order' => 'name asc']
            )
            ->andReturn([
                ['id' => 2, 'name' => 'Clinic'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'planning.slot',
                'search_count',
                [[
                    ['resource_id', '=', 44],
                    ['start_datetime', '<', '2026-06-10 17:00:00'],
                    ['end_datetime', '>', '2026-06-10 09:00:00'],
                ]]
            )
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'planning.slot',
                'create',
                [[
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'employee_id' => 35,
                    'resource_id' => 44,
                    'role_id' => 9,
                    'company_id' => 2,
                    'ems_work_location_id' => 7,
                    'allocated_hours' => 8.0,
                    'name' => 'Reception Coverage',
                    'note' => 'Morning cover',
                ]]
            )
            ->andReturn(81);

        $service = new OdooManagerPlanningService($serviceAccount);
        $slotId = $service->createShift([
            'employee_id' => 35,
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => 7,
            'shift_date' => '2026-06-10',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => 'Reception Coverage',
            'note' => 'Morning cover',
        ]);

        $this->assertSame(1, $slotId);
    }

    public function test_it_creates_a_shift_for_each_day_in_a_selected_date_range(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $this->allowWorkLocations($serviceAccount);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'ems_work_location_id' => ['type' => 'many2one'],
                'allocated_hours' => ['type' => 'float'],
                'name' => ['type' => 'char'],
                'note' => ['type' => 'text'],
            ]);

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
            ->withArgs(fn (string $model, string $method): bool => $model === 'hr.employee' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Odoo Employee',
                    'resource_id' => [44, 'Resource'],
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'employee@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.role' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 9,
                    'name' => 'Front Desk',
                    'company_id' => [2, 'Clinic'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.company',
                'search_read',
                [[]],
                ['fields' => ['id', 'name'], 'order' => 'name asc']
            )
            ->andReturn([
                ['id' => 2, 'name' => 'Clinic'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->times(3)
            ->with('planning.slot', 'search_count', Mockery::type('array'))
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->times(3)
            ->with('planning.slot', 'create', Mockery::type('array'))
            ->andReturn(81, 82, 83);

        $service = new OdooManagerPlanningService($serviceAccount);
        $createdCount = $service->createShift([
            'employee_id' => 35,
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => 7,
            'shift_date' => '2026-06-10',
            'shift_end_date' => '2026-06-12',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => 'Reception Coverage',
            'note' => 'Morning cover',
        ]);

        $this->assertSame(3, $createdCount);
    }

    public function test_it_creates_an_open_shift_without_an_employee(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $this->allowWorkLocations($serviceAccount);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'ems_work_location_id' => ['type' => 'many2one'],
                'allocated_hours' => ['type' => 'float'],
                'name' => ['type' => 'char'],
                'note' => ['type' => 'text'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.role' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 9,
                    'name' => 'Front Desk',
                    'company_id' => [2, 'Clinic'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.company',
                'search_read',
                [[]],
                ['fields' => ['id', 'name'], 'order' => 'name asc']
            )
            ->andReturn([
                ['id' => 2, 'name' => 'Clinic'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'planning.slot',
                'create',
                [[
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'role_id' => 9,
                    'company_id' => 2,
                    'ems_work_location_id' => 7,
                    'allocated_hours' => 8.0,
                    'name' => 'Front Desk - Open Shift',
                    'note' => 'Open reception coverage',
                ]]
            )
            ->andReturn(91);

        $service = new OdooManagerPlanningService($serviceAccount);
        $slotCount = $service->createShift([
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => 7,
            'shift_date' => '2026-06-10',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => '',
            'note' => 'Open reception coverage',
        ]);

        $this->assertSame(1, $slotCount);
    }

    public function test_company_location_fallback_allows_new_and_copied_shifts_without_a_work_location(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'ems_work_location_id' => ['type' => 'many2one'],
                'name' => ['type' => 'char'],
                'note' => ['type' => 'text'],
            ]);
        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);
        $serviceAccount->shouldReceive('executeKw')
            ->twice()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.role' && $method === 'search_read')
            ->andReturn([['id' => 9, 'name' => 'Front Desk', 'company_id' => [2, 'Clinic'], 'active' => true]]);
        $serviceAccount->shouldReceive('executeKw')
            ->twice()
            ->with('res.company', 'search_read', [[]], ['fields' => ['id', 'name'], 'order' => 'name asc'])
            ->andReturn([['id' => 2, 'name' => 'Clinic']]);
        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'hr.work.location' && $method === 'search_read')
            ->andReturn([]);
        $serviceAccount->shouldReceive('executeKw')
            ->twice()
            ->withArgs(function (string $model, string $method, array $args): bool {
                $payload = $args[0] ?? [];

                return $model === 'planning.slot'
                    && $method === 'create'
                    && ($payload['ems_work_location_id'] ?? null) === false
                    && ($payload['role_id'] ?? null) === 9
                    && ($payload['company_id'] ?? null) === 2
                    && ($payload['name'] ?? null) === 'Reception'
                    && ($payload['note'] ?? null) === 'Copied exactly';
            })
            ->andReturn(92, 93);

        $service = new OdooManagerPlanningService($serviceAccount);
        $createdIds = $service->createShiftsReturningIds([
            'employee_id' => null,
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => null,
            'shift_date' => '2026-06-17',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => 'Reception',
            'note' => 'Copied exactly',
            '_copy_existing_shift' => true,
        ]);

        $this->assertSame([92], $createdIds);

        $newShiftIds = $service->createShiftsReturningIds([
            'employee_id' => null,
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => null,
            'shift_date' => '2026-06-18',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => 'Reception',
            'note' => 'Copied exactly',
        ]);

        $this->assertSame([93], $newShiftIds);
    }

    public function test_it_rejects_conflicting_shifts_before_creating_the_slot(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $this->allowWorkLocations($serviceAccount);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'ems_work_location_id' => ['type' => 'many2one'],
            ]);

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
            ->withArgs(fn (string $model, string $method): bool => $model === 'hr.employee' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Odoo Employee',
                    'resource_id' => [44, 'Resource'],
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'employee@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.role' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 9,
                    'name' => 'Front Desk',
                    'company_id' => [2, 'Clinic'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.company',
                'search_read',
                [[]],
                ['fields' => ['id', 'name'], 'order' => 'name asc']
            )
            ->andReturn([
                ['id' => 2, 'name' => 'Clinic'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'search_count', Mockery::type('array'))
            ->andReturn(1);

        $service = new OdooManagerPlanningService($serviceAccount);

        $this->expectException(OdooException::class);
        $this->expectExceptionMessage('This employee already has a shift that overlaps with the selected time.');

        $service->createShift([
            'employee_id' => 35,
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => 7,
            'shift_date' => '2026-06-10',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => '',
            'note' => '',
        ]);
    }

    public function test_it_updates_an_odoo_shift_when_the_revision_matches(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $this->allowWorkLocations($serviceAccount);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'ems_work_location_id' => ['type' => 'many2one'],
                'allocated_hours' => ['type' => 'float'],
                'name' => ['type' => 'char'],
                'note' => ['type' => 'text'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'planning.slot'
                    && $method === 'search_read'
                    && $args === [[['id', '=', 71]]]
                    && ($kwargs['limit'] ?? null) === 1;
            })
            ->andReturn([
                [
                    'id' => 71,
                    'name' => 'Front Desk - Odoo Employee',
                    'note' => 'Old note',
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'write_date' => '2026-06-08 08:00:00',
                    'role_id' => [9, 'Front Desk'],
                    'company_id' => [2, 'Clinic'],
                    'employee_id' => [35, 'Odoo Employee'],
                ],
            ]);

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
            ->withArgs(fn (string $model, string $method): bool => $model === 'hr.employee' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Odoo Employee',
                    'resource_id' => [44, 'Resource'],
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'employee@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.role' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 9,
                    'name' => 'Front Desk',
                    'company_id' => [2, 'Clinic'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.company',
                'search_read',
                [[]],
                ['fields' => ['id', 'name'], 'order' => 'name asc']
            )
            ->andReturn([
                ['id' => 2, 'name' => 'Clinic'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'planning.slot',
                'search_count',
                [[
                    ['resource_id', '=', 44],
                    ['start_datetime', '<', '2026-06-10 18:00:00'],
                    ['end_datetime', '>', '2026-06-10 10:00:00'],
                    ['id', '!=', 71],
                ]]
            )
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'planning.slot',
                'write',
                [[71], [
                    'start_datetime' => '2026-06-10 10:00:00',
                    'end_datetime' => '2026-06-10 18:00:00',
                    'employee_id' => 35,
                    'resource_id' => 44,
                    'role_id' => 9,
                    'company_id' => 2,
                    'ems_work_location_id' => 7,
                    'allocated_hours' => 8.0,
                    'name' => 'Updated Shift',
                    'note' => 'Updated note',
                ]]
            )
            ->andReturn(true);

        $service = new OdooManagerPlanningService($serviceAccount);

        $service->updateShift(71, [
            'employee_id' => 35,
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => 7,
            'shift_date' => '2026-06-10',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'title' => 'Updated Shift',
            'note' => 'Updated note',
            'last_known_write_date' => '2026-06-08 08:00:00',
        ]);

        $this->assertTrue(true);
    }

    public function test_it_rejects_stale_shift_updates(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.slot' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 71,
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'write_date' => '2026-06-08 09:30:00',
                    'role_id' => [9, 'Front Desk'],
                    'company_id' => [2, 'Clinic'],
                    'employee_id' => [35, 'Odoo Employee'],
                ],
            ]);

        $service = new OdooManagerPlanningService($serviceAccount);

        $this->expectException(OdooException::class);
        $this->expectExceptionMessage('This shift was updated by someone else. Please reload the page before trying again.');

        $service->updateShift(71, [
            'employee_id' => 35,
            'role_id' => 9,
            'company_id' => 2,
            'shift_date' => '2026-06-10',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'title' => 'Updated Shift',
            'note' => 'Updated note',
            'last_known_write_date' => '2026-06-08 08:00:00',
        ]);
    }

    public function test_it_can_turn_a_shift_into_an_open_shift(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);
        $this->allowWorkLocations($serviceAccount);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
                'ems_work_location_id' => ['type' => 'many2one'],
                'allocated_hours' => ['type' => 'float'],
                'name' => ['type' => 'char'],
                'note' => ['type' => 'text'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'planning.slot'
                    && $method === 'search_read'
                    && $args === [[['id', '=', 71]]]
                    && ($kwargs['limit'] ?? null) === 1;
            })
            ->andReturn([
                [
                    'id' => 71,
                    'name' => 'Front Desk - Odoo Employee',
                    'note' => 'Existing note',
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'write_date' => '2026-06-08 08:00:00',
                    'role_id' => [9, 'Front Desk'],
                    'company_id' => [2, 'Clinic'],
                    'employee_id' => [35, 'Odoo Employee'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.role', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.role' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 9,
                    'name' => 'Front Desk',
                    'company_id' => [2, 'Clinic'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.company',
                'search_read',
                [[]],
                ['fields' => ['id', 'name'], 'order' => 'name asc']
            )
            ->andReturn([
                ['id' => 2, 'name' => 'Clinic'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'planning.slot',
                'write',
                [[71], [
                    'start_datetime' => '2026-06-11 09:00:00',
                    'end_datetime' => '2026-06-11 17:00:00',
                    'employee_id' => false,
                    'resource_id' => false,
                    'role_id' => 9,
                    'company_id' => 2,
                    'ems_work_location_id' => 7,
                    'allocated_hours' => 8.0,
                    'name' => 'Front Desk - Open Shift',
                    'note' => 'Needs coverage',
                ]]
            )
            ->andReturn(true);

        $service = new OdooManagerPlanningService($serviceAccount);
        $service->updateShift(71, [
            'role_id' => 9,
            'company_id' => 2,
            'work_location_id' => 7,
            'shift_date' => '2026-06-11',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => '',
            'note' => 'Needs coverage',
            'last_known_write_date' => '2026-06-08 08:00:00',
        ]);

        $this->assertTrue(true);
    }

    public function test_it_deletes_an_odoo_shift_when_the_revision_matches(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'start_datetime' => ['type' => 'datetime'],
                'end_datetime' => ['type' => 'datetime'],
                'employee_id' => ['type' => 'many2one'],
                'role_id' => ['type' => 'many2one'],
                'company_id' => ['type' => 'many2one'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'planning.slot' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 71,
                    'start_datetime' => '2026-06-10 09:00:00',
                    'end_datetime' => '2026-06-10 17:00:00',
                    'write_date' => '2026-06-08 08:00:00',
                    'role_id' => [9, 'Front Desk'],
                    'company_id' => [2, 'Clinic'],
                    'employee_id' => [35, 'Odoo Employee'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'unlink', [[71]])
            ->andReturn(true);

        $service = new OdooManagerPlanningService($serviceAccount);
        $service->deleteShift(71, '2026-06-08 08:00:00');

        $this->assertTrue(true);
    }

    private function allowWorkLocations(OdooServiceAccount $serviceAccount): void
    {
        $serviceAccount->shouldReceive('executeKw')
            ->zeroOrMoreTimes()
            ->with(
                'hr.work.location',
                'search_read',
                [[['active', '=', true]]],
                ['fields' => ['id', 'name', 'company_id', 'address_id', 'location_type', 'location_number'], 'order' => 'company_id asc, name asc']
            )
            ->andReturn([[
                'id' => 7,
                'name' => 'Main Clinic',
                'company_id' => [2, 'Clinic'],
                'address_id' => [17, '1 High Street'],
                'location_type' => 'office',
                'location_number' => 'MAIN',
            ]]);
    }
}
