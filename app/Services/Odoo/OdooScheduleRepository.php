<?php

namespace App\Services\Odoo;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OdooScheduleRepository
{
    public function __construct(private readonly OdooServiceAccount $odoo) {}

    public function areas(bool $activeOnly = true): Collection
    {
        $domain=$activeOnly?[["active","=",true]]:[];
        $rows=$this->search('ems.schedule.area',$domain,['id','name','company_id','role_id','color','sequence','active'],'sequence, name');
        $coverage=$this->search('ems.schedule.coverage',[],['id','area_id','weekday','minimum_people'],'weekday, id');
        return collect($rows)->map(fn(array $r)=>new OdooScheduleRecord(['id'=>(int)$r['id'],'name'=>$r['name'],'company_id'=>$this->id($r['company_id']??null),'odoo_role_id'=>$this->id($r['role_id']??null),'color'=>$r['color']??'#176b5b','sort_order'=>(int)($r['sequence']??0),'is_active'=>(bool)($r['active']??true),'coverageRequirements'=>collect($coverage)->filter(fn(array $c)=>$this->id($c['area_id']??null)===(int)$r['id'])->map(fn(array $c)=>new OdooScheduleRecord(['id'=>(int)$c['id'],'weekday'=>(int)($c['weekday']??0),'minimum_people'=>(int)($c['minimum_people']??0)]))->values()]));
    }

    public function upsertArea(array $data): int
    {
        $ids=$this->searchIds('ems.schedule.area',[["company_id","=",(int)$data['company_id']],["role_id","=",(int)$data['odoo_role_id']]],1);
        $values=['company_id'=>(int)$data['company_id'],'role_id'=>(int)$data['odoo_role_id'],'name'=>$data['name'],'color'=>$data['color'],'sequence'=>(int)$data['sort_order'],'active'=>true];
        $id=$ids?($this->write('ems.schedule.area',$ids,$values)?$ids[0]:$ids[0]):$this->create('ems.schedule.area',$values);
        foreach(range(0,6) as $weekday){$coverageIds=$this->searchIds('ems.schedule.coverage',[["area_id","=",$id],["weekday","=",(string)$weekday]],1);$v=['area_id'=>$id,'weekday'=>(string)$weekday,'minimum_people'=>(int)($data['coverage'][$weekday]??0)];$coverageIds?$this->write('ems.schedule.coverage',$coverageIds,$v):$this->create('ems.schedule.coverage',$v);}
        return $id;
    }
    public function updateArea(int $id,array $data): void {$this->write('ems.schedule.area',[$id],['name'=>$data['name'],'color'=>$data['color'],'sequence'=>(int)$data['sort_order'],'active'=>true]);foreach(range(0,6) as $w){$ids=$this->searchIds('ems.schedule.coverage',[["area_id","=",$id],["weekday","=",(string)$w]],1);$v=['area_id'=>$id,'weekday'=>(string)$w,'minimum_people'=>(int)($data['coverage'][$w]??0)];$ids?$this->write('ems.schedule.coverage',$ids,$v):$this->create('ems.schedule.coverage',$v);}}
    public function archiveArea(int $id): void {$this->write('ems.schedule.area',[$id],['active'=>false]);}

    public function dayEntries(Carbon $start,Carbon $end): Collection
    {
        $areas=$this->areas(false)->keyBy('id');
        return collect($this->search('ems.schedule.day.meta',[["schedule_date",">=",$start->toDateString()],["schedule_date","<=",$end->toDateString()]],['id','company_id','area_id','schedule_date','holiday_name','note','has_blocked_time','blocked_start','blocked_end'],'schedule_date, company_id, id'))->map(fn(array $r)=>new OdooScheduleRecord(['id'=>(int)$r['id'],'company_id'=>$this->id($r['company_id']??null),'schedule_area_id'=>$this->id($r['area_id']??null),'area'=>$areas->get($this->id($r['area_id']??null)),'schedule_date'=>Carbon::parse($r['schedule_date']),'holiday_name'=>($r['holiday_name']??null)?:null,'note'=>($r['note']??null)?:null,'blocked_start'=>($r['has_blocked_time']??false)?$this->floatToTime($r['blocked_start']??0):null,'blocked_end'=>($r['has_blocked_time']??false)?$this->floatToTime($r['blocked_end']??0):null]));
    }
    public function upsertDay(array $d): int {$domain=[["company_id","=",(int)$d['company_id']],["schedule_date","=",$d['schedule_date']],["area_id","=",!empty($d['schedule_area_id'])?(int)$d['schedule_area_id']:false]];$ids=$this->searchIds('ems.schedule.day.meta',$domain,1);$v=['company_id'=>(int)$d['company_id'],'area_id'=>!empty($d['schedule_area_id'])?(int)$d['schedule_area_id']:false,'schedule_date'=>$d['schedule_date'],'holiday_name'=>$d['holiday_name']??false,'note'=>$d['note']??false,'has_blocked_time'=>!empty($d['blocked_start'])&&!empty($d['blocked_end']),'blocked_start'=>$this->timeToFloat($d['blocked_start']??null),'blocked_end'=>$this->timeToFloat($d['blocked_end']??null)];return $ids?($this->write('ems.schedule.day.meta',$ids,$v)?$ids[0]:$ids[0]):$this->create('ems.schedule.day.meta',$v);}
    public function deleteDay(int $id): void {$this->unlink('ems.schedule.day.meta',[$id]);}

