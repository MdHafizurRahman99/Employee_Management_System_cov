@extends('layouts.admin.master')

@section('title', 'Schedule Day Details')

@section('css')
<style>
    .day-details{--ink:#18323d;--muted:#6b7d85;--line:#dce5e8;--sea:#176b5b;max-width:1480px;color:var(--ink)}
    .day-details-hero{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:22px 26px;border-radius:14px;background:linear-gradient(110deg,#173f48,#245d61);color:#fff;box-shadow:0 16px 34px rgba(22,60,70,.16)}.day-details-hero h1{font-size:1.55rem;font-weight:850;margin:0}.day-details-hero p{color:#cce0df;margin:5px 0 0}.week-nav{display:flex;align-items:center;gap:8px}.week-nav strong{white-space:nowrap}
    .day-details-layout{display:grid;grid-template-columns:360px 1fr;gap:18px;margin-top:18px}.day-panel,.week-timeline{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 5px 18px rgba(24,50,61,.06)}.panel-head{padding:16px 18px;border-bottom:1px solid var(--line);font-weight:850}.panel-body{padding:18px}.day-picker{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}.day-pick{border:1px solid var(--line);background:#f8fafb;border-radius:8px;padding:7px 2px;text-align:center;color:var(--ink);font-size:.7rem;font-weight:800}.day-pick strong{display:block;font-size:1rem}.day-pick.is-active{background:var(--sea);border-color:var(--sea);color:#fff}
    .timeline-day{display:grid;grid-template-columns:88px 1fr;min-height:105px;border-bottom:1px solid var(--line)}.timeline-day:last-child{border-bottom:0}.timeline-date{padding:17px 12px;text-align:center;background:#f8fafb;border-right:1px solid var(--line)}.timeline-date strong{display:block;font-size:1.35rem}.timeline-date span{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:850}.timeline-content{padding:13px 16px}.timeline-empty{color:#8a99a0;font-size:.82rem;padding-top:18px}.day-entry{display:flex;justify-content:space-between;gap:14px;padding:10px 12px;border:1px solid var(--line);border-left:4px solid #e2a229;border-radius:8px;margin-bottom:7px;background:#fffdfa}.day-entry:last-child{margin-bottom:0}.day-entry-title{font-weight:850}.day-entry-meta{font-size:.74rem;color:var(--muted);margin-top:3px}.day-entry-note{font-size:.82rem;margin-top:5px;color:#40545e}
    @media(max-width:991.98px){.day-details-layout{grid-template-columns:1fr}.day-details-hero{align-items:flex-start;flex-direction:column}}@media(max-width:575.98px){.day-details-hero{padding:18px}.week-nav{width:100%;justify-content:space-between}.timeline-day{grid-template-columns:68px 1fr}.day-details{padding-left:8px!important;padding-right:8px!important}}
</style>
@endsection

@section('content')
@php $companyNames=collect($companies)->pluck('name','id'); @endphp
<div class="container-fluid day-details">
    <header class="day-details-hero">
        <div><h1>Week notes & blocked time</h1><p>Give schedulers the operational context they need before placing a shift.</p></div>
        <div class="week-nav"><a class="btn btn-light btn-sm" href="{{ route('manager.schedule-days.index',['week'=>$weekStart->copy()->subWeek()->toDateString()]) }}"><i class="fas fa-chevron-left"></i></a><strong>{{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j, Y') }}</strong><a class="btn btn-light btn-sm" href="{{ route('manager.schedule-days.index',['week'=>$weekStart->copy()->addWeek()->toDateString()]) }}"><i class="fas fa-chevron-right"></i></a><a class="btn btn-outline-light btn-sm" href="{{ route('manager.shifts.create',['month'=>$weekStart->format('Y-m'),'day'=>$weekStart->toDateString()]) }}">Schedule</a></div>
    </header>
    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->has('schedule_day'))<div class="alert alert-danger mt-3">{{ $errors->first('schedule_day') }}</div>@endif
    @if($odooPlanningError)<div class="alert alert-warning mt-3">{{ $odooPlanningError }}</div>@endif
    @if($errors->any()&&!$errors->has('schedule_day'))<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif

    <div class="day-details-layout">
        <section class="day-panel"><div class="panel-head">Add day details</div><div class="panel-body">
            <form method="POST" action="{{ route('manager.schedule-days.store') }}">@csrf
                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                <input type="hidden" name="schedule_date" id="scheduleDayDate" value="{{ old('schedule_date',$weekStart->toDateString()) }}">
                <div class="day-picker mb-3">@foreach($days as $day)<button type="button" class="day-pick {{ old('schedule_date',$weekStart->toDateString())===$day->toDateString()?'is-active':'' }}" data-date="{{ $day->toDateString() }}">{{ $day->format('D') }}<strong>{{ $day->format('j') }}</strong></button>@endforeach</div>
                <div class="form-group"><label>Location</label><select class="form-control" name="company_id" id="dayCompany" required><option value="">Choose location</option>@foreach($companies as $company)<option value="{{ $company['id'] }}" @selected(old('company_id')==$company['id'])>{{ $company['name'] }}</option>@endforeach</select></div>
                <div class="form-group"><label>Area scope <span class="text-muted">(optional)</span></label><select class="form-control" name="schedule_area_id" id="dayArea"><option value="">Whole location</option>@foreach($areas as $area)<option value="{{ $area->id }}" data-company="{{ $area->company_id }}" @selected(old('schedule_area_id')==$area->id)>{{ $area->name }}</option>@endforeach</select></div>
                <div class="form-group"><label>Holiday or event label</label><input class="form-control" name="holiday_name" maxlength="120" value="{{ old('holiday_name') }}" placeholder="Public holiday"></div>
                <div class="form-group"><label>Scheduler note</label><textarea class="form-control" name="note" rows="3" maxlength="2000" placeholder="Delivery expected; keep access clear.">{{ old('note') }}</textarea></div>
                <label>Blocked time</label><div class="form-row"><div class="form-group col-6"><input class="form-control" name="blocked_start" type="time" value="{{ old('blocked_start') }}" aria-label="Blocked start time"></div><div class="form-group col-6"><input class="form-control" name="blocked_end" type="time" value="{{ old('blocked_end') }}" aria-label="Blocked end time"></div></div>
                <small class="form-text text-muted mb-3">Any Odoo shift overlapping this period is flagged in Week by Area.</small>
                <button class="btn btn-success btn-block" type="submit" {{ empty($companies)?'disabled':'' }}><i class="fas fa-calendar-plus mr-1"></i>Save day details</button>
            </form>
        </div></section>

        <section class="week-timeline"><div class="panel-head">Visible week</div>
            @foreach($days as $day)
                @php $dayEntries=$entries->get($day->toDateString(),collect()); @endphp
                <div class="timeline-day"><div class="timeline-date"><span>{{ $day->format('D') }}</span><strong>{{ $day->format('j') }}</strong><span>{{ $day->format('M') }}</span></div><div class="timeline-content">
                    @forelse($dayEntries as $entry)
                        <article class="day-entry"><div><div class="day-entry-title">{{ $entry->holiday_name ?: 'Schedule note' }}</div><div class="day-entry-meta">{{ $companyNames[$entry->company_id] ?? 'Location #'.$entry->company_id }}{{ $entry->area?' · '.$entry->area->name:' · Whole location' }}@if($entry->blocked_start) · <i class="fas fa-ban"></i> {{ substr($entry->blocked_start,0,5) }}–{{ substr($entry->blocked_end,0,5) }}@endif</div>@if($entry->note)<div class="day-entry-note">{{ $entry->note }}</div>@endif</div><form method="POST" action="{{ route('manager.schedule-days.destroy',$entry->id) }}" onsubmit="return confirm('Remove these day details?');">@csrf<input type="hidden" name="week" value="{{ $weekStart->toDateString() }}"><button class="btn btn-sm btn-link text-danger" title="Remove"><i class="fas fa-trash"></i></button></form></article>
                    @empty <div class="timeline-empty">No notes, holiday labels, or blocked time.</div> @endforelse
                </div></div>
            @endforeach
        </section>
    </div>
</div>
@endsection

@section('js')
<script>
document.querySelectorAll('.day-pick').forEach(function(button){button.addEventListener('click',function(){document.querySelectorAll('.day-pick').forEach(function(item){item.classList.remove('is-active')});button.classList.add('is-active');document.getElementById('scheduleDayDate').value=button.dataset.date})});
var company=document.getElementById('dayCompany'),area=document.getElementById('dayArea');function filterAreas(){var selected=company.value;Array.from(area.options).forEach(function(option,index){if(index===0)return;option.hidden=!!selected&&option.dataset.company!==selected});if(area.selectedOptions[0]&&area.selectedOptions[0].hidden)area.value=''}company.addEventListener('change',filterAreas);filterAreas();
</script>
@endsection
