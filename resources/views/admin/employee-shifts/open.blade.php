@extends('layouts.admin.master')

@section('title', 'Open Shifts')

@section('content')
<style>
    :root { --open-night:#172925; --open-mint:#39a67f; --open-lime:#d9ee9b; --open-paper:#f4f2ea; --open-line:#d9d5c8; }
    .open-page { color:var(--open-night); }
    .open-hero { border-radius:22px; padding:30px; background:var(--open-night); color:#fff; position:relative; overflow:hidden; box-shadow:0 20px 48px rgba(23,41,37,.22); }
    .open-hero:before { content:""; position:absolute; inset:0; opacity:.14; background-image:linear-gradient(30deg,transparent 40%,rgba(217,238,155,.7) 41%,transparent 42%),linear-gradient(150deg,transparent 68%,rgba(57,166,127,.8) 69%,transparent 70%); background-size:70px 70px; }
    .open-hero-copy,.open-hero-actions { position:relative; z-index:1; }
    .open-kicker { font-size:.7rem; font-weight:900; text-transform:uppercase; letter-spacing:.18em; color:var(--open-lime); }
    .open-title { font-family:Georgia,'Times New Roman',serif; font-size:2.15rem; margin:.3rem 0 .55rem; }
    .open-hero-actions { display:flex; flex-wrap:wrap; gap:.55rem; margin-top:18px; }
    .open-hero-actions .btn { border-radius:999px; }
    .open-summary { display:flex; justify-content:space-between; align-items:center; gap:15px; margin:20px 0 12px; }
    .open-count { font-family:Georgia,'Times New Roman',serif; font-size:1.25rem; }
    .open-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:15px; }
    .open-card { position:relative; overflow:hidden; background:#fff; border:1px solid var(--open-line); border-radius:17px; padding:20px; box-shadow:0 8px 24px rgba(36,51,46,.06); transition:transform .18s ease,box-shadow .18s ease; }
    .open-card:hover { transform:translateY(-3px); box-shadow:0 15px 34px rgba(36,51,46,.12); }
    .open-card:after { content:""; position:absolute; width:70px; height:70px; border-radius:50%; background:var(--open-lime); opacity:.34; right:-32px; top:-33px; }
    .open-date { color:#6d7974; text-transform:uppercase; letter-spacing:.1em; font-size:.7rem; font-weight:900; }
    .open-role { font-family:Georgia,'Times New Roman',serif; font-size:1.35rem; margin:7px 0 2px; }
    .open-time { font-size:1.05rem; font-weight:800; color:#226d57; }
    .open-company { color:#74807b; font-size:.82rem; margin:5px 0 15px; }
    .open-note { min-height:42px; padding:10px 11px; border-radius:10px; background:var(--open-paper); color:#5b645f; font-size:.82rem; margin-bottom:15px; }
    .open-claim { border-radius:11px; background:var(--open-mint); border-color:var(--open-mint); font-weight:800; }
    .open-claim:hover { background:#2c8c6b; border-color:#2c8c6b; }
    .open-empty { padding:65px 20px; border:1px dashed #aea99d; background:#faf9f5; border-radius:18px; text-align:center; }
    .open-safety { display:flex; gap:8px; align-items:flex-start; color:#68736f; font-size:.77rem; margin-top:10px; }
    @media(max-width:1100px){.open-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:650px){.open-hero{padding:23px}.open-title{font-size:1.75rem}.open-grid{grid-template-columns:1fr}.open-summary{align-items:flex-start;flex-direction:column}}
</style>

<div class="container-fluid open-page">
    <section class="open-hero">
        <div class="open-hero-copy"><div class="open-kicker">Pick up available hours</div><h1 class="open-title">Open Shifts</h1><p class="mb-0">{{ $weekStart->format('M j') }}–{{ $weekEnd->format('M j, Y') }} · Only shifts matching your Odoo company and planning roles are shown.</p></div>
        <div class="open-hero-actions">
            <a class="btn btn-light btn-sm" href="{{ route('employee.shifts.index') }}"><i class="fas fa-calendar-alt mr-1"></i>My Shifts</a>
            <a class="btn btn-outline-light btn-sm" href="{{ route('employee.open-shifts.index',['day'=>$weekStart->copy()->subWeek()->toDateString()]) }}"><i class="fas fa-chevron-left mr-1"></i>Previous week</a>
            <a class="btn btn-outline-light btn-sm" href="{{ route('employee.open-shifts.index',['day'=>$weekStart->copy()->addWeek()->toDateString()]) }}">Next week<i class="fas fa-chevron-right ml-1"></i></a>
        </div>
    </section>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->has('open_shift'))<div class="alert alert-danger mt-3">{{ $errors->first('open_shift') }}</div>@endif
    @if($odooShiftError)<div class="alert alert-danger mt-3">{{ $odooShiftError }}</div>@endif

    <div class="open-summary"><div class="open-count">{{ count($openShifts) }} available shift{{ count($openShifts)===1?'':'s' }}</div><div class="text-muted small"><i class="fas fa-shield-alt mr-1"></i>Claims are checked again for conflicts and availability before assignment.</div></div>

    @if(empty($openShifts))
        <div class="open-empty"><i class="fas fa-calendar-check fa-3x text-muted mb-3"></i><h5>No eligible open shifts this week</h5><p class="text-muted mb-0">Try the next week. Shifts outside your company or configured planning roles are hidden.</p></div>
    @else
        <div class="open-grid">
            @foreach($openShifts as $shift)
                <article class="open-card">
                    <div class="open-date">{{ $shift['date_label'] }}</div>
                    <h2 class="open-role">{{ $shift['role'] }}</h2>
                    <div class="open-time">{{ $shift['time_label'] }}</div>
                    <div class="open-company"><i class="fas fa-map-marker-alt mr-1"></i>{{ $shift['company'] }} · {{ $shift['duration_label'] }}</div>
                    <div class="open-note">{{ filled($shift['note']??null) ? $shift['note'] : 'No additional instructions for this shift.' }}</div>
                    <form method="POST" action="{{ route('employee.open-shifts.claim',$shift['id']) }}" onsubmit="return confirm('Claim this shift? It will be added to your schedule.');">
                        @csrf<input type="hidden" name="day" value="{{ $selectedDay->toDateString() }}"><input type="hidden" name="last_known_write_date" value="{{ $shift['write_date_value'] }}">
                        <button class="btn btn-success btn-block open-claim" type="submit"><i class="fas fa-hand-paper mr-1"></i>Claim shift</button>
                    </form>
                    <div class="open-safety"><i class="fas fa-sync-alt mt-1"></i><span>If someone else claims it first, the roster will reject this request instead of double-booking the shift.</span></div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
