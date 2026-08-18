@extends('layouts.admin.master')

@section('title', 'Team Calendar')

@section('css_after')
    <link rel="stylesheet" href="{{ asset('css/team-calendar.css') }}?v={{ filemtime(public_path('css/team-calendar.css')) }}">
@endsection

@section('content')
@php($canRequestLeave = $hasLeaveIdentity && !$leaveError && count($leaveTypes) > 0)
<main class="container-fluid pb-4 team-calendar-page">
    <h1 class="sr-only">Team Calendar</h1>
    <header class="tc-hero">
        <div class="tc-hero-main">
            <div class="tc-title-block">
                <div class="tc-eyebrow"><i class="fas fa-users"></i> Shared team view</div>
                <h1>Team Calendar</h1>
                <p>One clear view of published shifts, approved leave and team moments—so everyone knows who is working and who is away.</p>
            </div>
            <section class="tc-stats" aria-label="Calendar summary">
                <div class="tc-stat"><i class="fas fa-user-friends"></i><span><strong>{{ $summary['team_members'] }}</strong> Team members</span></div>
                <div class="tc-stat"><i class="fas fa-briefcase"></i><span><strong>{{ $summary['shifts'] }}</strong> Shifts</span></div>
                <div class="tc-stat"><i class="fas fa-plane-departure"></i><span><strong>{{ $summary['people_on_leave'] }}</strong> On leave</span></div>
                <div class="tc-stat"><i class="fas fa-birthday-cake"></i><span><strong>{{ $summary['birthdays'] + $summary['team_events'] }}</strong> Moments</span></div>
            </section>
            <div class="tc-hero-action">
                <span class="tc-period-label">Viewing {{ $selectedMonth->format('F Y') }}</span>
                @if($canRequestLeave)
                    <button type="button" class="btn tc-request-button" id="startLeaveRange"><i class="fas fa-calendar-plus"></i> Request leave</button>
                @endif
            </div>
        </div>
    </header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->has('leave_request'))<div class="alert alert-danger">{{ $errors->first('leave_request') }}</div>@endif
    @if($calendarError)<div class="alert alert-warning">Team calendar data is temporarily unavailable: {{ $calendarError }}</div>@endif
    @if($leaveError)<div class="alert alert-warning">Leave requests are temporarily unavailable: {{ $leaveError }}</div>@endif
    @if($hasLeaveIdentity && !$leaveError && !$leaveTypes)<div class="alert alert-info">No requestable leave types are currently available for your Odoo employee profile.</div>@endif

    <div class="tc-range-bar" id="leaveRangeBar" role="status" aria-live="polite">
        <div><strong id="leaveRangeTitle">Choose your leave dates</strong><br><span id="leaveRangeCopy">Select a start date, then an end date.</span></div>
        <div class="tc-range-actions"><button type="button" class="btn btn-light btn-sm" id="clearLeaveRange">Clear</button><button type="button" class="btn btn-success btn-sm" id="continueLeaveRequest" disabled>Review request</button></div>
    </div>

    <section class="tc-toolbar" aria-label="Calendar controls">
        <div class="tc-month-nav">
            <a class="btn btn-light btn-sm font-weight-bold" href="{{ route('team-calendar.index',['month'=>now()->format('Y-m')]) }}">Today</a>
            <a class="tc-icon-button" href="{{ route('team-calendar.index',['month'=>$previousMonth->format('Y-m')]) }}" aria-label="Previous month"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
            <a class="tc-icon-button" href="{{ route('team-calendar.index',['month'=>$nextMonth->format('Y-m')]) }}" aria-label="Next month"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
            <label class="tc-month-title">{{ $selectedMonth->format('F Y') }} <i class="fas fa-chevron-down" aria-hidden="true"></i><input type="month" id="calendarMonthJump" name="calendar_month_jump" value="{{ $selectedMonth->format('Y-m') }}" data-route="{{ route('team-calendar.index') }}" aria-label="Choose calendar month" autocomplete="off"></label>
        </div>
        <div class="tc-view-switch" aria-label="Calendar view">
            <button type="button" class="is-active" data-calendar-view="month" aria-pressed="true">Month</button>
            <button type="button" data-calendar-view="week" aria-pressed="false">Week</button>
            <button type="button" data-calendar-view="day" aria-pressed="false">Day</button>
        </div>
        <div class="tc-toolbar-actions">
            <button type="button" class="tc-filter-button" id="toggleCalendarFilters" aria-expanded="false" aria-controls="calendarFilters"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button>
            @if($canRequestLeave)<button type="button" class="tc-add-button" data-start-leave><i class="fas fa-plus" aria-hidden="true"></i> Request Leave</button>@endif
        </div>
        <div class="tc-filters" id="calendarFilters" hidden>
            <div class="tc-search"><i class="fas fa-search" aria-hidden="true"></i><input class="tc-filter-control" id="teamCalendarSearch" name="calendar_search" type="search" placeholder="Find a colleague…" aria-label="Find a colleague" autocomplete="off"></div>
            <select class="tc-filter-control" id="teamCalendarCompany" aria-label="Filter company"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company['id'] }}">{{ $company['name'] }}</option>@endforeach</select>
            <div class="tc-type-filters" aria-label="Event types">
                <label class="tc-type-toggle is-shift"><input type="checkbox" value="shift" checked><i class="fas fa-briefcase"></i> Shifts</label>
                <label class="tc-type-toggle is-leave"><input type="checkbox" value="leave" checked><i class="fas fa-plane-departure"></i> Leave</label>
                <label class="tc-type-toggle is-birthday"><input type="checkbox" value="birthday" checked><i class="fas fa-birthday-cake"></i> Birthdays</label>
                <label class="tc-type-toggle is-event"><input type="checkbox" value="event" checked><i class="fas fa-star"></i> Events</label>
            </div>
        </div>
    </section>

    <div class="tc-workspace">
        <section>
            <div class="tc-legend tc-legend-top"><span><i class="fas fa-circle my-shift"></i> My shift</span><span><i class="fas fa-circle team-shift"></i> Team shift</span><span><i class="fas fa-circle leave"></i> On leave</span><span><i class="fas fa-circle event"></i> Holiday / event</span><span><i class="fas fa-circle birthday"></i> Birthday</span></div>
            <div class="tc-calendar-shell" id="teamCalendar">
                <div class="tc-weekdays" aria-hidden="true">@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday)<span>{{ $weekday }}</span>@endforeach</div>
                @foreach($weeks as $week)
                    <div class="tc-week" role="row">
                        @foreach($week as $day)
                            @php($selectable = $canRequestLeave && $day['date']->copy()->endOfDay()->gte(now()))
                            <div class="tc-day {{ !$day['is_current_month']?'is-outside':'' }} {{ $day['is_today']?'is-today':'' }}"
                                role="gridcell" tabindex="0" data-calendar-day data-date="{{ $day['date_value'] }}" data-selectable="{{ $selectable?'1':'0' }}" aria-label="{{ $day['date']->format('l, d F Y') }}" @if($day['is_today']) aria-current="date" @endif>
                                <div class="tc-day-head"><span class="tc-day-number">{{ $day['date']->day }}</span><span class="tc-event-count" data-event-count>{{ count($day['events'])?:'' }}</span></div>
                                <div class="tc-events">
                                    @foreach($day['events'] as $event)
                                        <button type="button" class="tc-event is-{{ $event['type'] }} {{ $event['is_mine']?'is-mine':'' }}"
                                            data-event-type="{{ $event['type'] }}" data-employee="{{ strtolower($event['employee']) }}" data-company-id="{{ $event['company_id'] }}" title="{{ $event['title'] }} · {{ $event['time'] }}">
                                            <span><i class="fas {{ $event['icon'] }}"></i>{{ $event['calendar_title'] ?? $event['title'] }}</span><small>{{ $event['time'] }}</small>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="tc-lower-grid">
                <section class="tc-panel">
                    <div class="tc-panel-head"><h3>Upcoming Leave</h3><span>{{ count($upcomingLeaveRequests) }}</span></div>
                    <div class="tc-compact-list">
                        @forelse($upcomingLeaveRequests as $request)
                            <div class="tc-list-item"><i class="far fa-calendar-check is-leave"></i><div><strong>{{ $request['type'] }}</strong><span>{{ $request['start_date_label'] }} &rarr; {{ $request['end_date_label'] }} &middot; {{ $request['status_label'] }}</span></div></div>
                        @empty
                            <div class="tc-list-empty">You have no upcoming leave requests.</div>
                        @endforelse
                    </div>
                </section>
                <section class="tc-panel">
                    <div class="tc-panel-head"><h3>Team on Leave</h3><span>{{ count($teamOnLeave) }} people</span></div>
                    <div class="tc-compact-list">
                        @forelse($teamOnLeave as $event)
                            <div class="tc-list-item"><span class="tc-avatar">{{ strtoupper(substr($event['employee'], 0, 1)) }}</span><div><strong>{{ $event['employee'] }}</strong><span>{{ \Carbon\Carbon::parse($event['date'])->format('d M') }} &middot; Approved leave</span></div></div>
                        @empty
                            <div class="tc-list-empty">No approved leave in this month.</div>
                        @endforelse
                    </div>
                </section>
                <section class="tc-panel tc-request-panel">
                    <div class="tc-panel-head"><h3>Request Leave</h3><span>Choose a range</span></div>
                    @if($canRequestLeave)
                        <div class="tc-inline-range"><input type="date" id="quickLeaveStart" name="quick_leave_start" min="{{ now()->toDateString() }}" aria-label="Leave start date" autocomplete="off"><span aria-hidden="true">&rarr;</span><input type="date" id="quickLeaveEnd" name="quick_leave_end" min="{{ now()->toDateString() }}" aria-label="Leave end date" autocomplete="off"></div>
                        <button type="button" class="btn tc-panel-button" id="quickLeaveContinue"><i class="far fa-calendar-check" aria-hidden="true"></i> Review Leave Request</button>
                    @else
                        <span class="tc-unavailable">Leave request is currently unavailable</span>
                    @endif
                </section>
            </div>
        </section>

        <aside class="tc-side-rail">
            <section class="tc-rail-card tc-mini-calendar" aria-label="Mini calendar">
                <div class="tc-rail-head"><div><span>Mini Calendar</span><strong>{{ $selectedMonth->format('F Y') }}</strong></div><div class="tc-mini-nav"><a href="{{ route('team-calendar.index',['month'=>$previousMonth->format('Y-m')]) }}" aria-label="Previous month"><i class="fas fa-chevron-left" aria-hidden="true"></i></a><a href="{{ route('team-calendar.index',['month'=>$nextMonth->format('Y-m')]) }}" aria-label="Next month"><i class="fas fa-chevron-right" aria-hidden="true"></i></a></div></div>
                <div class="tc-mini-weekdays">@foreach(['S','M','T','W','T','F','S'] as $weekday)<span>{{ $weekday }}</span>@endforeach</div>
                <div class="tc-mini-days">
                    @foreach($weeks as $week)
                        @foreach($week as $day)
                            <button type="button" class="{{ !$day['is_current_month']?'is-outside':'' }} {{ $day['is_today']?'is-today':'' }}" data-mini-date="{{ $day['date_value'] }}" aria-label="{{ $day['date']->format('l, d F Y') }}" @if($day['is_today']) aria-current="date" @endif>{{ $day['date']->day }}</button>
                        @endforeach
                    @endforeach
                </div>
            </section>

            <section class="tc-rail-card tc-summary-card">
                <div class="tc-rail-title">My Leave Requests</div>
                <div class="tc-summary-row"><span>Pending approval</span><strong>{{ $leaveRequestSummary['pending'] }}</strong><i style="--value: {{ min(100, $leaveRequestSummary['pending'] * 20) }}%; --bar: #f59e0b"></i></div>
                <div class="tc-summary-row"><span>Approved</span><strong>{{ $leaveRequestSummary['approved'] }}</strong><i style="--value: {{ min(100, $leaveRequestSummary['approved'] * 12) }}%; --bar: #20ad6b"></i></div>
                <div class="tc-summary-row"><span>Other</span><strong>{{ $leaveRequestSummary['other'] }}</strong><i style="--value: {{ min(100, $leaveRequestSummary['other'] * 12) }}%; --bar: #94a3b8"></i></div>
            </section>

            <section class="tc-rail-card">
                <div class="tc-rail-title">Upcoming <small>Next 7 days</small></div>
                <div class="tc-compact-list">
                    @forelse($upcomingMoments as $event)
                        <div class="tc-list-item"><i class="fas {{ $event['icon'] }} is-{{ $event['type'] }}"></i><div><strong>{{ $event['calendar_title'] ?? $event['title'] }}</strong><span>{{ \Carbon\Carbon::parse($event['date'])->format('D, d M') }} &middot; {{ $event['time'] }}</span></div></div>
                    @empty
                        <div class="tc-list-empty">Nothing scheduled in the next 7 days.</div>
                    @endforelse
                </div>
            </section>

            <section class="tc-rail-card">
                <div class="tc-rail-title">My Upcoming Shifts</div>
                <div class="tc-compact-list">
                    @forelse($myUpcomingShifts as $event)
                        <div class="tc-list-item"><i class="fas fa-briefcase is-shift"></i><div><strong>{{ $event['time'] ?: 'Scheduled shift' }}</strong><span>{{ \Carbon\Carbon::parse($event['date'])->format('D, d M') }} &middot; {{ $event['detail'] }}</span></div></div>
                    @empty
                        <div class="tc-list-empty">No upcoming published shifts.</div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</main>

@if($canRequestLeave)
<div class="modal fade tc-leave-modal" id="teamLeaveModal" tabindex="-1" role="dialog" aria-labelledby="teamLeaveTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title" id="teamLeaveTitle">Request leave</h5><p>Your request will be sent to your manager for approval.</p></div><button type="button" class="close text-white" data-dismiss="modal" aria-label="Close leave request"><span aria-hidden="true">&times;</span></button></div>
        <form method="POST" action="{{ route('employee.leave.store') }}">@csrf
            <input type="hidden" name="return_to" value="team_calendar"><input type="hidden" name="calendar_month" value="{{ $selectedMonth->format('Y-m') }}">
            <div class="modal-body">
                <div class="tc-leave-summary"><i class="fas fa-calendar-check"></i><span id="leaveModalSummary">Choose a date range from the calendar.</span></div>
                <div class="form-group"><label for="teamLeaveType">Leave type</label><select class="form-control @error('leave_type_id') is-invalid @enderror" name="leave_type_id" id="teamLeaveType" required><option value="">Select leave type</option>@foreach($leaveTypes as $type)<option value="{{ $type['id'] }}" {{ (string)old('leave_type_id')===(string)$type['id']?'selected':'' }}>{{ $type['name'] }}</option>@endforeach</select>@error('leave_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="form-row"><div class="form-group col-6"><label for="teamLeaveStart">Start date</label><input class="form-control @error('start_date') is-invalid @enderror" type="date" name="start_date" id="teamLeaveStart" min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" required>@error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="form-group col-6"><label for="teamLeaveEnd">End date</label><input class="form-control @error('end_date') is-invalid @enderror" type="date" name="end_date" id="teamLeaveEnd" min="{{ now()->toDateString() }}" value="{{ old('end_date') }}" required>@error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                <div class="form-group mb-0"><label for="teamLeaveReason">Note for your manager <span class="text-muted font-weight-normal">(optional)</span></label><textarea class="form-control" name="reason" id="teamLeaveReason" rows="3" maxlength="2000" placeholder="Add useful context…" autocomplete="off">{{ old('reason') }}</textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success font-weight-bold"><i class="fas fa-paper-plane mr-1"></i>Send request</button></div>
        </form>
    </div></div>
</div>
@endif
@endsection

@section('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const eventsByDate = @json($eventsByDate);
    const dayCells = Array.from(document.querySelectorAll('[data-calendar-day]'));
    const search = document.getElementById('teamCalendarSearch');
    const company = document.getElementById('teamCalendarCompany');
    const typeInputs = Array.from(document.querySelectorAll('.tc-type-toggle input'));
    const agendaDate = document.getElementById('agendaDate');
    const agendaBody = document.getElementById('agendaBody');
    const rangeBar = document.getElementById('leaveRangeBar');
    const rangeTitle = document.getElementById('leaveRangeTitle');
    const rangeCopy = document.getElementById('leaveRangeCopy');
    const continueButton = document.getElementById('continueLeaveRequest');
    const startInput = document.getElementById('teamLeaveStart');
    const endInput = document.getElementById('teamLeaveEnd');
    const modalSummary = document.getElementById('leaveModalSummary');
    const calendar = document.getElementById('teamCalendar');
    const calendarWeeks = Array.from(document.querySelectorAll('.tc-week'));
    const viewButtons = Array.from(document.querySelectorAll('[data-calendar-view]'));
    const filtersPanel = document.getElementById('calendarFilters');
    const filterButton = document.getElementById('toggleCalendarFilters');
    let selectedDate = @json(now()->isSameMonth($selectedMonth) ? now()->toDateString() : $selectedMonth->toDateString());
    let rangeStart = startInput?.value || '';
    let rangeEnd = endInput?.value || '';
    let rangeMode = false;
    let calendarView = 'month';

    const dateLabel = (value, options = {weekday:'long', day:'2-digit', month:'long'}) => new Intl.DateTimeFormat('en-GB', options).format(new Date(value + 'T12:00:00'));
    const visibleEvents = (date) => (eventsByDate[date] || []).filter((event) => {
        const query = (search?.value || '').trim().toLowerCase();
        const companyId = company?.value || '';
        const enabledTypes = new Set(typeInputs.filter((input) => input.checked).map((input) => input.value));
        return enabledTypes.has(event.type) && (!query || (event.employee || '').toLowerCase().includes(query)) && (!companyId || String(event.company_id) === companyId);
    });

    const renderAgenda = (date) => {
        selectedDate = date;
        dayCells.forEach((cell) => cell.classList.toggle('is-selected', cell.dataset.date === date));
        document.querySelectorAll('[data-mini-date]').forEach((button) => button.classList.toggle('is-selected', button.dataset.miniDate === date));
        if (agendaDate) agendaDate.textContent = dateLabel(date);
        if (!agendaBody) return;
        const events = visibleEvents(date);
        agendaBody.replaceChildren();
        if (!events.length) {
            const empty = document.createElement('div'); empty.className = 'tc-agenda-empty'; empty.innerHTML = '<i class="far fa-calendar-check"></i>No visible team events on this day.'; agendaBody.appendChild(empty); return;
        }
        events.forEach((event) => {
            const item = document.createElement('article'); item.className = 'tc-agenda-item is-' + event.type;
            const title = document.createElement('strong'); title.textContent = event.title;
            const time = document.createElement('span'); time.textContent = event.time || 'All day';
            const detail = document.createElement('small'); detail.textContent = [event.detail, event.company].filter(Boolean).join(' · ');
            item.append(title, time, detail); agendaBody.appendChild(item);
        });
    };

    const applyFilters = () => {
        dayCells.forEach((cell) => {
            const events = visibleEvents(cell.dataset.date);
            const eventButtons = Array.from(cell.querySelectorAll('[data-event-type]'));
            eventButtons.forEach((button) => {
                const matches = events.some((event) => event.type === button.dataset.eventType && (event.employee || '').toLowerCase() === button.dataset.employee && String(event.company_id) === button.dataset.companyId);
                button.hidden = !matches;
            });
            const count = cell.querySelector('[data-event-count]'); if (count) count.textContent = events.length || '';
        });
        renderAgenda(selectedDate);
    };

    const renderRange = () => {
        const normalizedEnd = rangeEnd || rangeStart;
        dayCells.forEach((cell) => {
            const date = cell.dataset.date;
            cell.classList.toggle('is-in-range', Boolean(rangeStart && normalizedEnd && date >= rangeStart && date <= normalizedEnd));
            cell.classList.toggle('is-range-start', date === rangeStart);
            cell.classList.toggle('is-range-end', date === normalizedEnd);
        });
        rangeBar?.classList.toggle('is-visible', rangeMode || Boolean(rangeStart));
        if (!rangeStart) {
            if (rangeTitle) rangeTitle.textContent = 'Choose your leave dates';
            if (rangeCopy) rangeCopy.textContent = 'Select a start date, then an end date.';
            if (continueButton) continueButton.disabled = true;
            return;
        }
        if (rangeTitle) rangeTitle.textContent = rangeEnd ? 'Leave range selected' : 'Now choose the end date';
        if (rangeCopy) rangeCopy.textContent = rangeEnd ? dateLabel(rangeStart,{day:'2-digit',month:'short',year:'numeric'}) + ' — ' + dateLabel(rangeEnd,{day:'2-digit',month:'short',year:'numeric'}) : 'Starts ' + dateLabel(rangeStart,{day:'2-digit',month:'short',year:'numeric'});
        if (continueButton) continueButton.disabled = !rangeEnd;
    };

    const selectRangeDate = (date) => {
        if (!rangeMode || !rangeStart || (rangeStart && rangeEnd)) { rangeMode = true; rangeStart = date; rangeEnd = ''; }
        else { rangeEnd = date < rangeStart ? rangeStart : date; if (date < rangeStart) rangeStart = date; }
        renderRange();
    };
    const openLeaveModal = () => {
        if (!rangeStart) rangeStart = selectedDate;
        if (!rangeEnd) rangeEnd = rangeStart;
        if (startInput) startInput.value = rangeStart;
        if (endInput) endInput.value = rangeEnd;
        if (modalSummary) modalSummary.textContent = dateLabel(rangeStart,{day:'2-digit',month:'long',year:'numeric'}) + ' — ' + dateLabel(rangeEnd,{day:'2-digit',month:'long',year:'numeric'});
        if (window.jQuery) window.jQuery('#teamLeaveModal').modal('show');
    };
    const beginLeaveRange = () => {
        rangeMode = true; rangeStart = ''; rangeEnd = ''; renderRange();
        document.querySelector('[data-selectable="1"]')?.focus();
    };
    const applyCalendarView = () => {
        const selectedCell = dayCells.find((cell) => cell.dataset.date === selectedDate) || dayCells[0];
        const selectedWeek = selectedCell?.closest('.tc-week');
        calendar?.classList.toggle('is-week-view', calendarView === 'week');
        calendar?.classList.toggle('is-day-view', calendarView === 'day');
        calendarWeeks.forEach((week) => {
            week.hidden = calendarView !== 'month' && week !== selectedWeek;
            Array.from(week.querySelectorAll('[data-calendar-day]')).forEach((cell) => {
                cell.hidden = calendarView === 'day' && cell !== selectedCell;
            });
        });
        viewButtons.forEach((button) => {
            const active = button.dataset.calendarView === calendarView;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    dayCells.forEach((cell) => {
        cell.addEventListener('click', (event) => { if (event.target.closest('.tc-event')) return; renderAgenda(cell.dataset.date); if (rangeMode && cell.dataset.selectable === '1') selectRangeDate(cell.dataset.date); });
        cell.addEventListener('keydown', (event) => { if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('.tc-event')) { event.preventDefault(); cell.click(); } });
        cell.querySelectorAll('.tc-event').forEach((button) => button.addEventListener('click', () => renderAgenda(cell.dataset.date)));
    });
    search?.addEventListener('input', applyFilters); company?.addEventListener('change', applyFilters); typeInputs.forEach((input) => input.addEventListener('change', applyFilters));
    document.getElementById('startLeaveRange')?.addEventListener('click', beginLeaveRange);
    document.querySelectorAll('[data-start-leave]').forEach((button) => button.addEventListener('click', beginLeaveRange));
    filterButton?.addEventListener('click', () => {
        const opening = filtersPanel?.hasAttribute('hidden');
        filtersPanel?.toggleAttribute('hidden', !opening);
        filterButton.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    document.getElementById('calendarMonthJump')?.addEventListener('change', (event) => {
        if (event.target.value) window.location.href = event.target.dataset.route + '?month=' + encodeURIComponent(event.target.value);
    });
    viewButtons.forEach((button) => button.addEventListener('click', () => { calendarView = button.dataset.calendarView; applyCalendarView(); }));
    document.querySelectorAll('[data-mini-date]').forEach((button) => button.addEventListener('click', () => {
        renderAgenda(button.dataset.miniDate);
        applyCalendarView();
        document.querySelector(`[data-calendar-day][data-date="${button.dataset.miniDate}"]`)?.scrollIntoView({behavior:'smooth', block:'center'});
    }));
    document.getElementById('quickLeaveContinue')?.addEventListener('click', () => {
        const quickStart = document.getElementById('quickLeaveStart')?.value || '';
        const quickEnd = document.getElementById('quickLeaveEnd')?.value || quickStart;
        if (!quickStart) { beginLeaveRange(); return; }
        rangeStart = quickStart; rangeEnd = quickEnd < quickStart ? quickStart : quickEnd; renderRange(); openLeaveModal();
    });
    document.getElementById('clearLeaveRange')?.addEventListener('click', () => { rangeStart = ''; rangeEnd = ''; rangeMode = false; renderRange(); });
    continueButton?.addEventListener('click', openLeaveModal);
    startInput?.addEventListener('change', () => { rangeStart = startInput.value; if (endInput && endInput.value < rangeStart) endInput.value = rangeStart; rangeEnd = endInput?.value || rangeStart; renderRange(); });
    endInput?.addEventListener('change', () => { rangeEnd = endInput.value; renderRange(); });
    renderAgenda(selectedDate); applyFilters(); renderRange(); applyCalendarView();
    if (@json($errors->any() && old('return_to') === 'team_calendar') && window.jQuery) openLeaveModal();
});
</script>
@endsection
