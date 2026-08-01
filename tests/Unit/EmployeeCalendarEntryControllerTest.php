<?php

namespace Tests\Unit;

use App\Http\Controllers\EmployeeCalendarEntryController;
use App\Models\User;
use App\Services\Odoo\OdooEmployeeScheduleEntryService;
use App\Services\Odoo\OdooException;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Tests\TestCase;

class EmployeeCalendarEntryControllerTest extends TestCase
{
    public function test_employee_can_create_an_odoo_schedule_diary_entry(): void
    {
        $employee = new User(['odoo_employee_id' => 35]);
        $this->mock(OdooEmployeeScheduleEntryService::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('createEntry')
                ->once()
                ->withArgs(function (User $user, array $data) use ($employee): bool {
                    return $user === $employee
                        && $data['entry_date'] === '2026-07-28'
                        && $data['entry_type'] === 'unavailable'
                        && $data['start_time'] === '09:00'
                        && $data['end_time'] === '12:00';
                })
                ->andReturn(81);
        });
        $request = $this->request('POST', '/employee/calendar-entries', [
            'entry_date' => '2026-07-28',
            'entry_type' => 'unavailable',
            'title' => 'Medical appointment',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ], $employee);

        $response = (new EmployeeCalendarEntryController())->store(
            $request,
            app(OdooEmployeeScheduleEntryService::class)
        );

        $this->assertSame(route('employee.shifts.index', [
            'month' => '2026-07',
            'day' => '2026-07-28',
        ]), $response->getTargetUrl());
        $this->assertSame('Diary entry saved in Odoo.', $response->getSession()->get('success'));
    }

    public function test_odoo_entry_failure_is_returned_to_the_calendar(): void
    {
        $employee = new User(['odoo_employee_id' => 35]);
        $this->mock(OdooEmployeeScheduleEntryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createEntry')
                ->once()
                ->andThrow(new OdooException('Schedule diary is not enabled in Odoo yet.'));
        });
        $request = $this->request('POST', '/employee/calendar-entries', [
            'entry_date' => '2026-07-28',
            'entry_type' => 'note',
            'title' => 'Prefer front desk',
            'is_all_day' => '1',
        ], $employee);

        $response = (new EmployeeCalendarEntryController())->store(
            $request,
            app(OdooEmployeeScheduleEntryService::class)
        );

        $this->assertTrue($response->getSession()->get('errors')->has('calendar_entry'));
    }

    private function request(string $method, string $uri, array $data, User $user): Request
    {
        $request = Request::create($uri, $method, $data);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
