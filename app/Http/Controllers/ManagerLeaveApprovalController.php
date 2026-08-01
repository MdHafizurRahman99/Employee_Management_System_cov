<?php

namespace App\Http\Controllers;

use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerLeaveService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerLeaveApprovalController extends Controller
{
    /**
     * Display pending team leave requests for approval.
     */
    public function index(Request $request, OdooManagerLeaveService $leaveService): View
    {
        $employeeId = $request->query('employee_id');
        $pageData = [
            'employees' => [],
            'leaveRequests' => [],
            'summary' => $this->emptySummary(),
        ];
        $odooLeaveError = null;
        $hasManagerLeaveIdentity = filled($request->user()?->odoo_user_id);

        if ($hasManagerLeaveIdentity) {
            try {
                $pageData = $leaveService->getLeaveApprovalPageData(
                    $request->user(),
                    is_numeric($employeeId) ? (int) $employeeId : null
                );
            } catch (OdooException $exception) {
                $odooLeaveError = $exception->getMessage();
            }
        }

        return view('admin.manager-leave-approvals.index', [
            'employees' => $pageData['employees'],
            'leaveRequests' => $pageData['leaveRequests'],
            'leaveSummary' => $pageData['summary'],
            'odooLeaveError' => $odooLeaveError,
            'hasManagerLeaveIdentity' => $hasManagerLeaveIdentity,
            'selectedEmployeeId' => is_numeric($employeeId) ? (int) $employeeId : null,
        ]);
    }

    /**
     * Approve a pending leave request.
     */
    public function approve(Request $request, OdooManagerLeaveService $leaveService, int $leaveRequest): RedirectResponse
    {
        $validated = $request->validate([
            'last_known_write_date' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $leaveService->approveLeaveRequest(
                $request->user(),
                $leaveRequest,
                (string) ($validated['last_known_write_date'] ?? '')
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.leave-approvals.index', $this->preservedFilters($request))
                ->withErrors(['manager_leave' => $exception->getMessage()]);
        }

        return redirect()
            ->route('manager.leave-approvals.index', $this->preservedFilters($request))
            ->with('success', 'The leave request was approved successfully.');
    }

    /**
     * Refuse a pending leave request.
     */
    public function refuse(Request $request, OdooManagerLeaveService $leaveService, int $leaveRequest): RedirectResponse
    {
        $validated = $request->validate([
            'manager_note' => ['nullable', 'string', 'max:2000'],
            'last_known_write_date' => ['nullable', 'string', 'max:40'],
            'editing_leave_request_id' => ['nullable', 'integer'],
        ]);

        try {
            $leaveService->refuseLeaveRequest(
                $request->user(),
                $leaveRequest,
                (string) ($validated['last_known_write_date'] ?? ''),
                $validated['manager_note'] ?? null
            );
        } catch (OdooException $exception) {
            return redirect()
                ->route('manager.leave-approvals.index', $this->preservedFilters($request))
                ->withErrors(['manager_leave' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('manager.leave-approvals.index', $this->preservedFilters($request))
            ->with('success', 'The leave request was rejected successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'pending_count' => 0,
            'employees_count' => 0,
            'double_approval_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function preservedFilters(Request $request): array
    {
        return array_filter([
            'employee_id' => $request->input('employee_id', $request->query('employee_id')),
        ], fn (mixed $value) => $value !== null && $value !== '');
    }
}
