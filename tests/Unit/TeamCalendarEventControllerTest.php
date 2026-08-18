<?php

namespace Tests\Unit;

use App\Http\Controllers\TeamCalendarEventController;
use App\Services\Odoo\OdooScheduleRepository;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Tests\TestCase;

class TeamCalendarEventControllerTest extends TestCase
{
    public function test_it_stores_a_timed_team_event_and_returns_to_its_month(): void
    {
        $repository = $this->mock(OdooScheduleRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createDay')->once()->with([
                'company_id' => 8,
                'schedule_area_id' => null,
                'schedule_date' => '2026-09-14',
                'holiday_name' => 'Quarterly review',
                'note' => 'Conference room A',
                'blocked_start' => '14:00',
                'blocked_end' => '15:30',
            ])->andReturn(41);
        });
        $request = Request::create('/team-calendar/events', 'POST', [
            'title' => ' Quarterly review ',
            'schedule_date' => '2026-09-14',
            'company_id' => 8,
            'start_time' => '14:00',
            'end_time' => '15:30',
            'description' => ' Conference room A ',
            'calendar_month' => '2026-09',
        ]);

        $response = (new TeamCalendarEventController())->store($request, $repository);

        $this->assertSame(route('team-calendar.index', ['month' => '2026-09']), $response->getTargetUrl());
        $this->assertSame('Event added to the team calendar.', $response->getSession()->get('calendar_event_success'));
    }

    public function test_it_updates_an_existing_team_event(): void
    {
        $repository = $this->mock(OdooScheduleRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('updateDay')->once()->with(41, \Mockery::on(
                fn (array $data): bool => $data['holiday_name'] === 'Updated review'
                    && $data['blocked_start'] === null
                    && $data['blocked_end'] === null
            ));
        });
        $request = Request::create('/team-calendar/events/41', 'POST', [
            'title' => 'Updated review',
            'schedule_date' => '2026-09-21',
            'company_id' => 8,
        ]);

        $response = (new TeamCalendarEventController())->update($request, $repository, 41);

        $this->assertSame(route('team-calendar.index', ['month' => '2026-09']), $response->getTargetUrl());
    }

    public function test_it_deletes_an_event_and_keeps_the_selected_month(): void
    {
        $repository = $this->mock(OdooScheduleRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteDay')->once()->with(41);
        });
        $request = Request::create('/team-calendar/events/41/delete', 'POST', ['calendar_month' => '2026-09']);

        $response = (new TeamCalendarEventController())->destroy($request, $repository, 41);

        $this->assertSame(route('team-calendar.index', ['month' => '2026-09']), $response->getTargetUrl());
    }
}
