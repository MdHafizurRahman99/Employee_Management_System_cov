<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Scheduling\AutoScheduleService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class AutoScheduleServiceTest extends TestCase
{
    public function test_it_balances_coverage_around_leave_roles_and_weekly_hours(): void
    {
        $preview = (new AutoScheduleService())->preview(
            Carbon::parse('2026-07-20'),
            $this->pageData(),
            [$this->area(2)],
            [],
            $this->autoOptions()
        );

        $this->assertSame(2, $preview['summary']['positions_needed']);
        $this->assertSame(2, $preview['summary']['assigned']);
        $this->assertSame([13, 11], array_column($preview['proposals'], 'employee_id'));
        $this->assertSame('2026-07-20', $preview['proposals'][0]['date']);
        $this->assertSame(0, $preview['summary']['open']);
    }

    public function test_it_uses_open_shifts_and_respects_odoo_blocked_time(): void
    {
        $page = $this->pageData();
        $page['employees'] = [];
        $area = $this->area(1, 1);
        $blocked = new OdooScheduleRecord([
            'company_id' => 2,
            'schedule_area_id' => 4,
            'schedule_date' => Carbon::parse('2026-07-21'),
            'blocked_start' => '12:00',
            'blocked_end' => '15:00',
        ]);

        $preview = (new AutoScheduleService())->preview(
            Carbon::parse('2026-07-20'), $page, [$area], [$blocked], $this->autoOptions()
        );

        $this->assertSame('open', $preview['rows'][0]['status']);
        $this->assertSame('blocked', $preview['rows'][1]['status']);
        $this->assertCount(1, $preview['proposals']);
        $this->assertSame(1, $preview['summary']['blocked']);
    }

    public function test_it_skips_employee_diary_unavailability_from_odoo(): void
    {
        $page = $this->pageData();
        $page['employeeDiary'] = ['by_employee_date' => [
            13 => ['2026-07-20' => [[
                'entry_type' => 'unavailable',
                'is_all_day' => true,
                'start_time_value' => null,
                'end_time_value' => null,
            ]]],
        ]];

        $preview = (new AutoScheduleService())->preview(
            Carbon::parse('2026-07-20'),
            $page,
            [$this->area(1)],
            [],
            $this->autoOptions()
        );

        $this->assertSame(11, $preview['proposals'][0]['employee_id']);
    }

    public function test_it_keeps_unavailable_employees_as_last_resort_when_override_is_enabled(): void
    {
        $page = $this->pageData();
        $page['employeeDiary'] = ['by_employee_date' => [
            13 => ['2026-07-20' => [[
                'entry_type' => 'unavailable',
                'is_all_day' => true,
                'start_time_value' => null,
                'end_time_value' => null,
            ]]],
        ]];
        $options = $this->autoOptions();
        $options['allow_diary_override'] = true;

        $preview = (new AutoScheduleService())->preview(
            Carbon::parse('2026-07-20'),
            $page,
            [$this->area(1)],
            [],
            $options
        );

        $this->assertSame(11, $preview['proposals'][0]['employee_id']);
        $this->assertFalse($preview['proposals'][0]['diary_override']);
        $this->assertSame(0, $preview['summary']['diary_overrides']);
    }

    public function test_it_can_use_an_unavailable_employee_when_override_is_enabled_and_no_alternative_exists(): void
    {
        $page = $this->pageData();
        $page['weeklyRoster']['rows'][0]['scheduled_minutes'] = 2280;
        $page['employeeDiary'] = ['by_employee_date' => [
            13 => ['2026-07-20' => [[
                'entry_type' => 'unavailable',
                'is_all_day' => true,
                'start_time_value' => null,
                'end_time_value' => null,
            ]]],
        ]];
        $options = $this->autoOptions();
        $options['allow_diary_override'] = true;

        $preview = (new AutoScheduleService())->preview(
            Carbon::parse('2026-07-20'),
            $page,
            [$this->area(1)],
            [],
            $options
        );

        $this->assertSame(13, $preview['proposals'][0]['employee_id']);
        $this->assertTrue($preview['proposals'][0]['diary_override']);
        $this->assertSame('Diary unavailability preference overridden.', $preview['proposals'][0]['reason']);
        $this->assertSame(1, $preview['summary']['diary_overrides']);
    }

    public function test_it_prioritizes_an_employee_who_marked_the_shift_time_available(): void
    {
        $page = $this->pageData();
        $page['employeeDiary'] = ['by_employee_date' => [
            11 => ['2026-07-20' => [[
                'entry_type' => 'available',
                'is_all_day' => false,
                'start_time_value' => '09:00',
                'end_time_value' => '17:00',
            ]]],
        ]];

        $preview = (new AutoScheduleService())->preview(
            Carbon::parse('2026-07-20'),
            $page,
            [$this->area(1)],
            [],
            $this->autoOptions()
        );

        $this->assertSame(11, $preview['proposals'][0]['employee_id']);
    }

    public function test_apply_rolls_back_odoo_slots_after_a_later_failure(): void
    {
        $planning = Mockery::mock(OdooManagerPlanningService::class);
        $planning->shouldReceive('createShiftsReturningIds')->once()->andReturn([301]);
        $planning->shouldReceive('createShiftsReturningIds')->once()->andThrow(new OdooException('Odoo rejected the second proposal.'));
        $planning->shouldReceive('deleteShift')->once()->with(301);
        $preview = [
            'proposals' => [
                ['employee_id'=>11,'role_id'=>9,'company_id'=>2,'work_location_id'=>7,'date'=>'2026-07-20','start_time'=>'09:00','end_time'=>'17:00','area'=>'Front Desk','status'=>'assigned'],
                ['employee_id'=>13,'role_id'=>9,'company_id'=>2,'work_location_id'=>7,'date'=>'2026-07-21','start_time'=>'09:00','end_time'=>'17:00','area'=>'Front Desk','status'=>'assigned'],
            ],
        ];

        $this->expectException(OdooException::class);
        (new AutoScheduleService())->apply($preview, $planning);
    }

    /** @return array<string,mixed> */
    private function pageData(): array
    {
        return [
            'employees' => [
                ['id'=>11,'name'=>'Alex','company_id'=>2,'work_location_id'=>7,'planning_role_ids'=>[9]],
                ['id'=>12,'name'=>'Blair','company_id'=>2,'work_location_id'=>7,'planning_role_ids'=>[9]],
                ['id'=>13,'name'=>'Casey','company_id'=>2,'work_location_id'=>7,'planning_role_ids'=>[9]],
                ['id'=>14,'name'=>'Other role','company_id'=>2,'work_location_id'=>7,'planning_role_ids'=>[10]],
            ],
            'recentShifts' => [],
            'weeklyRoster' => ['rows' => [
                ['employee_id'=>11,'scheduled_minutes'=>1800,'cells'=>['2026-07-20'=>['time_off'=>[]]]],
                ['employee_id'=>12,'scheduled_minutes'=>300,'cells'=>['2026-07-20'=>['time_off'=>[['kind'=>'leave-approved']]]]],
                ['employee_id'=>13,'scheduled_minutes'=>600,'cells'=>['2026-07-20'=>['time_off'=>[]]]],
                ['employee_id'=>14,'scheduled_minutes'=>0,'cells'=>['2026-07-20'=>['time_off'=>[]]]],
            ]],
        ];
    }

    private function area(int $monday, int $tuesday = 0): OdooScheduleRecord
    {
        return new OdooScheduleRecord([
            'id'=>4,'name'=>'Front Desk','company_id'=>2,'odoo_role_id'=>9,'color'=>'#27705e','sort_order'=>1,'is_active'=>true,
            'coverageRequirements'=>collect([
                new OdooScheduleRecord(['weekday'=>0,'minimum_people'=>$monday]),
                new OdooScheduleRecord(['weekday'=>1,'minimum_people'=>$tuesday]),
            ]),
        ]);
    }

    /** @return array{company_id:int,work_location_id:int,start_time:string,end_time:string,max_weekly_hours:int,create_open_shifts:bool,allow_diary_override:bool} */
    private function autoOptions(): array
    {
        return ['company_id'=>2,'work_location_id'=>7,'start_time'=>'09:00','end_time'=>'17:00','max_weekly_hours'=>38,'create_open_shifts'=>true,'allow_diary_override'=>false];
    }
}
