@extends('layouts.admin.master')

@section('title', 'Scheduling Areas')

@section('css')
<style>
    .area-config-page{--ink:#172b3a;--muted:#687985;--line:#dbe4e8;--green:#176b5b;max-width:1480px;color:var(--ink)}
    .area-config-hero{background:#163c46;color:#fff;border-radius:14px;padding:24px 28px;display:flex;justify-content:space-between;gap:24px;align-items:center;box-shadow:0 16px 35px rgba(22,60,70,.16)}
    .area-config-hero h1{font-size:1.55rem;margin:0 0 5px;font-weight:800}.area-config-hero p{margin:0;color:#c9dadd}
    .area-config-layout{display:grid;grid-template-columns:minmax(300px,390px) 1fr;gap:18px;margin-top:18px}.area-config-card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 5px 18px rgba(23,43,58,.06)}
    .area-config-head{padding:16px 18px;border-bottom:1px solid var(--line);font-weight:800}.area-config-body{padding:18px}.coverage-inputs{display:grid;grid-template-columns:repeat(7,minmax(48px,1fr));gap:7px}.coverage-day label{font-size:.66rem;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:800;text-align:center;display:block}.coverage-day input{text-align:center;padding-left:4px;padding-right:4px}
    .saved-area{border-left:5px solid var(--area-color);margin-bottom:12px}.saved-area:last-child{margin-bottom:0}.saved-area-top{display:flex;justify-content:space-between;gap:12px;align-items:start}.saved-area-title{display:flex;gap:10px;align-items:center}.area-swatch{width:13px;height:13px;border-radius:50%;background:var(--area-color);box-shadow:0 0 0 4px color-mix(in srgb,var(--area-color) 16%,transparent)}.saved-area-meta{font-size:.76rem;color:var(--muted)}
    @media(max-width:991.98px){.area-config-layout{grid-template-columns:1fr}.area-config-hero{align-items:flex-start;flex-direction:column}.coverage-inputs{grid-template-columns:repeat(4,1fr)}}
</style>
@endsection

@section('content')
@php
    $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $companyNames = collect($companies)->pluck('name','id');
@endphp
<div class="container-fluid area-config-page">
    <div class="area-config-hero">
        <div><h1>Scheduling areas & coverage</h1><p>Give Odoo planning roles operational names and define the minimum people needed each day.</p></div>
        <a href="{{ route('manager.shifts.create',['view'=>'area']) }}" class="btn btn-light"><i class="fas fa-arrow-left mr-2"></i>Week by Area</a>
    </div>
    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->has('schedule_area'))<div class="alert alert-danger mt-3">{{ $errors->first('schedule_area') }}</div>@endif
    @if($odooPlanningError)<div class="alert alert-warning mt-3">{{ $odooPlanningError }}</div>@endif

    <div class="area-config-layout">
        <section class="area-config-card">
            <div class="area-config-head">Add or reconnect an area</div>
            <div class="area-config-body">
                <form method="POST" action="{{ route('manager.schedule-areas.store') }}">@csrf
                    <div class="form-group"><label>Location</label><select name="company_id" class="form-control" required><option value="">Choose location</option>@foreach($companies as $company)<option value="{{ $company['id'] }}" @selected(old('company_id')==$company['id'])>{{ $company['name'] }}</option>@endforeach</select></div>
                    <div class="form-group"><label>Odoo planning role</label><select name="odoo_role_id" class="form-control" required><option value="">Choose role</option>@foreach($roles as $role)<option value="{{ $role['id'] }}" @selected(old('odoo_role_id')==$role['id'])>{{ $role['name'] }}{{ !empty($role['company'])?' · '.$role['company']:'' }}</option>@endforeach</select><small class="form-text text-muted">Shifts remain Odoo planning slots; this mapping only controls schedule presentation and coverage.</small></div>
                    <div class="form-row"><div class="form-group col-8"><label>Area name</label><input name="name" class="form-control" maxlength="120" required value="{{ old('name') }}" placeholder="Reception"></div><div class="form-group col-4"><label>Colour</label><input name="color" type="color" class="form-control" required value="{{ old('color','#176b5b') }}"></div></div>
                    <div class="form-group"><label>Display order</label><input name="sort_order" type="number" min="0" max="9999" class="form-control" value="{{ old('sort_order',0) }}" required></div>
                    <label class="mb-2">Minimum people</label><div class="coverage-inputs mb-3">@foreach($days as $i=>$day)<div class="coverage-day"><label>{{ $day }}</label><input class="form-control" name="coverage[{{ $i }}]" type="number" min="0" max="999" value="{{ old('coverage.'.$i,0) }}" required></div>@endforeach</div>
                    <button class="btn btn-success btn-block" type="submit" {{ empty($roles)||empty($companies)?'disabled':'' }}><i class="fas fa-plus mr-1"></i>Save scheduling area</button>
                </form>
            </div>
        </section>

        <section class="area-config-card">
            <div class="area-config-head">Configured areas <span class="badge badge-light ml-1">{{ $areas->where('is_active',true)->count() }}</span></div>
            <div class="area-config-body">
                @forelse($areas->where('is_active',true) as $area)
                    @php $targets=$area->coverageRequirements->pluck('minimum_people','weekday'); @endphp
                    <form method="POST" action="{{ route('manager.schedule-areas.update',$area->id) }}" class="area-config-card saved-area" style="--area-color:{{ $area->color }}">@csrf
                        <div class="area-config-body">
                            <div class="saved-area-top mb-3"><div><div class="saved-area-title"><span class="area-swatch"></span><strong>{{ $area->name }}</strong></div><div class="saved-area-meta mt-1">{{ $companyNames[$area->company_id] ?? 'Location #'.$area->company_id }} · Odoo role #{{ $area->odoo_role_id }}</div></div><button class="btn btn-sm btn-outline-danger" formmethod="POST" formaction="{{ route('manager.schedule-areas.destroy',$area->id) }}" onclick="return confirm('Hide this area? Existing Odoo shifts will remain unchanged.');" type="submit">Hide</button></div>
                            <div class="form-row"><div class="form-group col-md-7"><label>Name</label><input name="name" class="form-control" value="{{ $area->name }}" required></div><div class="form-group col-3 col-md-2"><label>Colour</label><input name="color" type="color" class="form-control" value="{{ $area->color }}" required></div><div class="form-group col-9 col-md-3"><label>Order</label><input name="sort_order" type="number" min="0" max="9999" class="form-control" value="{{ $area->sort_order }}" required></div></div>
                            <div class="coverage-inputs">@foreach($days as $i=>$day)<div class="coverage-day"><label>{{ $day }}</label><input class="form-control" name="coverage[{{ $i }}]" type="number" min="0" max="999" value="{{ $targets[$i] ?? 0 }}" required></div>@endforeach</div>
                            <div class="text-right mt-3"><button class="btn btn-outline-success btn-sm" type="submit">Update targets</button></div>
                        </div>
                    </form>
                @empty
                    <div class="text-center text-muted py-5"><i class="fas fa-map-signs fa-3x mb-3 text-gray-300"></i><div>No scheduling areas configured yet.</div></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection
