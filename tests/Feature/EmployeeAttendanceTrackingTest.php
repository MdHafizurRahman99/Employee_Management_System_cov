<?php

namespace Tests\Feature;

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAttendanceTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_can_track_check_in_breaks_and_check_out_for_payment(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-06-09 09:00:00');
        $this->actingAs($user)
            ->post(route('employee.attendance.check-in'), ['month' => '2026-06'])
            ->assertRedirect(route('employee.attendance.index', ['month' => '2026-06']));

        $session = AttendanceSession::query()->firstOrFail();
        $this->assertSame('2026-06-09 09:00:00', $session->started_at?->format('Y-m-d H:i:s'));
        $this->assertNull($session->ended_at);

        Carbon::setTestNow('2026-06-09 12:00:00');
        $this->actingAs($user)
            ->post(route('employee.attendance.start-break'), ['month' => '2026-06'])
            ->assertRedirect(route('employee.attendance.index', ['month' => '2026-06']));

        $break = AttendanceBreak::query()->firstOrFail();
        $this->assertSame($session->id, $break->attendance_session_id);
        $this->assertSame('2026-06-09 12:00:00', $break->started_at?->format('Y-m-d H:i:s'));
        $this->assertNull($break->ended_at);

        Carbon::setTestNow('2026-06-09 12:30:00');
        $this->actingAs($user)
            ->post(route('employee.attendance.end-break'), ['month' => '2026-06'])
            ->assertRedirect(route('employee.attendance.index', ['month' => '2026-06']));

        $break->refresh();
        $this->assertSame('2026-06-09 12:30:00', $break->ended_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-06-09 17:00:00');
        $this->actingAs($user)
            ->post(route('employee.attendance.check-out'), ['month' => '2026-06'])
            ->assertRedirect(route('employee.attendance.index', ['month' => '2026-06']));

        $session->refresh();
        $this->assertSame('2026-06-09 17:00:00', $session->ended_at?->format('Y-m-d H:i:s'));

        $response = $this->actingAs($user)->get(route('employee.attendance.index', ['month' => '2026-06']));

        $response->assertOk();
        $response->assertSee('Payment-Ready Attendance for June 2026');
        $response->assertSee('8.00 hrs');
        $response->assertSee('0.50 hrs');
        $response->assertSee('7.50 hrs');
        $response->assertSee('Ready for Payroll');
    }

    public function test_checking_out_during_a_break_closes_the_break_for_payroll_tracking(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-06-10 09:00:00');
        $this->actingAs($user)->post(route('employee.attendance.check-in'));

        Carbon::setTestNow('2026-06-10 11:00:00');
        $this->actingAs($user)->post(route('employee.attendance.start-break'));

        Carbon::setTestNow('2026-06-10 11:15:00');
        $this->actingAs($user)->post(route('employee.attendance.check-out'));

        $session = AttendanceSession::query()->firstOrFail();
        $break = AttendanceBreak::query()->firstOrFail();

        $this->assertSame('2026-06-10 11:15:00', $session->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-10 11:15:00', $break->ended_at?->format('Y-m-d H:i:s'));
    }
}
