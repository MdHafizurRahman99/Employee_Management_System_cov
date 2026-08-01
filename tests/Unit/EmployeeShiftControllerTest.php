<?php

namespace Tests\Unit;

use App\Http\Controllers\EmployeeShiftController;
use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooPlanningService;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Scheduling\SchedulePublishService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Mockery\MockInterface;
use Tests\TestCase;

class EmployeeShiftControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_shift_calendar_data_for_the_selected_month(): void
    {
        Carbon::setTestNow('2026-06-10 09:00:00');

        $this->mock(OdooPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getShiftPageData')
                ->once()
                ->andReturn([
                    'shifts' => [[
                        'id' => 91,
                        'date_value' => '2026-06-10',
                        'date_label' => 'Wed, 10 Jun 2026',
                        'title' => 'Morning Shift',
                        'start_label' => '09:00 AM',
                        'end_label' => '05:00 PM',
                        'role' => 'Receptionist',
                        'company' => 'Clinic',
                        'start_at' => Carbon::parse('2026-06-10 09:00:00'),
                        'end_at' => Carbon::parse('2026-06-10 17:00:00'),
                        'is_today' => true,
                    ]],
                    'todayShift' => [
                        'id' => 91,
                        'title' => 'Morning Shift',
                        'date_value' => '2026-06-10',
                        'date_label' => 'Wed, 10 Jun 2026',
                        'start_label' => '09:00 AM',
                        'end_label' => '05:00 PM',
                        'role' => 'Receptionist',
                        'company' => 'Clinic',
                        'start_at' => Carbon::parse('2026-06-10 09:00:00'),
                        'end_at' => Carbon::parse('2026-06-10 17:00:00'),
                    ],
                    'shiftCalendar' => [],
                    'selectedCalendarDate' => Carbon::parse('2026-06-10'),
                    'selectedCalendarDateLabel' => 'Wed, 10 Jun 2026',
                    'selectedCalendarDateValue' => '2026-06-10',
                    'selectedCalendarShifts' => [[
                        'id' => 91,
                        'title' => 'Morning Shift',
                        'date_value' => '2026-06-10',
                        'date_label' => 'Wed, 10 Jun 2026',
                        'start_label' => '09:00 AM',
                        'end_label' => '05:00 PM',
                        'role' => 'Receptionist',
                        'company' => 'Clinic',
                        'start_at' => Carbon::parse('2026-06-10 09:00:00'),
                        'end_at' => Carbon::parse('2026-06-10 17:00:00'),
                    ]],
                ]);
        });

        $request = Request::create('/employee/shifts', 'GET', ['month' => '2026-06', 'day' => '2026-06-10']);
        $request->setUserResolver(fn (): User => new User(['odoo_employee_id' => 35, 'odoo_resource_id' => 44]));

        $view = (new EmployeeShiftController())->index($request, app(OdooPlanningService::class));
        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.employee-shifts.index', $view->getName());
        $this->assertSame('2026-06', $data['selectedMonth']->format('Y-m'));
        $this->assertSame('Morning Shift', $data['todayShift']['title']);
        $this->assertSame('2026-06-10', $data['selectedCalendarDateValue']);
        $this->assertCount(1, $data['selectedCalendarShifts']);
        $this->assertTrue($data['hasLeaveIdentity']);
        $this->assertTrue($data['shifts'][0]['can_request_unavailability']);
        $this->assertStringContainsString(route('employee.leave.index', [], false), $data['shifts'][0]['request_unavailability_url']);
        $this->assertStringContainsString('source=shift', $data['shifts'][0]['request_unavailability_url']);
    }

    public function test_it_exposes_odoo_shift_errors_without_throwing(): void
    {
        Carbon::setTestNow('2026-06-10 09:00:00');

        $this->mock(OdooPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getShiftPageData')
                ->once()
                ->andThrow(new OdooException('Shift data is temporarily unavailable.'));
        });

        $request = Request::create('/employee/shifts', 'GET', ['month' => '2026-06']);
        $request->setUserResolver(fn (): User => new User(['odoo_employee_id' => 35, 'odoo_resource_id' => 44]));

        $view = (new EmployeeShiftController())->index($request, app(OdooPlanningService::class));
        $data = $view->getData();

        $this->assertSame('Shift data is temporarily unavailable.', $data['odooShiftError']);
        $this->assertSame([], $data['shifts']);
    }

    public function test_employee_can_accept_an_assigned_shift(): void
    {
        $employee = new User(['email' => 'employee@example.com', 'odoo_employee_id' => 35]);
        $this->mock(OdooPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getShiftsForMonth')->once()->andReturn([['id' => 91]]);
        });
        $this->mock(SchedulePublishService::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('respondToShift')->once()->with(91, $employee, 'accepted', null);
        });

        $request = Request::create('/employee/shifts/91/respond', 'POST', ['month' => '2026-06', 'status' => 'accepted']);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => $employee);

        $response = (new EmployeeShiftController())->respond($request, app(OdooPlanningService::class), app(SchedulePublishService::class), 91);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.shifts.index', ['month' => '2026-06']), $response->getTargetUrl());
    }

    public function test_it_lists_eligible_open_shifts_for_the_selected_week(): void
    {
        $employee = new User(['odoo_employee_id' => 35]);
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getOpenShiftsForEmployee')->once()->andReturn([[
                'id' => 77, 'role' => 'Reception', 'company' => 'Clinic',
                'date_label' => 'Wed, 17 Jun 2026', 'time_label' => '09:00 AM - 05:00 PM',
            ]]);
        });
        $request = Request::create('/employee/open-shifts', 'GET', ['day' => '2026-06-17']);
        $request->setUserResolver(fn (): User => $employee);

        $view = (new EmployeeShiftController())->openShifts($request, app(OdooManagerPlanningService::class));

        $this->assertSame('admin.employee-shifts.open', $view->getName());
        $this->assertCount(1, $view->getData()['openShifts']);
        $this->assertSame('2026-06-17', $view->getData()['selectedDay']->toDateString());
    }

    public function test_employee_can_claim_an_open_shift(): void
    {
        $employee = new User(['odoo_employee_id' => 35]);
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('claimOpenShift')->once()->with($employee, 77, '2026-06-12 08:00:00');
        });
        $this->mock(SchedulePublishService::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('recordOpenShiftClaim')->once()->with(77, $employee);
        });
        $request = Request::create('/employee/open-shifts/77/claim', 'POST', [
            'day' => '2026-06-17', 'last_known_write_date' => '2026-06-12 08:00:00',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => $employee);

        $response = (new EmployeeShiftController())->claimOpenShift(
            $request, app(OdooManagerPlanningService::class), app(SchedulePublishService::class), 77
        );

        $this->assertSame(route('employee.open-shifts.index', ['day' => '2026-06-17']), $response->getTargetUrl());
    }

    public function test_a_stale_open_shift_claim_returns_a_visible_error(): void
    {
        $employee = new User(['odoo_employee_id' => 35]);
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('claimOpenShift')->once()->andThrow(new OdooException('This open shift has already been claimed or is no longer available.'));
        });
        $this->mock(SchedulePublishService::class, fn (MockInterface $mock) => null);
        $request = Request::create('/employee/open-shifts/77/claim', 'POST', [
            'day' => '2026-06-17', 'last_known_write_date' => '2026-06-12 08:00:00',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => $employee);

        $response = (new EmployeeShiftController())->claimOpenShift(
            $request, app(OdooManagerPlanningService::class), app(SchedulePublishService::class), 77
        );

        $this->assertTrue($response->getSession()->get('errors')->has('open_shift'));
    }
}
