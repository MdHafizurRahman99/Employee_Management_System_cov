<?php

namespace App\Services\Scheduling;

use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerPlanningService;
use App\Services\Odoo\OdooScheduleRepository;

class ScheduleUndoService
{
    public function __construct(private readonly OdooScheduleRepository $repository) {}

    /** @param array<int,int> $slotIds @return array{token:string,label:string,count:int,expires_at:\Carbon\Carbon} */
    public function recordCreatedSlots(array $slotIds,string $label,OdooManagerPlanningService $planning,?User $actor=null,?int $companyId=null): array
    {
        $operations=[];$uniqueIds=array_values(array_unique(array_map('intval',$slotIds)));
        foreach($uniqueIds as $slotId){
            $snapshot=$planning->getShiftSnapshot($slotId);
            if(!$snapshot)throw new OdooException('Odoo created the shifts, but an undo snapshot could not be read.');
            $operations[]=['type'=>'delete_created_slot','slot_id'=>$slotId,'expected_write_date'=>(string)($snapshot['write_date_value']??'')];
        }
        if(!$operations)throw new OdooException('Odoo created the shifts, but their undo snapshots could not be read.');
        return $this->repository->createUndoBatch($operations,$label,$actor?->name,$companyId);
    }

    /** @return array{undone:int,label:string} */
    public function undo(string $token,OdooManagerPlanningService $planning): array
    {
        $batch=$this->repository->undoBatch($token);
        if(!$batch)throw new OdooException('This undo action has expired, was already used, or is unavailable.');
        foreach($batch['operations'] as $operation){
            if(($operation['type']??'')!=='delete_created_slot')throw new OdooException('This undo operation type is not supported.');
            $current=$planning->getShiftSnapshot((int)$operation['slot_id']);
            if(!$current)throw new OdooException('A shift in this undo batch no longer exists. Nothing was changed.');
            $expected=(string)($operation['expected_write_date']??'');$actual=(string)($current['write_date_value']??'');
            if($expected!=='' && $actual!==$expected)throw new OdooException('A shift in this undo batch was edited after creation. Nothing was changed.');
        }
        $undone=0;
        foreach(array_reverse($batch['operations']) as $operation){
            $planning->deleteShift((int)$operation['slot_id'],(string)($operation['expected_write_date']??''));
            $undone++;
        }
        $this->repository->consumeUndoBatch((int)$batch['id']);
        return ['undone'=>$undone,'label'=>(string)$batch['label']];
    }
}
