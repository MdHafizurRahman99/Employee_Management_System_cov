<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooLeaveService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeLeaveController extends Controller
{
    /**
     * Display the leave request page for the logged-in employee.
     */
    public function index(Request $request, OdooLeaveService $leaveService): View
    {
        $leaveTypes = [];
        $leaveRequests = [];
        $odooLeaveError = null;
        $hasLeaveIdentity = filled($request->user()?->odoo_employee_id);
        $leaveFormPrefill = $this->resolveLeaveFormPrefill($request);

        if ($hasLeaveIdentity) {
            try {
                $pageData = $leaveService->getLeaveRequestPageData($request->user());
                $leaveTypes = $pageData['leaveTypes'];
                $leaveRequests = $pageData['leaveRequests'];
            } catch (OdooException $exception) {
                $odooLeaveError = $exception->getMessage();
            }
        }

        return view('admin.employee-leave.index', [
            'leaveTypes' => $leaveTypes,
            'leaveRequests' => $leaveRequests,
            'leaveSummary' => $this->summarizeLeaveRequests($leaveRequests),
            'odooLeaveError' => $odooLeaveError,
            'hasLeaveIdentity' => $hasLeaveIdentity,
            'leaveFormPrefill' => $leaveFormPrefill,
        ]);
    }

    /**
     * Submit a leave request to Odoo.
     */
    public function store(Request $request, OdooLeaveService $leaveService): RedirectResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_period' => ['nullable', 'in:am,pm'],
            'end_period' => ['nullable', 'in:am,pm'],
            'start_hour' => ['nullable', 'numeric', 'min:0', 'max:23.99'],
            'end_hour' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'in:shift'],
            'source_shift_id' => ['nullable', 'integer'],
            'source_shift_title' => ['nullable', 'string', 'max:120'],
            'source_shift_role' => ['nullable', 'string', 'max:120'],
            'source_shift_company' => ['nullable', 'string', 'max:120'],
            'source_shift_start_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'source_shift_end_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
        ]);

        try {
            $leaveService->submitLeaveRequest($request->user(), $validated);
        } catch (OdooException $exception) {
            return redirect()
                ->route('employee.leave.index')
                ->withErrors(['leave_request' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('employee.leave.index')
            ->with('success', 'Your leave request has been submitted successfully.');
    }

    /**
     * Cancel a pending leave request in Odoo.
     */
    public function cancel(Request $request, OdooLeaveService $leaveService, int $leaveRequest): RedirectResponse
    {
        try {
            $leaveService->cancelLeaveRequest($request->user(), $leaveRequest);
        } catch (OdooException $exception) {
            return redirect()
                ->route('employee.leave.index')
                ->withErrors(['leave_request' => $exception->getMessage()]);
        }

        return redirect()
            ->route('employee.leave.index')
            ->with('success', 'The leave request has been cancelled.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $leaveRequests
     * @return array<string, int>
     */
    private function summarizeLeaveRequests(array $leaveRequests): array
    {
        return [
            'total' => count($leaveRequests),
            'pending' => count(array_filter($leaveRequests, fn (array $leaveRequest) => $leaveRequest['status_label'] === 'Pending')),
            'approved' => count(array_filter($leaveRequests, fn (array $leaveRequest) => $leaveRequest['status_label'] === 'Approved')),
            'rejected' => count(array_filter($leaveRequests, fn (array $leaveRequest) => $leaveRequest['status_label'] === 'Rejected')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveLeaveFormPrefill(Request $request): array
    {
        $source = $this->requestPrefillValue($request, 'source');
        $startDate = $this->normalizeDateValue($this->requestPrefillValue($request, 'start_date'));
        $endDate = $this->normalizeDateValue($this->requestPrefillValue($request, 'end_date'));
        $startHour = $this->normalizeHourValue($this->requestPrefillValue($request, 'start_hour'));
        $endHour = $this->normalizeHourValue($this->requestPrefillValue($request, 'end_hour'));

        $sourceShift = null;

        if ($source === 'shift') {
            $sourceShift = [
                'id' => $this->normalizeIntegerValue($this->requestPrefillValue($request, 'source_shift_id')),
                'title' => $this->normalizeTextValue($this->requestPrefillValue($request, 'source_shift_title'), 120) ?? 'Assigned Shift',
                'role' => $this->normalizeTextValue($this->requestPrefillValue($request, 'source_shift_role'), 120),
                'company' => $this->normalizeTextValue($this->requestPrefillValue($request, 'source_shift_company'), 120),
                'date_label' => $this->normalizeTextValue($this->requestPrefillValue($request, 'source_shift_date_label'), 80),
                'time_label' => $this->normalizeTextValue($this->requestPrefillValue($request, 'source_shift_time_label'), 80),
                'start_at' => $this->normalizeDateTimeValue($this->requestPrefillValue($request, 'source_shift_start_at')),
                'end_at' => $this->normalizeDateTimeValue($this->requestPrefillValue($request, 'source_shift_end_at')),
            ];
        }

        return [
            'source' => $sourceShift ? 'shift' : null,
            'start_date' => $startDate,
            'end_date' => $endDate ?? $startDate,
            'start_hour' => $startHour,
            'end_hour' => $endHour,
            'source_shift' => $sourceShift,
            'is_multi_day_shift' => $sourceShift && $startDate && $endDate
                ? $startDate !== $endDate
                : false,
        ];
    }

    private function requestPrefillValue(Request $request, string $key): mixed
    {
        $oldValue = $request->old($key);

        return $oldValue !== null ? $oldValue : $request->query($key);
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', trim($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDateTimeValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', trim($value), 'UTC')
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeHourValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $normalizedValue = round((float) $value, 2);

        if ($normalizedValue < 0 || $normalizedValue > 24) {
            return null;
        }

        return number_format($normalizedValue, 2, '.', '');
    }

    private function normalizeIntegerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeTextValue(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return null;
        }

        return mb_substr($normalizedValue, 0, $maxLength);
    }
}
