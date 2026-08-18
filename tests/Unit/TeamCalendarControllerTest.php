<?php

namespace Tests\Unit;

use App\Http\Controllers\TeamCalendarController;
use App\Models\User;
use App\Services\Odoo\OdooLeaveService;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Odoo\OdooScheduleRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class TeamCalendarControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_builds_a_shared_calendar_with_shifts_approved_leave_and_birthdays(): void
    {
        Carbon::setTestNow('2026-06-10 09:00:00');
        $this->mock(OdooManagerPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getTeamCalendarDataForRange')->once()->andReturn([
                'employees' => [
                    ['id' => 35, 'name' => 'Alex Morgan', 'company_id' => 2, 'company' => 'Clinic'],
                    ['id' => 36, 'name' => 'Sam Lee', 'company_id' => 2, 'company' => 'Clinic'],
                ],
                'shifts' => [[
                    'employee_id' => 35, 'employee' => 'Alex Morgan', 'company_id' => 2, 'company' => 'Clinic',
                    'role' => 'Reception', 'work_location' => 'Front Office', 'shift_date_value' => '2026-06-12',
                    'time_label' => '09:00 AM - 05:00 PM', 'publish_state' => 'published',
                ]],
                'approved_leave' => [[
                    'employee_id' => 36, 'employee' => 'Sam Lee', 'date_value' => '2026-06-12',
                    'label' => 'Private Leave Type', 'time_label' => 'Full day', 'kind' => 'leave-approved',
                ], [
                    'employee_id' => 36, 'employee' => 'Sam Lee', 'date_value' => '2026-06-13',
                    'label' => 'Private Leave Type', 'time_label' => '24.00h', 'kind' => 'leave-approved',
                ], [
                    'employee_id' => 35, 'employee' => 'Alex Morgan', 'date_value' => '2026-07-02',
                    'label' => 'Private Leave Type', 'time_label' => 'Full day', 'kind' => 'leave-approved',
                ], [
                    'employee_id' => 35, 'employee' => 'Alex Morgan', 'date_value' => '2026-07-03',
                    'label' => 'Private Leave Type', 'time_label' => 'Full day', 'kind' => 'leave-approved',
                ]],
                'birthdays' => [[
                    'employee_id' => 36, 'employee' => 'Sam Lee', 'company_id' => 2, 'company' => 'Clinic',
                    'date_value' => '2026-06-15',
                ]],
            ]);
        });
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getLeaveRequestPageData')->once()->andReturn([
                'leaveTypes' => [['id' => 7, 'name' => 'Annual Leave']], 'leaveRequests' => [],
            ]);
        });
        $this->mock(OdooScheduleRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('teamCalendarEvents')->once()->andReturn(collect());
            $mock->shouldReceive('dayEntries')->once()->andReturn(collect([
                new OdooScheduleRecord([
                    'id' => 41,
                    'company_id' => 2,
                    'schedule_date' => Carbon::parse('2026-06-10'),
                    'holiday_name' => 'Team briefing',
                    'note' => 'Main meeting room',
                    'blocked_start' => '09:00',
                    'blocked_end' => '10:00',
                ]),
                new OdooScheduleRecord([
                    'id' => 42,
                    'company_id' => 2,
                    'schedule_date' => Carbon::parse('2026-06-10'),
                    'holiday_name' => 'Department review',
                    'note' => 'Conference room',
                    'blocked_start' => '11:00',
                    'blocked_end' => '12:00',
                ]),
                new OdooScheduleRecord([
                    'id' => 43,
                    'company_id' => 2,
                    'schedule_date' => Carbon::parse('2026-06-25'),
                    'holiday_name' => 'Quarterly social',
                    'note' => 'Rooftop',
                    'blocked_start' => '16:00',
                    'blocked_end' => '17:00',
                ]),
            ]));
        });
        $user = new User(['name' => 'Alex Morgan', 'odoo_employee_id' => 35]);
        $request = Request::create('/team-calendar', 'GET', ['month' => '2026-06', 'day' => '2026-06-18']);
        $request->setUserResolver(fn (): User => $user);

        $view = (new TeamCalendarController())->index(
            $request,
            app(OdooManagerPlanningService::class),
            app(OdooLeaveService::class),
            app(OdooScheduleRepository::class)
        );
        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.team-calendar.index', $view->getName());
        $this->assertSame('2026-06-18', $data['selectedCalendarDate']->toDateString());
        $this->assertCount(2, $data['eventsByDate']['2026-06-12'] ?? []);
        $this->assertSame(1, $data['summary']['shifts']);
        $this->assertSame(1, $data['summary']['people_on_leave']);
        $this->assertSame('2026-06-12', collect($data['eventsByDate']['2026-06-12'])->firstWhere('type', 'shift')['date']);
        $this->assertCount(2, $data['teamOnLeave']);
        $this->assertCount(4, $data['upcomingMoments']);
        $this->assertCount(2, collect($data['eventsByDate']['2026-06-10'])->where('type', 'event'));
        $this->assertSame('12–13 Jun', $data['teamOnLeave'][0]['date_range_label']);
        $this->assertSame('02–03 Jul', $data['teamOnLeave'][1]['date_range_label']);
        $this->assertSame('Happening now', $data['upcomingMoments'][0]['timing_label']);
        $this->assertSame('Today', $data['upcomingMoments'][0]['relative_date_label']);
        $this->assertSame('2026-06-25', $data['upcomingMoments'][3]['date']);
        $this->assertSame('All day', collect($data['eventsByDate']['2026-06-13'])->firstWhere('type', 'leave')['time']);
        $this->assertSame('Away · 12–13 Jun', collect($data['eventsByDate']['2026-06-12'])->firstWhere('type', 'leave')['calendar_subtitle']);
        $this->assertSame('Away through 13 Jun', collect($data['eventsByDate']['2026-06-13'])->firstWhere('type', 'leave')['calendar_subtitle']);
        $this->assertSame('Sam Lee', collect($data['eventsByDate']['2026-06-13'])->firstWhere('type', 'leave')['calendar_title']);
        $this->assertSame('Birthday', collect($data['eventsByDate']['2026-06-15'])->firstWhere('type', 'birthday')['time']);
        $this->assertCount(1, $data['myUpcomingShifts']);
        $this->assertTrue($data['weeks'][0][0]['date']->isSunday());
        $this->assertSame(['pending' => 0, 'approved' => 0, 'other' => 0], $data['leaveRequestSummary']);
        $this->assertSame('Sam Lee · On leave', collect($data['eventsByDate']['2026-06-12'])->firstWhere('type', 'leave')['title']);
        $this->assertStringNotContainsString('Private Leave Type', json_encode($data['eventsByDate']));
        $this->assertCount(1, $data['leaveTypes']);
        $this->assertFalse($data['canManageCalendar']);
    }
}
