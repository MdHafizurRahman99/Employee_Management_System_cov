<?php

namespace App\Services\Odoo;

use App\Models\User;
use Carbon\Carbon;

class OdooAttendanceService
{
    private ?array $attendanceFields = null;

    public function __construct(
        private readonly OdooServiceAccount $serviceAccount
    ) {
    }

    /**
     * @return array{days:array<int, array<string, mixed>>, summary:array<string, mixed>}
     */
    public function getAttendanceForMonth(User $user, Carbon $month): array
    {
        $month = $month->copy()->startOfMonth();
        $identityField = $this->resolveIdentityField($user);

        if (! $identityField) {
            return [
                'days' => [],
                'summary' => $this->emptySummary($month),
            ];
        }

        $fields = $this->attendanceFields();
        $checkInField = $this->resolveField($fields, ['check_in']);
        $checkOutField = $this->resolveField($fields, ['check_out']);
        $workedHoursField = $this->resolveField($fields, ['worked_hours']);

        if (! $checkInField || ! $checkOutField || ! $workedHoursField) {
            throw new OdooException('The Odoo attendance model does not expose the expected attendance fields.');
        }

        $requestedFields = array_values(array_filter(array_unique([
            'id',
            $checkInField,
            $checkOutField,
            $workedHoursField,
            $this->resolveField($fields, ['employee_id']),
        ])));

        $domain = [
            [$identityField['field'], '=', $identityField['value']],
            [$checkInField, '>=', $month->copy()->startOfMonth()->toDateTimeString()],
            [$checkInField, '<=', $month->copy()->endOfMonth()->endOfDay()->toDateTimeString()],
        ];

        $records = $this->serviceAccount->executeKw(
            'hr.attendance',
            'search_read',
            [$domain],
            [
                'fields' => $requestedFields,
                'order' => $checkInField.' asc',
            ]
        );

        if (! is_array($records)) {
            return [
                'days' => [],
                'summary' => $this->emptySummary($month),
            ];
        }

        $days = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $checkInAt = $this->parseDateTime($record[$checkInField] ?? null);

            if (! $checkInAt) {
                continue;
            }

            $dateKey = $checkInAt->toDateString();
            $day = $days[$dateKey] ?? $this->initializeDay($checkInAt);
            $days[$dateKey] = $this->accumulateDay($day, $record, $checkInAt, $checkOutField, $workedHoursField);
        }

        $attendanceDays = array_values(array_map(
            fn (array $day) => $this->finalizeDay($day),
            array_reverse($days)
        ));

