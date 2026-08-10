<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerShiftController;
use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Scheduling\SchedulePublishService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerShiftControllerTest extends TestCase
{
    public function test_it_returns_the_manager_shift_creation_view(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getShiftCreationPageDataForMonth')
                ->once()
                ->andReturn([
                    'employees' => [['id' => 35, 'name' => 'Odoo Employee', 'company' => 'Clinic']],
                    'roles' => [['id' => 9, 'name' => 'Front Desk', 'company' => 'Clinic']],
                    'companies' => [['id' => 2, 'name' => 'Clinic']],
                    'workLocations' => [['id' => 7, 'name' => 'Main Clinic', 'company_id' => 2, 'address' => '1 High Street']],
                    'recentShifts' => [['id' => 71, 'employee' => 'Odoo Employee']],
                    'shiftCalendar' => [],
                    'selectedCalendarDate' => Carbon::parse('2026-06-01'),
                    'selectedCalendarDateLabel' => 'Mon, 01 Jun 2026',
                    'selectedCalendarDateValue' => '2026-06-01',
                    'selectedCalendarShifts' => [],
                    'weeklyRoster' => [
                        'week_start' => Carbon::parse('2026-06-01'),
                        'week_end' => Carbon::parse('2026-06-07'),
                        'week_label' => 'Jun 1 - 7, 2026',
                        'previous_week_day' => Carbon::parse('2026-05-25'),
                        'next_week_day' => Carbon::parse('2026-06-08'),
                        'days' => [],
                        'rows' => [],
                        'summary' => [
                            'shift_count' => 0,
                            'scheduled_hours' => '0h',
                            'people_scheduled' => 0,
                            'open_shifts' => 0,
                            'coverage_days' => 0,
                        ],
                    ],
                ]);
        });

        $request = Request::create('/manager/shifts/create', 'GET', ['month' => '2026-06', 'day' => '2026-06-01']);

        $view = (new ManagerShiftController())->create($request, app(OdooManagerPlanningService::class));

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-shifts.create', $view->getName());
        $this->assertSame('2026-06', $data['selectedMonth']->format('Y-m'));
        $this->assertCount(1, $data['employees']);
        $this->assertCount(1, $data['roles']);
        $this->assertCount(1, $data['companies']);
        $this->assertCount(1, $data['recentShifts']);
        $this->assertSame('Jun 1 - 7, 2026', $data['weeklyRoster']['week_label']);
    }

    public function test_it_redirects_with_success_after_creating_a_shift(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createShiftsReturningIds')
                ->once()
                ->withArgs(function (array $payload): bool {
                    return $payload['employee_id'] === '35'
                        && $payload['role_id'] === '9'
                        && $payload['company_id'] === '2'
                        && $payload['shift_date'] === '2026-06-10'
                        && $payload['shift_end_date'] === '2026-06-12';
                })
                ->andReturn([101,102,103]);
        });

        $request = Request::create('/manager/shifts', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-10',
            'employee_id' => '35',
            'role_id' => '9',
            'company_id' => '2',
            'work_location_id' => '7',
            'shift_date' => '2026-06-10',
            'shift_end_date' => '2026-06-12',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => 'Reception Coverage',
            'note' => 'Morning cover',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->store($request, app(OdooManagerPlanningService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-10']), $response->getTargetUrl());
    }

    public function test_it_redirects_with_errors_when_shift_creation_fails(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createShiftsReturningIds')
                ->once()
                ->andThrow(new OdooException('This employee already has a shift that overlaps with the selected time.'));
        });

        $request = Request::create('/manager/shifts', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-10',
            'employee_id' => '35',
            'role_id' => '9',
            'company_id' => '2',
            'work_location_id' => '7',
            'shift_date' => '2026-06-10',
            'shift_end_date' => '2026-06-12',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'title' => 'Reception Coverage',
            'note' => 'Morning cover',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->store($request, app(OdooManagerPlanningService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-10']), $response->getTargetUrl());
    }

    public function test_it_redirects_with_success_after_updating_a_shift(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('updateShift')
                ->once()
                ->withArgs(function (int $shiftId, array $payload): bool {
                    return $shiftId === 71
                        && $payload['employee_id'] === '35'
                        && $payload['last_known_write_date'] === '2026-06-08 08:00:00';
                });
        });

        $request = Request::create('/manager/shifts/71/update', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-10',
            'employee_id' => '35',
            'role_id' => '9',
            'company_id' => '2',
            'work_location_id' => '7',
            'shift_date' => '2026-06-10',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'title' => 'Updated Shift',
            'note' => 'Updated note',
            'last_known_write_date' => '2026-06-08 08:00:00',
            'editing_shift_id' => '71',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->update($request, app(OdooManagerPlanningService::class), 71);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-10']), $response->getTargetUrl());
    }

    public function test_it_redirects_with_success_after_deleting_a_shift(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteShift')
                ->once()
                ->with(71, '2026-06-08 08:00:00');
        });

        $request = Request::create('/manager/shifts/71/delete', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-10',
            'last_known_write_date' => '2026-06-08 08:00:00',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->destroy($request, app(OdooManagerPlanningService::class), 71);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-10']), $response->getTargetUrl());
    }

    public function test_it_redirects_with_success_after_bulk_deleting_shifts(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteShift')
                ->once()
                ->with(71, '2026-06-08 08:00:00');
            $mock->shouldReceive('deleteShift')
                ->once()
                ->with(72, '2026-06-08 09:00:00');
        });

        $request = Request::create('/manager/shifts/bulk-delete', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-10',
            'shifts' => [
                ['id' => 71, 'last_known_write_date' => '2026-06-08 08:00:00'],
                ['id' => 72, 'last_known_write_date' => '2026-06-08 09:00:00'],
            ],
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->bulkDelete($request, app(OdooManagerPlanningService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-10']), $response->getTargetUrl());
    }

    public function test_it_redirects_with_success_after_bulk_converting_shifts_to_open(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('updateShift')
                ->once()
                ->withArgs(function (int $shiftId, array $payload): bool {
                    return $shiftId === 71
                        && $payload['employee_id'] === null
                        && $payload['role_id'] === 9
                        && $payload['company_id'] === 2
                        && $payload['shift_date'] === '2026-06-10'
                        && $payload['start_time'] === '09:00'
                        && $payload['end_time'] === '17:00'
                        && $payload['last_known_write_date'] === '2026-06-08 08:00:00';
                });
            $mock->shouldReceive('updateShift')
                ->once()
                ->withArgs(function (int $shiftId, array $payload): bool {
                    return $shiftId === 72
                        && $payload['employee_id'] === null
                        && $payload['role_id'] === 9
                        && $payload['company_id'] === 2
                        && $payload['shift_date'] === '2026-06-11'
                        && $payload['start_time'] === '10:00'
                        && $payload['end_time'] === '18:00'
                        && $payload['last_known_write_date'] === '2026-06-08 09:00:00';
                });
        });

        $request = Request::create('/manager/shifts/bulk-open', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-10',
            'shifts' => [
                [
                    'id' => 71,
                    'role_id' => 9,
                    'company_id' => 2,
                    'work_location_id' => 7,
                    'shift_date' => '2026-06-10',
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'title' => 'Reception Coverage',
                    'note' => 'Morning cover',
                    'last_known_write_date' => '2026-06-08 08:00:00',
                ],
                [
                    'id' => 72,
                    'role_id' => 9,
                    'company_id' => 2,
                    'work_location_id' => 7,
                    'shift_date' => '2026-06-11',
                    'start_time' => '10:00',
                    'end_time' => '18:00',
                    'title' => 'Late Cover',
                    'note' => 'Second day',
                    'last_known_write_date' => '2026-06-08 09:00:00',
                ],
            ],
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->bulkOpen($request, app(OdooManagerPlanningService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-10']), $response->getTargetUrl());
    }

    public function test_it_redirects_with_success_after_publishing_the_visible_week(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getWeeklyShiftsForDate')
                ->once()
                ->withArgs(fn (Carbon $date): bool => $date->toDateString() === '2026-06-10')
                ->andReturn([
                    ['id' => 71, 'write_date_value' => '2026-06-08 08:00:00'],
                    ['id' => 72, 'write_date_value' => '2026-06-08 09:00:00'],
                ]);
        });

        $this->mock(SchedulePublishService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('publishShifts')
                ->once()
                ->withArgs(function (array $shifts, ?User $publisher, bool $requiresConfirmation, string $notificationMode): bool {
                    return count($shifts) === 2
                        && $publisher?->email === 'manager@example.com'
                        && $requiresConfirmation === true
                        && $notificationMode === 'notify_email_app';
                })
                ->andReturn(2);
        });

        $request = Request::create('/manager/shifts/publish-week', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-10',
            'requires_confirmation' => '1',
            'notification_mode' => 'notify_email_app',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => new User(['email' => 'manager@example.com']));

        $controller = new ManagerShiftController();
        $response = $controller->publishWeek(
            $request,
            app(OdooManagerPlanningService::class),
            app(SchedulePublishService::class)
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-10']), $response->getTargetUrl());
    }

    public function test_it_copies_a_schedule_day_to_a_new_date(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getWeeklyShiftsForDate')->once()->andReturn([[
                'employee_id' => 35, 'role_id' => 9, 'company_id' => 2, 'work_location_id' => 7,
                'shift_date_value' => '2026-06-10', 'start_time_value' => '09:00',
                'end_time_value' => '17:00', 'title_value' => 'Reception', 'note' => 'Cover',
            ]]);
            $mock->shouldReceive('createShiftsReturningIds')->once()->withArgs(fn (array $data): bool =>
                $data['shift_date'] === '2026-06-17'
                && $data['employee_id'] === 35
                && $data['work_location_id'] === 7
                && $data['role_id'] === 9
                && $data['company_id'] === 2
                && $data['title'] === 'Reception'
                && $data['note'] === 'Cover'
                && $data['_copy_existing_shift'] === true
            )->andReturn([201]);
        });

        $request = Request::create('/manager/shifts/copy-period', 'POST', [
            'month' => '2026-06', 'day' => '2026-06-10', 'source_date' => '2026-06-10',
            'target_date' => '2026-06-17', 'period' => 'day',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->copyPeriod($request, app(OdooManagerPlanningService::class));

        $this->assertSame(route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-17']), $response->getTargetUrl());
    }

    public function test_it_publishes_every_shift_in_the_selected_full_week_range(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getShiftsForRange')->once()->withArgs(
                fn (Carbon $start, Carbon $end): bool =>
                    $start->toDateString() === '2026-06-01' && $end->toDateString() === '2026-06-28'
            )->andReturn([['id' => 1], ['id' => 2], ['id' => 3]]);
        });
        $this->mock(SchedulePublishService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('publishShifts')->once()->withArgs(
                fn (array $shifts): bool => count($shifts) === 3
            )->andReturn(3);
        });

        $request = Request::create('/manager/shifts/publish-week', 'POST', [
            'month' => '2026-06', 'day' => '2026-06-10',
            'start_date' => '2026-06-03', 'end_date' => '2026-06-24',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->publishWeek(
            $request,
            app(OdooManagerPlanningService::class),
            app(SchedulePublishService::class)
        );

        $this->assertSame(route('manager.shifts.create', [
            'month' => '2026-06', 'day' => '2026-06-10',
            'start_date' => '2026-06-03', 'end_date' => '2026-06-24',
        ]), $response->getTargetUrl());
    }

    public function test_it_copies_the_selected_visible_range_and_preserves_its_length(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getShiftsForRange')->once()->withArgs(
                fn (Carbon $start, Carbon $end): bool =>
                    $start->toDateString() === '2026-06-01' && $end->toDateString() === '2026-06-28'
            )->andReturn([[
                'employee_id' => 35, 'role_id' => 9, 'company_id' => 2, 'work_location_id' => 7,
                'shift_date_value' => '2026-06-28', 'start_time_value' => '09:00',
                'end_time_value' => '17:00', 'title_value' => 'Last day', 'note' => null,
            ]]);
            $mock->shouldReceive('createShiftsReturningIds')->once()->withArgs(
                fn (array $data): bool => $data['shift_date'] === '2026-07-26'
            )->andReturn([401]);
        });

        $request = Request::create('/manager/shifts/copy-period', 'POST', [
            'month' => '2026-06', 'day' => '2026-06-01',
            'start_date' => '2026-06-01', 'end_date' => '2026-06-28',
            'source_date' => '2026-06-01', 'target_date' => '2026-06-29', 'period' => 'range',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->copyPeriod($request, app(OdooManagerPlanningService::class));

        $this->assertSame(route('manager.shifts.create', [
            'month' => '2026-06', 'day' => '2026-06-29',
            'start_date' => '2026-06-29', 'end_date' => '2026-07-26',
        ]), $response->getTargetUrl());
    }

    public function test_it_copies_the_complete_visible_two_week_period(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getVisiblePeriodShiftsForDate')
                ->once()
                ->withArgs(fn (Carbon $date): bool => $date->toDateString() === '2026-06-01')
                ->andReturn([
                    [
                        'employee_id' => 35, 'role_id' => 9, 'company_id' => 2, 'work_location_id' => 7,
                        'shift_date_value' => '2026-06-01', 'start_time_value' => '09:00',
                        'end_time_value' => '17:00', 'title_value' => 'Week one', 'note' => null,
                    ],
                    [
                        'employee_id' => 36, 'role_id' => 9, 'company_id' => 2, 'work_location_id' => 7,
                        'shift_date_value' => '2026-06-14', 'start_time_value' => '10:00',
                        'end_time_value' => '18:00', 'title_value' => 'Day fourteen', 'note' => null,
                    ],
                ]);
            $mock->shouldReceive('createShiftsReturningIds')
                ->twice()
                ->withArgs(fn (array $data): bool => in_array($data['shift_date'], ['2026-06-15', '2026-06-28'], true))
                ->andReturn([301], [302]);
        });

        $request = Request::create('/manager/shifts/copy-period', 'POST', [
            'month' => '2026-06',
            'day' => '2026-06-01',
            'source_date' => '2026-06-01',
            'target_date' => '2026-06-15',
            'period' => 'two_weeks',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = (new ManagerShiftController())->copyPeriod(
            $request,
            app(OdooManagerPlanningService::class)
        );

        $this->assertSame(
            route('manager.shifts.create', ['month' => '2026-06', 'day' => '2026-06-15']),
            $response->getTargetUrl()
        );
        $this->assertSame('2 shift(s) were copied successfully.', $request->session()->get('success'));
    }

    public function test_it_builds_the_manager_confirmation_dashboard_with_status_filtering(): void
    {
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getWeeklyShiftsForDate')->once()->andReturn([
                ['id' => 71, 'employee' => 'Alice', 'role' => 'Reception', 'company' => 'Clinic', 'requires_confirmation' => true, 'confirmation_status' => 'accepted', 'publish_state' => 'published'],
                ['id' => 72, 'employee' => 'Bob', 'role' => 'Nurse', 'company' => 'Clinic', 'requires_confirmation' => true, 'confirmation_status' => 'declined', 'confirmation_note' => 'Training', 'publish_state' => 'published'],
                ['id' => 73, 'employee' => 'Cara', 'role' => 'Nurse', 'company' => 'Clinic', 'requires_confirmation' => false],
            ]);
        });

        $request = Request::create('/manager/shifts/confirmations', 'GET', [
            'month' => '2026-06', 'day' => '2026-06-10', 'status' => 'declined',
        ]);
        $view = (new ManagerShiftController())->confirmations($request, app(OdooManagerPlanningService::class));
        $data = $view->getData();

        $this->assertSame('admin.manager-shifts.confirmations', $view->getName());
        $this->assertSame(2, $data['summary']['all']);
        $this->assertSame(1, $data['summary']['accepted']);
        $this->assertSame(1, $data['summary']['declined']);
        $this->assertCount(1, $data['filteredShifts']);
        $this->assertSame('Bob', $data['filteredShifts'][0]['employee']);
    }

    public function test_manager_can_send_a_confirmation_reminder(): void
    {
        $manager = new User(['name' => 'Manager']);
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getWeeklyShiftsForDate')->once()->andReturn([['id' => 71, 'employee_id' => 35]]);
        });
        $this->mock(SchedulePublishService::class, function (MockInterface $mock) use ($manager): void {
            $mock->shouldReceive('sendConfirmationReminder')->once()
                ->withArgs(fn (array $shift, User $user): bool => $shift['id'] === 71 && $user === $manager)
                ->andReturn(1);
        });
        $request = Request::create('/manager/shifts/71/remind', 'POST', ['month' => '2026-07', 'day' => '2026-07-13']);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => $manager);

        $response = (new ManagerShiftController())->remindConfirmation(
            $request, app(OdooManagerPlanningService::class), app(SchedulePublishService::class), 71
        );

        $this->assertSame(route('manager.shifts.confirmations', ['month' => '2026-07', 'day' => '2026-07-13']), $response->getTargetUrl());
    }
}
