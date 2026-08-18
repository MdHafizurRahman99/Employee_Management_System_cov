<?php

namespace Tests\Unit;

use Carbon\Carbon;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Odoo\OdooServiceAccount;
use Mockery;
use Tests\TestCase;

class OdooScheduleRepositoryTest extends TestCase
{
    public function test_it_maps_odoo_areas_and_coverage_without_laravel_storage(): void
    {
        $odoo=Mockery::mock(OdooServiceAccount::class);
        $odoo->shouldReceive('executeKw')->once()->with('ems.schedule.area','search_read',[[['active','=',true]]],Mockery::on(fn(array $k):bool=>in_array('role_id',$k['fields'],true)))->andReturn([['id'=>4,'name'=>'Reception','company_id'=>[2,'Clinic'],'role_id'=>[9,'Front Desk'],'color'=>'#176b5b','sequence'=>3,'active'=>true]]);
        $odoo->shouldReceive('executeKw')->once()->with('ems.schedule.coverage','search_read',[[]],Mockery::type('array'))->andReturn([['id'=>7,'area_id'=>[4,'Reception'],'weekday'=>'0','minimum_people'=>2]]);

        $areas=(new OdooScheduleRepository($odoo))->areas();

        $this->assertSame(4,$areas[0]->id);
        $this->assertSame(9,$areas[0]->odoo_role_id);
        $this->assertSame(2,$areas[0]->coverageRequirements[0]->minimum_people);
    }

    public function test_it_persists_and_reads_undo_payloads_only_through_odoo(): void
    {
        Carbon::setTestNow('2026-07-15 09:00:00');
        $odoo=Mockery::mock(OdooServiceAccount::class);
        $capturedToken=null;
        $odoo->shouldReceive('executeKw')->once()->with('ems.schedule.undo.batch','create',Mockery::on(function(array $args)use(&$capturedToken):bool{
            $values=$args[0]??[];$capturedToken=$values['token']??null;$payload=json_decode($values['payload_json']??'',true);
            return is_string($capturedToken) && ($values['company_id']??null)===2 && ($payload['operations'][0]['slot_id']??null)===41;
        }))->andReturn(8);
        $repository=new OdooScheduleRepository($odoo);
        $batch=$repository->createUndoBatch([['type'=>'delete_created_slot','slot_id'=>41,'expected_write_date'=>'stamp']],'Create shift','Manager',2);

        $odoo->shouldReceive('executeKw')->once()->with('ems.schedule.undo.batch','search_read',Mockery::on(fn(array $args):bool=>($args[0][0][2]??null)===$capturedToken),Mockery::type('array'))->andReturn([[
            'id'=>8,'name'=>'Create shift','company_id'=>[2,'Clinic'],'actor_name'=>'Manager','operation_count'=>1,
            'payload_json'=>json_encode(['operations'=>[['type'=>'delete_created_slot','slot_id'=>41,'expected_write_date'=>'stamp']]]),'expires_at'=>'2026-07-15 09:10:00',
        ]]);
        $loaded=$repository->undoBatch($batch['token']);

        $this->assertSame(41,$loaded['operations'][0]['slot_id']);
        $this->assertSame(2,$loaded['company_id']);
        Carbon::setTestNow();
    }

    public function test_it_creates_multiple_team_calendar_events_on_the_same_day(): void
    {
        $odoo=Mockery::mock(OdooServiceAccount::class);
        $odoo->shouldReceive('executeKw')->once()->with('calendar.event','fields_get',[],['attributes'=>['type']])->andReturn([
            'name'=>['type'=>'char'],'start'=>['type'=>'datetime'],'stop'=>['type'=>'datetime'],
            'allday'=>['type'=>'boolean'],'description'=>['type'=>'html'],'notes'=>['type'=>'html'],
        ]);
        $odoo->shouldReceive('executeKw')->once()->with('calendar.event','create',Mockery::on(fn(array $args):bool=>
            ($args[0]['name']??null)==='Morning briefing'
            && str_contains((string)($args[0]['notes']??''),'EMS_TEAM_CALENDAR:')
        ))->andReturn(101);
        $odoo->shouldReceive('executeKw')->once()->with('calendar.event','create',Mockery::on(fn(array $args):bool=>
            ($args[0]['name']??null)==='Afternoon review'
            && str_contains((string)($args[0]['notes']??''),'EMS_TEAM_CALENDAR:')
        ))->andReturn(102);
        $repository=new OdooScheduleRepository($odoo);
        $base=['company_id'=>2,'schedule_date'=>'2026-08-18','schedule_area_id'=>null,'note'=>null];

        $first=$repository->createTeamCalendarEvent($base+['holiday_name'=>'Morning briefing','blocked_start'=>'09:00','blocked_end'=>'10:00']);
        $second=$repository->createTeamCalendarEvent($base+['holiday_name'=>'Afternoon review','blocked_start'=>'15:00','blocked_end'=>'16:00']);

        $this->assertSame(101,$first);
        $this->assertSame(102,$second);
    }
}
