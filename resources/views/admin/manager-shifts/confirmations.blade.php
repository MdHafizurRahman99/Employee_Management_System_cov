@extends('layouts.admin.master')

@section('title', 'Shift Confirmations')

@section('content')
<style>
    :root { --confirm-ink:#18322d; --confirm-paper:#f5f1e8; --confirm-line:#d9d2c4; --confirm-coral:#db604a; --confirm-green:#287a64; --confirm-amber:#b87818; }
    .confirm-page { color:var(--confirm-ink); }
    .confirm-hero { position:relative; overflow:hidden; border-radius:20px; padding:28px 30px; color:#fff; background:linear-gradient(125deg,#193b34 0%,#286a59 68%,#d99a55 140%); box-shadow:0 18px 45px rgba(24,50,45,.18); }
    .confirm-hero:after { content:""; position:absolute; width:260px; height:260px; right:-85px; top:-120px; border:42px solid rgba(255,255,255,.08); border-radius:50%; }
    .confirm-eyebrow { text-transform:uppercase; letter-spacing:.16em; font-size:.7rem; font-weight:800; opacity:.78; }
    .confirm-title { font-family:Georgia,'Times New Roman',serif; font-size:2rem; line-height:1.05; margin:.35rem 0 .55rem; }
    .confirm-actions { position:relative; z-index:1; display:flex; gap:.55rem; flex-wrap:wrap; }
    .confirm-actions .btn { border-radius:999px; }
    .confirm-metrics { display:grid; grid-template-columns:repeat(5,minmax(120px,1fr)); gap:12px; margin:18px 0; }
    .confirm-metric { border:1px solid var(--confirm-line); border-radius:14px; padding:15px 16px; background:#fff; box-shadow:0 7px 20px rgba(35,50,45,.05); transition:transform .18s ease,border-color .18s ease; }
    .confirm-metric:hover { transform:translateY(-2px); border-color:#9eb6ae; }
    .confirm-metric span { display:block; color:#74807c; font-size:.72rem; text-transform:uppercase; letter-spacing:.09em; font-weight:800; }
    .confirm-metric strong { display:block; font-family:Georgia,'Times New Roman',serif; font-size:1.8rem; margin-top:2px; }
    .confirm-filter { background:var(--confirm-paper); border:1px solid var(--confirm-line); border-radius:14px; padding:14px; }
    .confirm-filter .form-control,.confirm-filter .btn { border-radius:10px; }
    .confirmation-list { display:grid; gap:12px; margin-top:18px; }
    .confirmation-card { display:grid; grid-template-columns:minmax(210px,1.15fr) minmax(185px,.8fr) minmax(180px,.7fr); gap:18px; align-items:center; background:#fff; border:1px solid var(--confirm-line); border-left:5px solid var(--confirm-amber); border-radius:14px; padding:18px 20px; }
    .confirmation-card.is-accepted { border-left-color:var(--confirm-green); }
    .confirmation-card.is-declined { border-left-color:var(--confirm-coral); }
    .confirmation-person { font-family:Georgia,'Times New Roman',serif; font-size:1.16rem; font-weight:700; }
    .confirmation-meta { color:#718079; font-size:.82rem; margin-top:4px; }
    .confirmation-status { display:inline-flex; align-items:center; gap:7px; padding:6px 10px; border-radius:999px; font-size:.75rem; font-weight:800; background:#fff2d9; color:#8a570e; }
    .confirmation-card.is-accepted .confirmation-status { background:#e2f3ed; color:#17634f; }
    .confirmation-card.is-declined .confirmation-status { background:#fbe7e2; color:#a33c2d; }
    .confirmation-note { margin-top:8px; padding:9px 11px; background:#fff5f1; border-radius:9px; color:#7f3d32; font-size:.82rem; }
    .confirmation-empty { text-align:center; padding:55px 20px; border:1px dashed #bcb4a5; border-radius:16px; background:#fbfaf6; }
    @media(max-width:991px){.confirm-metrics{grid-template-columns:repeat(2,1fr)}.confirmation-card{grid-template-columns:1fr 1fr}.confirmation-response{grid-column:1/-1}}
    @media(max-width:575px){.confirm-hero{padding:22px}.confirm-title{font-size:1.65rem}.confirm-metrics{grid-template-columns:1fr 1fr}.confirmation-card{grid-template-columns:1fr}}
</style>

<div class="container-fluid confirm-page">
    <div class="confirm-hero">
        <div class="confirm-eyebrow">Roster response desk</div>
        <h1 class="confirm-title">Shift Confirmations</h1>
        <p class="mb-3">{{ $weekStart->format('M j') }}–{{ $weekEnd->format('M j, Y') }} · Review employee responses before the roster becomes tomorrow's problem.</p>
        <div class="confirm-actions">
            <a href="{{ route('manager.shifts.create', ['month'=>$selectedMonth->format('Y-m'),'day'=>$selectedDay->toDateString()]) }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left mr-1"></i>Team Schedule</a>
            <a href="{{ route('manager.shifts.confirmations', ['month'=>$weekStart->copy()->subWeek()->format('Y-m'),'day'=>$weekStart->copy()->subWeek()->toDateString()]) }}" class="btn btn-outline-light btn-sm">Previous week</a>
            <a href="{{ route('manager.shifts.confirmations', ['month'=>$weekStart->copy()->addWeek()->format('Y-m'),'day'=>$weekStart->copy()->addWeek()->toDateString()]) }}" class="btn btn-outline-light btn-sm">Next week</a>
        </div>
    </div>

    @if($odooPlanningError)<div class="alert alert-danger mt-3">{{ $odooPlanningError }}</div>@endif
    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif
    @if($errors->has('manager_shift'))<div class="alert alert-danger mt-3">{{ $errors->first('manager_shift') }}</div>@endif

    <div class="confirm-metrics">
        @foreach(['all'=>'All requests','pending'=>'Awaiting reply','accepted'=>'Accepted','declined'=>'Declined','updated'=>'Changed after publish'] as $key=>$label)
            <a class="confirm-metric text-decoration-none text-reset" href="{{ route('manager.shifts.confirmations', ['month'=>$selectedMonth->format('Y-m'),'day'=>$selectedDay->toDateString(),'status'=>$key]) }}"><span>{{ $label }}</span><strong>{{ $summary[$key] }}</strong></a>
        @endforeach
    </div>

    <form class="confirm-filter" method="GET" action="{{ route('manager.shifts.confirmations') }}">
        <div class="form-row align-items-end">
            <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}"><input type="hidden" name="day" value="{{ $selectedDay->toDateString() }}">
            <div class="col-md-5 mb-2 mb-md-0"><label class="small font-weight-bold">Find employee, role, company, or reason</label><input class="form-control" type="search" name="search" value="{{ $search }}" placeholder="Search confirmations"></div>
            <div class="col-md-4 mb-2 mb-md-0"><label class="small font-weight-bold">Response status</label><select class="form-control" name="status">@foreach(['all'=>'All','pending'=>'Pending','accepted'=>'Accepted','declined'=>'Declined','updated'=>'Changed after publish'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-dark btn-block" type="submit"><i class="fas fa-filter mr-1"></i>Apply filters</button></div>
        </div>
    </form>

    <div class="confirmation-list">
        @forelse($filteredShifts as $shift)
            @php($responseStatus=$shift['confirmation_status'] ?? 'pending')
            <article class="confirmation-card is-{{ $responseStatus }}">
                <div><div class="confirmation-person">{{ $shift['employee'] ?? 'Open shift' }}</div><div class="confirmation-meta">{{ $shift['role'] ?? 'No role' }} · {{ $shift['company'] ?? 'No company' }}</div></div>
                <div><strong>{{ $shift['date_label'] ?? $shift['shift_date_value'] }}</strong><div class="confirmation-meta">{{ $shift['time_label'] ?? (($shift['start_time_value'] ?? '').'–'.($shift['end_time_value'] ?? '')) }}</div>@if(($shift['publish_state']??'')==='updated')<span class="badge badge-warning mt-2">Changed after publishing</span>@endif</div>
                <div class="confirmation-response"><span class="confirmation-status"><i class="fas fa-{{ $responseStatus==='accepted'?'check':($responseStatus==='declined'?'times':'clock') }}"></i>{{ ucfirst($responseStatus) }}</span>@if($shift['confirmation_responded_at_label']??null)<div class="confirmation-meta">Responded {{ $shift['confirmation_responded_at_label'] }}</div>@endif @if($shift['notified_at_label']??null)<div class="confirmation-meta"><i class="fas fa-paper-plane mr-1"></i>Sent {{ $shift['notified_at_label'] }}</div>@endif @if($shift['reminder_sent_at_label']??null)<div class="confirmation-meta"><i class="fas fa-bell mr-1"></i>Reminded {{ $shift['reminder_sent_at_label'] }}</div>@endif @if(($shift['notification_status']??null)==='failed')<div class="confirmation-note"><strong>Delivery failed:</strong> {{ $shift['notification_error'] }}</div>@endif @if($shift['confirmation_note']??null)<div class="confirmation-note"><strong>Reason:</strong> {{ $shift['confirmation_note'] }}</div>@endif @if($responseStatus==='pending')<form class="mt-2" method="POST" action="{{ route('manager.shifts.remind',$shift['id']) }}">@csrf<input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}"><input type="hidden" name="day" value="{{ $selectedDay->toDateString() }}"><button class="btn btn-outline-warning btn-sm" type="submit"><i class="fas fa-bell mr-1"></i>Send reminder</button></form>@endif</div>
            </article>
        @empty
            <div class="confirmation-empty"><i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i><h5>No matching confirmations</h5><p class="text-muted mb-0">Try another status or week. Only published shifts requiring confirmation appear here.</p></div>
        @endforelse
    </div>
</div>
@endsection
