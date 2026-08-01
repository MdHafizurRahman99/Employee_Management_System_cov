<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerAttendanceController;
use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerAttendanceControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_the_team_attendance_view(): void
    {
        Carbon::setTestNow('2026-06-15 09:00:00');

        $this->mock(OdooManagerAttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getTeamAttendancePageData')
                ->once()
                ->andReturn([
                    'employees' => [['id' => 35, 'name' => 'Alice Jones', 'company' => 'Clinic']],
                    'records' => [['id' => 91, 'employee' => 'Alice Jones']],
                    'summary' => [
                        'range_label' => '01 Jun 2026 - 30 Jun 2026',
                        'records_count' => 1,
                        'employees_count' => 1,
                        'missing_clock_out_count' => 0,
                        'total_worked_hours' => 8.0,
                        'total_worked_hours_label' => '8.00 hrs',
                    ],
                ]);
        });

        $request = Request::create('/manager/attendance', 'GET', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]);
        $request->setUserResolver(fn (): User => new User(['odoo_user_id' => 27]));

        $view = (new ManagerAttendanceController())->index($request, app(OdooManagerAttendanceService::class));
        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-attendance.index', $view->getName());
        $this->assertCount(1, $data['employees']);
        $this->assertCount(1, $data['attendanceRecords']);
        $this->assertSame('8.00 hrs', $data['attendanceSummary']['total_worked_hours_label']);
    }

    public function test_it_redirects_with_success_after_submitting_a_correction(): void
    {
        $this->mock(OdooManagerAttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('correctAttendanceRecord')
                ->once()
                ->withArgs(function (User $user, int $attendanceId, array $payload): bool {
                    return $user->odoo_user_id === 27
                        && $attendanceId === 91
                        && $payload['check_in'] === '2026-06-10T09:00'
                        && $payload['last_known_write_date'] === '2026-06-10 09:05:00';
                });
        });

        $request = Request::create('/manager/attendance/91/correct', 'POST', [
            'check_in' => '2026-06-10T09:00',
            'check_out' => '2026-06-10T17:00',
            'correction_note' => 'Added missing clock-out.',
            'last_known_write_date' => '2026-06-10 09:05:00',
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => new User(['odoo_user_id' => 27]));

        $response = (new ManagerAttendanceController())->correct(
            $request,
            app(OdooManagerAttendanceService::class),
            91
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('manager.attendance.index', ['from_date' => '2026-06-01', 'to_date' => '2026-06-30']),
            $response->getTargetUrl()
        );
    }

    public function test_it_redirects_with_errors_when_correction_fails(): void
    {
        $this->mock(OdooManagerAttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('correctAttendanceRecord')
                ->once()
                ->andThrow(new OdooException('This attendance record was updated by someone else. Please reload the page before trying again.'));
        });

        $request = Request::create('/manager/attendance/91/correct', 'POST', [
            'check_in' => '2026-06-10T09:00',
            'check_out' => '2026-06-10T17:00',
            'correction_note' => 'Added missing clock-out.',
            'last_known_write_date' => '2026-06-10 09:05:00',
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
            'editing_attendance_id' => '91',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => new User(['odoo_user_id' => 27]));

        $response = (new ManagerAttendanceController())->correct(
            $request,
            app(OdooManagerAttendanceService::class),
            91
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('manager.attendance.index', ['from_date' => '2026-06-01', 'to_date' => '2026-06-30']),
            $response->getTargetUrl()
        );
    }
}