    public function complianceRules(): Collection {return collect($this->search('ems.schedule.compliance.rule',[],['id','company_id','break_required_after_minutes','minimum_break_minutes','maximum_shift_minutes','minimum_rest_minutes','active'],'company_id'))->map(fn(array $r)=>new OdooScheduleRecord(['id'=>(int)$r['id'],'company_id'=>$this->id($r['company_id']??null),'break_required_after_minutes'=>(int)$r['break_required_after_minutes'],'minimum_break_minutes'=>(int)$r['minimum_break_minutes'],'maximum_shift_minutes'=>(int)$r['maximum_shift_minutes'],'minimum_rest_minutes'=>(int)$r['minimum_rest_minutes'],'is_enabled'=>(bool)$r['active']]));}
    public function upsertComplianceRule(array $d): int {$ids=$this->searchIds('ems.schedule.compliance.rule',[["company_id","=",(int)$d['company_id']]],1);$v=['company_id'=>(int)$d['company_id'],'break_required_after_minutes'=>(int)$d['break_required_after_minutes'],'minimum_break_minutes'=>(int)$d['minimum_break_minutes'],'maximum_shift_minutes'=>(int)$d['maximum_shift_minutes'],'minimum_rest_minutes'=>(int)$d['minimum_rest_minutes'],'active'=>(bool)($d['is_enabled']??false)];return $ids?($this->write('ems.schedule.compliance.rule',$ids,$v)?$ids[0]:$ids[0]):$this->create('ems.schedule.compliance.rule',$v);}

    public function breaks(array $slotIds): Collection {if(!$slotIds)return collect();return collect($this->search('ems.schedule.shift.break',[["slot_id","in",array_values($slotIds)]],['id','slot_id','start_time','duration_minutes','is_paid','note'],'slot_id, start_time'))->map(fn(array $r)=>new OdooScheduleRecord(['id'=>(int)$r['id'],'odoo_slot_id'=>$this->id($r['slot_id']??null),'start_time'=>$this->floatToTime($r['start_time']??0),'duration_minutes'=>(int)$r['duration_minutes'],'is_paid'=>(bool)$r['is_paid'],'note'=>$r['note']?:null]));}
    public function createBreak(array $d): int {return $this->create('ems.schedule.shift.break',['slot_id'=>(int)$d['odoo_slot_id'],'start_time'=>$this->timeToFloat($d['start_time']),'duration_minutes'=>(int)$d['duration_minutes'],'is_paid'=>(bool)($d['is_paid']??false),'note'=>$d['note']??false]);}
    public function deleteBreak(int $id): void {$this->unlink('ems.schedule.shift.break',[$id]);}

