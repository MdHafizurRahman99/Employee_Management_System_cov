<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooScheduleRepository;
use App\Services\Scheduling\ScheduleUndoService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class ScheduleUndoServiceTest extends TestCase
{
    public function test_it_records_concurrency_safe_odoo_delete_operations(): void
    {
        $repository=Mockery::mock(OdooScheduleRepository::class);
        $planning=Mockery::mock(OdooManagerPlanningService::class);
        $planning->shouldReceive('getShiftSnapshot')->once()->with(41)->andReturn(['write_date_value'=>'2026-07-15 08:00:00']);
        $planning->shouldReceive('getShiftSnapshot')->once()->with(42)->andReturn(['write_date_value'=>'2026-07-15 08:01:00']);
        $repository->shouldReceive('createUndoBatch')->once()->withArgs(function(array $operations,string $label,?string $actor,?int $company):bool{
            return $label==='Auto schedule coverage' && $actor===null && $company===2 && $operations[1]['slot_id']===42 && $operations[1]['expected_write_date']==='2026-07-15 08:01:00';
        })->andReturn(['token'=>'11111111-1111-4111-8111-111111111111','label'=>'Auto schedule coverage','count'=>2,'expires_at'=>Carbon::parse('2026-07-15 10:00:00')]);

        $batch=(new ScheduleUndoService($repository))->recordCreatedSlots([41,42],'Auto schedule coverage',$planning,null,2);
        $this->assertSame(2,$batch['count']);
    }

    public function test_it_undoes_in_reverse_order_and_consumes_the_odoo_batch(): void
    {
        $repository=Mockery::mock(OdooScheduleRepository::class);
        $planning=Mockery::mock(OdooManagerPlanningService::class);
        $repository->shouldReceive('undoBatch')->once()->with('token')->andReturn(['id'=>9,'label'=>'Copy schedule period','operations'=>[
            ['type'=>'delete_created_slot','slot_id'=>41,'expected_write_date'=>'a'],
            ['type'=>'delete_created_slot','slot_id'=>42,'expected_write_date'=>'b'],
        ]]);
        $planning->shouldReceive('getShiftSnapshot')->once()->with(41)->andReturn(['write_date_value'=>'a']);
        $planning->shouldReceive('getShiftSnapshot')->once()->with(42)->andReturn(['write_date_value'=>'b']);
        $planning->shouldReceive('deleteShift')->once()->ordered()->with(42,'b');
        $planning->shouldReceive('deleteShift')->once()->ordered()->with(41,'a');
        $repository->shouldReceive('consumeUndoBatch')->once()->with(9);

        $result=(new ScheduleUndoService($repository))->undo('token',$planning);
        $this->assertSame(2,$result['undone']);
        $this->assertSame('Copy schedule period',$result['label']);
    }

    public function test_it_refuses_the_whole_undo_when_a_created_shift_was_edited(): void
    {
        $repository=Mockery::mock(OdooScheduleRepository::class);
        $planning=Mockery::mock(OdooManagerPlanningService::class);
        $repository->shouldReceive('undoBatch')->once()->andReturn(['id'=>9,'label'=>'Auto schedule','operations'=>[
            ['type'=>'delete_created_slot','slot_id'=>41,'expected_write_date'=>'original'],
        ]]);
        $planning->shouldReceive('getShiftSnapshot')->once()->with(41)->andReturn(['write_date_value'=>'newer']);
        $planning->shouldNotReceive('deleteShift');
        $repository->shouldNotReceive('consumeUndoBatch');

        $this->expectException(OdooException::class);
        (new ScheduleUndoService($repository))->undo('token',$planning);
    }
}
