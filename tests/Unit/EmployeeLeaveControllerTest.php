<?php

namespace Tests\Unit;

use App\Http\Controllers\EmployeeLeaveController;
use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooLeaveService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class EmployeeLeaveControllerTest extends TestCase
{
    public function test_it_returns_leave_page_data_for_the_view(): void
    {
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getLeaveRequestPageData')
                ->once()
                ->withArgs(fn (User $user): bool => $user->odoo_employee_id === 35)
                ->andReturn([
                    'leaveTypes' => [
                        [
                            'id' => 7,
                            'name' => 'Annual Leave',
                            'request_unit' => 'day',
                            'request_unit_label' => 'Day Based',
                            'availability_note' => null,
                            'can_request' => true,
                        ],
                    ],
                    'leaveRequests' => [
                        [
                            'id' => 21,
                            'type' => 'Annual Leave',
                            'status_label' => 'Pending',
                            'status_class' => 'warning',
                            'can_cancel' => true,
                            'start_date_label' => '10 Jun 2026',
                            'end_date_label' => '12 Jun 2026',
                            'duration_label' => '3.00 days',
                            'reason' => 'Trip',
                            'request_unit_label' => 'Day Based',
                            'submitted_at_label' => '01 Jun 2026 09:15 AM',
                        ],
                        [
                            'id' => 22,
                            'type' => 'Sick Leave',
                            'status_label' => 'Approved',
                            'status_class' => 'success',
                            'can_cancel' => false,
                            'start_date_label' => '15 Jun 2026',
                            'end_date_label' => '15 Jun 2026',
                            'duration_label' => '1.00 day',
                            'reason' => '',
                            'request_unit_label' => 'Day Based',
                            'submitted_at_label' => '02 Jun 2026 09:15 AM',
                        ],
                    ],
                ]);
        });

        $view = $this->controller()->index(
            $this->requestWithUser('GET', '/employee/leave-requests', [], $this->employeeUser()),
            app(OdooLeaveService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.employee-leave.index', $view->getName());
        $this->assertCount(1, $data['leaveTypes']);
        $this->assertCount(2, $data['leaveRequests']);
        $this->assertSame(1, $data['leaveSummary']['pending']);
        $this->assertSame(1, $data['leaveSummary']['approved']);
        $this->assertTrue($data['hasLeaveIdentity']);
    }

    public function test_it_exposes_leave_page_errors_without_throwing(): void
    {
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getLeaveRequestPageData')
                ->once()
                ->andThrow(new OdooException('Leave requests are temporarily unavailable.'));
        });

        $view = $this->controller()->index(
            $this->requestWithUser('GET', '/employee/leave-requests', [], $this->employeeUser()),
            app(OdooLeaveService::class)
        );

        $this->assertSame('Leave requests are temporarily unavailable.', $view->getData()['odooLeaveError']);
    }

    public function test_it_prefills_leave_request_data_when_opened_from_an_assigned_shift(): void
    {
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getLeaveRequestPageData')
                ->once()
                ->andReturn([
                    'leaveTypes' => [],
                    'leaveRequests' => [],
                ]);
        });

        $view = $this->controller()->index(
            $this->requestWithUser('GET', '/employee/leave-requests', [
                'source' => 'shift',
                'source_shift_id' => '91',
                'source_shift_title' => 'Morning Shift',
                'source_shift_role' => 'Receptionist',
                'source_shift_company' => 'Clinic',
                'source_shift_date_label' => 'Wed, 10 Jun 2026',
                'source_shift_time_label' => '09:00 AM - 05:00 PM',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
                'start_hour' => '9',
                'end_hour' => '17',
            ], $this->employeeUser()),
            app(OdooLeaveService::class)
        );

        $prefill = $view->getData()['leaveFormPrefill'];

        $this->assertSame('shift', $prefill['source']);
        $this->assertSame('2026-06-10', $prefill['start_date']);
        $this->assertSame('2026-06-10', $prefill['end_date']);
        $this->assertSame('9.00', $prefill['start_hour']);
        $this->assertSame('17.00', $prefill['end_hour']);
        $this->assertSame('Morning Shift', $prefill['source_shift']['title']);
        $this->assertFalse($prefill['is_multi_day_shift']);
    }

    public function test_it_redirects_after_a_successful_leave_submission(): void
    {
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('submitLeaveRequest')
                ->once()
                ->withArgs(function (User $user, array $payload): bool {
                    return $user->odoo_employee_id === 35
                        && $payload['leave_type_id'] === '7'
                        && $payload['start_date'] === '2026-06-10'
                        && $payload['end_date'] === '2026-06-12';
                })
                ->andReturn(55);
        });

        $response = $this->controller()->store(
            $this->requestWithUser('POST', '/employee/leave-requests', [
                'leave_type_id' => '7',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
                'reason' => 'Trip',
            ], $this->employeeUser()),
            app(OdooLeaveService::class)
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.leave.index'), $response->getTargetUrl());
    }

    public function test_it_passes_shift_bridge_fields_when_submitting_from_a_shift(): void
    {
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('submitLeaveRequest')
                ->once()
                ->withArgs(function (User $user, array $payload): bool {
                    return $user->odoo_employee_id === 35
                        && $payload['source'] === 'shift'
                        && $payload['source_shift_id'] === '91'
                        && $payload['source_shift_title'] === 'Morning Shift'
                        && $payload['source_shift_role'] === 'Receptionist'
                        && $payload['source_shift_company'] === 'Clinic'
                        && $payload['source_shift_start_at'] === '2026-06-10 03:00:00'
                        && $payload['source_shift_end_at'] === '2026-06-10 11:00:00';
                })
                ->andReturn(56);
        });

        $response = $this->controller()->store(
            $this->requestWithUser('POST', '/employee/leave-requests', [
                'leave_type_id' => '7',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
                'reason' => 'Unavailable for assigned shift',
                'source' => 'shift',
                'source_shift_id' => '91',
                'source_shift_title' => 'Morning Shift',
                'source_shift_role' => 'Receptionist',
                'source_shift_company' => 'Clinic',
                'source_shift_start_at' => '2026-06-10 03:00:00',
                'source_shift_end_at' => '2026-06-10 11:00:00',
            ], $this->employeeUser()),
            app(OdooLeaveService::class)
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.leave.index'), $response->getTargetUrl());
    }

    public function test_it_redirects_with_errors_when_leave_submission_fails(): void
    {
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('submitLeaveRequest')
                ->once()
                ->andThrow(new OdooException('This leave request overlaps with an existing request.'));
        });

        $response = $this->controller()->store(
            $this->requestWithUser('POST', '/employee/leave-requests', [
                'leave_type_id' => '7',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
                'reason' => 'Trip',
            ], $this->employeeUser()),
            app(OdooLeaveService::class)
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.leave.index'), $response->getTargetUrl());
    }

    public function test_it_redirects_after_a_successful_leave_cancellation(): void
    {
        $this->mock(OdooLeaveService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('cancelLeaveRequest')
                ->once()
                ->withArgs(fn (User $user, int $leaveRequestId): bool => $user->odoo_employee_id === 35 && $leaveRequestId === 21);
        });

        $response = $this->controller()->cancel(
            $this->requestWithUser('POST', '/employee/leave-requests/21/cancel', [], $this->employeeUser()),
            app(OdooLeaveService::class),
            21
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.leave.index'), $response->getTargetUrl());
    }

    private function controller(): EmployeeLeaveController
    {
        return new EmployeeLeaveController();
    }

    private function employeeUser(): User
    {
        $user = new User([
            'name' => 'Odoo Employee',
            'email' => 'employee@example.com',
            'odoo_user_id' => 27,
            'odoo_employee_id' => 35,
        ]);

        $user->setAttribute('id', 1);
        $user->exists = true;

        return $user;
    }

    private function requestWithUser(string $method, string $uri, array $payload, User $user): Request
    {
        $request = Request::create($uri, $method, $payload);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