    public function costRates(array $employeeIds,Carbon $start,Carbon $end): Collection {if(!$employeeIds)return collect();return collect($this->search('ems.schedule.cost.rate',[["employee_id","in",array_values($employeeIds)],["effective_from","<=",$end->toDateString()],"|",["effective_to","=",false],["effective_to",">=",$start->toDateString()]],['id','company_id','employee_id','hourly_rate','currency_id','effective_from','effective_to','source'],'effective_from desc, id desc'))->map(fn(array $r)=>new OdooScheduleRecord(['id'=>(int)$r['id'],'company_id'=>$this->id($r['company_id']??null),'employee_id'=>$this->id($r['employee_id']??null),'hourly_rate'=>(float)$r['hourly_rate'],'currency'=>$this->name($r['currency_id']??null),'effective_from'=>Carbon::parse($r['effective_from']),'effective_to'=>!empty($r['effective_to'])?Carbon::parse($r['effective_to']):null,'source'=>$r['source']]));}
    public function upsertRate(array $d): int {$old=$this->searchIds('ems.schedule.cost.rate',[["company_id","=",(int)$d['company_id']],["employee_id","=",(int)$d['employee_id']],["effective_to","=",false],["effective_from","<",$d['effective_from']]]);if($old)$this->write('ems.schedule.cost.rate',$old,['effective_to'=>Carbon::parse($d['effective_from'])->subDay()->toDateString()]);$currency=$this->currencyId($d['currency']);$ids=$this->searchIds('ems.schedule.cost.rate',[["company_id","=",(int)$d['company_id']],["employee_id","=",(int)$d['employee_id']],["effective_from","=",$d['effective_from']]],1);$v=['company_id'=>(int)$d['company_id'],'employee_id'=>(int)$d['employee_id'],'hourly_rate'=>(float)$d['hourly_rate'],'currency_id'=>$currency,'effective_from'=>$d['effective_from'],'effective_to'=>$d['effective_to']??false,'source'=>'manager_confirmed'];return $ids?($this->write('ems.schedule.cost.rate',$ids,$v)?$ids[0]:$ids[0]):$this->create('ems.schedule.cost.rate',$v);}
    public function weekBudgets(Carbon $week): Collection {return collect($this->search('ems.schedule.week.budget',[["week_start","=",$week->toDateString()]],['id','company_id','week_start','amount','currency_id'],'company_id'))->map(fn(array $r)=>new OdooScheduleRecord(['id'=>(int)$r['id'],'company_id'=>$this->id($r['company_id']??null),'week_start'=>Carbon::parse($r['week_start']),'amount'=>(float)$r['amount'],'currency'=>$this->name($r['currency_id']??null)]));}
    public function upsertBudget(array $d,Carbon $week): int {$ids=$this->searchIds('ems.schedule.week.budget',[["company_id","=",(int)$d['company_id']],["week_start","=",$week->toDateString()]],1);$v=['company_id'=>(int)$d['company_id'],'week_start'=>$week->toDateString(),'amount'=>(float)$d['amount'],'currency_id'=>$this->currencyId($d['currency'])];return $ids?($this->write('ems.schedule.week.budget',$ids,$v)?$ids[0]:$ids[0]):$this->create('ems.schedule.week.budget',$v);}

    public function templates(): Collection {return collect($this->search('ems.schedule.template',[["active","=",true]],['id','name','description','company_id','last_applied_at','last_applied_by','item_ids'],'create_date desc'))->map(fn(array $r)=>$this->templateRecord($r,false));}
    public function template(int $id): ?OdooScheduleRecord {$rows=$this->search('ems.schedule.template',[["id","=",$id],["active","=",true]],['id','name','description','company_id','last_applied_at','last_applied_by','item_ids'],null,1);return $rows?$this->templateRecord($rows[0],true):null;}
    public function createTemplate(array $data,array $shifts,Carbon $week): OdooScheduleRecord {$id=$this->create('ems.schedule.template',['name'=>$data['name'],'description'=>$data['description']??false,'company_id'=>(int)$data['company_id']]);foreach($shifts as $s){if((int)($s['company_id']??0)!==(int)$data['company_id'])continue;$this->create('ems.schedule.template.item',['template_id'=>$id,'day_offset'=>$week->diffInDays(Carbon::parse($s['shift_date_value']),false),'employee_id'=>!empty($s['employee_id'])?(int)$s['employee_id']:false,'role_id'=>(int)$s['role_id'],'work_location_id'=>!empty($s['work_location_id'])?(int)$s['work_location_id']:false,'start_time'=>$this->timeToFloat($s['start_time_value']),'end_time'=>$this->timeToFloat($s['end_time_value']),'title'=>$s['title_value']??false,'note'=>$s['note']??false]);}return $this->template($id)??throw new OdooException('Odoo did not return the saved template.');}
    public function markTemplateApplied(int $id): void {$this->write('ems.schedule.template',[$id],['last_applied_at'=>now()->utc()->format('Y-m-d H:i:s')]);}
    public function archiveTemplate(int $id): void {$this->write('ems.schedule.template',[$id],['active'=>false]);}

