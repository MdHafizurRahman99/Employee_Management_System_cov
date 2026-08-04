@extends('layouts.admin.master')

@section('title')
    Team Schedule
@endsection

@section('css')
    <style>
        .schedule-page {
            --schedule-ink: #16201d;
            --schedule-muted: #6c7773;
            --schedule-line: #dfe7e3;
            --schedule-soft: #f4f8f6;
            --schedule-panel: #ffffff;
            --schedule-green: #20b26b;
            --schedule-green-dark: #0e7c4b;
            --schedule-blue: #2774d8;
            --schedule-amber: #d99622;
            color: var(--schedule-ink);
        }

        .schedule-page .btn {
            border-radius: 6px;
            font-weight: 700;
        }

        .schedule-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--schedule-line);
            border-radius: 8px;
            background:
                linear-gradient(90deg, rgba(32, 178, 107, 0.08), rgba(39, 116, 216, 0.05)),
                var(--schedule-panel);
        }

        .schedule-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--schedule-green-dark);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .schedule-title {
            margin: 0;
            color: var(--schedule-ink);
            font-size: 1.65rem;
            font-weight: 900;
            letter-spacing: 0;
        }

        .schedule-subtitle {
            margin: 0.25rem 0 0;
            color: var(--schedule-muted);
            font-size: 0.92rem;
        }

        .schedule-period {
            display: inline-flex;
            align-items: center;
            min-height: 2.35rem;
            padding: 0 0.8rem;
            border: 1px solid var(--schedule-line);
            border-radius: 6px;
            background: #fff;
            color: var(--schedule-ink);
            font-weight: 900;
            white-space: nowrap;
        }

        .schedule-primary-action {
            border-color: var(--schedule-green);
            background: var(--schedule-green);
            color: #fff;
            box-shadow: 0 8px 16px rgba(32, 178, 107, 0.18);
        }

        .schedule-primary-action:hover {
            border-color: var(--schedule-green-dark);
            background: var(--schedule-green-dark);
            color: #fff;
        }

        .schedule-publish-action {
            border-color: #7c3aed;
            background: #7c3aed;
            color: #fff;
            box-shadow: 0 8px 16px rgba(124, 58, 237, 0.2);
        }

        .schedule-publish-action:hover {
            border-color: #6d28d9;
            background: #6d28d9;
            color: #fff;
        }

        .schedule-publish-action.is-clean {
            border-color: var(--schedule-green);
            background: var(--schedule-green);
            box-shadow: 0 8px 16px rgba(32, 178, 107, 0.18);
        }

        .schedule-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .schedule-stat {
            min-height: 92px;
            padding: 0.95rem 1rem;
            border: 1px solid var(--schedule-line);
            border-radius: 8px;
            background: var(--schedule-panel);
        }

        .schedule-stat span {
            display: block;
            color: var(--schedule-muted);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .schedule-stat strong {
            display: block;
            margin-top: 0.35rem;
            color: var(--schedule-ink);
            font-size: 1.55rem;
            line-height: 1;
        }

        .schedule-stat small {
            display: block;
            margin-top: 0.35rem;
            color: var(--schedule-muted);
            font-weight: 700;
        }

        .employee-diary-chip {
            display: flex;
            align-items: flex-start;
            gap: 0.38rem;
            width: 100%;
            border: 1px solid transparent;
            border-radius: 0.55rem;
            font-size: 0.68rem;
            font-weight: 800;
            line-height: 1.25;
            padding: 0.42rem 0.5rem;
        }

        .employee-diary-chip.is-available { background: #e7f5ed; border-color: #bfe2cf; color: #176344; }
        .employee-diary-chip.is-unavailable { background: #fbe9e5; border-color: #edc4ba; color: #963f2d; }
        .employee-diary-chip.is-note { background: #fff4d8; border-color: #ead6a3; color: #755315; }
        .employee-diary-chip i { margin-top: 0.08rem; }
        .employee-diary-chip span { min-width: 0; overflow: hidden; text-overflow: ellipsis; }

        .employee-diary-focus {
            border-left: 4px solid #bf8b2e;
            border-radius: 0.65rem;
            background: #fffbef;
            padding: 0.75rem 0.8rem;
        }

        .employee-diary-focus + .employee-diary-focus { margin-top: 0.55rem; }

        .schedule-intelligence {
            display: grid;
            grid-template-columns: 1.1fr 0.95fr 1.15fr;
            gap: 0.85rem;
        }

        .schedule-intelligence-panel {
            border: 1px solid var(--schedule-line);
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }

        .schedule-intelligence-panel .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--schedule-line);
            background: #f9fbfa;
        }

        .schedule-intelligence-panel .panel-body {
            padding: 0.85rem 1rem;
        }

        .schedule-alert {
            display: flex;
            gap: 0.7rem;
            padding: 0.75rem;
            border: 1px solid #dfe7e3;
            border-left: 4px solid var(--schedule-blue);
            border-radius: 6px;
            background: #f7fbff;
        }

        .schedule-alert + .schedule-alert {
            margin-top: 0.55rem;
        }

        .schedule-alert.is-warning {
            border-left-color: var(--schedule-amber);
            background: #fff9e8;
        }

        .schedule-alert.is-danger {
            border-left-color: #d64545;
            background: #fff1f1;
        }

        .schedule-alert.is-success {
            border-left-color: var(--schedule-green);
            background: #eefaf4;
        }

        .schedule-alert-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.75);
            color: inherit;
            flex: 0 0 auto;
        }

        .schedule-alert-title {
            color: var(--schedule-ink);
            font-weight: 900;
            line-height: 1.2;
        }

        .schedule-alert-message {
            margin-top: 0.18rem;
            color: var(--schedule-muted);
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .breakdown-row + .breakdown-row {
            margin-top: 0.7rem;
        }

        .breakdown-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.82rem;
            font-weight: 900;
        }

        .breakdown-track {
            height: 0.48rem;
            margin-top: 0.35rem;
            border-radius: 999px;
            background: #edf2f0;
            overflow: hidden;
        }

        .breakdown-fill {
            height: 100%;
            border-radius: inherit;
            background: var(--schedule-green);
        }

        .template-rail {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .template-chip,
        .time-preset {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            min-height: 2.15rem;
            padding: 0.35rem 0.6rem;
            border: 1px solid var(--schedule-line);
            border-radius: 6px;
            background: #fff;
            color: var(--schedule-ink);
            font-size: 0.78rem;
            font-weight: 900;
        }

        .template-chip:hover,
        .time-preset:hover {
            border-color: var(--schedule-green);
            background: #f1fbf6;
            color: var(--schedule-green-dark);
        }

        .schedule-clipboard {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid #c9ead9;
            border-radius: 8px;
            background: #eefaf4;
            color: var(--schedule-green-dark);
            font-weight: 800;
        }

        .schedule-clipboard.is-active {
            display: flex;
        }

        .roster-shell {
            border: 1px solid var(--schedule-line);
            border-radius: 8px;
            background: var(--schedule-panel);
            overflow: hidden;
            box-shadow: 0 16px 34px rgba(22, 32, 29, 0.06);
        }

        .roster-board-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--schedule-line);
            background: #f9fbfa;
        }

        .roster-search {
            position: relative;
            width: min(360px, 100%);
        }

        .roster-search i {
            position: absolute;
            top: 50%;
            left: 0.85rem;
            color: #8a9692;
            transform: translateY(-50%);
        }

        .roster-search input {
            height: 2.35rem;
            padding-left: 2.15rem;
            border-color: var(--schedule-line);
            border-radius: 6px;
            font-weight: 700;
        }

        .roster-filters {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .roster-filter {
            width: auto;
            min-width: 132px;
            height: 2.35rem;
            border-color: var(--schedule-line);
            border-radius: 6px;
            color: var(--schedule-ink);
            font-size: 0.82rem;
            font-weight: 800;
        }

        .roster-scroll {
            overflow-x: auto;
        }

        .roster-grid {
            display: grid;
            grid-template-columns: 248px repeat(var(--schedule-day-count, 14), minmax(158px, 1fr));
            min-width: 2460px;
        }

        .area-grid {
            display: grid;
            grid-template-columns: 248px repeat(var(--schedule-day-count, 14), minmax(158px, 1fr));
            min-width: 2460px;
        }

        .roster-corner,
        .area-corner,
        .roster-day-head,
        .area-day-head,
        .roster-person,
        .area-row-head,
        .roster-cell,
        .area-cell {
            border-right: 1px solid var(--schedule-line);
            border-bottom: 1px solid var(--schedule-line);
        }

        .roster-corner,
        .area-corner,
        .roster-person,
        .area-row-head {
            position: sticky;
            left: 0;
            z-index: 4;
            background: #fff;
        }

        .roster-corner,
        .area-corner {
            top: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 74px;
            padding: 0.9rem 1rem;
            z-index: 5;
            background: #f9fbfa;
            font-size: 0.76rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .roster-day-head,
        .area-day-head {
            min-height: 74px;
            padding: 0.75rem;
            background: #f9fbfa;
            color: var(--schedule-ink);
            text-decoration: none;
        }

        .roster-day-head:hover,
        .area-day-head:hover {
            color: var(--schedule-ink);
            text-decoration: none;
            background: #eef7f2;
        }

        .roster-day-head.is-selected,
        .area-day-head.is-selected {
            box-shadow: inset 0 -3px 0 var(--schedule-green);
        }

        .roster-day-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .roster-day-name {
            color: var(--schedule-muted);
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .roster-day-number {
            margin-top: 0.18rem;
            font-size: 1.25rem;
            font-weight: 900;
        }

        .roster-day-total {
            display: inline-flex;
            align-items: center;
            min-height: 1.55rem;
            padding: 0 0.45rem;
            border-radius: 6px;
            background: #e7f6ee;
            color: var(--schedule-green-dark);
            font-size: 0.72rem;
            font-weight: 900;
        }

        .roster-person {
            min-height: 116px;
            padding: 0.9rem 1rem;
            box-shadow: 8px 0 18px rgba(22, 32, 29, 0.04);
        }

        .roster-person[data-draggable="1"] {
            cursor: grab;
        }

        .roster-person.is-drag-source {
            background: #eefaf4;
            box-shadow: inset 0 0 0 2px rgba(32, 178, 107, 0.32);
        }

        .roster-person.is-open {
            background: #fff9e8;
        }

        .area-row-head {
            min-height: 116px;
            padding: 0.9rem 1rem;
            box-shadow: 8px 0 18px rgba(22, 32, 29, 0.04);
            background: #f8fbff;
        }

        .area-row-name {
            color: var(--schedule-ink);
            font-weight: 900;
            line-height: 1.2;
        }

        .area-row-meta {
            color: var(--schedule-muted);
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .area-row-stats {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.85rem;
            color: var(--schedule-muted);
            font-size: 0.78rem;
            font-weight: 800;
        }

        .roster-person-main {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .roster-avatar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #e7f6ee;
            color: var(--schedule-green-dark);
            font-size: 0.8rem;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .roster-person.is-open .roster-avatar {
            background: #fff0bf;
            color: #8a5b00;
        }

        .roster-person-name {
            color: var(--schedule-ink);
            font-weight: 900;
            line-height: 1.2;
        }

        .roster-person-meta {
            color: var(--schedule-muted);
            font-size: 0.78rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .roster-person-hours {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-top: 0.85rem;
            color: var(--schedule-muted);
            font-size: 0.78rem;
            font-weight: 800;
        }

        .roster-cell,
        .area-cell {
            min-height: 116px;
            padding: 0.55rem;
            background: #fff;
            transition: background-color 0.16s ease, box-shadow 0.16s ease;
        }

        .roster-cell.is-today,
        .area-cell.is-today {
            background: #f1fbf6;
        }

        .roster-cell.is-selected,
        .area-cell.is-selected {
            background: #eef8f3;
        }

        .roster-cell.is-drop-target,
        .area-cell.is-drop-target {
            box-shadow: inset 0 0 0 1px rgba(32, 178, 107, 0.2);
        }

        .roster-cell.is-drop-active,
        .area-cell.is-drop-active {
            background: #e7f6ee;
            box-shadow: inset 0 0 0 2px rgba(32, 178, 107, 0.5);
        }

        .roster-cell.is-key-selected {
            background: #edf5ff;
            box-shadow: inset 0 0 0 2px rgba(39, 116, 216, 0.42);
        }

        .shift-stack {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .shift-card {
            position: relative;
            padding: 0.55rem 0.55rem 0.5rem;
            border: 1px solid rgba(14, 124, 75, 0.18);
            border-left: 4px solid var(--schedule-green);
            border-radius: 6px;
            background: #e9f8f0;
            color: #174331;
            box-shadow: 0 7px 14px rgba(22, 32, 29, 0.07);
            transition: transform 0.16s ease, box-shadow 0.16s ease, opacity 0.16s ease;
        }

        .shift-card[data-draggable="1"] {
            cursor: grab;
        }

        .shift-card.is-drag-source {
            opacity: 0.7;
            transform: rotate(-1deg) scale(0.98);
            box-shadow: 0 12px 22px rgba(22, 32, 29, 0.14);
        }

        .shift-card.is-selected {
            box-shadow: 0 0 0 2px rgba(39, 116, 216, 0.38), 0 12px 22px rgba(22, 32, 29, 0.12);
        }

        .shift-card.is-related {
            box-shadow: inset 0 0 0 1px rgba(39, 116, 216, 0.28);
        }

        .shift-card.is-resizing {
            opacity: 0.92;
            transform: scale(1.01);
            box-shadow: 0 14px 26px rgba(22, 32, 29, 0.16);
        }

        .shift-card.shift-tone-blue {
            border-color: rgba(39, 116, 216, 0.2);
            border-left-color: var(--schedule-blue);
            background: #edf5ff;
            color: #173d69;
        }

        .shift-card.shift-tone-amber {
            border-color: rgba(217, 150, 34, 0.25);
            border-left-color: var(--schedule-amber);
            background: #fff6df;
            color: #61430d;
        }

        .shift-card.shift-tone-mint {
            border-color: rgba(25, 150, 132, 0.22);
            border-left-color: #199684;
            background: #e8f8f5;
            color: #164a43;
        }

        .shift-card.shift-tone-slate {
            border-color: rgba(84, 98, 111, 0.22);
            border-left-color: #54626f;
            background: #f1f4f6;
            color: #28333d;
        }

        .shift-time {
            padding-right: 4rem;
            font-size: 0.78rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .shift-status-badge {
            display: inline-flex;
            align-items: center;
            min-height: 1.2rem;
            margin-bottom: 0.35rem;
            padding: 0 0.38rem;
            border-radius: 999px;
            font-size: 0.64rem;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .shift-status-badge.is-published {
            background: rgba(32, 178, 107, 0.16);
            color: #0e7c4b;
        }

        .shift-status-badge.is-updated {
            background: rgba(217, 150, 34, 0.18);
            color: #8a5b00;
        }

        .shift-status-badge.is-unpublished {
            background: rgba(84, 98, 111, 0.16);
            color: #49545e;
        }

        .shift-role {
            margin-top: 0.25rem;
            font-size: 0.77rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .shift-note {
            margin-top: 0.2rem;
            color: rgba(22, 32, 29, 0.72);
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .shift-resize-handle {
            position: absolute;
            top: 0.3rem;
            bottom: 0.3rem;
            width: 0.48rem;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.6);
            opacity: 0;
            transition: opacity 0.14s ease, background-color 0.14s ease;
            z-index: 3;
        }

        .shift-resize-handle::before {
            content: '';
            display: block;
            width: 2px;
            height: 28px;
            margin: auto;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.46;
        }

        .shift-resize-handle.is-start {
            left: -0.12rem;
            cursor: w-resize;
        }

        .shift-resize-handle.is-end {
            right: -0.12rem;
            cursor: e-resize;
        }

        .shift-card:hover .shift-resize-handle,
        .shift-card.is-resizing .shift-resize-handle {
            opacity: 1;
        }

        .shift-card.is-resizing .shift-resize-handle {
            background: rgba(255, 255, 255, 0.84);
        }

        .shift-resize-meta {
            margin-top: 0.3rem;
            color: rgba(22, 32, 29, 0.76);
            font-size: 0.65rem;
            font-weight: 800;
            line-height: 1.15;
        }

        .shift-actions {
            position: absolute;
            top: 0.35rem;
            right: 0.35rem;
            display: flex;
            gap: 0.2rem;
        }

        .shift-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.45rem;
            height: 1.45rem;
            border: 1px solid rgba(22, 32, 29, 0.12);
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.8);
            color: inherit;
            font-size: 0.68rem;
            line-height: 1;
        }

        .shift-icon-btn:hover {
            background: #fff;
            color: inherit;
        }

        .cell-add {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 2.35rem;
            border: 1px dashed #b9c8c2;
            border-radius: 6px;
            background: transparent;
            color: #7d8a85;
            font-weight: 900;
        }

        .cell-add:hover {
            border-color: var(--schedule-green);
            background: #f1fbf6;
            color: var(--schedule-green-dark);
        }

        .cell-paste {
            display: none;
            border-color: #9bd7b9;
            background: #eefaf4;
            color: var(--schedule-green-dark);
        }

        .has-shift-clipboard .cell-paste {
            display: inline-flex;
        }

        .roster-density-compact .roster-person,
        .roster-density-compact .roster-cell {
            min-height: 92px;
        }

        .roster-density-compact .shift-card {
            padding-top: 0.45rem;
            padding-bottom: 0.42rem;
        }

        .schedule-shortcuts {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.35rem;
            color: var(--schedule-muted);
            font-size: 0.72rem;
            font-weight: 800;
        }

        .schedule-shortcuts kbd {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.45rem;
            min-height: 1.45rem;
            padding: 0 0.35rem;
            border: 1px solid var(--schedule-line);
            border-bottom-width: 2px;
            border-radius: 6px;
            background: #fff;
            color: var(--schedule-ink);
            font-size: 0.68rem;
            font-weight: 900;
            box-shadow: 0 2px 0 rgba(22, 32, 29, 0.05);
        }

        .schedule-bulkbar {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--schedule-line);
            background: #edf5ff;
        }

        .schedule-bulkbar.is-active {
            display: flex;
        }

        .schedule-bulkbar-title {
            color: #173d69;
            font-size: 0.82rem;
            font-weight: 900;
        }

        .schedule-bulkbar-meta {
            color: #547090;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .schedule-bulkbar-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .roster-timeoff-label {
            position: sticky;
            left: 0;
            z-index: 4;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 62px;
            padding: 0.75rem 1rem;
            border-right: 1px solid var(--schedule-line);
            border-bottom: 1px solid var(--schedule-line);
            background: #fff7fb;
            color: #7e365a;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .roster-timeoff-day {
            min-height: 62px;
            padding: 0.55rem 0.6rem;
            border-right: 1px solid var(--schedule-line);
            border-bottom: 1px solid var(--schedule-line);
            background: #fffafd;
        }

        .timeoff-day-stack {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .timeoff-day-chip,
        .timeoff-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.32rem;
            min-height: 1.3rem;
            padding: 0 0.42rem;
            border-radius: 999px;
            font-size: 0.64rem;
            font-weight: 900;
            line-height: 1;
            white-space: nowrap;
        }

        .timeoff-day-chip.is-approved,
        .timeoff-chip.is-approved {
            background: #ffe4ef;
            color: #a7346e;
        }

        .timeoff-day-chip.is-pending,
        .timeoff-chip.is-pending {
            background: #fff4d9;
            color: #8a5b00;
        }

        .timeoff-day-chip.is-unavailable,
        .timeoff-chip.is-unavailable {
            background: #ede5ff;
            color: #6b3ccf;
        }

        .timeoff-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem;
            margin-bottom: 0.35rem;
        }

        .schedule-side-panel {
            border: 1px solid var(--schedule-line);
            border-radius: 8px;
            background: #fff;
        }

        .schedule-side-panel .panel-head {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid var(--schedule-line);
            background: #f9fbfa;
        }

        .schedule-side-panel .panel-body {
            padding: 1rem;
        }

        .day-assignment {
            padding: 0.85rem;
            border: 1px solid var(--schedule-line);
            border-radius: 6px;
            background: #fff;
        }

        .day-assignment + .day-assignment {
            margin-top: 0.65rem;
        }

        .month-mini-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.35rem;
        }

        .month-mini-day {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            border: 1px solid var(--schedule-line);
            border-radius: 6px;
            background: #fff;
            color: var(--schedule-ink);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 900;
        }

        .month-mini-day:hover {
            border-color: var(--schedule-green);
            color: var(--schedule-green-dark);
            text-decoration: none;
        }

        .month-mini-day.is-outside {
            opacity: 0.45;
        }

        .month-mini-day.is-selected {
            border-color: var(--schedule-green);
            background: #e7f6ee;
            color: var(--schedule-green-dark);
        }

        .month-mini-count {
            margin-top: 0.15rem;
            color: var(--schedule-muted);
            font-size: 0.68rem;
        }

        .schedule-table th {
            border-top: 0;
            color: var(--schedule-muted);
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .schedule-table td {
            vertical-align: middle;
        }

        @media (max-width: 1199.98px) {
            .schedule-toolbar,
            .roster-board-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .schedule-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .schedule-intelligence {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767.98px) {
            .schedule-title {
                font-size: 1.35rem;
            }

            .schedule-stats {
                grid-template-columns: 1fr;
            }

            .schedule-toolbar .d-flex {
                width: 100%;
            }

            .schedule-toolbar .btn,
            .schedule-toolbar .schedule-period {
                flex: 1 1 auto;
                justify-content: center;
            }

            .roster-grid {
                grid-template-columns: 210px repeat(var(--schedule-day-count, 14), minmax(145px, 1fr));
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/deputy-schedule.css') }}">
@endsection

@section('content')
    @php
        $rosterDays = $weeklyRoster['days'] ?? [];
        $rosterRows = $weeklyRoster['rows'] ?? [];
        $rosterSummary = $weeklyRoster['summary'] ?? [];
        $weekLabel = $weeklyRoster['week_label'] ?? $selectedCalendarDateLabel;
        $previousWeekDay = $weeklyRoster['previous_week_day'] ?? $selectedCalendarDate->copy()->subWeeks(2);
        $nextWeekDay = $weeklyRoster['next_week_day'] ?? $selectedCalendarDate->copy()->addWeeks(2);
        $rosterAlerts = $weeklyRoster['alerts'] ?? [];
        $roleBreakdown = $weeklyRoster['role_breakdown'] ?? [];
        $companyBreakdown = $weeklyRoster['company_breakdown'] ?? [];
        $shiftTemplates = $weeklyRoster['shift_templates'] ?? [];
        $weeklyAreaBoard = $weeklyAreaBoard ?? [];
        $areaBoardDays = $weeklyAreaBoard['days'] ?? $rosterDays;
        $areaRows = $weeklyAreaBoard['rows'] ?? [];
        $coverageSummary = $weeklyAreaBoard['coverage_summary'] ?? ['configured_cells'=>0,'under_cells'=>0,'met_cells'=>0,'over_cells'=>0,'missing_people'=>0];
        $complianceSummary = $complianceSummary ?? ['violation_shifts'=>0,'missing_breaks'=>0,'long_shifts'=>0,'short_rest'=>0,'planned_breaks'=>0];
        $budgetForecast = $budgetForecast ?? ['summary'=>['projected_cost'=>0,'unknown_shifts'=>0,'currency'=>'AUD']];
        $canCreateShift = ! empty($employees) && ! empty($roles) && ! empty($companies);
        $selectedView = $selectedView ?? 'team';
        $publishedCount = $rosterSummary['published_shifts'] ?? 0;
        $unpublishedCount = $rosterSummary['unpublished_shifts'] ?? 0;
        $updatedCount = $rosterSummary['updated_shifts'] ?? 0;
        $confirmationCount = $rosterSummary['confirmation_shifts'] ?? 0;
        $approvedLeaveCount = $rosterSummary['approved_leave'] ?? 0;
        $pendingLeaveCount = $rosterSummary['pending_leave'] ?? 0;
        $unavailablePeopleCount = $rosterSummary['unavailable_people'] ?? 0;
        $warningCount = count(array_filter($rosterAlerts, fn ($alert) => in_array($alert['type'] ?? '', ['warning', 'danger'], true)));
        $timeOffDays = $weeklyRoster['time_off_days'] ?? [];
        $employeeDiary = $employeeDiary ?? ['entries' => [], 'by_employee_date' => [], 'by_date' => [], 'count' => 0];
        $employeeDiaryByCell = $employeeDiary['by_employee_date'] ?? [];
        $employeeDiaryByDate = $employeeDiary['by_date'] ?? [];
        $viewQuery = ['view' => $selectedView];
    @endphp

    <div class="container-fluid schedule-page">
        <div class="schedule-toolbar">
            <div class="schedule-heading">
                <div class="schedule-kicker">
                    <i class="fas fa-calendar-week"></i>
                    Odoo Planning
                </div>
                <h1 class="schedule-title">Team Schedule</h1>
                <p class="schedule-subtitle">Weekly roster by team member, synced with Odoo planning slots.</p>
            </div>

            <div class="schedule-commandbar">
                <div class="command-location-wrap dropdown">
                    <button id="deputyLocationButton" class="btn command-location text-left" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-building mr-2"></i><span id="deputyLocationLabel">All companies</span><i class="fas fa-chevron-down float-right mt-1"></i>
                    </button>
                    <div class="dropdown-menu location-picker-menu" aria-labelledby="deputyLocationButton">
                        <div class="location-picker-head"><strong>Companies</strong><button type="button" class="btn btn-link btn-sm p-0" id="selectAllLocations">Select all</button></div>
                        <div class="location-picker-list">
                            @foreach ($companies as $company)
                                <label class="location-picker-item"><input type="checkbox" class="deputy-location-option" value="{{ $company['id'] }}" data-label="{{ $company['name'] }}" checked><span>{{ $company['name'] }}</span></label>
                            @endforeach
                        </div>
                        <div class="location-picker-foot"><button type="button" class="btn btn-light btn-sm" id="clearLocations">Reset</button><button type="button" class="btn btn-primary btn-sm" id="closeLocationPicker">Done</button></div>
                    </div>
                </div>
                <a href="{{ route('manager.shifts.create', array_merge($viewQuery, ['month' => $previousWeekDay->format('Y-m'), 'day' => $previousWeekDay->format('Y-m-d')])) }}"
                    class="btn command-icon" title="Previous 2 weeks">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <span class="schedule-period">{{ $weekLabel }}</span>
                <a href="{{ route('manager.shifts.create', array_merge($viewQuery, ['month' => $nextWeekDay->format('Y-m'), 'day' => $nextWeekDay->format('Y-m-d')])) }}"
                    class="btn command-icon" title="Next 2 weeks">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <select id="deputyViewSwitch" class="form-control command-view" aria-label="Schedule view">
                    <option value="{{ route('manager.shifts.create', ['month' => $selectedMonth->format('Y-m'), 'day' => $selectedCalendarDateValue, 'view' => 'area']) }}" {{ $selectedView === 'area' ? 'selected' : '' }}>2 weeks by Area</option>
                    <option value="{{ route('manager.shifts.create', ['month' => $selectedMonth->format('Y-m'), 'day' => $selectedCalendarDateValue, 'view' => 'team']) }}" {{ $selectedView === 'team' ? 'selected' : '' }}>2 weeks by Team member</option>
                </select>
                <select id="mobileScheduleDay" class="form-control mobile-day-switch" aria-label="Visible schedule day">
                    @foreach($rosterDays as $day)
                        <option value="{{ $day['date_value'] }}" {{ $day['is_selected'] ? 'selected' : '' }}>{{ $day['weekday'] }} {{ $day['day_number'] }}</option>
                    @endforeach
                </select>
                <span class="command-spacer"></span>
                <div class="btn-group command-utility-group" role="group" aria-label="Date controls">
                    <a href="{{ route('manager.shifts.create', array_merge($viewQuery, ['month' => now()->format('Y-m'), 'day' => now()->format('Y-m-d')])) }}"
                        class="btn command-icon" title="Go to today" aria-label="Go to today">
                        <i class="fas fa-calendar-day"></i>
                    </a>
                    <a href="{{ route('manager.shifts.create', array_merge($viewQuery, ['month' => $selectedMonth->format('Y-m'), 'day' => $selectedCalendarDateValue])) }}"
                        class="btn command-icon" title="Refresh schedule" aria-label="Refresh schedule">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
                <button type="button" class="btn command-copy" data-toggle="modal" data-target="#copy_schedule_period" title="Copy shifts"><i class="fas fa-copy mr-1"></i>Copy</button>
                <a href="{{ route('manager.shifts.confirmations', ['month' => $selectedMonth->format('Y-m'), 'day' => $selectedCalendarDateValue]) }}" class="btn command-icon position-relative" title="Confirmations"><i class="fas fa-clipboard-check"></i>@if ($confirmationCount > 0)<span class="badge badge-danger position-absolute" style="right:-3px;top:-3px">{{ $confirmationCount }}</span>@endif</a>
                <div class="dropdown">
                    <button class="btn command-icon" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Schedule options"><i class="fas fa-cog"></i></button>
                    <div class="dropdown-menu dropdown-menu-right schedule-options-menu">
                        <h6 class="dropdown-header">Schedule workspace</h6>
                        <button class="dropdown-item" type="button" data-toggle="collapse" data-target="#scheduleWorkspaceTools"><i class="fas fa-chart-bar mr-2"></i>Checks and insights</button>
                        <button class="dropdown-item" type="button" data-toggle="modal" data-target="#bulk_edit_schedule"><i class="fas fa-edit mr-2"></i>Bulk update selected</button>
                        <a class="dropdown-item" href="{{ route('manager.auto-schedule.index', ['week'=>$selectedCalendarDateValue]) }}"><i class="fas fa-magic mr-2"></i>Auto schedule coverage</a>
                        <a class="dropdown-item" href="{{ route('manager.shifts.confirmations', ['month'=>$selectedMonth->format('Y-m'),'day'=>$selectedCalendarDateValue]) }}"><i class="fas fa-clipboard-check mr-2"></i>Shift confirmations</a>
                        <a class="dropdown-item" href="{{ route('manager.schedule-templates.index', ['target_week'=>$selectedCalendarDateValue]) }}"><i class="fas fa-layer-group mr-2"></i>Schedule templates</a>
                        <a class="dropdown-item" href="{{ route('manager.schedule-areas.index') }}"><i class="fas fa-map-signs mr-2"></i>Areas & coverage</a>
                        <a class="dropdown-item" href="{{ route('manager.schedule-days.index', ['week'=>$selectedCalendarDateValue]) }}"><i class="fas fa-sticky-note mr-2"></i>Notes & blocked time</a>
                        <a class="dropdown-item" href="{{ route('manager.schedule-compliance.index', ['week'=>$selectedCalendarDateValue]) }}"><i class="fas fa-mug-hot mr-2"></i>Breaks & compliance</a>
                        <a class="dropdown-item" href="{{ route('manager.schedule-budget.index', ['week'=>$selectedCalendarDateValue]) }}"><i class="fas fa-coins mr-2"></i>Wage forecast & budget</a>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item" type="button" id="optionsCompactDensity"><i class="fas fa-compress-alt mr-2"></i>Toggle compact rows</button>
                        <button class="dropdown-item" type="button" data-toggle="modal" data-target="#schedule_keyboard_help"><i class="fas fa-keyboard mr-2"></i>Keyboard controls</button>
                        <button class="dropdown-item" type="button" data-toggle="modal" data-target="#create_odoo_shift" {{ $canCreateShift ? '' : 'disabled' }}><i class="fas fa-plus mr-2"></i>Create new shift</button>
                    </div>
                </div>
                <button type="button" class="btn command-icon schedule-primary-action"
                    data-toggle="modal" data-target="#create_odoo_shift" {{ $canCreateShift ? '' : 'disabled' }}>
                    <i class="fas fa-plus"></i>
                </button>
                <div class="btn-group publish-split" role="group">
                    <button type="button"
                        class="btn schedule-publish-action {{ $unpublishedCount === 0 ? 'is-clean' : '' }}"
                        data-toggle="modal" data-target="#publish_week_schedule"
                        {{ count($rosterRows) === 0 ? 'disabled' : '' }}>
                        <i class="fas fa-bullhorn mr-1"></i>{{ $unpublishedCount > 0 ? 'Publish shifts' : 'All shifts published' }}
                    </button>
                    <button type="button" class="btn schedule-publish-action dropdown-toggle dropdown-toggle-split {{ $unpublishedCount === 0 ? 'is-clean' : '' }}" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" {{ count($rosterRows) === 0 ? 'disabled' : '' }}><span class="sr-only">Publish options</span></button>
                    <div class="dropdown-menu dropdown-menu-right publish-preset-menu">
                        <button class="dropdown-item publish-mode-preset" type="button" data-mode="mark_only" data-confirmation="0"><i class="fas fa-check mr-2"></i>Mark as published only</button>
                        <button class="dropdown-item publish-mode-preset" type="button" data-mode="notify_email_app" data-confirmation="0"><i class="fas fa-paper-plane mr-2"></i>Publish and notify</button>
                        <button class="dropdown-item publish-mode-preset" type="button" data-mode="notify_email_app" data-confirmation="1"><i class="fas fa-user-check mr-2"></i>Publish, notify and confirm</button>
                    </div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('manager_shift'))
            <div class="alert alert-danger">
                {{ $errors->first('manager_shift') }}
            </div>
        @endif

        @if ($odooPlanningError)
            <div class="alert alert-warning">
                {{ $odooPlanningError }}
            </div>
        @endif

        @if (session('schedule_undo'))
            @php
                $scheduleUndo = session('schedule_undo');
            @endphp
            <div class="schedule-undo-toast" id="scheduleUndoToast" role="status" aria-live="polite">
                <div class="schedule-undo-icon"><i class="fas fa-history"></i></div>
                <div class="schedule-undo-copy"><strong>{{ $scheduleUndo['label'] }}</strong><small>{{ $scheduleUndo['count'] }} Odoo shift(s) created · Undo available for 10 minutes</small></div>
                <form method="POST" action="{{ route('manager.schedule.undo') }}">@csrf<input type="hidden" name="token" value="{{ $scheduleUndo['token'] }}"><button class="btn btn-warning btn-sm" type="submit"><i class="fas fa-undo mr-1"></i>Undo</button></form>
                <button class="schedule-undo-dismiss" type="button" aria-label="Dismiss undo message" onclick="this.closest('.schedule-undo-toast').remove()">×</button>
                <span class="schedule-undo-timer" aria-hidden="true"></span>
            </div>
        @endif

        @unless ($canCreateShift)
            <div class="alert alert-info">
                Shift creation is unavailable until Odoo returns employees, roles, and companies.
            </div>
        @endunless

        <div class="schedule-stats mb-4">
            <div class="schedule-stat">
                <span>Week Shifts</span>
                <strong>{{ $rosterSummary['shift_count'] ?? 0 }}</strong>
                <small>{{ count($recentShifts) }} loaded in this calendar range</small>
            </div>
            <div class="schedule-stat">
                <span>Scheduled Hours</span>
                <strong>{{ $rosterSummary['scheduled_hours'] ?? '0h' }}</strong>
                <small>{{ $rosterSummary['coverage_days'] ?? 0 }} covered day{{ ($rosterSummary['coverage_days'] ?? 0) === 1 ? '' : 's' }}</small>
            </div>
            <div class="schedule-stat">
                <span>People Scheduled</span>
                <strong>{{ $rosterSummary['people_scheduled'] ?? 0 }}</strong>
                <small>{{ count($employees) }} active employee{{ count($employees) === 1 ? '' : 's' }}</small>
            </div>
            <div class="schedule-stat">
                <span>Open Shifts</span>
                <strong>{{ $rosterSummary['open_shifts'] ?? 0 }}</strong>
                <small>Avg {{ $rosterSummary['average_shift'] ?? '0h' }} per shift</small>
            </div>
            <div class="schedule-stat">
                <span>Published</span>
                <strong>{{ $publishedCount }}</strong>
                <small>{{ $confirmationCount }} require confirmation</small>
            </div>
            <div class="schedule-stat">
                <span>Unpublished</span>
                <strong>{{ $unpublishedCount }}</strong>
                <small>{{ $updatedCount }} updated since last publish</small>
            </div>
            <div class="schedule-stat">
                <span>Approved Leave</span>
                <strong>{{ $approvedLeaveCount }}</strong>
                <small>{{ $pendingLeaveCount }} pending leave</small>
            </div>
            <div class="schedule-stat">
                <span>Unavailable</span>
                <strong>{{ $unavailablePeopleCount }}</strong>
                <small>People with weekly unavailability</small>
            </div>
            <div class="schedule-stat">
                <span>Employee Diary</span>
                <strong>{{ $employeeDiary['count'] ?? 0 }}</strong>
                <small>Availability signals and notes for this week</small>
            </div>
        </div>

        <div class="schedule-intelligence collapse" id="scheduleWorkspaceTools">
            <div class="schedule-intelligence-panel">
                <div class="panel-head">
                    <div>
                        <h6 class="m-0 font-weight-bold">Roster Checks</h6>
                        <p class="mb-0 small text-muted">Open shifts, long shifts, overtime and coverage warnings.</p>
                    </div>
                    <span class="badge badge-light">{{ count($rosterAlerts) }}</span>
                </div>
                <div class="panel-body">
                    @foreach ($rosterAlerts as $alert)
                        <div class="schedule-alert is-{{ $alert['type'] ?? 'info' }}">
                            <span class="schedule-alert-icon">
                                <i class="fas {{ $alert['icon'] ?? 'fa-info-circle' }}"></i>
                            </span>
                            <div>
                                <div class="schedule-alert-title">{{ $alert['title'] }}</div>
                                <div class="schedule-alert-message">{{ $alert['message'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="schedule-intelligence-panel">
                <div class="panel-head">
                    <div>
                        <h6 class="m-0 font-weight-bold">Coverage Mix</h6>
                        <p class="mb-0 small text-muted">Busiest day: {{ $rosterSummary['busiest_day'] ?? 'No shifts' }}</p>
                    </div>
                </div>
                <div class="panel-body">
                    @forelse ($roleBreakdown as $role)
                        <div class="breakdown-row">
                            <div class="breakdown-top">
                                <span>{{ $role['name'] }}</span>
                                <span>{{ $role['hours_label'] }} / {{ $role['shift_count'] }} shift{{ $role['shift_count'] === 1 ? '' : 's' }}</span>
                            </div>
                            <div class="breakdown-track">
                                <div class="breakdown-fill" style="width: {{ $role['share'] }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No role coverage to summarize yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="schedule-intelligence-panel">
                <div class="panel-head">
                    <div>
                        <h6 class="m-0 font-weight-bold">Templates & Clipboard</h6>
                        <p class="mb-0 small text-muted">Reuse common shifts or paste a copied card into another cell.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <div id="shiftClipboard" class="schedule-clipboard mb-3">
                        <span>
                            <i class="fas fa-copy mr-1"></i>
                            <span id="shiftClipboardLabel">Shift copied</span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-success" id="clearShiftClipboard">Clear</button>
                    </div>

                    @if (! empty($shiftTemplates))
                        <div class="template-rail">
                            @foreach ($shiftTemplates as $template)
                                <button type="button" class="template-chip apply-shift-template"
                                    data-toggle="modal"
                                    data-target="#create_odoo_shift"
                                    data-role-id="{{ $template['role_id'] }}"
                                    data-company-id="{{ $template['company_id'] }}"
                                    data-work-location-id="{{ $template['work_location_id'] }}"
                                    data-start-time="{{ $template['start_time'] }}"
                                    data-end-time="{{ $template['end_time'] }}"
                                    data-title="{{ $template['title'] }}"
                                    data-note="{{ $template['note'] }}">
                                    <i class="fas fa-layer-group"></i>
                                    {{ $template['title'] }} {{ $template['time_label'] }}
                                </button>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">Create a few shifts and this panel will suggest reusable templates.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="roster-shell mb-4">
            <div class="roster-board-head">
                <div>
                    <h6 class="m-0 font-weight-bold">{{ $selectedView === 'area' ? '2 Weeks by Area' : '2 Weeks by Team Member' }}</h6>
                    <p class="mb-0 small text-muted">
                        {{ $selectedView === 'area'
                            ? 'Review the week grouped by Odoo planning role, then drag shifts across areas and days.'
                            : 'Filter the roster, copy shifts, or use the plus button in a cell to prefill a shift.' }}
                    </p>
                    <div class="schedule-shortcuts mt-2">
                        <span>Shortcuts:</span>
                        <kbd>A</kbd><span>Add</span>
                        <kbd>C</kbd><span>Copy</span>
                        <kbd>V</kbd><span>Paste</span>
                        <kbd>P</kbd><span>Publish</span>
                        <kbd>Del</kbd><span>Delete</span>
                        <kbd>Esc</kbd><span>Clear</span>
                    </div>
                </div>
                @if ($selectedView === 'team')
                    <div class="roster-filters">
                        <div class="roster-search">
                            <i class="fas fa-search"></i>
                            <input type="search" id="rosterSearch" class="form-control" placeholder="Search team member">
                        </div>
                        <select id="rosterRoleFilter" class="form-control roster-filter">
                            <option value="">All roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                            @endforeach
                        </select>
                        <select id="rosterCompanyFilter" class="form-control roster-filter">
                            <option value="">All companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company['id'] }}">{{ $company['name'] }}</option>
                            @endforeach
                        </select>
                        <select id="rosterWorkLocationFilter" class="form-control roster-filter">
                            <option value="">All work locations</option>
                            @foreach ($workLocations as $location)
                                <option value="{{ $location['id'] }}">{{ $location['name'] }}</option>
                            @endforeach
                        </select>
                        <select id="rosterStatusFilter" class="form-control roster-filter">
                            <option value="">All shifts</option>
                            <option value="assigned">Assigned</option>
                            <option value="open">Open</option>
                            <option value="empty">Unscheduled</option>
                        </select>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleRosterDensitySecondary" title="Toggle compact density">
                            <i class="fas fa-compress-alt"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="clearRosterFilters" title="Clear filters">
                            Clear
                        </button>
                    </div>
                @else
                    <div class="area-coverage-summary">
                        @if(($coverageSummary['configured_cells'] ?? 0) > 0)
                            <span class="coverage-summary-chip is-under"><i class="fas fa-exclamation-triangle"></i> {{ $coverageSummary['under_cells'] }} understaffed</span>
                            <span class="coverage-summary-chip is-met"><i class="fas fa-check-circle"></i> {{ $coverageSummary['met_cells'] }} covered</span>
                            <span class="coverage-summary-chip"><i class="fas fa-user-plus"></i> {{ $coverageSummary['missing_people'] }} people needed</span>
                        @else
                            <span class="small text-muted font-weight-bold">No coverage targets configured.</span>
                        @endif
                        <a href="{{ route('manager.schedule-areas.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-sliders-h mr-1"></i>Configure</a>
                    </div>
                @endif
            </div>

            <div class="schedule-bulkbar" id="scheduleBulkBar">
                <div>
                    <div class="schedule-bulkbar-title"><span id="selectedShiftCount">0</span> shift(s) selected</div>
                    <div class="schedule-bulkbar-meta">Use Ctrl/Cmd + click to multi-select cards across the roster.</div>
                </div>
                <div class="schedule-bulkbar-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="bulkClearSelection">
                        Clear
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="bulkOpenShift">
                        <i class="fas fa-inbox mr-1"></i>
                        Make Open
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="bulkEditShift" data-toggle="modal" data-target="#bulk_edit_schedule">
                        <i class="fas fa-edit mr-1"></i>Bulk Edit
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="bulkDeleteShift">
                        <i class="fas fa-trash mr-1"></i>
                        Delete Selected
                    </button>
                </div>
            </div>

            @if ($selectedView === 'team')
                <div class="roster-scroll">
                    <div class="roster-grid" id="teamRosterGrid" style="--schedule-day-count: {{ count($rosterDays) }}">
                    <div class="roster-corner">
                        <span>Team Member</span>
                        <span>{{ count($rosterRows) }}</span>
                    </div>

                    @foreach ($rosterDays as $day)
                        <a href="{{ route('manager.shifts.create', array_merge($viewQuery, ['month' => $day['date']->format('Y-m'), 'day' => $day['date_value']])) }}"
                            class="roster-day-head {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_selected'] ? 'is-selected' : '' }}" data-schedule-date="{{ $day['date_value'] }}">
                            <div class="roster-day-top">
                                <div>
                                    <div class="roster-day-name">{{ $day['weekday'] }}</div>
                                    <div class="roster-day-number">{{ $day['day_number'] }}</div>
                                </div>
                                <span class="roster-day-total">{{ $day['hours_label'] }}</span>
                            </div>
                            <div class="small text-muted font-weight-bold mt-1">
                                {{ $day['shift_count'] }} shift{{ $day['shift_count'] === 1 ? '' : 's' }}
                            </div>
                            @if(!empty($day['holiday_labels']) || !empty($day['has_day_note']) || !empty($day['blocked_labels']))
                                <div class="day-signal-row">@if(!empty($day['holiday_labels']))<span class="day-signal is-holiday"><i class="fas fa-star"></i>{{ implode(', ',$day['holiday_labels']) }}</span>@endif @if(!empty($day['has_day_note']))<span class="day-signal" title="Day note"><i class="fas fa-sticky-note"></i></span>@endif @if(!empty($day['blocked_labels']))<span class="day-signal is-blocked" title="Blocked {{ implode(', ',$day['blocked_labels']) }}"><i class="fas fa-ban"></i></span>@endif</div>
                            @endif
                        </a>
                    @endforeach

                    <div class="roster-timeoff-label">
                        <span>Time Off</span>
                        <span>{{ $approvedLeaveCount + $pendingLeaveCount + $unavailablePeopleCount }}</span>
                    </div>

                    @foreach ($rosterDays as $day)
                        @php
                            $dayTimeOff = $timeOffDays[$day['date_value']] ?? ['approved_leave_count' => 0, 'pending_leave_count' => 0, 'unavailable_count' => 0];
                        @endphp
                        <div class="roster-timeoff-day">
                            <div class="timeoff-day-stack">
                                @if (($dayTimeOff['approved_leave_count'] ?? 0) > 0)
                                    <span class="timeoff-day-chip is-approved">
                                        <i class="fas fa-plane-departure"></i>
                                        {{ $dayTimeOff['approved_leave_count'] }} approved
                                    </span>
                                @endif
                                @if (($dayTimeOff['pending_leave_count'] ?? 0) > 0)
                                    <span class="timeoff-day-chip is-pending">
                                        <i class="fas fa-hourglass-half"></i>
                                        {{ $dayTimeOff['pending_leave_count'] }} pending
                                    </span>
                                @endif
                                @if (($dayTimeOff['unavailable_count'] ?? 0) > 0)
                                    <span class="timeoff-day-chip is-unavailable">
                                        <i class="fas fa-user-clock"></i>
                                        {{ $dayTimeOff['unavailable_count'] }} unavailable
                                    </span>
                                @endif
                                @if (($dayTimeOff['approved_leave_count'] ?? 0) === 0 && ($dayTimeOff['pending_leave_count'] ?? 0) === 0 && ($dayTimeOff['unavailable_count'] ?? 0) === 0)
                                    <span class="small text-muted font-weight-bold">No time off</span>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @forelse ($rosterRows as $rowIndex => $row)
                        @php
                            $rowKey = 'roster-row-'.$rowIndex;
                            $rowSearch = strtolower(($row['employee'] ?? '').' '.($row['company'] ?? '').' '.($row['work_email'] ?? ''));
                        @endphp
                        <div class="roster-person {{ $row['is_open'] ? 'is-open' : '' }}"
                            data-roster-key="{{ $rowKey }}"
                            data-roster-search="{{ $rowSearch }}"
                            data-roster-company-id="{{ $row['company_id'] ?? '' }}"
                            data-roster-work-location-id="{{ $row['work_location_id'] ?? '' }}"
                            data-roster-has-shifts="{{ $row['shift_count'] > 0 ? '1' : '0' }}"
                            data-roster-open="{{ $row['is_open'] ? '1' : '0' }}"
                            data-employee-id="{{ $row['is_open'] ? '' : $row['employee_id'] }}"
                            data-draggable="{{ $canCreateShift ? '1' : '0' }}">
                            <div class="roster-person-main">
                                <span class="roster-avatar">{{ $row['initials'] }}</span>
                                <div class="min-w-0">
                                    <div class="roster-person-name">{{ $row['employee'] }}</div>
                                    <div class="roster-person-meta roster-person-company" title="{{ $row['company'] }}">{{ $row['company'] }}</div>
                                    @if (! empty($row['work_email']))
                                        <div class="roster-person-meta roster-person-email" title="{{ $row['work_email'] }}">{{ $row['work_email'] }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="roster-person-hours">
                                <span><i class="fas fa-clock"></i> {{ $row['scheduled_hours'] }}</span>
                                <span><i class="fas fa-layer-group"></i> {{ $row['shift_count'] }} shift{{ $row['shift_count'] === 1 ? '' : 's' }}</span>
                            </div>
                        </div>

                        @foreach ($rosterDays as $day)
                            @php
                                $cell = $row['cells'][$day['date_value']] ?? ['shifts' => [], 'shift_count' => 0, 'hours_label' => '0h'];
                                $cellDiary = $row['is_open'] ? [] : ($employeeDiaryByCell[$row['employee_id']][$day['date_value']] ?? []);
                            @endphp
                            <div class="roster-cell schedule-drop-cell {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_selected'] ? 'is-selected' : '' }}"
                                tabindex="0" role="gridcell" aria-label="{{ $row['employee'] }} on {{ $day['date']->format('l, F j') }}"
                                data-roster-key="{{ $rowKey }}"
                                data-shift-date="{{ $day['date_value'] }}"
                                data-employee-id="{{ $row['is_open'] ? '' : $row['employee_id'] }}"
                                data-work-location-id="{{ $row['is_open'] ? '' : ($row['work_location_id'] ?? '') }}"
                                data-roster-open="{{ $row['is_open'] ? '1' : '0' }}">
                                <div class="shift-stack">
                                    @foreach ($cellDiary as $diaryEntry)
                                        <div class="employee-diary-chip is-{{ $diaryEntry['type_class'] }}"
                                            title="{{ $diaryEntry['type_label'] }}: {{ $diaryEntry['title'] }} — {{ $diaryEntry['time_label'] }}{{ $diaryEntry['notes'] ? ' — '.$diaryEntry['notes'] : '' }}">
                                            <i class="fas {{ $diaryEntry['icon'] }}"></i>
                                            <span>{{ $diaryEntry['title'] }} · {{ $diaryEntry['time_label'] }}</span>
                                        </div>
                                    @endforeach
                                    @if (! empty($cell['time_off']))
                                        <div class="timeoff-chip-row">
                                            @foreach ($cell['time_off'] as $entry)
                                                <span class="timeoff-chip {{ ($entry['kind'] ?? '') === 'leave-approved' ? 'is-approved' : (($entry['kind'] ?? '') === 'leave-pending' ? 'is-pending' : 'is-unavailable') }}"
                                                    title="{{ $entry['label'] ?? '' }} {{ $entry['time_label'] ?? '' }}">
                                                    @if (($entry['kind'] ?? '') === 'leave-approved')
                                                        <i class="fas fa-plane-departure"></i>
                                                    @elseif (($entry['kind'] ?? '') === 'leave-pending')
                                                        <i class="fas fa-hourglass-half"></i>
                                                    @else
                                                        <i class="fas fa-user-clock"></i>
                                                    @endif
                                                    {{ $entry['short_label'] ?? $entry['label'] ?? 'Time Off' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @foreach ($cell['shifts'] as $shift)
                                        <div class="shift-card shift-tone-{{ $shift['tone'] ?? 'green' }}" tabindex="0" role="button" aria-label="{{ $shift['role'] }} shift, {{ $shift['time_label'] }}, {{ $shift['publish_state_label'] ?? 'Unpublished' }}"
                                            data-roster-key="{{ $rowKey }}"
                                            data-shift-id="{{ $shift['id'] }}"
                                            data-role-id="{{ $shift['role_id'] ?? '' }}"
                                            data-company-id="{{ $shift['company_id'] ?? '' }}"
                                            data-work-location-id="{{ $shift['work_location_id'] ?? '' }}"
                                            data-employee-id="{{ $shift['employee_id'] ?? '' }}"
                                            data-shift-title="{{ $shift['title_value'] }}"
                                            data-shift-note="{{ $shift['note'] }}"
                                            data-shift-date="{{ $shift['shift_date_value'] }}"
                                            data-shift-start="{{ $shift['start_time_value'] }}"
                                            data-shift-end="{{ $shift['end_time_value'] }}"
                                            data-shift-write-date="{{ $shift['write_date_value'] }}"
                                            data-update-url="{{ route('manager.shifts.update', $shift['id']) }}"
                                            data-draggable="{{ $canCreateShift ? '1' : '0' }}">
                                            <button type="button" tabindex="-1" class="shift-resize-handle is-start" aria-label="Resize shift start with pointer"></button>
                                            <button type="button" tabindex="-1" class="shift-resize-handle is-end" aria-label="Resize shift end with pointer"></button>
                                            <button type="button" class="shift-overflow-toggle" aria-label="Open shift actions" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="shift-actions">
                                                <button type="button" class="shift-icon-btn copy-odoo-shift"
                                                    title="Copy shift"
                                                    data-shift-title="{{ $shift['title_value'] }}"
                                                    data-shift-note="{{ $shift['note'] }}"
                                                    data-shift-start="{{ $shift['start_time_value'] }}"
                                                    data-shift-end="{{ $shift['end_time_value'] }}"
                                                    data-shift-role-id="{{ $shift['role_id'] }}"
                                                    data-shift-company-id="{{ $shift['company_id'] }}"
                                                    data-shift-work-location-id="{{ $shift['work_location_id'] ?? '' }}"
                                                    data-shift-label="{{ $shift['role'] }} {{ $shift['time_label'] }}">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <button type="button" class="shift-icon-btn edit-odoo-shift"
                                                    title="Edit shift"
                                                    data-toggle="modal"
                                                    data-target="#edit_odoo_shift"
                                                    data-shift-id="{{ $shift['id'] }}"
                                                    data-shift-title="{{ $shift['title_value'] }}"
                                                    data-shift-note="{{ $shift['note'] }}"
                                                    data-shift-date="{{ $shift['shift_date_value'] }}"
                                                    data-shift-start="{{ $shift['start_time_value'] }}"
                                                    data-shift-end="{{ $shift['end_time_value'] }}"
                                                    data-shift-employee-id="{{ $shift['employee_id'] }}"
                                                    data-shift-role-id="{{ $shift['role_id'] }}"
                                                    data-shift-company-id="{{ $shift['company_id'] }}"
                                                    data-shift-work-location-id="{{ $shift['work_location_id'] ?? '' }}"
                                                    data-shift-write-date="{{ $shift['write_date_value'] }}"
                                                    data-update-url="{{ route('manager.shifts.update', $shift['id']) }}">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <form action="{{ route('manager.shifts.destroy', $shift['id']) }}"
                                                    method="POST" class="d-inline-block"
                                                    onsubmit="return confirm('Delete this Odoo shift?');">
                                                    @csrf
                                                    <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                                                    <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
                                                    <input type="hidden" name="last_known_write_date" value="{{ $shift['write_date_value'] }}">
                                                    <button type="submit" class="shift-icon-btn" title="Delete shift">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="shift-status-badge is-{{ $shift['publish_state'] ?? 'unpublished' }}">
                                                {{ $shift['publish_state_label'] ?? 'Unpublished' }}
                                            </div>
                                            @if($shift['was_open_shift_claim'] ?? false)<div class="shift-status-badge is-published mt-1"><i class="fas fa-hand-paper mr-1"></i>Claimed open shift</div>@endif
                                            <div class="shift-time">{{ $shift['time_label'] }}</div>
                                            @if(!empty($shift['compliance_warnings']))<div class="shift-compliance-chip"><i class="fas fa-exclamation-triangle"></i>{{ count($shift['compliance_warnings']) }} warning{{ count($shift['compliance_warnings'])===1?'':'s' }}</div>@elseif(($shift['planned_break_minutes']??0)>0)<div class="shift-break-chip"><i class="fas fa-mug-hot"></i>{{ $shift['planned_break_minutes'] }}m break</div>@endif
                                            <div class="shift-role">{{ $shift['role'] }}</div>
                                            <div class="shift-location"><i class="fas fa-map-marker-alt"></i>{{ $shift['work_location'] ?? 'No work location' }}</div>
                                            <div class="shift-note">{{ $shift['duration_label'] }}{{ $shift['company'] ? ' | '.$shift['company'] : '' }}</div>
                                            <div class="shift-resize-meta">Drag edges to resize in 15-minute steps</div>
                                        </div>
                                    @endforeach

                                    @if ($canCreateShift)
                                        <button type="button" class="cell-add quick-add-shift"
                                            title="Add shift"
                                            data-toggle="modal"
                                            data-target="#create_odoo_shift"
                                            data-shift-date="{{ $day['date_value'] }}"
                                            data-employee-id="{{ $row['is_open'] ? '' : $row['employee_id'] }}"
                                            data-company-id="{{ $row['is_open'] ? '' : ($row['company_id'] ?? '') }}"
                                            data-work-location-id="{{ $row['is_open'] ? '' : ($row['work_location_id'] ?? '') }}">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button type="button" class="cell-add cell-paste paste-shift"
                                            title="Paste copied shift"
                                            data-toggle="modal"
                                            data-target="#create_odoo_shift"
                                            data-shift-date="{{ $day['date_value'] }}"
                                            data-employee-id="{{ $row['is_open'] ? '' : $row['employee_id'] }}">
                                            <i class="fas fa-paste mr-1"></i>
                                            Paste
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @empty
                        <div class="p-4 text-center text-muted" style="grid-column: 1 / -1;">
                            <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                            <div>No roster rows are available from Odoo yet.</div>
                        </div>
                    @endforelse
                    </div>
                </div>
            @else
                <div class="roster-scroll">
                    <div class="area-grid" id="areaBoardGrid" style="--schedule-day-count: {{ count($areaBoardDays) }}">
                        <div class="area-corner">
                            <span>Area</span>
                            <span>{{ count($areaRows) }}</span>
                        </div>

                        @foreach ($areaBoardDays as $day)
                            <a href="{{ route('manager.shifts.create', array_merge($viewQuery, ['month' => $day['date']->format('Y-m'), 'day' => $day['date_value']])) }}"
                                class="area-day-head {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_selected'] ? 'is-selected' : '' }}" data-schedule-date="{{ $day['date_value'] }}">
                                <div class="roster-day-top">
                                    <div>
                                        <div class="roster-day-name">{{ $day['weekday'] }}</div>
                                        <div class="roster-day-number">{{ $day['day_number'] }}</div>
                                    </div>
                                </div>
                                @if(!empty($day['holiday_labels']) || !empty($day['has_day_note']) || !empty($day['blocked_labels']))
                                    <div class="day-signal-row">@if(!empty($day['holiday_labels']))<span class="day-signal is-holiday"><i class="fas fa-star"></i>{{ implode(', ',$day['holiday_labels']) }}</span>@endif @if(!empty($day['has_day_note']))<span class="day-signal"><i class="fas fa-sticky-note"></i></span>@endif @if(!empty($day['blocked_labels']))<span class="day-signal is-blocked"><i class="fas fa-ban"></i></span>@endif</div>
                                @endif
                            </a>
                        @endforeach

                        @forelse ($areaRows as $rowIndex => $row)
                            @php
                                $areaKey = 'area-row-'.$rowIndex;
                            @endphp
                            <div class="area-row-head" data-area-key="{{ $areaKey }}" data-area-company-id="{{ $row['company_id'] ?? '' }}">
                                <div class="area-row-name"><span class="area-name-swatch" style="background:{{ $row['area_color'] ?? '#64748b' }}"></span>{{ $row['area_name'] ?? $row['role'] }}</div>
                                <div class="area-row-meta">{{ $row['company'] }} · {{ $row['role'] }}</div>
                                <div class="area-row-stats">
                                    <span><i class="fas fa-layer-group"></i> {{ $row['shift_count'] }} shift{{ $row['shift_count'] === 1 ? '' : 's' }}</span>
                                    <span><i class="fas fa-inbox"></i> {{ $row['open_shift_count'] }} open</span>
                                    <span><i class="fas fa-clock"></i> {{ $row['scheduled_hours'] }}</span>
                                </div>
                            </div>

                            @foreach ($areaBoardDays as $day)
                                @php
                                    $cell = $row['cells'][$day['date_value']] ?? ['shifts' => [], 'shift_count' => 0, 'assigned_count' => 0, 'open_count' => 0, 'hours_label' => '0h'];
                                @endphp
                                <div class="area-cell schedule-drop-cell coverage-{{ $cell['coverage_status'] ?? 'unconfigured' }} {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_selected'] ? 'is-selected' : '' }}"
                                    tabindex="0" role="gridcell" aria-label="{{ $row['role'] }} on {{ $day['date']->format('l, F j') }}"
                                    data-area-key="{{ $areaKey }}"
                                    data-area-company-id="{{ $row['company_id'] ?? '' }}"
                                    data-shift-date="{{ $day['date_value'] }}"
                                    data-role-id="{{ $row['role_id'] }}"
                                    data-company-id="{{ $row['company_id'] ?? '' }}">
                                    @if(($cell['coverage_required'] ?? 0) > 0)
                                        <div class="coverage-cell-badge" aria-label="{{ $cell['assigned_count'] }} of {{ $cell['coverage_required'] }} required people scheduled">
                                            <i class="fas {{ ($cell['coverage_status'] ?? '') === 'under' ? 'fa-exclamation-triangle' : 'fa-user-check' }}"></i>
                                            {{ $cell['assigned_count'] }}/{{ $cell['coverage_required'] }}
                                        </div>
                                    @endif
                                    @if(($cell['blocked_shift_count'] ?? 0) > 0)
                                        <div class="blocked-shift-warning"><i class="fas fa-ban"></i> {{ $cell['blocked_shift_count'] }} blocked-time conflict{{ $cell['blocked_shift_count']===1?'':'s' }}</div>
                                    @elseif(!empty($cell['blocked_labels']))
                                        <div class="blocked-window-label"><i class="fas fa-ban"></i> {{ implode(', ',$cell['blocked_labels']) }}</div>
                                    @endif
                                    <div class="shift-stack">
                                        @foreach ($cell['shifts'] as $shift)
                                            <div class="shift-card shift-tone-{{ $shift['tone'] ?? 'green' }}" tabindex="0" role="button" aria-label="{{ $shift['employee'] ?: 'Open shift' }}, {{ $shift['time_label'] }}, {{ $shift['publish_state_label'] ?? 'Unpublished' }}"
                                                data-area-key="{{ $areaKey }}"
                                                data-shift-id="{{ $shift['id'] }}"
                                                data-role-id="{{ $shift['role_id'] ?? '' }}"
                                                data-company-id="{{ $shift['company_id'] ?? '' }}"
                                                data-work-location-id="{{ $shift['work_location_id'] ?? '' }}"
                                                data-employee-id="{{ $shift['employee_id'] ?? '' }}"
                                                data-shift-title="{{ $shift['title_value'] }}"
                                                data-shift-note="{{ $shift['note'] }}"
                                                data-shift-date="{{ $shift['shift_date_value'] }}"
                                                data-shift-start="{{ $shift['start_time_value'] }}"
                                                data-shift-end="{{ $shift['end_time_value'] }}"
                                                data-shift-write-date="{{ $shift['write_date_value'] }}"
                                                data-update-url="{{ route('manager.shifts.update', $shift['id']) }}"
                                                data-draggable="{{ $canCreateShift ? '1' : '0' }}">
                                                <button type="button" tabindex="-1" class="shift-resize-handle is-start" aria-label="Resize shift start with pointer"></button>
                                                <button type="button" tabindex="-1" class="shift-resize-handle is-end" aria-label="Resize shift end with pointer"></button>
                                                <button type="button" class="shift-overflow-toggle" aria-label="Open shift actions" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></button>
                                                <div class="shift-actions">
                                                    <button type="button" class="shift-icon-btn copy-odoo-shift"
                                                        title="Copy shift"
                                                        data-shift-title="{{ $shift['title_value'] }}"
                                                        data-shift-note="{{ $shift['note'] }}"
                                                        data-shift-start="{{ $shift['start_time_value'] }}"
                                                        data-shift-end="{{ $shift['end_time_value'] }}"
                                                        data-shift-role-id="{{ $shift['role_id'] }}"
                                                        data-shift-company-id="{{ $shift['company_id'] }}"
                                                        data-shift-work-location-id="{{ $shift['work_location_id'] ?? '' }}"
                                                        data-shift-label="{{ $shift['employee'] }} {{ $shift['time_label'] }}">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                    <button type="button" class="shift-icon-btn edit-odoo-shift"
                                                        title="Edit shift"
                                                        data-toggle="modal"
                                                        data-target="#edit_odoo_shift"
                                                        data-shift-id="{{ $shift['id'] }}"
                                                        data-shift-title="{{ $shift['title_value'] }}"
                                                        data-shift-note="{{ $shift['note'] }}"
                                                        data-shift-date="{{ $shift['shift_date_value'] }}"
                                                        data-shift-start="{{ $shift['start_time_value'] }}"
                                                        data-shift-end="{{ $shift['end_time_value'] }}"
                                                        data-shift-employee-id="{{ $shift['employee_id'] }}"
                                                        data-shift-role-id="{{ $shift['role_id'] }}"
                                                        data-shift-company-id="{{ $shift['company_id'] }}"
                                                        data-shift-work-location-id="{{ $shift['work_location_id'] ?? '' }}"
                                                        data-shift-write-date="{{ $shift['write_date_value'] }}"
                                                        data-update-url="{{ route('manager.shifts.update', $shift['id']) }}">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <form action="{{ route('manager.shifts.destroy', $shift['id']) }}"
                                                        method="POST" class="d-inline-block"
                                                        onsubmit="return confirm('Delete this Odoo shift?');">
                                                        @csrf
                                                        <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                                                        <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
                                                        <input type="hidden" name="view" value="{{ $selectedView }}">
                                                        <input type="hidden" name="last_known_write_date" value="{{ $shift['write_date_value'] }}">
                                                        <button type="submit" class="shift-icon-btn" title="Delete shift">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <div class="shift-status-badge is-{{ $shift['publish_state'] ?? 'unpublished' }}">
                                                    {{ $shift['publish_state_label'] ?? 'Unpublished' }}
                                                </div>
                                                @if($shift['was_open_shift_claim'] ?? false)<div class="shift-status-badge is-published mt-1"><i class="fas fa-hand-paper mr-1"></i>Claimed open shift</div>@endif
                                                <div class="shift-time">{{ $shift['time_label'] }}</div>
                                                @if(!empty($shift['compliance_warnings']))<div class="shift-compliance-chip"><i class="fas fa-exclamation-triangle"></i>{{ count($shift['compliance_warnings']) }} warning{{ count($shift['compliance_warnings'])===1?'':'s' }}</div>@elseif(($shift['planned_break_minutes']??0)>0)<div class="shift-break-chip"><i class="fas fa-mug-hot"></i>{{ $shift['planned_break_minutes'] }}m break</div>@endif
                                                <div class="shift-role">{{ $shift['employee'] ?: 'Open Shift' }}</div>
                                                <div class="shift-location"><i class="fas fa-map-marker-alt"></i>{{ $shift['work_location'] ?? 'No work location' }}</div>
                                                <div class="shift-note">{{ $shift['duration_label'] }}{{ $shift['company'] ? ' | '.$shift['company'] : '' }}</div>
                                                <div class="shift-resize-meta">Drag across areas or days to reassign</div>
                                            </div>
                                        @endforeach

                                        @if ($canCreateShift)
                                            <button type="button" class="cell-add quick-add-shift"
                                                title="Add shift"
                                                data-toggle="modal"
                                                data-target="#create_odoo_shift"
                                                data-shift-date="{{ $day['date_value'] }}"
                                                data-role-id="{{ $row['role_id'] }}"
                                                data-company-id="{{ $row['company_id'] ?? '' }}">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button type="button" class="cell-add cell-paste paste-shift"
                                                title="Paste copied shift"
                                                data-toggle="modal"
                                                data-target="#create_odoo_shift"
                                                data-shift-date="{{ $day['date_value'] }}"
                                                data-role-id="{{ $row['role_id'] }}"
                                                data-company-id="{{ $row['company_id'] ?? '' }}">
                                                <i class="fas fa-paste mr-1"></i>
                                                Paste
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @empty
                            <div class="p-4 text-center text-muted" style="grid-column: 1 / -1;">
                                <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                                <div>No area rows are available from Odoo yet.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

        </div>

        <div class="row">
            <div class="col-xl-4 mb-4">
                <div class="schedule-side-panel h-100">
                    <div class="panel-head">
                        <h6 class="m-0 font-weight-bold">Day Focus</h6>
                        <p class="mb-0 small text-muted">{{ $selectedCalendarDateLabel }}</p>
                    </div>
                    <div class="panel-body">
                        @php $selectedDayDiary = $employeeDiaryByDate[$selectedCalendarDateValue] ?? []; @endphp
                        @if (! empty($selectedDayDiary))
                            <div class="mb-3">
                                <div class="small font-weight-bold text-uppercase text-muted mb-2">Employee diary signals</div>
                                @foreach ($selectedDayDiary as $diaryEntry)
                                    <div class="employee-diary-focus">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <strong>{{ $diaryEntry['employee_name'] }}</strong>
                                            <span class="badge badge-{{ $diaryEntry['type_class'] === 'available' ? 'success' : ($diaryEntry['type_class'] === 'unavailable' ? 'danger' : 'warning') }}">{{ $diaryEntry['type_label'] }}</span>
                                        </div>
                                        <div class="small font-weight-bold mt-1">{{ $diaryEntry['title'] }} · {{ $diaryEntry['time_label'] }}</div>
                                        @if ($diaryEntry['notes'])<div class="small text-muted mt-1">{{ $diaryEntry['notes'] }}</div>@endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if (empty($selectedCalendarShifts))
                            <div class="text-center py-4">
                                <i class="fas fa-user-clock fa-3x text-gray-300 mb-3"></i>
                                <p class="text-muted mb-0">No employees are assigned on this day.</p>
                            </div>
                        @else
                            @foreach ($selectedCalendarShifts as $shift)
                                <div class="day-assignment">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="font-weight-bold">{{ $shift['employee'] }}</div>
                                            <div class="small text-muted">{{ $shift['role'] }}</div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-weight-bold text-success">{{ $shift['duration_label'] ?? '' }}</div>
                                            <div class="small text-muted">{{ $shift['time_label'] }}</div>
                                        </div>
                                    </div>
                                    @if ($shift['title_value'] || $shift['note'])
                                        <div class="small text-muted mt-2">
                                            @if ($shift['title_value'])
                                                <div>{{ $shift['title'] }}</div>
                                            @endif
                                            @if ($shift['note'])
                                                <div>{{ $shift['note'] }}</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4 mb-4">
                <div class="schedule-side-panel h-100">
                    <div class="panel-head d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="m-0 font-weight-bold">{{ $selectedMonth->format('F Y') }}</h6>
                            <p class="mb-0 small text-muted">Month map</p>
                        </div>
                        <div class="text-nowrap">
                            <a href="{{ route('manager.shifts.create', ['month' => $previousMonth->format('Y-m')]) }}"
                                class="btn btn-sm btn-outline-secondary" title="Previous month">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <a href="{{ route('manager.shifts.create', ['month' => $nextMonth->format('Y-m')]) }}"
                                class="btn btn-sm btn-outline-secondary" title="Next month">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="month-mini-grid mb-2">
                            @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $weekday)
                                <div class="text-center small text-muted font-weight-bold">{{ $weekday }}</div>
                            @endforeach
                        </div>
                        <div class="month-mini-grid">
                            @foreach ($shiftCalendar as $week)
                                @foreach ($week as $day)
                                    <a href="{{ route('manager.shifts.create', ['month' => $day['date']->format('Y-m'), 'day' => $day['date_value']]) }}"
                                        class="month-mini-day {{ $day['is_current_month'] ? '' : 'is-outside' }} {{ $day['is_selected'] ? 'is-selected' : '' }}">
                                        <span>{{ $day['day_number'] }}</span>
                                        <span class="month-mini-count">{{ $day['shift_count'] }}</span>
                                    </a>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 mb-4">
                <div class="schedule-side-panel h-100">
                    <div class="panel-head">
                        <h6 class="m-0 font-weight-bold">Odoo Lists</h6>
                        <p class="mb-0 small text-muted">Selectable planning records</p>
                    </div>
                    <div class="panel-body">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="font-weight-bold">Employees</span>
                            <span>{{ count($employees) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="font-weight-bold">Roles</span>
                            <span>{{ count($roles) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-2">
                            <span class="font-weight-bold">Companies</span>
                            <span>{{ count($companies) }}</span>
                        </div>
                        @if (! empty($companyBreakdown))
                            <hr>
                            <div class="small text-muted font-weight-bold text-uppercase mb-2">Company workload</div>
                            @foreach ($companyBreakdown as $companyRow)
                                <div class="breakdown-row">
                                    <div class="breakdown-top">
                                        <span>{{ $companyRow['name'] }}</span>
                                        <span>{{ $companyRow['hours_label'] }}</span>
                                    </div>
                                    <div class="breakdown-track">
                                        <div class="breakdown-fill" style="width: {{ $companyRow['share'] }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="schedule-side-panel mb-4">
            <div class="panel-head d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="m-0 font-weight-bold">Planning Slots</h6>
                    <p class="mb-0 small text-muted">Loaded Odoo planning slots for the current calendar range.</p>
                </div>
                <span class="badge badge-light mt-2 mt-sm-0">{{ count($recentShifts) }} slot{{ count($recentShifts) === 1 ? '' : 's' }}</span>
            </div>
            <div class="panel-body">
                @if (empty($recentShifts))
                    <div class="text-center py-4">
                        <i class="fas fa-clock fa-3x text-gray-300 mb-3"></i>
                        <p class="text-muted mb-0">No planning slots were found.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover schedule-table mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Role</th>
                                    <th>Updated</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentShifts as $shift)
                                    <tr class="{{ $shift['date_value'] === $selectedCalendarDateValue ? 'table-success' : '' }}">
                                        <td>
                                            <div class="font-weight-bold">{{ $shift['employee'] }}</div>
                                            <div class="small text-muted">{{ $shift['company'] }}</div>
                                            <div class="small text-muted"><i class="fas fa-map-marker-alt mr-1"></i>{{ $shift['work_location'] ?? 'No work location' }}</div>
                                        </td>
                                        <td>{{ $shift['date_label'] }}</td>
                                        <td>{{ $shift['time_label'] }}</td>
                                        <td>{{ $shift['role'] }}</td>
                                        <td>{{ $shift['updated_label'] }}</td>
                                        <td class="text-nowrap">
                                            <button type="button"
                                                class="btn btn-outline-primary btn-sm mr-2 edit-odoo-shift"
                                                data-toggle="modal"
                                                data-target="#edit_odoo_shift"
                                                data-shift-id="{{ $shift['id'] }}"
                                                data-shift-title="{{ $shift['title_value'] }}"
                                                data-shift-note="{{ $shift['note'] }}"
                                                data-shift-date="{{ $shift['shift_date_value'] }}"
                                                data-shift-start="{{ $shift['start_time_value'] }}"
                                                data-shift-end="{{ $shift['end_time_value'] }}"
                                                data-shift-employee-id="{{ $shift['employee_id'] }}"
                                                data-shift-role-id="{{ $shift['role_id'] }}"
                                                data-shift-company-id="{{ $shift['company_id'] }}"
                                                data-shift-work-location-id="{{ $shift['work_location_id'] ?? '' }}"
                                                data-shift-write-date="{{ $shift['write_date_value'] }}"
                                                data-update-url="{{ route('manager.shifts.update', $shift['id']) }}">
                                                <i class="fas fa-pen mr-1"></i>
                                                Edit
                                            </button>
                                            <form action="{{ route('manager.shifts.destroy', $shift['id']) }}"
                                                method="POST" class="d-inline-block"
                                                onsubmit="return confirm('Delete this Odoo shift?');">
                                                @csrf
                                                <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                                                <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
                                                <input type="hidden" name="last_known_write_date" value="{{ $shift['write_date_value'] }}">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash mr-1"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <form id="dragShiftUpdateForm" action="" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="month" id="drag_shift_month">
        <input type="hidden" name="day" id="drag_shift_day">
        <input type="hidden" name="employee_id" id="drag_shift_employee_id">
        <input type="hidden" name="role_id" id="drag_shift_role_id">
        <input type="hidden" name="company_id" id="drag_shift_company_id">
        <input type="hidden" name="work_location_id" id="drag_shift_work_location_id">
        <input type="hidden" name="shift_date" id="drag_shift_date">
        <input type="hidden" name="start_time" id="drag_shift_start_time">
        <input type="hidden" name="end_time" id="drag_shift_end_time">
        <input type="hidden" name="title" id="drag_shift_title">
        <input type="hidden" name="note" id="drag_shift_note">
        <input type="hidden" name="last_known_write_date" id="drag_shift_write_date">
    </form>

    <form id="bulkDeleteShiftForm" action="{{ route('manager.shifts.bulk-delete') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
        <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
        <div id="bulkDeleteShiftFields"></div>
    </form>

    <form id="bulkOpenShiftForm" action="{{ route('manager.shifts.bulk-open') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
        <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
        <div id="bulkOpenShiftFields"></div>
    </form>

    <div id="copy_schedule_period" class="modal fade copy-schedule-modal" tabindex="-1" role="dialog" aria-labelledby="copyScheduleTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form action="{{ route('manager.shifts.copy-period') }}" method="POST">
                    @csrf
                    <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                    <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
                    <div class="modal-header">
                        <div class="copy-modal-heading">
                            <span class="copy-modal-icon"><i class="fas fa-copy"></i></span>
                            <div>
                                <h5 class="modal-title" id="copyScheduleTitle">Copy schedule</h5>
                                <p>Reuse the same people, roles, work locations, times, titles, and notes.</p>
                            </div>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="copySchedulePeriod">What would you like to copy?</label>
                            <select id="copySchedulePeriod" name="period" class="form-control">
                                <option value="day">Selected day only</option>
                                <option value="two_weeks">Visible 2-week schedule</option>
                            </select>
                            <small class="form-text text-muted">The two-week option copies all 14 visible days and preserves each shift’s day offset.</small>
                        </div>
                        <div class="copy-date-grid">
                            <div class="form-group mb-0">
                                <label for="copyScheduleFrom">Copy from</label>
                                <input id="copyScheduleFrom" type="date" name="source_date" class="form-control" value="{{ $selectedCalendarDateValue }}" required>
                            </div>
                            <span class="copy-date-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
                            <div class="form-group mb-0">
                                <label for="copyScheduleTo">Copy to</label>
                                <input id="copyScheduleTo" type="date" name="target_date" class="form-control" value="{{ $weeklyRoster['next_week_day']->toDateString() }}" required>
                            </div>
                        </div>
                        <div class="copy-preserve-note">
                            <i class="fas fa-shield-alt"></i>
                            <span>Existing shifts are not changed. If any copied shift fails, the entire new copy attempt is rolled back.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-copy mr-1"></i>Copy schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="schedule_keyboard_help" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Schedule keyboard controls</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body keyboard-help-list">
                <div><kbd>Arrow keys</kbd><span>Move between roster cells</span></div>
                <div><kbd>Enter</kbd><span>Add in a cell or edit a selected shift</span></div>
                <div><kbd>Alt</kbd> + <kbd>←</kbd>/<kbd>→</kbd><span>Move selected shift by one day</span></div>
                <div><kbd>Alt</kbd> + <kbd>↑</kbd>/<kbd>↓</kbd><span>Move selected shift by 15 minutes</span></div>
                <div><kbd>Shift</kbd> + <kbd>Alt</kbd> + <kbd>↑</kbd>/<kbd>↓</kbd><span>Resize selected shift by 15 minutes</span></div>
                <div><kbd>C</kbd>/<kbd>V</kbd><span>Copy and paste selected shift</span></div>
                <div><kbd>Delete</kbd><span>Delete selected shift(s)</span></div>
            </div>
        </div></div>
    </div>

    <div id="bulk_edit_schedule" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
            <form id="bulkUpdateShiftForm" action="{{ route('manager.shifts.bulk-update') }}" method="POST">
                @csrf
                <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
                <div id="bulkUpdateShiftFields"></div>
                <div class="modal-header"><h5 class="modal-title">Edit Selected Shifts</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <p class="small text-muted">Only completed fields will be applied. Existing dates and other values stay unchanged.</p>
                    <div class="form-group"><label>Employee</label><select name="employee_id" class="form-control"><option value="">Keep current employee</option>@foreach($employees as $employee)<option value="{{ $employee['id'] }}">{{ $employee['name'] }}</option>@endforeach</select></div>
                    <div class="form-row"><div class="form-group col"><label>Role</label><select name="role_id" class="form-control"><option value="">Keep current role</option>@foreach($roles as $role)<option value="{{ $role['id'] }}">{{ $role['name'] }}</option>@endforeach</select></div><div class="form-group col"><label>Company</label><select name="company_id" class="form-control"><option value="">Keep current company</option>@foreach($companies as $company)<option value="{{ $company['id'] }}">{{ $company['name'] }}</option>@endforeach</select></div></div>
                    <div class="form-group"><label>Work Location</label><select name="work_location_id" class="form-control"><option value="">Keep current work location</option>@foreach($workLocations as $location)<option value="{{ $location['id'] }}">{{ $location['name'] }}{{ $location['address'] ? ' · '.$location['address'] : '' }}</option>@endforeach</select></div>
                    <div class="form-row"><div class="form-group col"><label>Start time</label><input type="time" name="start_time" class="form-control"></div><div class="form-group col"><label>End time</label><input type="time" name="end_time" class="form-control"></div></div>
                    <div class="form-group"><label>Title</label><input type="text" name="title" maxlength="120" class="form-control"></div>
                    <div class="form-group mb-0"><label>Note</label><textarea name="note" maxlength="2000" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update selected shifts</button></div>
            </form>
        </div></div>
    </div>

    <div id="publish_week_schedule" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Publish Visible Week</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('manager.shifts.publish-week') }}" method="POST">
                        @csrf
                        <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                        <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">

                        <div class="mb-3">
                            <div class="font-weight-bold">{{ $weekLabel }}</div>
                            <div class="small text-muted">This publishes the shifts currently visible in the roster week.</div>
                        </div>

                        <div class="schedule-alert is-{{ $unpublishedCount > 0 ? 'warning' : 'success' }} mb-3">
                            <span class="schedule-alert-icon">
                                <i class="fas {{ $unpublishedCount > 0 ? 'fa-bullhorn' : 'fa-check-circle' }}"></i>
                            </span>
                            <div>
                                <div class="schedule-alert-title">{{ $unpublishedCount }} unpublished shift{{ $unpublishedCount === 1 ? '' : 's' }}</div>
                                <div class="schedule-alert-message">{{ $publishedCount }} shift{{ $publishedCount === 1 ? ' is' : 's are' }} already published for this week.</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="publish_notification_mode">Notification Mode</label>
                            <select name="notification_mode" id="publish_notification_mode" class="form-control">
                                <option value="mark_only">Mark as published only</option>
                                <option value="notify_email_app">Notify by email/app</option>
                                <option value="notify_all">Notify by all enabled channels</option>
                            </select>
                        </div>

                        <div class="form-group mb-4">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="publish_requires_confirmation" name="requires_confirmation" value="1">
                                <label class="custom-control-label" for="publish_requires_confirmation">Require confirmation from team members</label>
                            </div>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn schedule-publish-action {{ $unpublishedCount === 0 ? 'is-clean' : '' }}">
                                <i class="fas fa-bullhorn mr-1"></i>
                                Publish Week
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="create_odoo_shift" class="modal fade create-shift-modal" tabindex="-1" role="dialog" aria-labelledby="createShiftTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="createShiftTitle">Create shift</h5>
                        <p class="modal-subtitle">Choose where the shift belongs, then add the schedule.</p>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if (! $canCreateShift)
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-gray-300 mb-3"></i>
                            <p class="text-muted mb-0">Shift creation is unavailable until Odoo returns employees, roles, and companies.</p>
                        </div>
                    @else
                        <form id="create-odoo-shift-form" action="{{ route('manager.shifts.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="month" id="create_month_filter" value="{{ old('month', $selectedMonth->format('Y-m')) }}">
                            <input type="hidden" name="day" id="create_day_filter" value="{{ old('day', $selectedCalendarDateValue) }}">

                            <div class="form-group create-shift-presets">
                                <label>Quick time</label>
                                <div class="template-rail">
                                    <button type="button" class="time-preset" data-start-time="07:00" data-end-time="15:00">
                                        <i class="fas fa-sun"></i>
                                        Morning
                                    </button>
                                    <button type="button" class="time-preset" data-start-time="09:00" data-end-time="17:00">
                                        <i class="fas fa-briefcase"></i>
                                        Day
                                    </button>
                                    <button type="button" class="time-preset" data-start-time="15:00" data-end-time="23:00">
                                        <i class="fas fa-cloud-sun"></i>
                                        Evening
                                    </button>
                                </div>
                            </div>

                            <div class="create-shift-flow">
                                <div class="create-shift-step">
                                    <span class="create-shift-step-number">1</span>
                                    <div class="create-shift-step-field">
                                        <label for="company_id">Company</label>
                                        <select name="company_id" id="company_id"
                                            class="form-control @error('company_id') is-invalid @enderror" required>
                                            <option value="">Select company</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company['id'] }}"
                                                    {{ (string) old('company_id') === (string) $company['id'] ? 'selected' : '' }}>
                                                    {{ $company['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>

                                <div class="create-shift-step">
                                    <span class="create-shift-step-number">2</span>
                                    <div class="create-shift-step-field">
                                        <label for="work_location_id">Work location</label>
                                        <select name="work_location_id" id="work_location_id" class="form-control @error('work_location_id') is-invalid @enderror">
                                            <option value="">Select company first</option>
                                            @foreach ($workLocations as $location)
                                                <option value="{{ $location['id'] }}" data-company-id="{{ $location['company_id'] }}" data-location-type="{{ $location['location_type'] }}" data-location-address="{{ $location['address'] }}" {{ (string)old('work_location_id')===(string)$location['id']?'selected':'' }}>
                                                    {{ $location['name'] }}{{ $location['address'] ? ' — '.$location['address'] : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('work_location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted" id="workLocationHelp">Locations are filtered by company.</small>
                                    </div>
                                </div>

                                <div class="create-shift-step">
                                    <span class="create-shift-step-number">3</span>
                                    <div class="create-shift-step-field">
                                        <label for="employee_id">Employee</label>
                                        <select name="employee_id" id="employee_id"
                                            class="form-control @error('employee_id') is-invalid @enderror">
                                            <option value="">Open shift (no employee)</option>
                                            @foreach ($employees as $employee)
                                                <option value="{{ $employee['id'] }}"
                                                    data-company-id="{{ $employee['company_id'] ?? '' }}"
                                                    data-work-location-id="{{ $employee['work_location_id'] ?? '' }}"
                                                    {{ (string) old('employee_id') === (string) $employee['id'] ? 'selected' : '' }}>
                                                    {{ $employee['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">Leave as open shift when the employee is not decided.</small>
                                    </div>
                                </div>
                            </div>

                            <div id="createShiftDiaryContext" class="alert alert-warning d-none" role="status" aria-live="polite">
                                <div class="font-weight-bold mb-1"><i class="fas fa-book-open mr-1" aria-hidden="true"></i>Employee Diary</div>
                                <div id="createShiftDiaryContextBody" class="small"></div>
                                <div class="small mt-2">This is a scheduling preference, not a hard block. Review it, then continue if coverage requires an override.</div>
                            </div>

                            <div class="form-group">
                                <label for="role_id">Role</label>
                                <select name="role_id" id="role_id"
                                    class="form-control @error('role_id') is-invalid @enderror" required>
                                    <option value="">Select company first</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role['id'] }}"
                                            data-company-id="{{ $role['company_id'] ?? '' }}"
                                            {{ (string) old('role_id') === (string) $role['id'] ? 'selected' : '' }}>
                                            {{ $role['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="shift_date">Start Date</label>
                                    <input type="date" name="shift_date" id="shift_date"
                                        class="form-control @error('shift_date') is-invalid @enderror"
                                        value="{{ old('shift_date', $selectedCalendarDateValue) }}" required>
                                    @error('shift_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="shift_end_date">End Date</label>
                                    <input type="date" name="shift_end_date" id="shift_end_date"
                                        class="form-control @error('shift_end_date') is-invalid @enderror"
                                        value="{{ old('shift_end_date', old('shift_date', $selectedCalendarDateValue)) }}">
                                    @error('shift_end_date')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="start_time">Start Time</label>
                                    <input type="time" name="start_time" id="start_time"
                                        class="form-control @error('start_time') is-invalid @enderror"
                                        value="{{ old('start_time') }}" required>
                                    @error('start_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="end_time">End Time</label>
                                    <input type="time" name="end_time" id="end_time"
                                        class="form-control @error('end_time') is-invalid @enderror"
                                        value="{{ old('end_time') }}" required>
                                    @error('end_time')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="title">Shift Title</label>
                                <input type="text" name="title" id="title"
                                    class="form-control @error('title') is-invalid @enderror"
                                    value="{{ old('title') }}"
                                    placeholder="Optional. Defaults to role and employee name.">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="note">Notes</label>
                                <textarea name="note" id="note" rows="3"
                                    class="form-control @error('note') is-invalid @enderror"
                                    placeholder="Optional handover or shift notes">{{ old('note') }}</textarea>
                                @error('note')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="text-right">
                                <button type="submit" class="btn schedule-primary-action">
                                    <i class="fas fa-check mr-1"></i>
                                    Create Shift
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="edit_odoo_shift" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Odoo Shift</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="edit-odoo-shift-form" action="" method="POST">
                        @csrf
                        <input type="hidden" name="month" value="{{ $selectedMonth->format('Y-m') }}">
                        <input type="hidden" name="day" value="{{ $selectedCalendarDateValue }}">
                        <input type="hidden" name="last_known_write_date" id="edit_last_known_write_date">
                        <input type="hidden" name="editing_shift_id" id="editing_shift_id">

                        <div class="form-group">
                            <label for="edit_employee_id">Employee</label>
                            <select name="employee_id" id="edit_employee_id" class="form-control">
                                <option value="">Select employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee['id'] }}" data-company-id="{{ $employee['company_id'] ?? '' }}" data-work-location-id="{{ $employee['work_location_id'] ?? '' }}">
                                        {{ $employee['name'] }}{{ $employee['company'] ? ' - '.$employee['company'] : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Leave blank to turn this into an open shift.</small>
                        </div>

                        <div id="editShiftDiaryContext" class="alert alert-warning d-none" role="status" aria-live="polite">
                            <div class="font-weight-bold mb-1"><i class="fas fa-book-open mr-1" aria-hidden="true"></i>Employee Diary</div>
                            <div id="editShiftDiaryContextBody" class="small"></div>
                            <div class="small mt-2">This is a scheduling preference, not a hard block. Review it, then continue if coverage requires an override.</div>
                        </div>

                        <div class="form-group schedule-work-location-field">
                            <label for="edit_work_location_id"><i class="fas fa-map-marker-alt mr-1 text-danger"></i>Work Location</label>
                            <select name="work_location_id" id="edit_work_location_id" class="form-control" required>
                                <option value="">Select physical work location</option>
                                @foreach($workLocations as $location)
                                    <option value="{{ $location['id'] }}" data-company-id="{{ $location['company_id'] }}">{{ $location['name'] }}{{ $location['address'] ? ' · '.$location['address'] : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="edit_role_id">Role</label>
                                <select name="role_id" id="edit_role_id" class="form-control" required>
                                    <option value="">Select role</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role['id'] }}">
                                            {{ $role['name'] }}{{ $role['company'] ? ' - '.$role['company'] : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="edit_company_id">Company</label>
                                <select name="company_id" id="edit_company_id" class="form-control" required>
                                    <option value="">Select company</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company['id'] }}">{{ $company['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="edit_shift_date">Shift Date</label>
                                <input type="date" name="shift_date" id="edit_shift_date" class="form-control" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="edit_start_time">Start Time</label>
                                <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="edit_end_time">End Time</label>
                                <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_title">Shift Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control"
                                placeholder="Optional. Defaults to role and employee name.">
                        </div>

                        <div class="form-group mb-0">
                            <label for="edit_note">Notes</label>
                            <textarea name="note" id="edit_note" rows="4" class="form-control"
                                placeholder="Optional handover or shift notes"></textarea>
                        </div>

                        <div class="mt-4 text-right">
                            <button type="submit" class="btn schedule-primary-action">
                                <i class="fas fa-check mr-1"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const employeeSelect = document.getElementById('employee_id');
            const roleSelect = document.getElementById('role_id');
            const companySelect = document.getElementById('company_id');
            const workLocationSelect = document.getElementById('work_location_id');
            const shiftDateInput = document.getElementById('shift_date');
            const shiftEndDateInput = document.getElementById('shift_end_date');
            const createMonthFilter = document.getElementById('create_month_filter');
            const createDayFilter = document.getElementById('create_day_filter');
            const rosterSearch = document.getElementById('rosterSearch');
            const rosterRoleFilter = document.getElementById('rosterRoleFilter');
            const rosterCompanyFilter = document.getElementById('rosterCompanyFilter');
            const rosterWorkLocationFilter = document.getElementById('rosterWorkLocationFilter');
            const rosterStatusFilter = document.getElementById('rosterStatusFilter');
            const clearRosterFilters = document.getElementById('clearRosterFilters');
            const toggleRosterDensity = document.getElementById('toggleRosterDensity');
            const toggleRosterDensitySecondary = document.getElementById('toggleRosterDensitySecondary');
            const optionsCompactDensity = document.getElementById('optionsCompactDensity');
            const deputyLocationButton = document.getElementById('deputyLocationButton');
            const deputyLocationLabel = document.getElementById('deputyLocationLabel');
            const deputyLocationOptions = Array.from(document.querySelectorAll('.deputy-location-option'));
            const selectAllLocations = document.getElementById('selectAllLocations');
            const clearLocations = document.getElementById('clearLocations');
            const closeLocationPicker = document.getElementById('closeLocationPicker');
            const deputyViewSwitch = document.getElementById('deputyViewSwitch');
            const mobileScheduleDay = document.getElementById('mobileScheduleDay');
            const rosterGrid = document.getElementById('teamRosterGrid');
            const areaGrid = document.getElementById('areaBoardGrid');
            const selectedCompanyIds = new Set();
            const schedulePage = document.querySelector('.schedule-page');
            const startTimeInput = document.getElementById('start_time');
            const endTimeInput = document.getElementById('end_time');
            const titleInput = document.getElementById('title');
            const noteInput = document.getElementById('note');
            const createShiftDiaryContext = document.getElementById('createShiftDiaryContext');
            const createShiftDiaryContextBody = document.getElementById('createShiftDiaryContextBody');
            const employeeDiaryByCell = @json($employeeDiaryByCell);
            const shiftClipboard = document.getElementById('shiftClipboard');
            const shiftClipboardLabel = document.getElementById('shiftClipboardLabel');
            const clearShiftClipboard = document.getElementById('clearShiftClipboard');
            const dragShiftUpdateForm = document.getElementById('dragShiftUpdateForm');
            const publishWeekButton = document.querySelector('[data-target="#publish_week_schedule"]');
            const publishNotificationMode = document.getElementById('publish_notification_mode');
            const publishRequiresConfirmation = document.getElementById('publish_requires_confirmation');
            const scheduleBulkBar = document.getElementById('scheduleBulkBar');
            const selectedShiftCount = document.getElementById('selectedShiftCount');
            const bulkClearSelection = document.getElementById('bulkClearSelection');
            const bulkOpenShift = document.getElementById('bulkOpenShift');
            const bulkDeleteShift = document.getElementById('bulkDeleteShift');
            const bulkDeleteShiftForm = document.getElementById('bulkDeleteShiftForm');
            const bulkDeleteShiftFields = document.getElementById('bulkDeleteShiftFields');
            const bulkOpenShiftForm = document.getElementById('bulkOpenShiftForm');
            const bulkOpenShiftFields = document.getElementById('bulkOpenShiftFields');
            const bulkEditShift = document.getElementById('bulkEditShift');
            const bulkUpdateShiftForm = document.getElementById('bulkUpdateShiftForm');
            const bulkUpdateShiftFields = document.getElementById('bulkUpdateShiftFields');
            let copiedShift = null;
            const dragState = {
                type: null,
                payload: null,
            };
            const selectionState = {
                primaryCard: null,
                cards: new Set(),
                cell: null,
            };
            const resizeState = {
                card: null,
                edge: null,
                startX: 0,
                originalStartMinutes: 0,
                originalEndMinutes: 0,
                previewStartMinutes: 0,
                previewEndMinutes: 0,
                originalTimeLabel: '',
                originalNoteLabel: '',
            };

            const updateCreateFilters = () => {
                if (!shiftDateInput || !shiftDateInput.value) {
                    return;
                }

                if (createMonthFilter) {
                    createMonthFilter.value = shiftDateInput.value.slice(0, 7);
                }

                if (createDayFilter) {
                    createDayFilter.value = shiftDateInput.value;
                }
            };

            const selectedOption = (select) => select?.options[select.selectedIndex] || null;

            const filterCompanyOptions = (select, companyId, includeShared = false) => {
                if (!select) return 0;
                let available = 0;

                Array.from(select.options).forEach((option) => {
                    if (!option.value) return;
                    const optionCompanyId = option.dataset.companyId || '';
                    const matches = Boolean(companyId)
                        && (optionCompanyId === companyId || (includeShared && !optionCompanyId));
                    option.disabled = !matches;
                    option.hidden = !matches;
                    if (matches) available++;
                });

                const current = selectedOption(select);
                if (current?.value && current.disabled) select.value = '';

                return available;
            };

            const syncCompanyDependencies = () => {
                const companyId = companySelect?.value || '';
                const companyName = selectedOption(companySelect)?.textContent.trim() || 'company';
                const locationCount = filterCompanyOptions(workLocationSelect, companyId);
                filterCompanyOptions(employeeSelect, companyId);
                filterCompanyOptions(roleSelect, companyId, true);

                const locationPlaceholder = workLocationSelect?.querySelector('option[value=""]');
                const rolePlaceholder = roleSelect?.querySelector('option[value=""]');

                if (workLocationSelect && locationPlaceholder) {
                    workLocationSelect.disabled = !companyId;
                    if (!companyId) {
                        locationPlaceholder.textContent = 'Select company first';
                    } else if (locationCount === 0) {
                        locationPlaceholder.textContent = `Company location — ${companyName}`;
                        workLocationSelect.value = '';
                    } else {
                        locationPlaceholder.textContent = 'Select work location';
                        if (!workLocationSelect.value && locationCount === 1) {
                            const onlyLocation = Array.from(workLocationSelect.options)
                                .find((option) => option.value && !option.disabled);
                            if (onlyLocation) workLocationSelect.value = onlyLocation.value;
                        }
                    }
                }

                if (employeeSelect) employeeSelect.disabled = !companyId;
                if (roleSelect) roleSelect.disabled = !companyId;
                if (rolePlaceholder) rolePlaceholder.textContent = companyId ? 'Select role' : 'Select company first';

                const workLocationHelp = document.getElementById('workLocationHelp');
                if (workLocationHelp) {
                    workLocationHelp.textContent = companyId && locationCount === 0
                        ? `${companyName} has no work locations, so its company location will be used.`
                        : 'Locations are filtered by company.';
                }
            };

            const selectCompanyFrom = (sourceSelect) => {
                const companyId = selectedOption(sourceSelect)?.dataset.companyId || '';
                if (companyId && companySelect && companySelect.value !== companyId) {
                    companySelect.value = companyId;
                    syncCompanyDependencies();
                }
            };

            const autoSelectEmployeeWorkLocation = () => {
                if (!employeeSelect || !workLocationSelect) return;
                const option = selectedOption(employeeSelect);
                const locationId = option ? option.dataset.workLocationId : '';
                if (locationId) {
                    const locationOption = Array.from(workLocationSelect.options)
                        .find((candidate) => candidate.value === locationId && !candidate.disabled);
                    if (locationOption) workLocationSelect.value = locationId;
                }
            };

            const prefillCreateShift = (data) => {
                if (shiftDateInput && data.shiftDate) {
                    shiftDateInput.value = data.shiftDate;
                }

                if (shiftEndDateInput && data.shiftDate) {
                    shiftEndDateInput.value = data.shiftDate;
                }

                let companyId = data.companyId || '';
                if (!companyId && data.employeeId && employeeSelect) {
                    const employeeOption = Array.from(employeeSelect.options)
                        .find((option) => option.value === String(data.employeeId));
                    companyId = employeeOption?.dataset.companyId || '';
                }
                if (!companyId && data.roleId && roleSelect) {
                    const roleOption = Array.from(roleSelect.options)
                        .find((option) => option.value === String(data.roleId));
                    companyId = roleOption?.dataset.companyId || '';
                }
                if (companySelect && companyId) companySelect.value = companyId;
                syncCompanyDependencies();

                if (employeeSelect && Object.prototype.hasOwnProperty.call(data, 'employeeId')) {
                    employeeSelect.value = data.employeeId || '';
                    autoSelectEmployeeWorkLocation();
                }

                if (roleSelect && data.roleId) roleSelect.value = data.roleId;

                if (workLocationSelect && data.workLocationId) {
                    workLocationSelect.value = data.workLocationId;
                }

                if (startTimeInput && data.startTime) {
                    startTimeInput.value = data.startTime;
                }

                if (endTimeInput && data.endTime) {
                    endTimeInput.value = data.endTime;
                }

                if (titleInput && Object.prototype.hasOwnProperty.call(data, 'title')) {
                    titleInput.value = data.title || '';
                }

                if (noteInput && Object.prototype.hasOwnProperty.call(data, 'note')) {
                    noteInput.value = data.note || '';
                }

                updateCreateFilters();
                updateDiaryContext();
            };

            const openCreateShiftModal = () => {
                if (window.jQuery) {
                    window.jQuery('#create_odoo_shift').modal('show');
                }
            };

            const openPublishWeekModal = () => {
                if (window.jQuery) {
                    window.jQuery('#publish_week_schedule').modal('show');
                }
            };

            document.querySelectorAll('.publish-mode-preset').forEach((button) => {
                button.addEventListener('click', function() {
                    if (publishNotificationMode) publishNotificationMode.value = this.dataset.mode || 'mark_only';
                    if (publishRequiresConfirmation) publishRequiresConfirmation.checked = this.dataset.confirmation === '1';
                    openPublishWeekModal();
                });
            });

            const isTypingContext = () => {
                const activeElement = document.activeElement;

                if (!activeElement) {
                    return false;
                }

                const tagName = activeElement.tagName;

                return ['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName) || activeElement.isContentEditable;
            };

            const hasOpenModal = () => Boolean(document.querySelector('.modal.show'));

            const getSelectedShiftCards = () => Array.from(selectionState.cards);

            const updateBulkSelectionState = () => {
                const selectedCards = getSelectedShiftCards();
                const selectedCount = selectedCards.length;
                const hasAssignedSelection = selectedCards.some((card) => Boolean(card.dataset.employeeId));

                if (selectedShiftCount) {
                    selectedShiftCount.textContent = String(selectedCount);
                }

                if (scheduleBulkBar) {
                    scheduleBulkBar.classList.toggle('is-active', selectedCount > 0);
                }

                if (bulkOpenShift) {
                    bulkOpenShift.disabled = selectedCount === 0 || !hasAssignedSelection;
                }

                if (bulkDeleteShift) {
                    bulkDeleteShift.disabled = selectedCount === 0;
                }
                if (bulkEditShift) {
                    bulkEditShift.disabled = selectedCount === 0;
                }
            };

            const clearShiftSelection = () => {
                document.querySelectorAll('.shift-card.is-selected, .shift-card.is-related').forEach((card) => {
                    card.classList.remove('is-selected', 'is-related');
                });

                selectionState.primaryCard = null;
                selectionState.cards.clear();
                updateBulkSelectionState();
            };

            const clearCellSelection = () => {
                if (selectionState.cell) {
                    selectionState.cell.classList.remove('is-key-selected');
                }

                selectionState.cell = null;
            };

            const selectRosterCell = (cell) => {
                if (!cell) {
                    clearCellSelection();
                    return;
                }

                if (selectionState.cell && selectionState.cell !== cell) {
                    selectionState.cell.classList.remove('is-key-selected');
                }

                selectionState.cell = cell;
                cell.classList.add('is-key-selected');
            };

            const syncShiftSelectionClasses = () => {
                document.querySelectorAll('.shift-card.is-selected, .shift-card.is-related').forEach((card) => {
                    card.classList.remove('is-selected', 'is-related');
                });

                const selectedCards = getSelectedShiftCards();

                selectedCards.forEach((card) => {
                    card.classList.add('is-selected');
                });

                if (selectedCards.length === 1 && selectionState.primaryCard) {
                    const employeeId = selectionState.primaryCard.dataset.employeeId || '';

                    if (employeeId) {
                        document.querySelectorAll('.shift-card').forEach((candidate) => {
                            if (!selectionState.cards.has(candidate) && candidate.dataset.employeeId === employeeId) {
                                candidate.classList.add('is-related');
                            }
                        });
                    }
                }

                updateBulkSelectionState();
            };

            const selectShiftCard = (card, options = {}) => {
                if (!card) {
                    clearShiftSelection();
                    return;
                }

                const toggle = Boolean(options.toggle);
                const append = Boolean(options.append);

                if (toggle) {
                    if (selectionState.cards.has(card)) {
                        selectionState.cards.delete(card);

                        if (selectionState.primaryCard === card) {
                            selectionState.primaryCard = getSelectedShiftCards()[0] || null;
                        }
                    } else {
                        selectionState.cards.add(card);
                        selectionState.primaryCard = card;
                    }
                } else {
                    if (!append) {
                        selectionState.cards.clear();
                    }

                    selectionState.cards.add(card);
                    selectionState.primaryCard = card;
                }

                if (selectionState.cards.size === 0) {
                    selectionState.primaryCard = null;
                }

                syncShiftSelectionClasses();

                const parentCell = card.closest('.roster-cell');

                if (parentCell) {
                    selectRosterCell(parentCell);
                }
            };

            const clearSchedulerSelection = () => {
                clearShiftSelection();
                clearCellSelection();
            };

            const clearDragState = () => {
                dragState.type = null;
                dragState.payload = null;

                document.querySelectorAll('.roster-person.is-drag-source, .shift-card.is-drag-source').forEach((element) => {
                    element.classList.remove('is-drag-source');
                });

                document.querySelectorAll('.roster-cell.is-drop-target, .roster-cell.is-drop-active').forEach((element) => {
                    element.classList.remove('is-drop-target', 'is-drop-active');
                });
            };

            const toMinutes = (timeValue) => {
                if (!timeValue || !/^\d{2}:\d{2}$/.test(timeValue)) {
                    return 0;
                }

                const [hours, minutes] = timeValue.split(':').map(Number);

                return (hours * 60) + minutes;
            };

            const toTimeValue = (minutes) => {
                const bounded = Math.max(0, Math.min((23 * 60) + 45, minutes));
                const hours = Math.floor(bounded / 60);
                const mins = bounded % 60;

                return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
            };

            const toTimeLabel = (minutes) => {
                const normalized = Math.max(0, Math.min((23 * 60) + 59, minutes));
                const hours24 = Math.floor(normalized / 60);
                const mins = normalized % 60;
                const suffix = hours24 >= 12 ? 'PM' : 'AM';
                let hours12 = hours24 % 12;
                hours12 = hours12 === 0 ? 12 : hours12;

                return String(hours12).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ' ' + suffix;
            };

            const formatDurationLabel = (minutes) => {
                if (minutes <= 0) {
                    return '0h';
                }

                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;

                if (mins === 0) {
                    return hours + 'h';
                }

                if (hours === 0) {
                    return mins + 'm';
                }

                return hours + 'h ' + mins + 'm';
            };

            const submitShiftUpdatePayload = (payload) => {
                if (!dragShiftUpdateForm || !payload.updateUrl) {
                    return;
                }

                dragShiftUpdateForm.setAttribute('action', payload.updateUrl);
                document.getElementById('drag_shift_month').value = (payload.shiftDate || '').slice(0, 7);
                document.getElementById('drag_shift_day').value = payload.shiftDate || '';
                document.getElementById('drag_shift_employee_id').value = payload.employeeId || '';
                document.getElementById('drag_shift_role_id').value = payload.roleId || '';
                document.getElementById('drag_shift_company_id').value = payload.companyId || '';
                document.getElementById('drag_shift_work_location_id').value = payload.workLocationId || '';
                document.getElementById('drag_shift_date').value = payload.shiftDate || '';
                document.getElementById('drag_shift_start_time').value = payload.startTime || '';
                document.getElementById('drag_shift_end_time').value = payload.endTime || '';
                document.getElementById('drag_shift_title').value = payload.title || '';
                document.getElementById('drag_shift_note').value = payload.note || '';
                document.getElementById('drag_shift_write_date').value = payload.writeDate || '';
                dragShiftUpdateForm.submit();
            };

            const buildBulkHiddenFields = (container, rows) => {
                if (!container) {
                    return;
                }

                container.innerHTML = '';

                rows.forEach((row, index) => {
                    Object.entries(row).forEach(([key, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'shifts[' + index + '][' + key + ']';
                        input.value = value ?? '';
                        container.appendChild(input);
                    });
                });
            };

            const buildSelectedShiftPayloads = () => getSelectedShiftCards().map((card) => ({
                id: card.dataset.shiftId || '',
                role_id: card.dataset.roleId || '',
                company_id: card.dataset.companyId || '',
                work_location_id: card.dataset.workLocationId || '',
                shift_date: card.dataset.shiftDate || '',
                start_time: card.dataset.shiftStart || '',
                end_time: card.dataset.shiftEnd || '',
                title: card.dataset.shiftTitle || '',
                note: card.dataset.shiftNote || '',
                last_known_write_date: card.dataset.shiftWriteDate || '',
                employee_id: card.dataset.employeeId || '',
            }));

            const submitBulkDelete = () => {
                const rows = buildSelectedShiftPayloads().map((payload) => ({
                    id: payload.id,
                    last_known_write_date: payload.last_known_write_date,
                }));

                if (rows.length === 0 || !bulkDeleteShiftForm) {
                    return;
                }

                buildBulkHiddenFields(bulkDeleteShiftFields, rows);
                bulkDeleteShiftForm.submit();
            };

            const submitBulkOpen = () => {
                const rows = buildSelectedShiftPayloads().filter((payload) => payload.employee_id !== '');

                if (rows.length === 0 || !bulkOpenShiftForm) {
                    return;
                }

                buildBulkHiddenFields(bulkOpenShiftFields, rows);
                bulkOpenShiftForm.submit();
            };

            if (bulkUpdateShiftForm) {
                bulkUpdateShiftForm.addEventListener('submit', function(event) {
                    const rows = buildSelectedShiftPayloads();
                    if (rows.length === 0) { event.preventDefault(); return; }
                    buildBulkHiddenFields(bulkUpdateShiftFields, rows);
                });
            }

            const setCopiedShift = (payload, label) => {
                copiedShift = payload;

                if (shiftClipboardLabel) {
                    shiftClipboardLabel.textContent = label || 'Shift copied';
                }

                if (shiftClipboard) {
                    shiftClipboard.classList.add('is-active');
                }

                if (schedulePage) {
                    schedulePage.classList.add('has-shift-clipboard');
                }
            };

            const clearCopiedShift = () => {
                copiedShift = null;

                if (shiftClipboard) {
                    shiftClipboard.classList.remove('is-active');
                }

                if (schedulePage) {
                    schedulePage.classList.remove('has-shift-clipboard');
                }
            };

            const canDropOnRosterCell = (cell) => {
                if (!cell || !dragState.type || !dragState.payload) {
                    return false;
                }

                const targetEmployeeId = cell.dataset.employeeId || '';
                const targetIsOpen = cell.dataset.rosterOpen === '1';

                if (dragState.type === 'employee') {
                    if (dragState.payload.isOpen) {
                        return targetIsOpen;
                    }

                    return targetEmployeeId !== '' && targetEmployeeId === dragState.payload.employeeId;
                }

                if (dragState.type === 'shift') {
                    return true;
                }

                return false;
            };

            const refreshDropTargets = () => {
                document.querySelectorAll('.roster-cell').forEach((cell) => {
                    cell.classList.toggle('is-drop-target', canDropOnRosterCell(cell));
                });
            };

            const submitDraggedShiftUpdate = (targetCell) => {
                if (!dragShiftUpdateForm || !dragState.payload) {
                    clearDragState();
                    return;
                }

                const targetDate = targetCell.dataset.shiftDate || '';
                const targetEmployeeId = targetCell.dataset.employeeId || '';
                const sourceDate = dragState.payload.shiftDate || '';
                const sourceEmployeeId = dragState.payload.employeeId || '';

                if (!targetDate || !dragState.payload.updateUrl) {
                    clearDragState();
                    return;
                }

                if (targetDate === sourceDate && targetEmployeeId === sourceEmployeeId) {
                    clearDragState();
                    return;
                }

                submitShiftUpdatePayload({
                    updateUrl: dragState.payload.updateUrl,
                    shiftDate: targetDate,
                    employeeId: targetEmployeeId,
                    roleId: dragState.payload.roleId,
                    companyId: dragState.payload.companyId,
                    workLocationId: dragState.payload.workLocationId,
                    startTime: dragState.payload.startTime,
                    endTime: dragState.payload.endTime,
                    title: dragState.payload.title,
                    note: dragState.payload.note,
                    writeDate: dragState.payload.writeDate,
                });
            };

            const renderResizePreview = () => {
                if (!resizeState.card) {
                    return;
                }

                const timeElement = resizeState.card.querySelector('.shift-time');
                const noteElement = resizeState.card.querySelector('.shift-note');
                const previewDuration = resizeState.previewEndMinutes - resizeState.previewStartMinutes;
                const companySuffix = resizeState.card.dataset.companyId && noteElement && resizeState.originalNoteLabel.includes('|')
                    ? ' | ' + resizeState.originalNoteLabel.split('|').slice(1).join('|').trim()
                    : '';

                if (timeElement) {
                    timeElement.textContent = toTimeLabel(resizeState.previewStartMinutes) + ' - ' + toTimeLabel(resizeState.previewEndMinutes);
                }

                if (noteElement) {
                    noteElement.textContent = formatDurationLabel(previewDuration) + companySuffix;
                }
            };

            const restoreResizePreview = () => {
                if (!resizeState.card) {
                    return;
                }

                const timeElement = resizeState.card.querySelector('.shift-time');
                const noteElement = resizeState.card.querySelector('.shift-note');

                if (timeElement) {
                    timeElement.textContent = resizeState.originalTimeLabel;
                }

                if (noteElement) {
                    noteElement.textContent = resizeState.originalNoteLabel;
                }
            };

            const stopResize = (shouldSubmit) => {
                if (!resizeState.card) {
                    return;
                }

                const card = resizeState.card;
                const cardWasChanged = resizeState.previewStartMinutes !== resizeState.originalStartMinutes
                    || resizeState.previewEndMinutes !== resizeState.originalEndMinutes;

                card.classList.remove('is-resizing');
                card.setAttribute('draggable', card.dataset.draggable === '1' ? 'true' : 'false');
                document.body.classList.remove('shift-resize-active');

                if (shouldSubmit && cardWasChanged) {
                    submitShiftUpdatePayload({
                        updateUrl: card.dataset.updateUrl || '',
                        shiftDate: card.dataset.shiftDate || '',
                        employeeId: card.dataset.employeeId || '',
                        roleId: card.dataset.roleId || '',
                        companyId: card.dataset.companyId || '',
                        workLocationId: card.dataset.workLocationId || '',
                        startTime: toTimeValue(resizeState.previewStartMinutes),
                        endTime: toTimeValue(resizeState.previewEndMinutes),
                        title: card.dataset.shiftTitle || '',
                        note: card.dataset.shiftNote || '',
                        writeDate: card.dataset.shiftWriteDate || '',
                    });
                } else {
                    restoreResizePreview();
                }

                resizeState.card = null;
                resizeState.edge = null;
            };

            const updateResizeFromPointer = (clientX) => {
                if (!resizeState.card || !resizeState.edge) {
                    return;
                }

                const deltaX = clientX - resizeState.startX;
                const minutesDelta = Math.round(deltaX / 12) * 15;
                const minimumDuration = 30;
                const latestEnd = (23 * 60) + 45;

                if (resizeState.edge === 'start') {
                    const candidateStart = Math.max(0, Math.min(
                        resizeState.originalEndMinutes - minimumDuration,
                        resizeState.originalStartMinutes + minutesDelta
                    ));

                    resizeState.previewStartMinutes = candidateStart;
                    resizeState.previewEndMinutes = resizeState.originalEndMinutes;
                } else {
                    const candidateEnd = Math.min(latestEnd, Math.max(
                        resizeState.originalStartMinutes + minimumDuration,
                        resizeState.originalEndMinutes + minutesDelta
                    ));

                    resizeState.previewStartMinutes = resizeState.originalStartMinutes;
                    resizeState.previewEndMinutes = candidateEnd;
                }

                renderResizePreview();
            };

            const handleRosterCellDrop = (targetCell) => {
                if (!dragState.type || !dragState.payload) {
                    clearDragState();
                    return;
                }

                const targetDate = targetCell.dataset.shiftDate || '';

                if (!targetDate) {
                    clearDragState();
                    return;
                }

                if (dragState.type === 'employee') {
                    prefillCreateShift({
                        shiftDate: targetDate,
                        employeeId: dragState.payload.isOpen ? '' : dragState.payload.employeeId,
                        companyId: dragState.payload.isOpen ? '' : dragState.payload.companyId,
                        workLocationId: dragState.payload.isOpen ? '' : dragState.payload.workLocationId,
                    });
                    clearDragState();
                    openCreateShiftModal();
                    return;
                }

                if (dragState.type === 'shift') {
                    submitDraggedShiftUpdate(targetCell);
                }
            };

            const syncEndDate = () => {
                if (!shiftDateInput || !shiftEndDateInput) {
                    return;
                }

                if (!shiftEndDateInput.value || shiftEndDateInput.value < shiftDateInput.value) {
                    shiftEndDateInput.value = shiftDateInput.value;
                }

                updateCreateFilters();
            };

            const updateDiaryContext = () => {
                if (!createShiftDiaryContext || !createShiftDiaryContextBody) return;

                const employeeId = employeeSelect?.value || '';
                const dateValue = shiftDateInput?.value || '';
                const entries = employeeDiaryByCell?.[employeeId]?.[dateValue] || [];

                createShiftDiaryContextBody.replaceChildren();
                createShiftDiaryContext.classList.toggle('d-none', entries.length === 0);
                if (entries.length === 0) return;

                const shiftStart = startTimeInput?.value || '';
                const shiftEnd = endTimeInput?.value || '';
                let hasConflict = false;

                entries.forEach((entry) => {
                    const entryStart = entry.start_time_value || '';
                    const entryEnd = entry.end_time_value || '';
                    const conflicts = entry.entry_type === 'unavailable'
                        && (entry.is_all_day || (
                            entryStart && entryEnd && shiftStart && shiftEnd
                            && entryStart < shiftEnd && entryEnd > shiftStart
                        ));
                    hasConflict = hasConflict || conflicts;

                    const line = document.createElement('div');
                    line.className = conflicts ? 'font-weight-bold text-danger' : '';
                    line.textContent = `${entry.type_label}: ${entry.title} · ${entry.time_label}${entry.notes ? ` — ${entry.notes}` : ''}`;
                    createShiftDiaryContextBody.appendChild(line);
                });

                createShiftDiaryContext.classList.toggle('alert-danger', hasConflict);
                createShiftDiaryContext.classList.toggle('alert-warning', !hasConflict);
            };

            if (employeeSelect) {
                employeeSelect.addEventListener('change', function() {
                    selectCompanyFrom(employeeSelect);
                    autoSelectEmployeeWorkLocation();
                    updateDiaryContext();
                });
            }

            if (roleSelect) {
                roleSelect.addEventListener('change', function() {
                    selectCompanyFrom(roleSelect);
                });
            }

            if (companySelect) companySelect.addEventListener('change', syncCompanyDependencies);
            syncCompanyDependencies();

            if (shiftDateInput) {
                shiftDateInput.addEventListener('change', function() {
                    syncEndDate();
                    updateDiaryContext();
                });
                updateCreateFilters();
            }
            if (startTimeInput) startTimeInput.addEventListener('change', updateDiaryContext);
            if (endTimeInput) endTimeInput.addEventListener('change', updateDiaryContext);
            updateDiaryContext();

            document.querySelectorAll('.quick-add-shift').forEach((button) => {
                button.addEventListener('click', function() {
                    prefillCreateShift({
                        shiftDate: button.dataset.shiftDate || '',
                        employeeId: button.dataset.employeeId || '',
                        companyId: button.dataset.companyId || '',
                        workLocationId: button.dataset.workLocationId || '',
                        roleId: button.dataset.roleId || '',
                    });
                });
            });

            document.querySelectorAll('.time-preset').forEach((button) => {
                button.addEventListener('click', function() {
                    prefillCreateShift({
                        startTime: button.dataset.startTime || '',
                        endTime: button.dataset.endTime || '',
                    });
                });
            });

            document.querySelectorAll('.apply-shift-template').forEach((button) => {
                button.addEventListener('click', function() {
                    prefillCreateShift({
                        roleId: button.dataset.roleId || '',
                        companyId: button.dataset.companyId || '',
                        workLocationId: button.dataset.workLocationId || '',
                        startTime: button.dataset.startTime || '',
                        endTime: button.dataset.endTime || '',
                        title: button.dataset.title || '',
                        note: button.dataset.note || '',
                    });
                });
            });

            document.querySelectorAll('.copy-odoo-shift').forEach((button) => {
                button.addEventListener('click', function() {
                    setCopiedShift({
                        roleId: button.dataset.shiftRoleId || '',
                        companyId: button.dataset.shiftCompanyId || '',
                        workLocationId: button.dataset.shiftWorkLocationId || '',
                        startTime: button.dataset.shiftStart || '',
                        endTime: button.dataset.shiftEnd || '',
                        title: button.dataset.shiftTitle || '',
                        note: button.dataset.shiftNote || '',
                    }, button.dataset.shiftLabel || 'Shift copied');
                });
            });

            document.querySelectorAll('.paste-shift').forEach((button) => {
                button.addEventListener('click', function() {
                    if (!copiedShift) {
                        return;
                    }

                    prefillCreateShift(Object.assign({}, copiedShift, {
                        shiftDate: button.dataset.shiftDate || '',
                        employeeId: button.dataset.employeeId || '',
                    }));
                });
            });

            if (clearShiftClipboard) {
                clearShiftClipboard.addEventListener('click', function() {
                    clearCopiedShift();
                });
            }

            if (bulkClearSelection) {
                bulkClearSelection.addEventListener('click', function() {
                    clearSchedulerSelection();
                });
            }

            if (bulkDeleteShift) {
                bulkDeleteShift.addEventListener('click', function() {
                    const count = getSelectedShiftCards().length;

                    if (count === 0) {
                        return;
                    }

                    if (window.confirm('Delete ' + count + ' selected Odoo shift' + (count === 1 ? '' : 's') + '?')) {
                        submitBulkDelete();
                    }
                });
            }

            if (bulkOpenShift) {
                bulkOpenShift.addEventListener('click', function() {
                    const count = buildSelectedShiftPayloads().filter((payload) => payload.employee_id !== '').length;

                    if (count === 0) {
                        return;
                    }

                    if (window.confirm('Convert ' + count + ' selected shift' + (count === 1 ? '' : 's') + ' to open shifts?')) {
                        submitBulkOpen();
                    }
                });
            }

            updateBulkSelectionState();

            const applyRosterFilters = () => {
                const query = rosterSearch ? rosterSearch.value.trim().toLowerCase() : '';
                const roleId = rosterRoleFilter ? rosterRoleFilter.value : '';
                const companyId = rosterCompanyFilter ? rosterCompanyFilter.value : '';
                const workLocationId = rosterWorkLocationFilter ? rosterWorkLocationFilter.value : '';
                const status = rosterStatusFilter ? rosterStatusFilter.value : '';
                const hasCompanyScope = selectedCompanyIds.size > 0 && selectedCompanyIds.size < deputyLocationOptions.length;
                // Company filters match the employee's company even when that employee
                // has no shifts yet. Role, work location, and assignment status are the
                // filters that genuinely require a matching shift card.
                const hasShiftFilter = roleId || workLocationId || status === 'open' || status === 'assigned';

                document.querySelectorAll('[data-roster-search]').forEach((row) => {
                    const key = row.dataset.rosterKey || '';
                    const haystack = row.dataset.rosterSearch || '';
                    const rowIsOpen = row.dataset.rosterOpen === '1';
                    const rowHasShifts = row.dataset.rosterHasShifts === '1';
                    let matchingCards = 0;

                    document.querySelectorAll('.shift-card[data-roster-key="' + key + '"]').forEach((card) => {
                        const cardIsOpen = !card.dataset.employeeId || card.dataset.employeeId === '0';
                        const matchesRole = !roleId || card.dataset.roleId === roleId;
                        const matchesCompany = (!companyId || card.dataset.companyId === companyId)
                            && (selectedCompanyIds.size === 0 || selectedCompanyIds.has(card.dataset.companyId));
                        const matchesWorkLocation = !workLocationId || card.dataset.workLocationId === workLocationId;
                        const matchesStatus = !status
                            || (status === 'open' && cardIsOpen)
                            || (status === 'assigned' && !cardIsOpen);
                        const cardMatches = matchesRole && matchesCompany && matchesWorkLocation && matchesStatus && status !== 'empty';

                        if (cardMatches) {
                            matchingCards++;
                        }

                        card.classList.toggle('d-none', Boolean((roleId || companyId || workLocationId || hasCompanyScope || status) && !cardMatches));
                    });

                    const matchesSearch = !query || haystack.includes(query);
                    const matchesEmpty = status === 'empty' ? !rowIsOpen && !rowHasShifts : true;
                    const matchesCompanyRow = (!companyId || row.dataset.rosterCompanyId === companyId || matchingCards > 0)
                        && (!hasCompanyScope || selectedCompanyIds.has(row.dataset.rosterCompanyId) || matchingCards > 0);
                    const matchesShiftFilter = hasShiftFilter ? matchingCards > 0 : true;
                    const shouldHide = !matchesSearch || !matchesEmpty || !matchesCompanyRow || !matchesShiftFilter;

                    document.querySelectorAll('[data-roster-key="' + key + '"]:not(.shift-card)').forEach((element) => {
                        element.classList.toggle('d-none', shouldHide);
                    });
                });
            };

            [rosterSearch, rosterRoleFilter, rosterCompanyFilter, rosterWorkLocationFilter, rosterStatusFilter].forEach((control) => {
                if (!control) {
                    return;
                }

                control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', applyRosterFilters);
            });

            if (clearRosterFilters) {
                clearRosterFilters.addEventListener('click', function() {
                    if (rosterSearch) {
                        rosterSearch.value = '';
                    }

                    if (rosterRoleFilter) {
                        rosterRoleFilter.value = '';
                    }

                    if (rosterCompanyFilter) {
                        rosterCompanyFilter.value = '';
                    }

                    if (rosterWorkLocationFilter) rosterWorkLocationFilter.value = '';

                    if (rosterStatusFilter) {
                        rosterStatusFilter.value = '';
                    }

                    applyRosterFilters();
                });
            }

            const toggleScheduleDensity = () => {
                if (rosterGrid) rosterGrid.classList.toggle('roster-density-compact');
                if (areaGrid) areaGrid.classList.toggle('roster-density-compact');
                const isCompact = Boolean((rosterGrid && rosterGrid.classList.contains('roster-density-compact')) || (areaGrid && areaGrid.classList.contains('roster-density-compact')));
                window.localStorage.setItem('deputyScheduleCompact', isCompact ? '1' : '0');
            };

            [toggleRosterDensity, toggleRosterDensitySecondary, optionsCompactDensity].forEach((button) => {
                if (button) button.addEventListener('click', toggleScheduleDensity);
            });

            if (deputyViewSwitch) {
                deputyViewSwitch.addEventListener('change', function() {
                    if (this.value) window.location.assign(this.value);
                });
            }

            const applyLocationSelection = () => {
                selectedCompanyIds.clear();
                deputyLocationOptions.filter((option) => option.checked).forEach((option) => selectedCompanyIds.add(option.value));
                const selectedOptions = deputyLocationOptions.filter((option) => option.checked);

                if (deputyLocationLabel) {
                    deputyLocationLabel.textContent = selectedOptions.length === 0 || selectedOptions.length === deputyLocationOptions.length
                        ? 'All companies'
                        : (selectedOptions.length === 1 ? selectedOptions[0].dataset.label : selectedOptions.length + ' companies');
                }

                window.sessionStorage.setItem('deputyScheduleCompanies', JSON.stringify(Array.from(selectedCompanyIds)));
                applyRosterFilters();
                document.querySelectorAll('[data-area-company-id]').forEach((element) => {
                    const hasCompanyScope = selectedCompanyIds.size > 0 && selectedCompanyIds.size < deputyLocationOptions.length;
                    element.classList.toggle('d-none', hasCompanyScope && !selectedCompanyIds.has(element.dataset.areaCompanyId));
                });
            };

            deputyLocationOptions.forEach((option) => option.addEventListener('change', applyLocationSelection));
            document.querySelectorAll('.location-picker-menu').forEach((menu) => menu.addEventListener('click', (event) => event.stopPropagation()));
            if (selectAllLocations) selectAllLocations.addEventListener('click', function() { deputyLocationOptions.forEach((option) => option.checked = true); applyLocationSelection(); });
            if (clearLocations) clearLocations.addEventListener('click', function() { deputyLocationOptions.forEach((option) => option.checked = false); applyLocationSelection(); });
            if (closeLocationPicker) closeLocationPicker.addEventListener('click', function() { if (window.jQuery) window.jQuery(deputyLocationButton).dropdown('toggle'); });

            try {
                const savedCompanies = JSON.parse(window.sessionStorage.getItem('deputyScheduleCompanies') || '[]');
                if (Array.isArray(savedCompanies) && savedCompanies.length > 0) {
                    deputyLocationOptions.forEach((option) => option.checked = savedCompanies.includes(option.value));
                }
            } catch (error) {
                window.sessionStorage.removeItem('deputyScheduleCompanies');
            }
            applyLocationSelection();

            const applyMobileDayFocus = () => {
                const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
                const selectedDate = mobileScheduleDay ? mobileScheduleDay.value : @json($selectedCalendarDateValue);

                [rosterGrid, areaGrid].forEach((grid) => {
                    if (!grid) return;
                    grid.classList.toggle('mobile-day-focus', isMobile);
                    grid.querySelectorAll('[data-schedule-date], [data-shift-date]').forEach((element) => {
                        const elementDate = element.dataset.scheduleDate || element.dataset.shiftDate;
                        element.classList.toggle('mobile-day-hidden', isMobile && elementDate !== selectedDate);
                    });
                });
            };

            if (mobileScheduleDay) mobileScheduleDay.addEventListener('change', applyMobileDayFocus);
            window.addEventListener('resize', applyMobileDayFocus);
            applyMobileDayFocus();

            if (window.localStorage.getItem('deputyScheduleCompact') === '1') {
                if (rosterGrid) rosterGrid.classList.add('roster-density-compact');
                if (areaGrid) areaGrid.classList.add('roster-density-compact');
            }

            document.querySelectorAll('.roster-cell').forEach((cell) => {
                cell.addEventListener('click', function(event) {
                    if (event.target.closest('.shift-card, .cell-add, .shift-actions, .shift-resize-handle, form, button, a')) {
                        return;
                    }

                    selectRosterCell(cell);
                    clearShiftSelection();
                });
            });

            document.querySelectorAll('.shift-card').forEach((card) => {
                card.addEventListener('click', function(event) {
                    if (event.target.closest('.shift-actions, .shift-resize-handle, form, button, a')) {
                        return;
                    }

                    event.stopPropagation();
                    const multiSelect = event.ctrlKey || event.metaKey;
                    selectShiftCard(card, {
                        toggle: multiSelect,
                    });
                });

                card.addEventListener('dblclick', function(event) {
                    if (event.target.closest('.shift-actions, .shift-resize-handle, form, button, a')) {
                        return;
                    }

                    const editButton = card.querySelector('.edit-odoo-shift');

                    if (editButton) {
                        editButton.click();
                    }
                });

                card.addEventListener('keydown', function(event) {
                    if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) {
                        event.preventDefault();
                        const toggle = card.querySelector('.shift-overflow-toggle');
                        if (toggle) toggle.click();
                    }
                });
            });

            const closeShiftActionMenus = (exceptCard = null) => {
                document.querySelectorAll('.shift-card.actions-open').forEach((card) => {
                    if (card === exceptCard) return;
                    card.classList.remove('actions-open');
                    const toggle = card.querySelector('.shift-overflow-toggle');
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                });
            };

            document.querySelectorAll('.shift-overflow-toggle').forEach((toggle) => {
                toggle.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const card = this.closest('.shift-card');
                    if (!card) return;
                    const willOpen = !card.classList.contains('actions-open');
                    closeShiftActionMenus(card);
                    card.classList.toggle('actions-open', willOpen);
                    this.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                });
            });

            document.addEventListener('click', function(event) {
                if (!event.target.closest('.shift-card')) closeShiftActionMenus();
            });

            const visibleScheduleCells = () => Array.from(document.querySelectorAll('.schedule-drop-cell:not(.d-none):not(.mobile-day-hidden)'));
            const focusAdjacentScheduleCell = (cell, key) => {
                const cells = visibleScheduleCells();
                const currentIndex = cells.indexOf(cell);
                if (currentIndex < 0) return;
                const currentDate = cell.dataset.shiftDate || '';
                const currentRow = cell.dataset.rosterKey || cell.dataset.areaKey || '';
                const sameRow = cells.filter((candidate) => (candidate.dataset.rosterKey || candidate.dataset.areaKey || '') === currentRow);
                const sameDate = cells.filter((candidate) => candidate.dataset.shiftDate === currentDate);
                let target = null;

                if (key === 'ArrowLeft' || key === 'ArrowRight') {
                    const index = sameRow.indexOf(cell);
                    target = sameRow[index + (key === 'ArrowRight' ? 1 : -1)] || null;
                } else {
                    const index = sameDate.indexOf(cell);
                    target = sameDate[index + (key === 'ArrowDown' ? 1 : -1)] || null;
                }

                if (target) { target.focus(); selectRosterCell(target); }
            };

            document.querySelectorAll('.schedule-drop-cell').forEach((cell) => {
                cell.addEventListener('keydown', function(event) {
                    if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key) && !event.altKey) {
                        event.preventDefault();
                        focusAdjacentScheduleCell(cell, event.key);
                        return;
                    }
                    if (event.key === 'Enter' && !event.target.closest('.shift-card')) {
                        event.preventDefault();
                        prefillCreateShift({ shiftDate: cell.dataset.shiftDate || '', employeeId: cell.dataset.employeeId || '', roleId: cell.dataset.roleId || '', companyId: cell.dataset.companyId || '', workLocationId: cell.dataset.workLocationId || '' });
                        openCreateShiftModal();
                    }
                });
            });

            document.querySelectorAll('.shift-resize-handle').forEach((handle) => {
                handle.addEventListener('mousedown', function(event) {
                    const card = handle.closest('.shift-card');

                    if (!card) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    resizeState.card = card;
                    resizeState.edge = handle.classList.contains('is-start') ? 'start' : 'end';
                    resizeState.startX = event.clientX;
                    resizeState.originalStartMinutes = toMinutes(card.dataset.shiftStart || '');
                    resizeState.originalEndMinutes = toMinutes(card.dataset.shiftEnd || '');
                    resizeState.previewStartMinutes = resizeState.originalStartMinutes;
                    resizeState.previewEndMinutes = resizeState.originalEndMinutes;
                    resizeState.originalTimeLabel = card.querySelector('.shift-time')?.textContent || '';
                    resizeState.originalNoteLabel = card.querySelector('.shift-note')?.textContent || '';

                    card.classList.add('is-resizing');
                    card.setAttribute('draggable', 'false');
                    document.body.classList.add('shift-resize-active');
                });
            });

            document.addEventListener('mousemove', function(event) {
                if (!resizeState.card) {
                    return;
                }

                event.preventDefault();
                updateResizeFromPointer(event.clientX);
            });

            document.addEventListener('mouseup', function() {
                if (!resizeState.card) {
                    return;
                }

                stopResize(true);
            });

            document.addEventListener('keydown', function(event) {
                if (event.key !== 'Escape' || !resizeState.card) {
                    return;
                }

                event.preventDefault();
                stopResize(false);
            });

            document.addEventListener('keydown', function(event) {
                if (resizeState.card || isTypingContext() || hasOpenModal()) {
                    return;
                }

                const key = event.key;
                const normalizedKey = typeof key === 'string' ? key.toLowerCase() : '';

                if (event.altKey && selectionState.primaryCard && getSelectedShiftCards().length === 1
                    && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(key)) {
                    event.preventDefault();
                    const card = selectionState.primaryCard;
                    let shiftDate = card.dataset.shiftDate || '';
                    let startMinutes = toMinutes(card.dataset.shiftStart || '');
                    let endMinutes = toMinutes(card.dataset.shiftEnd || '');

                    if (key === 'ArrowLeft' || key === 'ArrowRight') {
                        const date = new Date(shiftDate + 'T12:00:00');
                        date.setDate(date.getDate() + (key === 'ArrowRight' ? 1 : -1));
                        shiftDate = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
                    } else if (event.shiftKey) {
                        endMinutes = Math.max(startMinutes + 15, Math.min((23 * 60) + 59, endMinutes + (key === 'ArrowDown' ? 15 : -15)));
                    } else {
                        const delta = key === 'ArrowDown' ? 15 : -15;
                        const duration = endMinutes - startMinutes;
                        startMinutes = Math.max(0, Math.min((24 * 60) - duration - 1, startMinutes + delta));
                        endMinutes = startMinutes + duration;
                    }

                    submitShiftUpdatePayload({
                        updateUrl: card.dataset.updateUrl || '', employeeId: card.dataset.employeeId || '',
                        roleId: card.dataset.roleId || '', companyId: card.dataset.companyId || '', workLocationId: card.dataset.workLocationId || '', shiftDate,
                        startTime: toTimeValue(startMinutes), endTime: toTimeValue(endMinutes),
                        title: card.dataset.shiftTitle || '', note: card.dataset.shiftNote || '',
                        writeDate: card.dataset.shiftWriteDate || '',
                    });
                    return;
                }

                if (normalizedKey === 'escape') {
                    event.preventDefault();
                    clearSchedulerSelection();
                    clearDragState();
                    return;
                }

                if (normalizedKey === 'a') {
                    event.preventDefault();

                    const targetDate = selectionState.cell?.dataset.shiftDate
                        || selectionState.primaryCard?.dataset.shiftDate
                        || @json($selectedCalendarDateValue);
                    const targetEmployeeId = selectionState.cell?.dataset.employeeId
                        || selectionState.primaryCard?.dataset.employeeId
                        || '';

                    prefillCreateShift({
                        shiftDate: targetDate,
                        employeeId: targetEmployeeId,
                    });
                    openCreateShiftModal();
                    return;
                }

                if (normalizedKey === 'c' && selectionState.primaryCard && getSelectedShiftCards().length === 1) {
                    event.preventDefault();
                    setCopiedShift({
                        roleId: selectionState.primaryCard.dataset.roleId || '',
                        companyId: selectionState.primaryCard.dataset.companyId || '',
                        workLocationId: selectionState.primaryCard.dataset.workLocationId || '',
                        startTime: selectionState.primaryCard.dataset.shiftStart || '',
                        endTime: selectionState.primaryCard.dataset.shiftEnd || '',
                        title: selectionState.primaryCard.dataset.shiftTitle || '',
                        note: selectionState.primaryCard.dataset.shiftNote || '',
                    }, selectionState.primaryCard.querySelector('.shift-role')?.textContent?.trim() || 'Shift copied');
                    return;
                }

                if (normalizedKey === 'v' && copiedShift && selectionState.cell) {
                    event.preventDefault();
                    prefillCreateShift(Object.assign({}, copiedShift, {
                        shiftDate: selectionState.cell.dataset.shiftDate || '',
                        employeeId: selectionState.cell.dataset.employeeId || '',
                    }));
                    openCreateShiftModal();
                    return;
                }

                if (normalizedKey === 'p' && publishWeekButton && !publishWeekButton.disabled) {
                    event.preventDefault();
                    openPublishWeekModal();
                    return;
                }

                if ((key === 'Delete' || key === 'Backspace') && getSelectedShiftCards().length > 0) {
                    const selectedCount = getSelectedShiftCards().length;

                    if (selectedCount === 1) {
                        const deleteForm = selectionState.primaryCard?.querySelector('form');

                        if (!deleteForm) {
                            return;
                        }

                        event.preventDefault();

                        if (window.confirm('Delete this Odoo shift?')) {
                            deleteForm.submit();
                        }

                        return;
                    }

                    event.preventDefault();

                    if (window.confirm('Delete ' + selectedCount + ' selected Odoo shifts?')) {
                        submitBulkDelete();
                    }

                    return;
                }

                if (key === 'Enter' && selectionState.primaryCard && getSelectedShiftCards().length === 1) {
                    const editButton = selectionState.primaryCard.querySelector('.edit-odoo-shift');

                    if (!editButton) {
                        return;
                    }

                    event.preventDefault();
                    editButton.click();
                }
            });

            document.querySelectorAll('.roster-person[data-draggable="1"]').forEach((row) => {
                row.setAttribute('draggable', 'true');

                row.addEventListener('dragstart', function(event) {
                    dragState.type = 'employee';
                    dragState.payload = {
                        employeeId: row.dataset.employeeId || '',
                        companyId: row.dataset.rosterCompanyId || '',
                        workLocationId: row.dataset.rosterWorkLocationId || '',
                        isOpen: row.dataset.rosterOpen === '1',
                    };

                    row.classList.add('is-drag-source');
                    refreshDropTargets();

                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = 'copy';
                        event.dataTransfer.setData('text/plain', dragState.payload.employeeId || 'open-shift');
                    }
                });

                row.addEventListener('dragend', function() {
                    clearDragState();
                });
            });

            document.querySelectorAll('.shift-card[data-draggable="1"]').forEach((card) => {
                card.setAttribute('draggable', 'true');

                card.addEventListener('dragstart', function(event) {
                    dragState.type = 'shift';
                    dragState.payload = {
                        shiftId: card.dataset.shiftId || '',
                        employeeId: card.dataset.employeeId || '',
                        roleId: card.dataset.roleId || '',
                        companyId: card.dataset.companyId || '',
                        workLocationId: card.dataset.workLocationId || '',
                        shiftDate: card.dataset.shiftDate || '',
                        startTime: card.dataset.shiftStart || '',
                        endTime: card.dataset.shiftEnd || '',
                        title: card.dataset.shiftTitle || '',
                        note: card.dataset.shiftNote || '',
                        writeDate: card.dataset.shiftWriteDate || '',
                        updateUrl: card.dataset.updateUrl || '',
                    };

                    card.classList.add('is-drag-source');
                    refreshDropTargets();

                    if (event.dataTransfer) {
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', dragState.payload.shiftId || 'shift');
                    }
                });

                card.addEventListener('dragend', function() {
                    clearDragState();
                });
            });

            document.querySelectorAll('.roster-cell').forEach((cell) => {
                cell.addEventListener('dragenter', function(event) {
                    if (!canDropOnRosterCell(cell)) {
                        return;
                    }

                    event.preventDefault();
                    cell.classList.add('is-drop-active');
                });

                cell.addEventListener('dragover', function(event) {
                    if (!canDropOnRosterCell(cell)) {
                        return;
                    }

                    event.preventDefault();

                    if (event.dataTransfer) {
                        event.dataTransfer.dropEffect = dragState.type === 'shift' ? 'move' : 'copy';
                    }

                    cell.classList.add('is-drop-active');
                });

                cell.addEventListener('dragleave', function() {
                    cell.classList.remove('is-drop-active');
                });

                cell.addEventListener('drop', function(event) {
                    if (!canDropOnRosterCell(cell)) {
                        clearDragState();
                        return;
                    }

                    event.preventDefault();
                    cell.classList.remove('is-drop-active');
                    handleRosterCellDrop(cell);
                });
            });

            const editButtons = document.querySelectorAll('.edit-odoo-shift');
            const editForm = document.getElementById('edit-odoo-shift-form');
            const editCompanySelect = document.getElementById('edit_company_id');
            const editWorkLocationSelect = document.getElementById('edit_work_location_id');
            const editEmployeeSelect = document.getElementById('edit_employee_id');
            const editShiftDateInput = document.getElementById('edit_shift_date');
            const editStartTimeInput = document.getElementById('edit_start_time');
            const editEndTimeInput = document.getElementById('edit_end_time');
            const editShiftDiaryContext = document.getElementById('editShiftDiaryContext');
            const editShiftDiaryContextBody = document.getElementById('editShiftDiaryContextBody');
            const updateEditDiaryContext = () => {
                if (!editShiftDiaryContext || !editShiftDiaryContextBody) return;

                const employeeId = editEmployeeSelect?.value || '';
                const dateValue = editShiftDateInput?.value || '';
                const entries = employeeDiaryByCell?.[employeeId]?.[dateValue] || [];
                const shiftStart = editStartTimeInput?.value || '';
                const shiftEnd = editEndTimeInput?.value || '';
                let hasConflict = false;

                editShiftDiaryContextBody.replaceChildren();
                editShiftDiaryContext.classList.toggle('d-none', entries.length === 0);
                entries.forEach((entry) => {
                    const entryStart = entry.start_time_value || '';
                    const entryEnd = entry.end_time_value || '';
                    const conflicts = entry.entry_type === 'unavailable'
                        && (entry.is_all_day || (
                            entryStart && entryEnd && shiftStart && shiftEnd
                            && entryStart < shiftEnd && entryEnd > shiftStart
                        ));
                    hasConflict = hasConflict || conflicts;

                    const line = document.createElement('div');
                    line.className = conflicts ? 'font-weight-bold text-danger' : '';
                    line.textContent = `${entry.type_label}: ${entry.title} · ${entry.time_label}${entry.notes ? ` — ${entry.notes}` : ''}`;
                    editShiftDiaryContextBody.appendChild(line);
                });

                editShiftDiaryContext.classList.toggle('alert-danger', hasConflict);
                editShiftDiaryContext.classList.toggle('alert-warning', !hasConflict);
            };
            const syncEditWorkLocationOptions = () => {
                if (!editCompanySelect || !editWorkLocationSelect) return;
                Array.from(editWorkLocationSelect.options).forEach((option) => {
                    if (!option.value) return;
                    const matches = !editCompanySelect.value || option.dataset.companyId === editCompanySelect.value;
                    option.disabled = !matches;
                    option.hidden = !matches;
                });
                const selected = editWorkLocationSelect.options[editWorkLocationSelect.selectedIndex];
                if (selected && selected.value && selected.disabled) editWorkLocationSelect.value = '';
            };
            if (editCompanySelect) editCompanySelect.addEventListener('change', syncEditWorkLocationOptions);
            [editEmployeeSelect, editShiftDateInput, editStartTimeInput, editEndTimeInput].forEach((control) => {
                if (control) control.addEventListener('change', updateEditDiaryContext);
            });

            editButtons.forEach((button) => {
                button.addEventListener('click', function() {
                    if (!editForm) {
                        return;
                    }

                    editForm.setAttribute('action', button.dataset.updateUrl || '');
                    document.getElementById('editing_shift_id').value = button.dataset.shiftId || '';
                    document.getElementById('edit_last_known_write_date').value = button.dataset.shiftWriteDate || '';
                    document.getElementById('edit_employee_id').value = button.dataset.shiftEmployeeId || '';
                    document.getElementById('edit_role_id').value = button.dataset.shiftRoleId || '';
                    document.getElementById('edit_company_id').value = button.dataset.shiftCompanyId || '';
                    syncEditWorkLocationOptions();
                    document.getElementById('edit_work_location_id').value = button.dataset.shiftWorkLocationId || '';
                    document.getElementById('edit_shift_date').value = button.dataset.shiftDate || '';
                    document.getElementById('edit_start_time').value = button.dataset.shiftStart || '';
                    document.getElementById('edit_end_time').value = button.dataset.shiftEnd || '';
                    document.getElementById('edit_title').value = button.dataset.shiftTitle || '';
                    document.getElementById('edit_note').value = button.dataset.shiftNote || '';
                    updateEditDiaryContext();
                });
            });

            const editingShiftId = @json(old('editing_shift_id'));
            const shouldOpenCreate = @json($errors->any() && ! old('editing_shift_id'));

            if (editingShiftId) {
                const matchingButton = document.querySelector('.edit-odoo-shift[data-shift-id="' + editingShiftId + '"]');

                if (matchingButton) {
                    matchingButton.click();
                }
            } else if (shouldOpenCreate && window.jQuery) {
                window.jQuery('#create_odoo_shift').modal('show');
            }
        });
    </script>
@endsection
