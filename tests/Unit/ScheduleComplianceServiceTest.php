<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Scheduling\ScheduleComplianceService;
use Carbon\Carbon;
use Tests\TestCase;

class ScheduleComplianceServiceTest extends TestCase
{
    public function test_it_flags_missing_break_long_shift_and_short_rest(): void
    {
        $rule = new OdooScheduleRecord(['company_id'=>2,'break_required_after_minutes'=>300,'minimum_break_minutes'=>30,'maximum_shift_minutes'=>600,'minimum_rest_minutes'=>600,'is_enabled'=>true]);
        $break = new OdooScheduleRecord(['odoo_slot_id'=>11,'start_time'=>'12:00','duration_minutes'=>20,'is_paid'=>false]);
        $shifts = [
            ['id'=>11,'company_id'=>2,'employee_id'=>7,'duration_minutes'=>480,'start_at'=>Carbon::parse('2026-07-13 09:00'),'end_at'=>Carbon::parse('2026-07-13 17:00')],
            ['id'=>12,'company_id'=>2,'employee_id'=>7,'duration_minutes'=>660,'start_at'=>Carbon::parse('2026-07-13 19:00'),'end_at'=>Carbon::parse('2026-07-14 06:00')],
        ];

        $result = (new ScheduleComplianceService())->evaluateShiftList($shifts, [$rule], [$break]);

        $this->assertSame(20, $result['shifts'][0]['planned_break_minutes']);
        $this->assertArrayHasKey('missing_break', $result['shifts'][0]['compliance_warnings']);
        $this->assertArrayHasKey('long_shift', $result['shifts'][1]['compliance_warnings']);
        $this->assertArrayHasKey('short_rest', $result['shifts'][1]['compliance_warnings']);
        $this->assertSame(2, $result['summary']['missing_breaks']);
        $this->assertSame(1, $result['summary']['long_shifts']);
        $this->assertSame(1, $result['summary']['short_rest']);
        $this->assertSame(2, $result['summary']['violation_shifts']);
    }
}
