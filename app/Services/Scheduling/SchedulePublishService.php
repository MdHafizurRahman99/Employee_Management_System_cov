<?php

namespace App\Services\Scheduling;

use App\Models\User;
use App\Notifications\SchedulePublishedNotification;
use App\Notifications\ShiftConfirmationResponseNotification;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SchedulePublishService
{
    private const FIELDS=['id','ems_publish_state','ems_published_at','ems_published_by','ems_requires_confirmation','ems_confirmation_status','ems_confirmation_note','ems_confirmation_responded_at','ems_confirmation_responded_by','ems_was_open_shift_claim','ems_claimed_at','ems_claimed_by','ems_notification_mode','ems_notification_status','ems_notification_sent_at','ems_reminder_sent_at','ems_notification_error'];
    public function __construct(private readonly OdooServiceAccount $odoo) {}

    public function decorateShifts(array $shifts): array
    {
        $ids=collect($shifts)->pluck('id')->filter()->map(fn($id)=>(int)$id)->unique()->values()->all();
        if(!$ids)return $shifts;
        if(collect($shifts)->every(fn(array $shift):bool=>array_key_exists('_odoo_schedule_meta',$shift))){$meta=collect($shifts)->mapWithKeys(fn(array $shift):array=>[(int)$shift['id']=>array_merge(['id'=>(int)$shift['id']],$shift['_odoo_schedule_meta'])]);}else{$rows=$this->odoo->executeKw('planning.slot','search_read',[[['id','in',$ids]]],['fields'=>self::FIELDS]);$meta=collect(is_array($rows)?$rows:[])->keyBy('id');}
        return array_map(function(array $shift)use($meta):array{$m=$meta->get((int)($shift['id']??0),[]);$state=(string)($m['ems_publish_state']??'unpublished');$shift['is_published']=$state==='published';$shift['was_published']=$state==='updated';$shift['publish_state']=$state;$shift['publish_state_label']=ucfirst($state);$shift['requires_confirmation']=(bool)($m['ems_requires_confirmation']??false);$shift['notification_mode']=(string)($m['ems_notification_mode']??'');$shift['published_at_label']=$this->dateLabel($m['ems_published_at']??null);$shift['confirmation_status']=$m['ems_confirmation_status']??null;$shift['confirmation_note']=($m['ems_confirmation_note']??null)?:null;$shift['confirmation_responded_at_label']=$this->dateLabel($m['ems_confirmation_responded_at']??null);$shift['was_open_shift_claim']=(bool)($m['ems_was_open_shift_claim']??false);$shift['claimed_at_label']=$this->dateLabel($m['ems_claimed_at']??null);$shift['notification_status']=$m['ems_notification_status']??null;$shift['notified_at_label']=$this->dateLabel($m['ems_notification_sent_at']??null);$shift['reminder_sent_at_label']=$this->dateLabel($m['ems_reminder_sent_at']??null);$shift['notification_error']=($m['ems_notification_error']??null)?:null;return $shift;},$shifts);
    }

    public function publishShifts(array $shifts,?User $publisher,bool $requiresConfirmation=false,string $notificationMode=''): int
    {
        $publishable=array_values(array_filter($shifts,fn(array $s):bool=>(int)($s['id']??0)>0));$now=now()->utc()->format('Y-m-d H:i:s');
        foreach($publishable as $shift){$id=(int)$shift['id'];$values=['ems_publish_state'=>'published','ems_published_at'=>$now,'ems_published_by'=>$publisher?->odoo_user_id?:false,'ems_requires_confirmation'=>$requiresConfirmation,'ems_notification_mode'=>$notificationMode?:false,'ems_confirmation_status'=>$requiresConfirmation?'pending':'not_required','ems_confirmation_responded_at'=>false,'ems_confirmation_responded_by'=>false,'ems_confirmation_note'=>false,'ems_notification_status'=>in_array($notificationMode,['notify_email_app','notify_all'],true)?'pending':'not_requested','ems_notification_sent_at'=>false,'ems_notification_error'=>false];$this->writeSlot($id,$values);if(in_array($notificationMode,['notify_email_app','notify_all'],true))$this->notifyAssignedEmployees(array_merge($shift,['requires_confirmation'=>$requiresConfirmation]));}
        return count($publishable);
    }

    public function respondToShift(int $slotId,User $employee,string $status,?string $note=null): void
    {
        if(!in_array($status,['accepted','declined'],true))throw new \InvalidArgumentException('Unsupported shift response.');$meta=$this->slot($slotId);if(!$meta||!($meta['ems_requires_confirmation']??false)||($meta['ems_confirmation_status']??'')!=='pending')throw new \RuntimeException('This shift is not awaiting confirmation.');$this->writeSlot($slotId,['ems_confirmation_status'=>$status,'ems_confirmation_responded_at'=>now()->utc()->format('Y-m-d H:i:s'),'ems_confirmation_responded_by'=>$employee->odoo_user_id?:false,'ems_confirmation_note'=>$status==='declined'?trim((string)$note):false]);$managerId=$this->many2oneId($meta['ems_published_by']??null);$manager=$managerId?User::query()->where('odoo_user_id',$managerId)->first():null;if($manager){try{$manager->notify(new ShiftConfirmationResponseNotification($slotId,(string)($employee->name?:$employee->email?:'Employee'),$status,$note));}catch(\Throwable $e){Log::warning('Shift response email failed',['slot_id'=>$slotId,'message'=>$e->getMessage()]);}}
    }

    public function sendConfirmationReminder(array $shift,User $manager): int
    {
        $id=(int)($shift['id']??0);$meta=$this->slot($id);if(!$meta||!($meta['ems_requires_confirmation']??false)||($meta['ems_confirmation_status']??'')!=='pending')throw new \RuntimeException('This shift is not awaiting employee confirmation.');$recipients=User::query()->where('odoo_employee_id',(int)($shift['employee_id']??0))->get();if($recipients->isEmpty()){$this->writeSlot($id,['ems_notification_status'=>'unavailable','ems_notification_error'=>'No application user matches the assigned Odoo employee.']);return 0;}$sent=0;foreach($recipients as $recipient){try{$recipient->notify(new SchedulePublishedNotification(array_merge($shift,['requires_confirmation'=>true]),true));$sent++;}catch(\Throwable $e){$this->writeSlot($id,['ems_notification_status'=>'failed','ems_notification_error'=>$e->getMessage()]);}}if($sent)$this->writeSlot($id,['ems_notification_status'=>'delivered','ems_reminder_sent_at'=>now()->utc()->format('Y-m-d H:i:s'),'ems_notification_error'=>false]);return $sent;
    }

    public function recordOpenShiftClaim(int $slotId,User $employee): void {$this->writeSlot($slotId,['ems_was_open_shift_claim'=>true,'ems_claimed_at'=>now()->utc()->format('Y-m-d H:i:s'),'ems_claimed_by'=>$employee->odoo_user_id?:false]);}

    private function notifyAssignedEmployees(array $shift): void {$id=(int)$shift['id'];$recipients=User::query()->where('odoo_employee_id',(int)($shift['employee_id']??0))->get();if($recipients->isEmpty()){$this->writeSlot($id,['ems_notification_status'=>'unavailable','ems_notification_error'=>'No application user matches the assigned Odoo employee.']);return;}$sent=0;foreach($recipients as $recipient){try{$recipient->notify(new SchedulePublishedNotification($shift));$sent++;}catch(\Throwable $e){$this->writeSlot($id,['ems_notification_status'=>'failed','ems_notification_error'=>$e->getMessage()]);}}if($sent)$this->writeSlot($id,['ems_notification_status'=>'delivered','ems_notification_sent_at'=>now()->utc()->format('Y-m-d H:i:s'),'ems_notification_error'=>false]);}
    private function slot(int $id):?array{$rows=$this->odoo->executeKw('planning.slot','search_read',[[['id','=',$id]]],['fields'=>self::FIELDS,'limit'=>1]);return is_array($rows)&&isset($rows[0])?$rows[0]:null;}
    private function writeSlot(int $id,array $values):void{if(!$this->odoo->executeKw('planning.slot','write',[[$id],$values],['context'=>['skip_ems_publish_state'=>true]]))throw new OdooException('Odoo did not update schedule metadata.');}
    private function dateLabel(mixed $value):?string{if(!is_string($value)||$value==='')return null;try{return Carbon::parse($value,'UTC')->timezone(config('app.timezone'))->format('d M Y h:i A');}catch(\Throwable){return null;}}
    private function many2oneId(mixed $value):?int{return is_array($value)&&is_numeric($value[0]??null)?(int)$value[0]:null;}
}
