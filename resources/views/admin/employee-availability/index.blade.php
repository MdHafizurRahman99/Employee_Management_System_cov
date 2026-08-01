@extends('layouts.admin.master')

@section('title')
    My Availability
@endsection

@section('css')
    <style>
        .availability-shell {
            --availability-ink: #17353d;
            --availability-ink-soft: #5d7580;
            --availability-teal: #177d78;
            --availability-teal-deep: #0d5b57;
            --availability-sand: #f4ede1;
            --availability-paper: #ffffff;
            --availability-border: rgba(23, 53, 61, 0.11);
            --availability-shadow: 0 22px 48px rgba(17, 39, 46, 0.08);
        }

        .availability-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--availability-border);
            border-radius: 1.75rem;
            background:
                radial-gradient(circle at top right, rgba(23, 125, 120, 0.18), transparent 28%),
                linear-gradient(135deg, #fcfaf7 0%, #f4fbfb 55%, #ffffff 100%);
            box-shadow: var(--availability-shadow);
            padding: 2rem;
        }

        .availability-hero::after {
            content: "";
            position: absolute;
            inset: auto -3rem -4rem auto;
            width: 13rem;
            height: 13rem;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(244, 237, 225, 0.95), rgba(244, 237, 225, 0));
            pointer-events: none;
        }

        .availability-eyebrow {
            color: var(--availability-teal-deep);
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .availability-hero h1 {
            color: var(--availability-ink);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2rem, 3vw, 3.1rem);
            line-height: 1.05;
            margin: 0.85rem 0 0.65rem;
            max-width: 42rem;
        }

        .availability-hero p {
            color: var(--availability-ink-soft);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 0;
            max-width: 42rem;
        }

        .availability-hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        .availability-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
            gap: 1rem;
        }

        .availability-summary-card {
            border: 1px solid var(--availability-border);
            border-radius: 1.35rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(247, 251, 251, 0.94));
            box-shadow: 0 16px 34px rgba(17, 39, 46, 0.06);
            padding: 1.1rem 1.2rem;
        }

        .availability-summary-label {
            color: var(--availability-ink-soft);
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .availability-summary-value {
            color: var(--availability-ink);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 2rem;
            line-height: 1;
            margin-top: 0.6rem;
        }

        .availability-summary-note {
            color: var(--availability-ink-soft);
            font-size: 0.82rem;
            margin-top: 0.45rem;
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));
            gap: 1rem;
        }

        .availability-day-card {
            border: 1px solid var(--availability-border);
            border-radius: 1.5rem;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(249, 252, 252, 0.95));
            box-shadow: 0 18px 38px rgba(17, 39, 46, 0.06);
            padding: 1.05rem;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .availability-day-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 40px rgba(17, 39, 46, 0.09);
        }

        .availability-day-card.status-success {
            border-color: rgba(23, 125, 120, 0.22);
        }

        .availability-day-card.status-danger {
            border-color: rgba(170, 74, 58, 0.22);
        }

        .availability-day-card.status-primary {
            border-color: rgba(78, 115, 223, 0.22);
        }

        .availability-day-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 0.95rem;
        }

        .availability-day-head h2 {
            color: var(--availability-ink);
            font-size: 1.08rem;
            margin: 0;
        }

        .availability-day-meta {
            color: var(--availability-ink-soft);
            font-size: 0.84rem;
            margin-top: 0.28rem;
        }

        .availability-day-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            padding: 0.42rem 0.7rem;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .availability-day-status.status-success {
            background: rgba(23, 125, 120, 0.12);
            color: var(--availability-teal-deep);
        }

        .availability-day-status.status-danger {
            background: rgba(170, 74, 58, 0.12);
            color: #954433;
        }

        .availability-day-status.status-primary {
            background: rgba(78, 115, 223, 0.12);
            color: #3759b9;
        }

        .availability-day-status.status-muted {
            background: rgba(93, 117, 128, 0.12);
            color: var(--availability-ink-soft);
        }

        .availability-rule-list {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }

        .availability-rule {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.8rem;
            border: 1px solid rgba(23, 53, 61, 0.08);
            border-radius: 1rem;
            background: #fff;
            padding: 0.8rem 0.85rem;
        }

        .availability-rule-main {
            min-width: 0;
        }

        .availability-rule-time {
            color: var(--availability-ink);
            font-weight: 800;
            line-height: 1.35;
        }

        .availability-rule-caption {
            color: var(--availability-ink-soft);
            font-size: 0.82rem;
            margin-top: 0.2rem;
        }

        .availability-rule-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-shrink: 0;
        }

        .availability-empty {
            border: 1px dashed rgba(93, 117, 128, 0.28);
            border-radius: 1rem;
            color: var(--availability-ink-soft);
            font-size: 0.9rem;
            line-height: 1.6;
            padding: 1rem;
        }

        .availability-day-footer {
            margin-top: 0.9rem;
        }

        .availability-guide-card {
            border: 1px solid var(--availability-border);
            border-radius: 1.5rem;
            background: linear-gradient(180deg, #fff, #fbfcfc);
            box-shadow: 0 18px 40px rgba(17, 39, 46, 0.05);
            padding: 1.4rem;
            height: 100%;
        }

        .availability-guide-card h3 {
            color: var(--availability-ink);
            font-size: 1.05rem;
            margin-bottom: 0.75rem;
        }

        .availability-guide-list {
            margin: 0;
            padding-left: 1.1rem;
            color: var(--availability-ink-soft);
            line-height: 1.75;
        }

        .availability-modal-header {
            background: linear-gradient(135deg, #f6f9fb 0%, #eef7f7 100%);
        }

        .availability-time-help {
            color: var(--availability-ink-soft);
            font-size: 0.8rem;
        }

        @media (max-width: 767.98px) {
            .availability-hero {
                padding: 1.4rem;
            }

            .availability-day-card {
                padding: 0.95rem;
            }

            .availability-rule {
                flex-direction: column;
            }

            .availability-rule-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid availability-shell">
        <div class="availability-hero mb-4">
            <div class="availability-eyebrow">Employee Planner</div>
            <h1>Shape your weekly availability before shifts are assigned.</h1>
            <p>
                Set your normal open hours, blocked periods, and recurring exceptions here. Managers can still use leave
                requests for one-off changes, but this page defines your default weekly pattern in Odoo.
            </p>
            <div class="availability-hero-actions">
                <button type="button" class="btn btn-primary add-availability-rule" data-day="" data-day-label="">
                    <i class="fas fa-plus mr-1"></i>
                    Add Availability Rule
                </button>
                <a href="{{ route('employee.leave.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-plane-departure mr-1"></i>
                    Use Leave Requests for One-Off Changes
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('availability'))
            <div class="alert alert-danger">
                {{ $errors->first('availability') }}
            </div>
        @endif

        @if ($odooAvailabilityError)
            <div class="alert alert-warning">
                {{ $odooAvailabilityError }}
            </div>
        @endif

        @if (! $hasAvailabilityIdentity)
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <i class="fas fa-user-clock fa-3x text-gray-300 mb-3"></i>
                    <h5 class="mb-2">Weekly availability is not ready for this account</h5>
                    <p class="text-muted mb-0">
                        This employee profile is not linked to an Odoo employee record yet, so weekly availability cannot be saved.
                    </p>
                </div>
            </div>
        @else
            <div class="availability-summary-grid mb-4">
                <div class="availability-summary-card">
                    <div class="availability-summary-label">Configured Days</div>
                    <div class="availability-summary-value">{{ $availabilitySummary['configured_days'] }}</div>
                    <div class="availability-summary-note">Days that already have at least one rule.</div>
                </div>
                <div class="availability-summary-card">
                    <div class="availability-summary-label">Total Rules</div>
                    <div class="availability-summary-value">{{ $availabilitySummary['total_rules'] }}</div>
                    <div class="availability-summary-note">All recurring availability lines saved in Odoo.</div>
                </div>
                <div class="availability-summary-card">
                    <div class="availability-summary-label">Open Windows</div>
                    <div class="availability-summary-value">{{ $availabilitySummary['available_rules'] }}</div>
                    <div class="availability-summary-note">Rules that mark you available for work.</div>
                </div>
                <div class="availability-summary-card">
                    <div class="availability-summary-label">Blocked Windows</div>
                    <div class="availability-summary-value">{{ $availabilitySummary['unavailable_rules'] }}</div>
                    <div class="availability-summary-note">Rules that block recurring commitments or time away.</div>
                </div>
            </div>

            <div class="availability-grid mb-4">
                @foreach ($availabilityDays as $day)
                    <div class="availability-day-card status-{{ $day['status_class'] }}">
                        <div class="availability-day-head">
                            <div>
                                <h2>{{ $day['label'] }}</h2>
                                <div class="availability-day-meta">{{ $day['entry_count'] }} rule{{ $day['entry_count'] === 1 ? '' : 's' }}</div>
                            </div>
                            <span class="availability-day-status status-{{ $day['status_class'] }}">
                                {{ $day['status_label'] }}
                            </span>
                        </div>

                        @if ($day['has_rules'])
                            <div class="availability-rule-list">
                                @foreach ($day['entries'] as $entry)
                                    <div class="availability-rule">
                                        <div class="availability-rule-main">
                                            <span class="badge badge-{{ $entry['availability_class'] }}">
                                                {{ $entry['availability_label'] }}
                                            </span>
                                            <div class="availability-rule-time mt-2">{{ $entry['time_label'] }}</div>
                                            <div class="availability-rule-caption">
                                                {{ $entry['is_full_day'] ? 'Covers the whole day.' : 'Recurring custom time window.' }}
                                            </div>
                                        </div>
                                        <div class="availability-rule-actions">
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm edit-availability-rule"
                                                data-id="{{ $entry['id'] }}"
                                                data-day="{{ $entry['day_of_week'] }}"
                                                data-day-label="{{ $day['label'] }}"
                                                data-type="{{ $entry['availability_type'] }}"
                                                data-full-day="{{ $entry['is_full_day'] ? '1' : '0' }}"
                                                data-start-time="{{ $entry['start_time'] !== null ? sprintf('%02d:%02d', (int) floor($entry['start_time']), (int) round(($entry['start_time'] - floor($entry['start_time'])) * 60)) : '' }}"
                                                data-end-time="{{ $entry['end_time'] !== null ? sprintf('%02d:%02d', (int) floor($entry['end_time']), (int) round(($entry['end_time'] - floor($entry['end_time'])) * 60)) : '' }}"
                                                data-update-url="{{ route('employee.availability.update', $entry['id']) }}">
                                                <i class="fas fa-pen mr-1"></i>
                                                Edit
                                            </button>
                                            <form action="{{ route('employee.availability.destroy', $entry['id']) }}"
                                                method="POST" onsubmit="return confirm('Delete this weekly availability rule?');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash-alt mr-1"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="availability-empty">
                                No recurring rule has been saved for {{ strtolower($day['label']) }} yet.
                            </div>
                        @endif

                        <div class="availability-day-footer">
                            <button type="button" class="btn btn-light btn-sm border add-availability-rule"
                                data-day="{{ $day['key'] }}" data-day-label="{{ $day['label'] }}">
                                <i class="fas fa-plus mr-1"></i>
                                Add Rule for {{ $day['short_label'] }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="availability-guide-card">
                        <h3>How to set a clean weekly pattern</h3>
                        <ol class="availability-guide-list">
                            <li>Start with full-day rules when your day is consistently open or blocked.</li>
                            <li>Add shorter exceptions for lunch breaks, meetings, study time, or recurring clinics.</li>
                            <li>Use Leave Requests for one-off absences that should not permanently change the weekly pattern.</li>
                        </ol>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="availability-guide-card">
                        <h3>What managers will see</h3>
                        <ul class="availability-guide-list">
                            <li>Your availability becomes the recurring baseline before new planning slots are created.</li>
                            <li>Blocked windows help reduce avoidable conflicts when the team builds the rota.</li>
                            <li>Leave requests are still the best tool for individual shifts or date-specific time off.</li>
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="modal fade" id="availabilityRuleModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header availability-modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="availabilityRuleModalTitle">Add weekly availability rule</h5>
                        <div class="small text-muted" id="availabilityRuleModalSubtitle">Choose a weekday, then define whether you are open or blocked.</div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="availabilityRuleForm" action="{{ route('employee.availability.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="availability_entry_id" id="availability_entry_id" value="{{ old('availability_entry_id') }}">
                        <div class="form-group">
                            <label for="day_of_week">Weekday</label>
                            <select name="day_of_week" id="day_of_week" class="form-control" required>
                                <option value="0" {{ old('day_of_week') === '0' ? 'selected' : '' }}>Monday</option>
                                <option value="1" {{ old('day_of_week') === '1' ? 'selected' : '' }}>Tuesday</option>
                                <option value="2" {{ old('day_of_week') === '2' ? 'selected' : '' }}>Wednesday</option>
                                <option value="3" {{ old('day_of_week') === '3' ? 'selected' : '' }}>Thursday</option>
                                <option value="4" {{ old('day_of_week') === '4' ? 'selected' : '' }}>Friday</option>
                                <option value="5" {{ old('day_of_week') === '5' ? 'selected' : '' }}>Saturday</option>
                                <option value="6" {{ old('day_of_week') === '6' ? 'selected' : '' }}>Sunday</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="availability_type">Rule Type</label>
                            <select name="availability_type" id="availability_type" class="form-control" required>
                                <option value="available" {{ old('availability_type', 'available') === 'available' ? 'selected' : '' }}>Available</option>
                                <option value="unavailable" {{ old('availability_type') === 'unavailable' ? 'selected' : '' }}>Unavailable</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <input type="hidden" name="is_full_day" value="0">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_full_day" name="is_full_day" value="1"
                                    {{ old('is_full_day') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_full_day">Full day</label>
                            </div>
                            <div class="availability-time-help mt-2">Switch this on when the whole day should be open or blocked.</div>
                        </div>

                        <div id="availabilityTimeFields">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="start_time">Start Time</label>
                                    <input type="time" name="start_time" id="start_time" class="form-control"
                                        value="{{ old('start_time') }}">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="end_time">End Time</label>
                                    <input type="time" name="end_time" id="end_time" class="form-control"
                                        value="{{ old('end_time') }}">
                                </div>
                            </div>
                            <div class="availability-time-help">Example: set <strong>Available</strong> 09:00-17:00, then add <strong>Unavailable</strong> 13:00-14:00 for lunch.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="availabilityRuleSubmitButton">Save Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = $('#availabilityRuleModal');
            const form = document.getElementById('availabilityRuleForm');
            const modalTitle = document.getElementById('availabilityRuleModalTitle');
            const modalSubtitle = document.getElementById('availabilityRuleModalSubtitle');
            const submitButton = document.getElementById('availabilityRuleSubmitButton');
            const daySelect = document.getElementById('day_of_week');
            const typeSelect = document.getElementById('availability_type');
            const fullDayToggle = document.getElementById('is_full_day');
            const timeFields = document.getElementById('availabilityTimeFields');
            const startTimeInput = document.getElementById('start_time');
            const endTimeInput = document.getElementById('end_time');
            const availabilityEntryIdInput = document.getElementById('availability_entry_id');
            const defaultAction = @json(route('employee.availability.store'));

            const syncTimeFields = () => {
                const isFullDay = fullDayToggle.checked;
                timeFields.style.display = isFullDay ? 'none' : 'block';

                if (isFullDay) {
                    startTimeInput.value = '';
                    endTimeInput.value = '';
                }
            };

            const resetModalForCreate = (day, dayLabel) => {
                form.setAttribute('action', defaultAction);
                modalTitle.textContent = 'Add weekly availability rule';
                modalSubtitle.textContent = dayLabel
                    ? 'Create a recurring rule for ' + dayLabel + '.'
                    : 'Choose a weekday, then define whether you are open or blocked.';
                submitButton.textContent = 'Save Rule';
                availabilityEntryIdInput.value = '';
                daySelect.value = day !== '' ? day : '0';
                typeSelect.value = 'available';
                fullDayToggle.checked = false;
                startTimeInput.value = '';
                endTimeInput.value = '';
                syncTimeFields();
            };

            document.querySelectorAll('.add-availability-rule').forEach((button) => {
                button.addEventListener('click', function() {
                    resetModalForCreate(button.dataset.day || '', button.dataset.dayLabel || '');
                    modal.modal('show');
                });
            });

            document.querySelectorAll('.edit-availability-rule').forEach((button) => {
                button.addEventListener('click', function() {
                    form.setAttribute('action', button.dataset.updateUrl || defaultAction);
                    modalTitle.textContent = 'Edit weekly availability rule';
                    modalSubtitle.textContent = 'Adjust the recurring rule for ' + (button.dataset.dayLabel || 'this day') + '.';
                    submitButton.textContent = 'Update Rule';
                    availabilityEntryIdInput.value = button.dataset.id || '';
                    daySelect.value = button.dataset.day || '0';
                    typeSelect.value = button.dataset.type || 'available';
                    fullDayToggle.checked = button.dataset.fullDay === '1';
                    startTimeInput.value = button.dataset.startTime || '';
                    endTimeInput.value = button.dataset.endTime || '';
                    syncTimeFields();
                    modal.modal('show');
                });
            });

            fullDayToggle.addEventListener('change', syncTimeFields);
            syncTimeFields();

            const editingEntryId = @json(old('availability_entry_id'));
            const shouldReopenModal = @json((bool) ($errors->has('availability') || old('day_of_week')));

            if (editingEntryId) {
                const matchingEditButton = document.querySelector('.edit-availability-rule[data-id="' + editingEntryId + '"]');

                if (matchingEditButton) {
                    matchingEditButton.click();
                } else if (shouldReopenModal) {
                    modal.modal('show');
                }
            } else if (shouldReopenModal) {
                modal.modal('show');
            }
        });
    </script>
@endsection
