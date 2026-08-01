<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Scheduling\ScheduleDayService;
use Carbon\Carbon;
use Tests\TestCase;

class ScheduleDayServiceTest extends TestCase
{
    public function test_it_adds_day_signals_and_flags_shifts_during_blocked_time(): void
    {
        $meta = new OdooScheduleRecord([
            'company_id' => 2,
            'schedule_area_id' => 4,
            'schedule_date' => Carbon::parse('2026-07-13'),
            'holiday_name' => 'Clinic event',
            'note' => 'Keep reception clear.',
            'blocked_start' => '11:00',
            'blocked_end' => '11:30',
        ]);
        $day = ['date_value' => '2026-07-13'];
        $board = ['days' => [$day], 'rows' => [[
            'company_id' => 2,
            'schedule_area_id' => 4,
            'cells' => ['2026-07-13' => [
                'date_value' => '2026-07-13',
                'shifts' => [['start_at' => Carbon::parse('2026-07-13 10:00'), 'end_at' => Carbon::parse('2026-07-13 12:00')]],
            ]],
        ]]];

        [$roster, $result] = (new ScheduleDayService())->applyMetadata(['days' => [$day]], $board, [$meta]);

        $this->assertSame(['Clinic event'], $roster['days'][0]['holiday_labels']);
        $this->assertTrue($roster['days'][0]['has_day_note']);
        $this->assertSame(['11:00–11:30'], $result['days'][0]['blocked_labels']);
        $this->assertSame(1, $result['rows'][0]['cells']['2026-07-13']['blocked_shift_count']);
        $this->assertSame(['Keep reception clear.'], $result['rows'][0]['cells']['2026-07-13']['day_notes']);
    }
}