    /** @param array<int,array<string,mixed>> $operations @return array{token:string,label:string,count:int,expires_at:Carbon} */
    public function createUndoBatch(array $operations,string $label,?string $actorName=null,?int $companyId=null): array
    {
        if(!$operations)throw new OdooException('An undo batch requires at least one Odoo operation.');
        $token=(string)Str::uuid();$expires=now()->utc()->addMinutes(10);
        $this->create('ems.schedule.undo.batch',['token'=>$token,'name'=>$label,'company_id'=>$companyId?:false,'actor_name'=>$actorName?:false,'operation_count'=>count($operations),'payload_json'=>json_encode(['operations'=>array_values($operations)],JSON_THROW_ON_ERROR),'expires_at'=>$expires->format('Y-m-d H:i:s')]);
        return ['token'=>$token,'label'=>$label,'count'=>count($operations),'expires_at'=>$expires];
    }
    /** @return array<string,mixed>|null */
    public function undoBatch(string $token): ?array
    {
        $rows=$this->search('ems.schedule.undo.batch',[['token','=',$token],['consumed_at','=',false],['expires_at','>=',now()->utc()->format('Y-m-d H:i:s')]],['id','name','company_id','actor_name','operation_count','payload_json','expires_at'],null,1);
        if(!$rows)return null;$row=$rows[0];$payload=json_decode((string)($row['payload_json']??''),true,512,JSON_THROW_ON_ERROR);
        return ['id'=>(int)$row['id'],'label'=>(string)$row['name'],'company_id'=>$this->id($row['company_id']??null),'actor_name'=>($row['actor_name']??false)?:null,'operation_count'=>(int)$row['operation_count'],'operations'=>is_array($payload['operations']??null)?$payload['operations']:[],'expires_at'=>Carbon::parse($row['expires_at'].' UTC')];
    }
    public function consumeUndoBatch(int $id): void {$this->write('ems.schedule.undo.batch',[$id],['consumed_at'=>now()->utc()->format('Y-m-d H:i:s')]);}

    private function templateRecord(array $r,bool $load): OdooScheduleRecord {$items=$load?$this->search('ems.schedule.template.item',[["template_id","=",(int)$r['id']]],['id','day_offset','employee_id','role_id','company_id','work_location_id','start_time','end_time','title','note'],'day_offset, start_time'):[];return new OdooScheduleRecord(['id'=>(int)$r['id'],'name'=>$r['name'],'description'=>$r['description']?:null,'company_id'=>$this->id($r['company_id']??null),'last_applied_at'=>!empty($r['last_applied_at'])?Carbon::parse($r['last_applied_at'].' UTC')->timezone(config('app.timezone')):null,'items_count'=>count($r['item_ids']??[]),'items'=>collect($items)->map(fn(array $i)=>new OdooScheduleRecord(['id'=>(int)$i['id'],'day_offset'=>(int)$i['day_offset'],'employee_id'=>$this->id($i['employee_id']??null),'role_id'=>$this->id($i['role_id']??null),'company_id'=>$this->id($i['company_id']??null),'work_location_id'=>$this->id($i['work_location_id']??null),'work_location'=>$this->name($i['work_location_id']??null),'start_time'=>$this->floatToTime($i['start_time']),'end_time'=>$this->floatToTime($i['end_time']),'title'=>$i['title']?:null,'note'=>$i['note']?:null]))]);}

    private function search(string $model,array $domain,array $fields,?string $order=null,?int $limit=null): array {$kw=['fields'=>$fields];if($order)$kw['order']=$order;if($limit)$kw['limit']=$limit;$r=$this->odoo->executeKw($model,'search_read',[$domain],$kw);return is_array($r)?$r:[];}
    private function searchIds(string $model,array $domain,?int $limit=null): array {$kw=[];if($limit)$kw['limit']=$limit;$r=$this->odoo->executeKw($model,'search',[$domain],$kw);return is_array($r)?array_map('intval',$r):[];}
    private function create(string $model,array $values): int {$r=$this->odoo->executeKw($model,'create',[$values]);if(!is_numeric($r)||$r<1)throw new OdooException("Odoo did not create {$model}.");return (int)$r;}
    private function write(string $model,array $ids,array $values): bool {return (bool)$this->odoo->executeKw($model,'write',[$ids,$values]);}
    private function unlink(string $model,array $ids): void {if(!$this->odoo->executeKw($model,'unlink',[$ids]))throw new OdooException("Odoo did not delete {$model}.");}
    private function id(mixed $v): ?int {return is_array($v)&&is_numeric($v[0]??null)?(int)$v[0]:(is_numeric($v)?(int)$v:null);}
    private function name(mixed $v): string {return is_array($v)?(string)($v[1]??''):(string)$v;}
    private function timeToFloat(?string $t): float {if(!$t)return 0.0;[$h,$m]=array_pad(array_map('intval',explode(':',$t)),2,0);return $h+$m/60;}
    private function floatToTime(mixed $v): ?string {$v=(float)$v;if($v<0)return null;$minutes=(int)round($v*60);return sprintf('%02d:%02d',intdiv($minutes,60),$minutes%60);}
    private function currencyId(string $code): int {$ids=$this->searchIds('res.currency',[["name","=",strtoupper($code)]],1);if(!$ids)throw new OdooException('Odoo currency '.strtoupper($code).' is unavailable.');return $ids[0];}
}
