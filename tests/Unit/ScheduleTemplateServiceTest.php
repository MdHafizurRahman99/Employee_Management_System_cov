<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Scheduling\ScheduleTemplateService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class ScheduleTemplateServiceTest extends TestCase
{
    public function test_preview_marks_employee_time_overlaps_as_conflicts(): void
    {
        $template = $this->templateWithItems([
            ['day_offset'=>0,'employee_id'=>35,'role_id'=>9,'company_id'=>2,'start_time'=>'09:00','end_time'=>'17:00'],
            ['day_offset'=>1,'employee_id'=>36,'role_id'=>9,'company_id'=>2,'start_time'=>'09:00','end_time'=>'17:00'],
        ]);
        $preview = (new ScheduleTemplateService())->preview($template, Carbon::parse('2026-07-20'), [[
            'employee_id'=>35,'shift_date_value'=>'2026-07-20','start_time_value'=>'12:00','end_time_value'=>'18:00',
        ]]);

        $this->assertSame(2, $preview['total']);
        $this->assertSame(1, $preview['conflicts']);
        $this->assertSame(1, $preview['ready']);
        $this->assertTrue($preview['rows'][0]['has_conflict']);
    }

    public function test_apply_rolls_back_created_odoo_slots_after_a_later_failure(): void
    {
        $template = $this->templateWithItems([
            ['day_offset'=>0,'employee_id'=>35,'role_id'=>9,'company_id'=>2,'start_time'=>'09:00','end_time'=>'17:00'],
            ['day_offset'=>1,'employee_id'=>36,'role_id'=>9,'company_id'=>2,'start_time'=>'09:00','end_time'=>'17:00'],
        ]);
        $planning = Mockery::mock(OdooManagerPlanningService::class);
        $planning->shouldReceive('createShiftsReturningIds')->once()->andReturn([101]);
        $planning->shouldReceive('createShiftsReturningIds')->once()->andThrow(new OdooException('Odoo rejected the second shift.'));
        $planning->shouldReceive('deleteShift')->once()->with(101);

        $this->expectException(OdooException::class);
        (new ScheduleTemplateService())->apply($template, Carbon::parse('2026-07-20'), [], $planning, null, false);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function templateWithItems(array $items): OdooScheduleRecord
    {
        return new OdooScheduleRecord(['id'=>1,'name'=>'Standard Week','items'=>collect(array_map(fn(array $item)=>new OdooScheduleRecord($item),$items))]);
    }
}
