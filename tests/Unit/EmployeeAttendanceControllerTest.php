<?php

namespace Tests\Unit;

use App\Http\Controllers\EmployeeAttendanceController;
use App\Models\User;
use App\Services\Attendance\AttendanceTrackerService;
use App\Services\Odoo\OdooAttendanceService;
use App\Services\Odoo\OdooException;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class EmployeeAttendanceControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_returns_current_month_attendance_data_for_the_view(): void
    {
        Carbon::setTestNow('2026-06-08 09:00:00');
        $this->mockAttendanceTrackerService();

        $this->mock(OdooAttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAttendanceForMonth')
                ->once()
                ->withArgs(function (User $user, Carbon $month): bool {
                    return $user->odoo_employee_id === 35 && $month->format('Y-m') === '2026-06';
                })
                ->andReturn([
                    'days' => [
                        [
                            'date' => '2026-06-08',
                            'date_label' => 'Mon, 08 Jun 2026',
                            'clock_in_at' => Carbon::parse('2026-06-08 09:00:00'),
                            'clock_in_label' => '09:00 AM',
                            'clock_out_at' => null,
                            'clock_out_label' => 'Missing clock-out',
                            'clock_out_note' => 'Awaiting clock-out',
                            'worked_hours' => 0.0,
                            'worked_hours_label' => 'Pending',
                            'session_count' => 1,
                            'open_sessions_count' => 1,
                            'missing_clock_out' => true,
                            'status_label' => 'Clocked In',
                            'status_class' => 'warning',
                            'is_today' => true,
                        ],
                    ],
                    'summary' => [
                        'month_label' => 'June 2026',
                        'total_days' => 1,
                        'complete_days' => 0,
                        'open_days' => 1,
                        'total_sessions' => 1,
                        'total_worked_hours' => 0.0,
                        'total_worked_hours_label' => '0.00 hrs',
                        'average_hours_per_day' => 0.0,
                        'average_hours_per_day_label' => '0.00 hrs',
                    ],
                ]);
        });

        $view = $this->controller()->index(
            $this->requestWithUser('2026-06', $this->employeeUser()),
            app(AttendanceTrackerService::class),
            app(OdooAttendanceService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.employee-attendance.index', $view->getName());
        $this->assertSame('2026-06', $data['selectedMonth']->format('Y-m'));
        $this->assertSame('June 2026', $data['currentMonthSummary']['month_label']);
        $this->assertTrue($data['attendanceTracker']['can_check_in']);
        $this->assertTrue($data['hasAttendanceIdentity']);
        $this->assertSame('Missing clock-out', $data['attendanceDays'][0]['clock_out_label']);
        $this->assertSame('Clocked In', $data['attendanceDays'][0]['status_label']);
    }

    public function test_it_uses_the_selected_month_records_and_current_month_summary_separately(): void
    {
        Carbon::setTestNow('2026-06-08 09:00:00');
        $this->mockAttendanceTrackerService();

        $this->mock(OdooAttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAttendanceForMonth')
                ->once()
                ->withArgs(function (User $user, Carbon $month): bool {
                    return $user->odoo_employee_id === 35 && $month->format('Y-m') === '2026-05';
                })
                ->andReturn([
                    'days' => [],
                    'summary' => [
                        'month_label' => 'May 2026',
                        'total_days' => 0,
                        'complete_days' => 0,
                        'open_days' => 0,
                        'total_sessions' => 0,
                        'total_worked_hours' => 0.0,
                        'total_worked_hours_label' => '0.00 hrs',
                        'average_hours_per_day' => 0.0,
                        'average_hours_per_day_label' => '0.00 hrs',
                    ],
                ]);

            $mock->shouldReceive('getAttendanceSummaryForMonth')
                ->once()
                ->withArgs(function (User $user, Carbon $month): bool {
                    return $user->odoo_employee_id === 35 && $month->format('Y-m') === '2026-06';
                })
                ->andReturn([
                    'month_label' => 'June 2026',
                    'total_days' => 4,
                    'complete_days' => 4,
                    'open_days' => 0,
                    'total_sessions' => 6,
                    'total_worked_hours' => 32.0,
                    'total_worked_hours_label' => '32.00 hrs',
                    'average_hours_per_day' => 8.0,
                    'average_hours_per_day_label' => '8.00 hrs',
                ]);
        });

        $view = $this->controller()->index(
            $this->requestWithUser('2026-05', $this->employeeUser()),
            app(AttendanceTrackerService::class),
            app(OdooAttendanceService::class)
        );

        $data = $view->getData();

        $this->assertSame('2026-05', $data['selectedMonth']->format('Y-m'));
        $this->assertTrue($data['canViewNextMonth']);
        $this->assertSame('May 2026', $data['selectedMonthSummary']['month_label']);
        $this->assertSame('June 2026', $data['currentMonthSummary']['month_label']);
        $this->assertSame('32.00 hrs', $data['currentMonthSummary']['total_worked_hours_label']);
    }

    public function test_it_clamps_future_month_requests_back_to_the_current_month(): void
    {
        Carbon::setTestNow('2026-06-08 09:00:00');
        $this->mockAttendanceTrackerService();

        $this->mock(OdooAttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAttendanceForMonth')
                ->once()
                ->withArgs(function (User $user, Carbon $month): bool {
                    return $user->odoo_employee_id === 35 && $month->format('Y-m') === '2026-06';
                })
                ->andReturn([
                    'days' => [],
                    'summary' => [
                        'month_label' => 'June 2026',
                        'total_days' => 0,
                        'complete_days' => 0,
                        'open_days' => 0,
                        'total_sessions' => 0,
                        'total_worked_hours' => 0.0,
                        'total_worked_hours_label' => '0.00 hrs',
                        'average_hours_per_day' => 0.0,
                        'average_hours_per_day_label' => '0.00 hrs',
                    ],
                ]);
        });

        $view = $this->controller()->index(
            $this->requestWithUser('2026-09', $this->employeeUser()),
            app(AttendanceTrackerService::class),
            app(OdooAttendanceService::class)
        );

        $data = $view->getData();

        $this->assertSame('2026-06', $data['selectedMonth']->format('Y-m'));
        $this->assertFalse($data['canViewNextMonth']);
    }

    public function test_it_exposes_odoo_errors_without_throwing_from_the_controller(): void
    {
        Carbon::setTestNow('2026-06-08 09:00:00');
        $this->mockAttendanceTrackerService();

        $this->mock(OdooAttendanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAttendanceForMonth')
                ->once()
                ->andThrow(new OdooException('Attendance is temporarily unavailable.'));
        });

        $view = $this->controller()->index(
            $this->requestWithUser('2026-06', $this->employeeUser()),
            app(AttendanceTrackerService::class),
            app(OdooAttendanceService::class)
        );

        $data = $view->getData();

        $this->assertSame('Attendance is temporarily unavailable.', $data['odooAttendanceError']);
        $this->assertSame([], $data['attendanceDays']);
    }

    private function mockAttendanceTrackerService(): void
    {
        $this->mock(AttendanceTrackerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getPageData')
                ->andReturn([
                    'tracker' => [
                        'status_label' => 'Checked Out',
                        'status_class' => 'secondary',
                        'active_session_started_label' => null,
                        'active_break_started_label' => null,
                        'live_gross_hours_label' => '0.00 hrs',
                        'live_break_hours_label' => '0.00 hrs',
                        'live_payable_hours_label' => '0.00 hrs',
                        'can_check_in' => true,
                        'can_start_break' => false,
                        'can_end_break' => false,
                        'can_check_out' => false,
                    ],
                    'days' => [],
                    'summary' => [
                        'month_label' => 'June 2026',
                        'total_days' => 0,
                        'complete_days' => 0,
                        'open_days' => 0,
                        'total_sessions' => 0,
                        'total_breaks' => 0,
                        'total_gross_hours_label' => '0.00 hrs',
                        'total_break_hours_label' => '0.00 hrs',
                        'total_payable_hours_label' => '0.00 hrs',
                        'average_payable_hours_label' => '0.00 hrs',
                    ],
                ]);
        });
    }

    private function controller(): EmployeeAttendanceController
    {
        return new EmployeeAttendanceController();
    }

    private function employeeUser(): User
    {
        $user = new User([
            'name' => 'Odoo Employee',
            'email' => 'employee@example.com',
            'odoo_user_id' => 27,
            'odoo_employee_id' => 35,
        ]);

        $user->setAttribute('id', 1);
        $user->exists = true;

        return $user;
    }

    private function requestWithUser(string $month, User $user): Request
    {
        $request = Request::create('/employee/attendance', 'GET', ['month' => $month]);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
