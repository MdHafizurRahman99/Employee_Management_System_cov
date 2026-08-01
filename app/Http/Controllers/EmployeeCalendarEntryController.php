<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooEmployeeScheduleEntryService;
use App\Services\Odoo\OdooException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeCalendarEntryController extends Controller
{
    public function store(
        Request $request,
        OdooEmployeeScheduleEntryService $scheduleEntries
    ): RedirectResponse {
        $validated = $this->validateEntry($request);

        try {
            $scheduleEntries->createEntry($request->user(), $validated);
        } catch (OdooException $exception) {
            return $this->entryError($validated['entry_date'], $exception);
        }

        return $this->backToCalendar($validated['entry_date'], 'Diary entry saved in Odoo.');
    }

    public function update(
        Request $request,
        OdooEmployeeScheduleEntryService $scheduleEntries,
        int $calendarEntry
    ): RedirectResponse {
        $validated = $this->validateEntry($request);

        try {
            $scheduleEntries->updateEntry($request->user(), $calendarEntry, $validated);
        } catch (OdooException $exception) {
            return $this->entryError($validated['entry_date'], $exception);
        }

        return $this->backToCalendar($validated['entry_date'], 'Odoo diary entry updated.');
    }

    public function destroy(
        Request $request,
        OdooEmployeeScheduleEntryService $scheduleEntries,
        int $calendarEntry
    ): RedirectResponse {
        try {
            $date = $scheduleEntries->deleteEntry($request->user(), $calendarEntry);
        } catch (OdooException $exception) {
            return redirect()->route('employee.shifts.index')
                ->withErrors(['calendar_entry' => $exception->getMessage()]);
        }

        return $this->backToCalendar($date, 'Odoo diary entry removed.');
    }

    /** @return array<string, mixed> */
    private function validateEntry(Request $request): array
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'entry_type' => ['required', 'in:available,unavailable,note'],
            'title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_all_day' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $validated['is_all_day'] = (bool) ($validated['is_all_day'] ?? false);
        $validated['title'] = trim((string) ($validated['title'] ?? '')) ?: null;
        $validated['notes'] = trim((string) ($validated['notes'] ?? '')) ?: null;

        if ($validated['entry_type'] === 'note' && ! $validated['title'] && ! $validated['notes']) {
            throw ValidationException::withMessages(['calendar_entry' => 'Add a title or note for this diary entry.']);
        }

        if ($validated['is_all_day']) {
            $validated['start_time'] = null;
            $validated['end_time'] = null;
        } elseif (empty($validated['start_time']) || empty($validated['end_time'])) {
            throw ValidationException::withMessages(['calendar_entry' => 'Choose both a start and end time, or mark the entry as all day.']);
        } elseif ($validated['end_time'] <= $validated['start_time']) {
            throw ValidationException::withMessages(['calendar_entry' => 'The end time must be later than the start time.']);
        }

        return $validated;
    }

    private function backToCalendar(string $date, string $message): RedirectResponse
    {
        return redirect()->route('employee.shifts.index', [
            'month' => substr($date, 0, 7),
            'day' => $date,
        ])->with('success', $message);
    }

    private function entryError(string $date, OdooException $exception): RedirectResponse
    {
        return redirect()->route('employee.shifts.index', [
            'month' => substr($date, 0, 7),
            'day' => $date,
        ])->withErrors(['calendar_entry' => $exception->getMessage()])->withInput();
    }
}
