<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Scheduling\ScheduleBudgetService;
use Carbon\Carbon;
use Tests\TestCase;

class ScheduleBudgetServiceTest extends TestCase
{
    public function test_it_projects_only_confirmed_rates_and_subtracts_unpaid_breaks(): void
    {
        $rate=new OdooScheduleRecord(['company_id'=>2,'employee_id'=>7,'hourly_rate'=>40,'currency'=>'AUD','effective_from'=>Carbon::parse('2026-07-01'),'effective_to'=>null,'source'=>'manager_confirmed']);
        $break=new OdooScheduleRecord(['odoo_slot_id'=>11,'start_time'=>'12:00','duration_minutes'=>30,'is_paid'=>false]);
        $budget=new OdooScheduleRecord(['company_id'=>2,'week_start'=>Carbon::parse('2026-07-13'),'amount'=>500,'currency'=>'AUD']);
        $shifts=[
            ['id'=>11,'company_id'=>2,'company'=>'Clinic','employee_id'=>7,'employee'=>'Alice','duration_minutes'=>480,'start_at'=>Carbon::parse('2026-07-13 09:00')],
            ['id'=>12,'company_id'=>2,'company'=>'Clinic','employee_id'=>null,'employee'=>null,'duration_minutes'=>240,'start_at'=>Carbon::parse('2026-07-14 09:00')],
        ];

        $result=(new ScheduleBudgetService())->project($shifts,[$rate],[$break],[$budget]);

        $this->assertSame(450,$result['shifts'][0]['payable_minutes']);
        $this->assertSame(300.0,$result['shifts'][0]['projected_cost']);
        $this->assertCount(1, $result['shifts']);
        $this->assertSame(300.0,$result['summary']['projected_cost']);
        $this->assertSame(200.0,$result['summary']['variance']);
        $this->assertSame(0,$result['summary']['unknown_shifts']);
        $this->assertArrayNotHasKey('open_shifts',$result['summary']);
        $this->assertTrue($result['summary']['totals_comparable']);
    }
}
