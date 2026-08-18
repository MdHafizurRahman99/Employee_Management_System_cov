@extends('layouts.admin.master')

@section('title')Schedule Templates@endsection

@section('content')
<style>
    .template-workspace{--tw-ink:#191831;--tw-purple:#5146d8;--tw-line:#dedde7;color:var(--tw-ink)}
    .template-hero{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:22px;border-radius:14px;background:#27234f;color:#fff}
    .template-hero h1{font-size:1.65rem;font-weight:800;margin:0}.template-hero p{opacity:.78;margin:.35rem 0 0}
    .template-layout{display:grid;grid-template-columns:300px minmax(0,1fr);gap:16px;margin-top:16px}
    .template-panel{border:1px solid var(--tw-line);border-radius:12px;background:#fff;overflow:hidden}.template-panel-head{padding:14px 16px;border-bottom:1px solid var(--tw-line);font-weight:800}.template-panel-body{padding:15px}
    .template-list{display:grid;gap:7px}.template-list-item{display:block;padding:11px;border:1px solid #e4e3eb;border-radius:8px;color:var(--tw-ink)}.template-list-item:hover,.template-list-item.is-active{text-decoration:none;border-color:#bdb7fb;background:#f3f1ff}.template-list-item small{display:block;color:#73717d;margin-top:3px}
    .preview-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px}.preview-summary div{padding:12px;border-radius:9px;background:#f4f3fa}.preview-summary strong{display:block;font-size:1.25rem}.preview-row{display:grid;grid-template-columns:115px 125px minmax(130px,1fr) 105px;gap:10px;align-items:center;padding:10px;border-top:1px solid #ecebf1;font-size:.8rem}.preview-row.is-conflict{background:#fff1ef}.preview-state{font-weight:800;color:#26804b}.preview-row.is-conflict .preview-state{color:#c13e32}
    @media(max-width:900px){.template-layout{grid-template-columns:1fr}.preview-row{grid-template-columns:1fr 1fr}.template-hero{align-items:flex-start;flex-direction:column}}
</style>
<div class="container-fluid template-workspace">
    <div class="template-hero"><div><h1>Schedule Templates</h1><p>Save a proven Odoo roster, preview it against another week, and apply only safe shifts.</p></div><a class="btn btn-light" href="{{ route('manager.shifts.create') }}"><i class="fas fa-arrow-left mr-1"></i>Team Schedule</a></div>
    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->has('schedule_template'))<div class="alert alert-danger mt-3">{{ $errors->first('schedule_template') }}</div>@endif
    @if($odooPlanningError)<div class="alert alert-warning mt-3">{{ $odooPlanningError }}</div>@endif

    <div class="template-layout">
        <aside>
            <div class="template-panel mb-3"><div class="template-panel-head">Save visible week</div><div class="template-panel-body">
                <form method="POST" action="{{ route('manager.schedule-templates.store') }}">@csrf
                    <div class="form-group"><label>Name</label><input class="form-control" name="name" maxlength="120" required value="{{ old('name') }}" placeholder="Standard clinic week"></div>
                    <div class="form-group"><label>Source week</label><input class="form-control" type="date" name="source_day" required value="{{ old('source_day',now()->toDateString()) }}"></div>
                    <div class="form-group"><label>Odoo location</label><select class="form-control" name="company_id" required><option value="">Choose location</option>@foreach($companies as $company)<option value="{{ $company['id'] }}" @selected(old('company_id')==$company['id'])>{{ $company['name'] }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Description</label><textarea class="form-control" name="description" maxlength="500" rows="2">{{ old('description') }}</textarea></div>
                    <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-save mr-1"></i>Save week as template</button>
                </form>
            </div>
            <div class="template-panel"><div class="template-panel-head">Saved templates</div><div class="template-panel-body template-list">
                @forelse($templates as $template)
                    <a class="template-list-item {{ $selectedTemplate?->id===$template->id?'is-active':'' }}" href="{{ route('manager.schedule-templates.index',['template'=>$template->id,'target_week'=>$targetWeek->toDateString()]) }}"><strong>{{ $template->name }}</strong><small>{{ $template->items_count }} shifts · {{ $template->last_applied_at ? 'Last used '.$template->last_applied_at->diffForHumans() : 'Not applied yet' }}</small></a>
                @empty<p class="text-muted mb-0">No templates saved yet.</p>@endforelse
            </div></div>
        </aside>

        <main class="template-panel">
            @if(!$selectedTemplate)
                <div class="template-panel-body text-center py-5"><i class="fas fa-layer-group fa-3x text-muted mb-3"></i><h5>Select or save a template</h5><p class="text-muted mb-0">A destination-week preview will appear here before anything is written to Odoo.</p></div>
            @else
                <div class="template-panel-head d-flex justify-content-between align-items-center"><span>{{ $selectedTemplate->name }}</span><form method="POST" action="{{ route('manager.schedule-templates.archive',$selectedTemplate->id) }}" onsubmit="return confirm('Archive this template?');">@csrf<button class="btn btn-outline-danger btn-sm">Archive</button></form></div>
                <div class="template-panel-body">
                    <form class="form-inline mb-3" method="GET"><input type="hidden" name="template" value="{{ $selectedTemplate->id }}"><label class="mr-2">Destination week</label><input class="form-control mr-2" type="date" name="target_week" value="{{ $targetWeek->toDateString() }}"><button class="btn btn-outline-primary">Preview</button></form>
                    @if($preview)
                        <div class="preview-summary"><div><span>Total</span><strong>{{ $preview['total'] }}</strong></div><div><span>Ready</span><strong class="text-success">{{ $preview['ready'] }}</strong></div><div><span>Conflicts</span><strong class="text-danger">{{ $preview['conflicts'] }}</strong></div></div>
                        <div class="border rounded">
                            @foreach($preview['rows'] as $row)
                                <div class="preview-row {{ $row['has_conflict']?'is-conflict':'' }}"><strong>{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</strong><span>{{ $row['start_time'] }}–{{ $row['end_time'] }}</span><span>Role #{{ $row['item']->role_id }} · Employee #{{ $row['item']->employee_id }}</span><span class="preview-state">{{ $row['has_conflict']?'Conflict':'Ready' }}</span></div>
                            @endforeach
                        </div>
                        <form class="mt-3 text-right" method="POST" action="{{ route('manager.schedule-templates.apply',$selectedTemplate->id) }}">@csrf<input type="hidden" name="target_week" value="{{ $targetWeek->toDateString() }}"><label class="mr-3"><input type="checkbox" name="skip_conflicts" value="1" {{ $preview['conflicts']?'checked':'' }}> Skip conflicting shifts</label><button class="btn btn-success" type="submit" {{ $preview['ready']===0?'disabled':'' }}><i class="fas fa-play mr-1"></i>Apply {{ $preview['ready'] }} ready shifts</button></form>
                    @endif
                </div>
            @endif
        </main>
    </div>
</div>
@endsection
