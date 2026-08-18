<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooScheduleRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamCalendarEventController extends Controller
{
    public function store(Request $request, OdooScheduleRepository $repository): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            $repository->upsertDay($this->repositoryPayload($validated));
        } catch (OdooException $exception) {
            return back()->withErrors(['calendar_event' => $exception->getMessage()])->withInput();
        }

        return $this->calendarRedirect($validated['schedule_date'])
            ->with('calendar_event_success', 'Event added to the team calendar.');
    }

    public function update(Request $request, OdooScheduleRepository $repository, int $calendarEvent): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            $repository->updateDay($calendarEvent, $this->repositoryPayload($validated));
        } catch (OdooException $exception) {
            return back()->withErrors(['calendar_event' => $exception->getMessage()])->withInput();
        }

        return $this->calendarRedirect($validated['schedule_date'])
            ->with('calendar_event_success', 'Event updated successfully.');
    }

    public function destroy(Request $request, OdooScheduleRepository $repository, int $calendarEvent): RedirectResponse
    {
        $validated = $request->validate(['calendar_month' => ['nullable', 'date_format:Y-m']]);

        try {
            $repository->deleteDay($calendarEvent);
        } catch (OdooException $exception) {
            return back()->withErrors(['calendar_event' => $exception->getMessage()]);
        }

        return redirect()->route('team-calendar.index', ['month' => $validated['calendar_month'] ?? now()->format('Y-m')])
            ->with('calendar_event_success', 'Event removed from the team calendar.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'company_id' => ['required', 'integer', 'min:1'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_with:start_time', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:2000'],
            'calendar_month' => ['nullable', 'date_format:Y-m'],
        ]);
    }

    /** @param array<string, mixed> $validated
     *  @return array<string, mixed>
     */
    private function repositoryPayload(array $validated): array
    {
        return [
            'company_id' => (int) $validated['company_id'],
            'schedule_area_id' => null,
            'schedule_date' => $validated['schedule_date'],
            'holiday_name' => trim($validated['title']),
            'note' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            'blocked_start' => $validated['start_time'] ?? null,
            'blocked_end' => $validated['end_time'] ?? null,
        ];
    }

    private function calendarRedirect(string $date): RedirectResponse
    {
        return redirect()->route('team-calendar.index', ['month' => substr($date, 0, 7)]);
    }
}
