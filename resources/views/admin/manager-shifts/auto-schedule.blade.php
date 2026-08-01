@extends('layouts.admin.master')

@section('title')Auto Schedule@endsection

@section('content')
<style>
    .auto-workspace{--auto-ink:#182321;--auto-deep:#17342f;--auto-green:#2b735f;--auto-mint:#dff0e9;--auto-safety:#e5a63b;--auto-paper:#f4f1e8;--auto-line:#d8d4c9;color:var(--auto-ink)}
    .auto-hero{position:relative;overflow:hidden;display:flex;justify-content:space-between;gap:24px;align-items:flex-end;padding:27px 29px;border-radius:18px;background:var(--auto-deep);color:#fff;box-shadow:0 18px 42px rgba(23,52,47,.16)}
    .auto-hero:after{content:"";position:absolute;right:-55px;top:-75px;width:220px;height:220px;border:38px solid rgba(229,166,59,.17);border-radius:50%}.auto-hero>div{position:relative;z-index:1}
    .auto-kicker{font-size:.68rem;font-weight:900;letter-spacing:.17em;text-transform:uppercase;color:#a7d7c8}.auto-hero h1{margin:.35rem 0 .3rem;font:800 2rem/1.05 Georgia,'Times New Roman',serif}.auto-hero p{max-width:690px;margin:0;color:#d0dfda;font-size:.88rem}
    .auto-step{display:inline-flex;align-items:center;gap:7px;margin-top:13px;padding:6px 10px;border:1px solid rgba(255,255,255,.18);border-radius:999px;font-size:.72rem;font-weight:800}.auto-step b{display:grid;place-items:center;width:19px;height:19px;border-radius:50%;background:var(--auto-safety);color:#37250a}
    .auto-layout{display:grid;grid-template-columns:330px minmax(0,1fr);gap:17px;margin-top:17px}.auto-panel{background:#fff;border:1px solid var(--auto-line);border-radius:14px;overflow:hidden;box-shadow:0 7px 22px rgba(30,48,43,.05)}.auto-panel-head{padding:14px 17px;border-bottom:1px solid var(--auto-line);background:#faf9f5;font-weight:900}.auto-panel-body{padding:17px}
    .auto-config label{font-size:.72rem;font-weight:900;letter-spacing:.04em;text-transform:uppercase;color:#5f6d68}.auto-config .form-control{border-color:#d5d2c8;border-radius:9px}.auto-config-note{padding:11px 12px;border-left:3px solid var(--auto-safety);background:#fff7e7;color:#6d5123;font-size:.77rem;line-height:1.45}
    .auto-metrics{display:grid;grid-template-columns:repeat(6,minmax(95px,1fr));gap:10px;margin-bottom:15px}.auto-metric{padding:13px 14px;border:1px solid var(--auto-line);border-radius:11px;background:var(--auto-paper)}.auto-metric span{display:block;color:#69736f;font-size:.65rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.auto-metric strong{display:block;margin-top:2px;font:800 1.55rem/1.1 Georgia,'Times New Roman',serif}.auto-metric.is-ready{background:var(--auto-mint);border-color:#bad9cf}.auto-metric.is-alert{background:#fff0dd;border-color:#eccb9a}
    .auto-table{border:1px solid var(--auto-line);border-radius:11px;overflow:hidden}.auto-row{display:grid;grid-template-columns:105px minmax(150px,1.1fr) 115px minmax(150px,1fr) 105px;gap:12px;align-items:center;padding:11px 13px;border-top:1px solid #ebe8df;font-size:.78rem}.auto-row:first-child{border-top:0}.auto-row-head{background:#f1efe8;color:#68726e;font-size:.64rem;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.auto-area{display:flex;align-items:center;gap:8px;font-weight:800}.auto-area-dot{width:9px;height:30px;border-radius:5px}.auto-person{font-weight:800}.auto-reason{display:block;color:#7a7f7d;font-size:.7rem;margin-top:2px}.auto-status{display:inline-flex;justify-content:center;padding:5px 8px;border-radius:999px;font-size:.67rem;font-weight:900;text-transform:uppercase;letter-spacing:.04em;background:#e0f0e9;color:#23644f}.auto-status.is-open{background:#e9e6f8;color:#514a9b}.auto-status.is-blocked,.auto-status.is-unfilled{background:#ffeadc;color:#9b4d27}
    .auto-apply{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-top:15px;padding:15px 16px;border:1px solid #bad9cf;border-radius:12px;background:#edf7f3}.auto-apply strong{display:block}.auto-apply small{color:#5f716b}.auto-empty{display:grid;place-items:center;min-height:360px;padding:45px;text-align:center;background:linear-gradient(135deg,#fbfaf6,#f2f0e8)}.auto-empty-icon{display:grid;place-items:center;width:70px;height:70px;border:1px solid #c9c6bd;border-radius:50%;color:var(--auto-green);font-size:1.7rem;margin-bottom:15px}
    @media(max-width:1050px){.auto-layout{grid-template-columns:1fr}.auto-metrics{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:720px){.auto-hero{align-items:flex-start;flex-direction:column}.auto-metrics{grid-template-columns:1fr 1fr}.auto-row{grid-template-columns:1fr 1fr}.auto-row-head{display:none}.auto-apply{align-items:flex-start;flex-direction:column}}
</style>

<div class="container-fluid auto-workspace">
    <header class="auto-hero">
        <div>
            <div class="auto-kicker">Coverage planning engine</div>
            <h1>Auto Schedule</h1>
            <p>Turn configured Odoo coverage gaps into a balanced draft. Availability, approved leave, role eligibility, overlaps, blocked time, and weekly-hour limits are checked before a shift is proposed.</p>
            <span class="auto-step"><b>1</b> Configure</span> <span class="auto-step"><b>2</b> Review every proposal</span> <span class="auto-step"><b>3</b> Create Odoo shifts</span>
        </div>
        <div><a class="btn btn-light btn-sm" href="{{ route('manager.shifts.create',['month'=>$weekStart->format('Y-m'),'day'=>$weekStart->toDateString(),'view'=>'area']) }}"><i class="fas fa-arrow-left mr-1"></i>Week by Area</a></div>
    </header>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->has('auto_schedule'))<div class="alert alert-danger mt-3">{{ $errors->first('auto_schedule') }}</div>@endif
    @if($odooPlanningError)<div class="alert alert-warning mt-3">{{ $odooPlanningError }}</div>@endif

    <div class="auto-layout">
        <aside class="auto-panel auto-config">
            <div class="auto-panel-head"><i class="fas fa-sliders-h mr-2"></i>Draft controls</div>
            <div class="auto-panel-body">
                <form method="GET" action="{{ route('manager.auto-schedule.index') }}">
                    <input type="hidden" name="preview" value="1">
                    <div class="form-group"><label>Schedule week</label><input class="form-control" type="date" name="week" value="{{ old('week',$weekStart->toDateString()) }}" required></div>
                    <div class="form-group"><label>Odoo location</label><select class="form-control" name="company_id" required><option value="">Choose location</option>@foreach($companies as $company)<option value="{{ $company['id'] }}" @selected((int)$options['company_id']===(int)$company['id'])>{{ $company['name'] }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Work location</label><select class="form-control" name="work_location_id" required><option value="">Choose physical workplace</option>@foreach($workLocations as $location)<option value="{{ $location['id'] }}" data-company-id="{{ $location['company_id'] }}" @selected((int)$options['work_location_id']===(int)$location['id'])>{{ $location['name'] }}{{ $location['address'] ? ' · '.$location['address'] : '' }}</option>@endforeach</select></div>
                    <div class="form-row"><div class="form-group col-6"><label>Default start</label><input class="form-control" type="time" name="start_time" value="{{ $options['start_time'] }}" required></div><div class="form-group col-6"><label>Default end</label><input class="form-control" type="time" name="end_time" value="{{ $options['end_time'] }}" required></div></div>
                    <div class="form-group"><label>Weekly hours ceiling</label><div class="input-group"><input class="form-control" type="number" name="max_weekly_hours" min="1" max="80" value="{{ $options['max_weekly_hours'] }}" required><div class="input-group-append"><span class="input-group-text">hours</span></div></div></div>
                    <div class="custom-control custom-switch mb-3"><input class="custom-control-input" id="createOpenShifts" type="checkbox" name="create_open_shifts" value="1" @checked($options['create_open_shifts'])><label class="custom-control-label text-capitalize" for="createOpenShifts">Create open shifts when nobody is eligible</label></div>
                    <div class="custom-control custom-switch mb-3"><input class="custom-control-input" id="allowDiaryOverride" type="checkbox" name="allow_diary_override" value="1" @checked($options['allow_diary_override'])><label class="custom-control-label" for="allowDiaryOverride">Allow employee diary preference overrides</label><small class="form-text text-muted">When enabled, auto-scheduling may assign an employee during a diary unavailability entry. Approved leave and shift conflicts still block assignment.</small></div>
                    <div class="auto-config-note mb-3"><strong>Preview first.</strong> This screen holds no schedule records in Laravel. Only the final approved proposals become Odoo <code>planning.slot</code> records.</div>
                    <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-magic mr-1"></i>Build coverage draft</button>
                </form>
                <hr>
                <small class="text-muted"><strong>{{ $areas->where('company_id',$options['company_id'])->count() }}</strong> configured area(s) for this location. Coverage targets are managed in Odoo-backed Areas &amp; Coverage.</small>
            </div>
        </aside>

        <main class="auto-panel">
            @if(!$preview)
                <div class="auto-empty"><div><div class="auto-empty-icon mx-auto"><i class="fas fa-project-diagram"></i></div><h4>Build a reviewable draft</h4><p class="text-muted mb-0">Choose a location and hours. Nothing is created until you inspect the assignments and approve the draft.</p></div></div>
            @else
                <div class="auto-panel-head d-flex justify-content-between align-items-center"><span>{{ $weekStart->format('M j') }}–{{ $weekEnd->format('M j, Y') }}</span><small>{{ $preview['employee_count'] }} eligible location employees · {{ $preview['area_count'] }} coverage areas</small></div>
                <div class="auto-panel-body">
                    <div class="auto-metrics">
                        <div class="auto-metric"><span>Coverage cells</span><strong>{{ $preview['summary']['coverage_cells'] }}</strong></div>
                        <div class="auto-metric"><span>Positions needed</span><strong>{{ $preview['summary']['positions_needed'] }}</strong></div>
                        <div class="auto-metric is-ready"><span>Assigned</span><strong>{{ $preview['summary']['assigned'] }}</strong></div>
                        <div class="auto-metric {{ $preview['summary']['diary_overrides']>0?'is-alert':'' }}"><span>Diary overrides</span><strong>{{ $preview['summary']['diary_overrides'] }}</strong></div>
                        <div class="auto-metric"><span>Open shifts</span><strong>{{ $preview['summary']['open'] }}</strong></div>
                        <div class="auto-metric {{ ($preview['summary']['blocked']+$preview['summary']['unfilled'])>0?'is-alert':'' }}"><span>Needs attention</span><strong>{{ $preview['summary']['blocked']+$preview['summary']['unfilled'] }}</strong></div>
                    </div>

                    @if($preview['rows']===[])
                        <div class="alert alert-success mb-0"><i class="fas fa-check-circle mr-1"></i>Configured coverage is already filled for this week. No additional shift is proposed.</div>
                    @else
                        <div class="auto-table">
                            <div class="auto-row auto-row-head"><span>Date</span><span>Area</span><span>Hours</span><span>Assignment</span><span>Status</span></div>
                            @foreach($preview['rows'] as $row)
                                <div class="auto-row">
                                    <strong>{{ \Carbon\Carbon::parse($row['date'])->format('D, M j') }}</strong>
                                    <div class="auto-area"><span class="auto-area-dot" style="background:{{ $row['area_color'] }}"></span><span>{{ $row['area'] }}<small class="d-block text-muted">{{ $row['existing_positions'] }}/{{ $row['required'] }} existing</small></span></div>
                                    <span>{{ $row['start_time'] }}–{{ $row['end_time'] }}</span>
                                    <div><span class="auto-person">{{ $row['employee'] ?: 'Not scheduled' }}</span>@if($row['reason'])<small class="auto-reason">{{ $row['reason'] }}</small>@endif</div>
                                    <span><span class="auto-status is-{{ $row['status'] }}">{{ $row['status'] }}</span></span>
                                </div>
                            @endforeach
                        </div>

                        @if(count($preview['proposals'])>0)
                            <div class="auto-apply">
                                <div><strong>Ready to create {{ count($preview['proposals']) }} Odoo shift(s)</strong><small>Assignments are recomputed once at approval time so stale coverage cannot be silently applied.</small></div>
                                <form method="POST" action="{{ route('manager.auto-schedule.apply') }}" onsubmit="return confirm('Create these auto-scheduled shifts in Odoo?');">@csrf
                                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}"><input type="hidden" name="company_id" value="{{ $options['company_id'] }}"><input type="hidden" name="work_location_id" value="{{ $options['work_location_id'] }}"><input type="hidden" name="start_time" value="{{ $options['start_time'] }}"><input type="hidden" name="end_time" value="{{ $options['end_time'] }}"><input type="hidden" name="max_weekly_hours" value="{{ $options['max_weekly_hours'] }}">@if($options['create_open_shifts'])<input type="hidden" name="create_open_shifts" value="1">@endif @if($options['allow_diary_override'])<input type="hidden" name="allow_diary_override" value="1">@endif
                                    <button class="btn btn-success" type="submit"><i class="fas fa-check mr-1"></i>Approve and create in Odoo</button>
                                </form>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </main>
    </div>
</div>
@endsection
