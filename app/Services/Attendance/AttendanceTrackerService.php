<?php

namespace App\Services\Attendance;

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceTrackerService
{
    /**
     * @return array{
     *     tracker:array<string, mixed>,
     *     days:array<int, array<string, mixed>>,
     *     summary:array<string, mixed>
     * }
     */
    public function getPageData(User $user, Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $sessions = AttendanceSession::query()
            ->with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('started_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth()->endOfDay(),
            ])
            ->orderBy('started_at')
            ->get();

        $activeSession = $this->activeSessionQuery($user)
            ->with('breaks')
            ->first();

        $daysByDate = [];

        foreach ($sessions as $session) {
            $dateKey = $session->started_at->toDateString();
            $day = $daysByDate[$dateKey] ?? $this->initializeDay($session->started_at);

            $daysByDate[$dateKey] = $this->accumulateDay($day, $session);
        }

        $days = array_values(array_map(
            fn (array $day): array => $this->finalizeDay($day),
            array_reverse($daysByDate)
        ));

        return [
            'tracker' => $this->buildTracker($activeSession),
            'days' => $days,
            'summary' => $this->summarizeMonth($sessions, $days, $month),
        ];
    }

    public function checkIn(User $user): AttendanceSession
    {
        return DB::transaction(function () use ($user): AttendanceSession {
            if ($this->activeSessionQuery($user)->lockForUpdate()->exists()) {
                throw new AttendanceTrackerException('You are already checked in.');
            }

            return AttendanceSession::create([
                'user_id' => $user->id,
                'started_at' => now(),
            ]);
        });
    }

    public function startBreak(User $user): AttendanceBreak
    {
        return DB::transaction(function () use ($user): AttendanceBreak {
            $session = $this->activeSessionQuery($user)->with('breaks')->lockForUpdate()->first();

            if (! $session) {
                throw new AttendanceTrackerException('Check in before starting a break.');
            }

            if ($this->activeBreakForSession($session)) {
                throw new AttendanceTrackerException('You are already on a break.');
            }

            return AttendanceBreak::create([
                'attendance_session_id' => $session->id,
                'started_at' => now(),
            ]);
        });
    }

    public function endBreak(User $user): AttendanceBreak
    {
        return DB::transaction(function () use ($user): AttendanceBreak {
            $session = $this->activeSessionQuery($user)->with('breaks')->lockForUpdate()->first();

            if (! $session) {
                throw new AttendanceTrackerException('You do not have an active session.');
            }

            $activeBreak = $this->activeBreakForSession($session);

            if (! $activeBreak) {
                throw new AttendanceTrackerException('There is no active break to end.');
            }

            $activeBreak->forceFill([
                'ended_at' => now(),
            ])->save();

            return $activeBreak->fresh();
        });
    }

    public function checkOut(User $user): AttendanceSession
    {
        return DB::transaction(function () use ($user): AttendanceSession {
            $session = $this->activeSessionQuery($user)->with('breaks')->lockForUpdate()->first();

            if (! $session) {
                throw new AttendanceTrackerException('You are not currently checked in.');
            }

            $checkoutTime = now();
            $activeBreak = $this->activeBreakForSession($session);

            if ($activeBreak) {
                $activeBreak->forceFill([
                    'ended_at' => $checkoutTime,
                ])->save();
            }

            $session->forceFill([
                'ended_at' => $checkoutTime,
            ])->save();

            return $session->fresh('breaks');
        });
    }

    private function activeSessionQuery(User $user)
    {
        return AttendanceSession::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest('started_at');
    }

    private function activeBreakForSession(AttendanceSession $session): ?AttendanceBreak
    {
        $activeBreak = $session->breaks
            ->first(fn (AttendanceBreak $break): bool => $break->ended_at === null);

        if ($activeBreak) {
            return $activeBreak;
        }

        return $session->breaks()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeDay(Carbon $startedAt): array
    {
        return [
            'date' => $startedAt->toDateString(),
            'date_label' => $startedAt->format('d-m-Y'),
            'first_check_in_at' => $startedAt,
            'last_check_out_at' => null,
            'gross_minutes' => 0,
            'break_minutes' => 0,
            'payable_minutes' => 0,
            'session_count' => 0,
            'break_count' => 0,
            'open_session_count' => 0,
            'open_break_count' => 0,
            'is_today' => $startedAt->isToday(),
        ];
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<string, mixed>
     */
    private function accumulateDay(array $day, AttendanceSession $session): array
    {
        $metrics = $this->sessionMetrics($session, true);

        if ($session->started_at->lt($day['first_check_in_at'])) {
            $day['first_check_in_at'] = $session->started_at;
        }

        if ($session->ended_at && (! $day['last_check_out_at'] || $session->ended_at->gt($day['last_check_out_at']))) {
            $day['last_check_out_at'] = $session->ended_at;
        }

        $day['gross_minutes'] += $metrics['gross_minutes'];
        $day['break_minutes'] += $metrics['break_minutes'];
        $day['payable_minutes'] += $metrics['payable_minutes'];
        $day['session_count']++;
        $day['break_count'] += $metrics['break_count'];
        $day['open_session_count'] += $metrics['is_open'] ? 1 : 0;
        $day['open_break_count'] += $metrics['open_break_count'];

        return $day;
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<string, mixed>
     */
    private function finalizeDay(array $day): array
    {
        $openSessions = (int) $day['open_session_count'];
        $openBreaks = (int) $day['open_break_count'];

        if ($openBreaks > 0) {
            $statusLabel = 'On Break';
            $statusClass = 'warning';
            $clockOutLabel = 'On break';
        } elseif ($openSessions > 0) {
            $statusLabel = $day['is_today'] ? 'Clocked In' : 'Pending Checkout';
            $statusClass = $day['is_today'] ? 'primary' : 'danger';
            $clockOutLabel = 'Active';
        } else {
            $statusLabel = 'Ready for Payroll';
            $statusClass = 'success';
            $clockOutLabel = $day['last_check_out_at']
                ? $day['last_check_out_at']->format('h:i A')
                : 'Not recorded';
        }

        return [
            'date' => $day['date'],
            'date_label' => $day['date_label'],
            'first_check_in_at' => $day['first_check_in_at'],
            'first_check_in_label' => $day['first_check_in_at']->format('h:i A'),
            'last_check_out_at' => $day['last_check_out_at'],
            'last_check_out_label' => $clockOutLabel,
            'gross_hours' => round(((int) $day['gross_minutes']) / 60, 2),
            'gross_hours_label' => $this->formatMinutes((int) $day['gross_minutes']),
            'break_hours' => round(((int) $day['break_minutes']) / 60, 2),
            'break_hours_label' => $this->formatMinutes((int) $day['break_minutes']),
            'payable_hours' => round(((int) $day['payable_minutes']) / 60, 2),
            'payable_hours_label' => $this->formatMinutes((int) $day['payable_minutes']),
            'session_count' => (int) $day['session_count'],
            'break_count' => (int) $day['break_count'],
            'open_session_count' => $openSessions,
            'open_break_count' => $openBreaks,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'is_today' => (bool) $day['is_today'],
        ];
    }

    /**
     * @param  Collection<int, AttendanceSession>  $sessions
     * @param  array<int, array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function summarizeMonth(Collection $sessions, array $days, Carbon $month): array
    {
        $sessionMetrics = $sessions->map(fn (AttendanceSession $session): array => $this->sessionMetrics($session, false));
        $totalDays = count($days);
        $openDays = count(array_filter($days, fn (array $day): bool => (int) $day['open_session_count'] > 0));
        $completeDays = $totalDays - $openDays;
        $totalGrossMinutes = (int) $sessionMetrics->sum('gross_minutes');
        $totalBreakMinutes = (int) $sessionMetrics->sum('break_minutes');
        $totalPayableMinutes = (int) $sessionMetrics->sum('payable_minutes');
        $averagePayableMinutes = $completeDays > 0
            ? (int) round($totalPayableMinutes / $completeDays)
            : 0;

        return [
            'month_label' => $month->format('F Y'),
            'total_days' => $totalDays,
            'complete_days' => $completeDays,
            'open_days' => $openDays,
            'total_sessions' => $sessions->count(),
            'total_breaks' => (int) $sessionMetrics->sum('break_count'),
            'total_gross_hours_label' => $this->formatMinutes($totalGrossMinutes),
            'total_break_hours_label' => $this->formatMinutes($totalBreakMinutes),
            'total_payable_hours_label' => $this->formatMinutes($totalPayableMinutes),
            'average_payable_hours_label' => $this->formatMinutes($averagePayableMinutes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTracker(?AttendanceSession $activeSession): array
    {
        if (! $activeSession) {
            return [
                'status_label' => 'Checked Out',
                'status_class' => 'secondary',
                'active_session_started_label' => null,
                'active_break_started_label' => null,
                'live_gross_hours_label' => $this->formatMinutes(0),
                'live_break_hours_label' => $this->formatMinutes(0),
                'live_payable_hours_label' => $this->formatMinutes(0),
                'can_check_in' => true,
                'can_start_break' => false,
                'can_end_break' => false,
                'can_check_out' => false,
            ];
        }

        $activeBreak = $this->activeBreakForSession($activeSession);
        $metrics = $this->sessionMetrics($activeSession, true);

        return [
            'status_label' => $activeBreak ? 'On Break' : 'Working',
            'status_class' => $activeBreak ? 'warning' : 'success',
            'active_session_started_label' => $activeSession->started_at->format('d-m-Y h:i A'),
            'active_break_started_label' => $activeBreak?->started_at?->format('d-m-Y h:i A'),
            'live_gross_hours_label' => $this->formatMinutes($metrics['gross_minutes']),
            'live_break_hours_label' => $this->formatMinutes($metrics['break_minutes']),
            'live_payable_hours_label' => $this->formatMinutes($metrics['payable_minutes']),
            'can_check_in' => false,
            'can_start_break' => ! $activeBreak,
            'can_end_break' => (bool) $activeBreak,
            'can_check_out' => true,
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private function sessionMetrics(AttendanceSession $session, bool $includeLiveProgress): array
    {
        $grossMinutes = 0;
        $breakMinutes = 0;
        $openBreakCount = 0;
        $sessionEnd = $session->ended_at ?? ($includeLiveProgress ? now() : null);

        if ($sessionEnd) {
            $grossMinutes = $session->started_at->diffInMinutes($sessionEnd);
        }

        foreach ($session->breaks as $break) {
            $breakEnd = $break->ended_at ?? ($includeLiveProgress ? now() : null);

            if ($breakEnd) {
                $breakMinutes += $break->started_at->diffInMinutes($breakEnd);
            } else {
                $openBreakCount++;
            }
        }

        return [
            'gross_minutes' => $grossMinutes,
            'break_minutes' => $breakMinutes,
            'payable_minutes' => max($grossMinutes - $breakMinutes, 0),
            'break_count' => $session->breaks->count(),
            'open_break_count' => $openBreakCount,
            'is_open' => $session->ended_at === null,
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        return number_format($minutes / 60, 2).' hrs';
    }
}
