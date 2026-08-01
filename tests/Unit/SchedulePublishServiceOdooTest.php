<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooServiceAccount;
use App\Services\Scheduling\SchedulePublishService;
use Mockery;
use Tests\TestCase;

class SchedulePublishServiceOdooTest extends TestCase
{
    public function test_publish_state_is_written_to_odoo_planning_slot(): void
    {
        $odoo=Mockery::mock(OdooServiceAccount::class);$written=[];
        $odoo->shouldReceive('executeKw')->once()->withArgs(function(string $model,string $method,array $args,array $kwargs)use(&$written):bool{$written=$args[1];return $model==='planning.slot'&&$method==='write'&&$args[0]===[71]&&$kwargs===['context'=>['skip_ems_publish_state'=>true]];})->andReturn(true);
        $user=new User(['name'=>'Manager']);$user->odoo_user_id=44;

        $count=(new SchedulePublishService($odoo))->publishShifts([['id'=>71,'write_date_value'=>'2026-07-15 01:00:00']],$user,false,'mark_only');

        $this->assertSame(1,$count);
        $this->assertSame('published',$written['ems_publish_state']);
        $this->assertSame(44,$written['ems_published_by']);
    }

    public function test_embedded_odoo_metadata_decorates_shift_without_another_database(): void
    {
        $odoo=Mockery::mock(OdooServiceAccount::class);
        $shift=(new SchedulePublishService($odoo))->decorateShifts([['id'=>71,'_odoo_schedule_meta'=>['ems_publish_state'=>'published','ems_requires_confirmation'=>true,'ems_confirmation_status'=>'pending']]])[0];
        $this->assertTrue($shift['is_published']);
        $this->assertSame('pending',$shift['confirmation_status']);
    }
}
