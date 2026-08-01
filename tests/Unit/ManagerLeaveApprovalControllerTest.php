<?php

namespace Tests\Unit;

use App\Http\Controllers\ManagerLeaveApprovalController;
use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerLeaveService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class ManagerLeaveApprovalControllerTest extends TestCase
{
    public function test_it_returns_the_manager_leave_approval_view(): void
    {
        $this->mock(OdooManagerLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getLeaveApprovalPageData')
                ->once()
                ->andReturn([
                    'employees' => [['id' => 35, 'name' => 'Alice Jones', 'company' => 'Clinic']],
                    'leaveRequests' => [['id' => 81, 'employee' => 'Alice Jones']],
                    'summary' => [
                        'pending_count' => 1,
                        'employees_count' => 1,
                        'double_approval_count' => 0,
                    ],
                ]);
        });

        $request = Request::create('/manager/leave-approvals', 'GET');
        $request->setUserResolver(fn (): User => new User(['odoo_user_id' => 27]));

        $view = (new ManagerLeaveApprovalController())->index($request, app(OdooManagerLeaveService::class));
        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.manager-leave-approvals.index', $view->getName());
        $this->assertCount(1, $data['employees']);
        $this->assertCount(1, $data['leaveRequests']);
        $this->assertSame(1, $data['leaveSummary']['pending_count']);
    }

    public function test_it_redirects_with_success_after_approving_a_leave_request(): void
    {
        $this->mock(OdooManagerLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('approveLeaveRequest')
                ->once()
                ->withArgs(fn (User $user, int $leaveRequestId, string $writeDate): bool => $user->odoo_user_id === 27 && $leaveRequestId === 81 && $writeDate === '2026-06-01 09:05:00')
                ->andReturn([
                    'id' => 81,
                    'state' => 'validate',
                    'status_label' => 'Approved',
                ]);
        });

        $request = Request::create('/manager/leave-approvals/81/approve', 'POST', [
            'employee_id' => '35',
            'last_known_write_date' => '2026-06-01 09:05:00',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => new User(['odoo_user_id' => 27]));

        $response = (new ManagerLeaveApprovalController())->approve(
            $request,
            app(OdooManagerLeaveService::class),
            81
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('manager.leave-approvals.index', ['employee_id' => '35']),
            $response->getTargetUrl()
        );
    }

    public function test_it_redirects_with_errors_after_refusal_failures(): void
    {
        $this->mock(OdooManagerLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refuseLeaveRequest')
                ->once()
                ->andThrow(new OdooException('This leave request was updated by someone else. Please reload the page before trying again.'));
        });

        $request = Request::create('/manager/leave-approvals/81/refuse', 'POST', [
            'employee_id' => '35',
            'manager_note' => 'Dates overlap with clinic cover.',
            'last_known_write_date' => '2026-06-01 09:05:00',
            'editing_leave_request_id' => '81',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => new User(['odoo_user_id' => 27]));

        $response = (new ManagerLeaveApprovalController())->refuse(
            $request,
            app(OdooManagerLeaveService::class),
            81
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            route('manager.leave-approvals.index', ['employee_id' => '35']),
            $response->getTargetUrl()
        );
    }
}