        return [
            'days' => $attendanceDays,
            'summary' => $this->summarizeDays($attendanceDays, $month),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttendanceSummaryForMonth(User $user, Carbon $month): array
    {
        return $this->getAttendanceForMonth($user, $month)['summary'];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(Carbon $month): array
    {
        return [
            'month_label' => $month->format('F Y'),
            'total_days' => 0,
            'complete_days' => 0,
            'open_days' => 0,
            'total_sessions' => 0,
            'total_worked_hours' => 0.0,
            'total_worked_hours_label' => $this->formatHours(0),
            'average_hours_per_day' => 0.0,
            'average_hours_per_day_label' => $this->formatHours(0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeDay(Carbon $checkInAt): array
    {
        return [
            'date' => $checkInAt->toDateString(),
            'date_label' => $checkInAt->format('d-m-Y'),
            'clock_in_at' => $checkInAt,
            'clock_out_at' => null,
            'worked_hours' => 0.0,
            'session_count' => 0,
            'open_sessions_count' => 0,
            'is_today' => $checkInAt->isToday(),
        ];
    }

    /**
     * @param  array<string, mixed>  $day
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function accumulateDay(
        array $day,
        array $record,
        Carbon $checkInAt,
        string $checkOutField,
        string $workedHoursField
    ): array {
        $checkOutAt = $this->parseDateTime($record[$checkOutField] ?? null);
        $workedHours = $this->normalizeWorkedHours($record[$workedHoursField] ?? null, $checkInAt, $checkOutAt);

        if ($checkInAt->lt($day['clock_in_at'])) {
            $day['clock_in_at'] = $checkInAt;
        }

        if ($checkOutAt && (! $day['clock_out_at'] || $checkOutAt->gt($day['clock_out_at']))) {
            $day['clock_out_at'] = $checkOutAt;
        }

        $day['worked_hours'] = round(((float) $day['worked_hours']) + $workedHours, 2);
        $day['session_count'] = (int) $day['session_count'] + 1;

        if (! $checkOutAt) {
            $day['open_sessions_count'] = (int) $day['open_sessions_count'] + 1;
        }

        return $day;
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<string, mixed>
     */
    private function finalizeDay(array $day): array
    {
        $openSessions = (int) $day['open_sessions_count'];
        $clockOutAt = $day['clock_out_at'];
        $workedHours = round((float) $day['worked_hours'], 2);
        $hasMissingClockOut = $openSessions > 0;

        if ($hasMissingClockOut) {
            $statusLabel = $day['is_today'] ? 'Clocked In' : 'Missing Clock-out';
            $statusClass = $day['is_today'] ? 'warning' : 'danger';
        } else {
            $statusLabel = 'Complete';
            $statusClass = 'success';
        }

        return [
            'date' => $day['date'],
            'date_label' => $day['date_label'],
            'clock_in_at' => $day['clock_in_at'],
            'clock_in_label' => $day['clock_in_at']->format('h:i A'),
            'clock_out_at' => $clockOutAt,
            'clock_out_label' => $clockOutAt ? $clockOutAt->format('h:i A') : 'Missing clock-out',
            'clock_out_note' => $this->clockOutNote($openSessions, $clockOutAt !== null),
            'worked_hours' => $workedHours,
            'worked_hours_label' => $hasMissingClockOut && $workedHours <= 0
                ? 'Pending'
                : $this->formatHours($workedHours),
            'session_count' => (int) $day['session_count'],
            'open_sessions_count' => $openSessions,
            'missing_clock_out' => $hasMissingClockOut,
            'status_label' => $statusLabel,
            'status_class' => $statusClass,
            'is_today' => (bool) $day['is_today'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function summarizeDays(array $days, Carbon $month): array
    {
        $totalDays = count($days);
        $openDays = count(array_filter($days, fn (array $day) => $day['missing_clock_out']));
        $totalSessions = array_reduce(
            $days,
            fn (int $carry, array $day) => $carry + (int) $day['session_count'],
            0
        );
        $totalWorkedHours = round(array_reduce(
            $days,
            fn (float $carry, array $day) => $carry + (float) $day['worked_hours'],
            0.0
        ), 2);
        $averageHours = $totalDays > 0 ? round($totalWorkedHours / $totalDays, 2) : 0.0;

        return [
            'month_label' => $month->format('F Y'),
            'total_days' => $totalDays,
            'complete_days' => $totalDays - $openDays,
            'open_days' => $openDays,
            'total_sessions' => $totalSessions,
            'total_worked_hours' => $totalWorkedHours,
            'total_worked_hours_label' => $this->formatHours($totalWorkedHours),
            'average_hours_per_day' => $averageHours,
            'average_hours_per_day_label' => $this->formatHours($averageHours),
        ];
    }

    private function resolveIdentityField(User $user): ?array
    {
        $fields = $this->attendanceFields();

        if ($user->odoo_employee_id && isset($fields['employee_id'])) {
            return ['field' => 'employee_id', 'value' => (int) $user->odoo_employee_id];
        }

        return null;
    }

    private function attendanceFields(): array
    {
        if ($this->attendanceFields !== null) {
            return $this->attendanceFields;
        }

        $fields = $this->serviceAccount->executeKw(
            'hr.attendance',
            'fields_get',
            [],
            [
                'attributes' => ['string', 'type', 'relation'],
            ]
        );

        $this->attendanceFields = is_array($fields) ? $fields : [];

        return $this->attendanceFields;
    }

    private function resolveField(array $fields, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (isset($fields[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeWorkedHours(mixed $value, Carbon $checkInAt, ?Carbon $checkOutAt): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (! $checkOutAt) {
            return 0.0;
        }

        return round($checkInAt->diffInMinutes($checkOutAt) / 60, 2);
    }

    private function formatHours(float|int $hours): string
    {
        return number_format((float) $hours, 2).' hrs';
    }

    private function clockOutNote(int $openSessions, bool $hasClosedClockOut): ?string
    {
        if ($openSessions < 1) {
            return null;
        }

        if ($hasClosedClockOut) {
            return $openSessions.' session'.$this->pluralSuffix($openSessions).' still open';
        }

        return 'Awaiting clock-out';
    }

    private function pluralSuffix(int $count): string
    {
        return $count === 1 ? '' : 's';
    }
}
