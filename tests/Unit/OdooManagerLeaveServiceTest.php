<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\LeaveRequestStatusChangedNotification;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooManagerLeaveService;
use App\Services\Odoo\OdooServiceAccount;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class OdooManagerLeaveServiceTest extends TestCase
{
    public function test_it_builds_pending_team_leave_requests_for_the_manager(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'work_email' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.employee'
                    && $method === 'search_read'
                    && $args === [[
                        ['leave_manager_id', '=', 27],
                        ['active', '=', true],
                    ]];
            })
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Alice Jones',
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'alice@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.employee'
                    && $method === 'search_read'
                    && $args === [[
                        ['parent_id.user_id', '=', 27],
                        ['active', '=', true],
                    ]];
            })
            ->andReturn([
                [
                    'id' => 36,
                    'name' => 'Bob Smith',
                    'company_id' => [2, 'Clinic'],
                    'work_email' => 'bob@example.com',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.employee'
                    && $method === 'search_read'
                    && $args === [[
                        ['attendance_manager_id', '=', 27],
                        ['active', '=', true],
                    ]];
            })
            ->andReturn([]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.users',
                'search_read',
                [[['id', '=', 27]]],
                ['fields' => ['group_ids'], 'limit' => 1]
            )
            ->andReturn([
                ['group_ids' => []],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'holiday_status_id' => ['type' => 'many2one'],
                'state' => ['type' => 'selection'],
                'validation_type' => ['type' => 'selection'],
                'request_date_from' => ['type' => 'date'],
                'request_date_to' => ['type' => 'date'],
                'number_of_days' => ['type' => 'float'],
                'number_of_hours' => ['type' => 'float'],
                'notes' => ['type' => 'text'],
                'create_date' => ['type' => 'datetime'],
                'leave_type_request_unit' => ['type' => 'selection'],
                'can_approve' => ['type' => 'boolean'],
                'can_validate' => ['type' => 'boolean'],
                'can_refuse' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs = []): bool {
                return $model === 'hr.leave'
                    && $method === 'search_read'
                    && $args === [[
                        ['employee_id', 'in', [35, 36]],
                        ['state', 'in', ['confirm', 'validate1']],
                    ]]
                    && ($kwargs['limit'] ?? null) === 100;
            })
            ->andReturn([
                [
                    'id' => 81,
                    'employee_id' => [35, 'Alice Jones'],
                    'holiday_status_id' => [7, 'Annual Leave'],
                    'state' => 'confirm',
                    'validation_type' => 'manager',
                    'request_date_from' => '2026-06-20',
                    'request_date_to' => '2026-06-22',
                    'number_of_days' => 3.0,
                    'number_of_hours' => 24.0,
                    'notes' => 'Family trip',
                    'create_date' => '2026-06-01 09:00:00',
                    'leave_type_request_unit' => 'day',
                    'can_approve' => true,
                    'can_validate' => false,
                    'can_refuse' => true,
                    'write_date' => '2026-06-01 09:05:00',
                ],
                [
                    'id' => 82,
                    'employee_id' => [36, 'Bob Smith'],
                    'holiday_status_id' => [8, 'Study Leave'],
                    'state' => 'validate1',
                    'validation_type' => 'both',
                    'request_date_from' => '2026-06-25',
                    'request_date_to' => '2026-06-25',
                    'number_of_days' => 0.0,
                    'number_of_hours' => 2.5,
                    'notes' => '',
                    'create_date' => '2026-06-02 10:00:00',
                    'leave_type_request_unit' => 'hour',
                    'can_approve' => false,
                    'can_validate' => true,
                    'can_refuse' => true,
                    'write_date' => '2026-06-02 10:05:00',
                ],
            ]);

        $service = new OdooManagerLeaveService($serviceAccount);
        $data = $service->getLeaveApprovalPageData(new User(['odoo_user_id' => 27]));

        $this->assertCount(2, $data['employees']);
        $this->assertSame('Alice Jones', $data['employees'][0]['name']);
        $this->assertSame('Bob Smith', $data['employees'][1]['name']);

        $this->assertCount(2, $data['leaveRequests']);
        $this->assertSame('Pending Approval', $data['leaveRequests'][0]['status_label']);
        $this->assertSame('Pending Second Approval', $data['leaveRequests'][1]['status_label']);
        $this->assertTrue($data['leaveRequests'][1]['can_approve_action']);

        $this->assertSame(2, $data['summary']['pending_count']);
        $this->assertSame(2, $data['summary']['employees_count']);
        $this->assertSame(1, $data['summary']['double_approval_count']);
    }

    public function test_it_approves_a_leave_request_and_notifies_the_local_employee(): void
    {
        Notification::fake();

        $employeeUser = new User([
            'name' => 'Alice Jones',
            'email' => 'alice@example.com',
            'password' => 'password',
            'auth_source' => 'odoo',
            'role' => 'user',
            'odoo_user_id' => 41,
            'odoo_employee_id' => 35,
        ]);
        $employeeUser->setAttribute('id', 1);
        $employeeUser->exists = true;

        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'work_email' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
            ]);

        foreach ([
            ['field' => 'leave_manager_id', 'result' => [[
                'id' => 35,
                'name' => 'Alice Jones',
                'company_id' => [2, 'Clinic'],
                'work_email' => 'alice@example.com',
            ]]],
            ['field' => 'parent_id.user_id', 'result' => []],
            ['field' => 'attendance_manager_id', 'result' => []],
        ] as $relationQuery) {
            $serviceAccount->shouldReceive('executeKw')
                ->once()
                ->withArgs(function (string $model, string $method, array $args) use ($relationQuery): bool {
                    return $model === 'hr.employee'
                        && $method === 'search_read'
                        && $args === [[
                            [$relationQuery['field'], '=', 27],
                            ['active', '=', true],
                        ]];
                })
                ->andReturn($relationQuery['result']);
        }

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.users',
                'search_read',
                [[['id', '=', 27]]],
                ['fields' => ['group_ids'], 'limit' => 1]
            )
            ->andReturn([
                ['group_ids' => []],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'holiday_status_id' => ['type' => 'many2one'],
                'state' => ['type' => 'selection'],
                'validation_type' => ['type' => 'selection'],
                'request_date_from' => ['type' => 'date'],
                'request_date_to' => ['type' => 'date'],
                'number_of_days' => ['type' => 'float'],
                'number_of_hours' => ['type' => 'float'],
                'notes' => ['type' => 'text'],
                'create_date' => ['type' => 'datetime'],
                'leave_type_request_unit' => ['type' => 'selection'],
                'can_approve' => ['type' => 'boolean'],
                'can_validate' => ['type' => 'boolean'],
                'can_refuse' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs = []): bool {
                return $model === 'hr.leave'
                    && $method === 'search_read'
                    && $args === [[['id', '=', 81]]]
                    && ($kwargs['limit'] ?? null) === 1;
            })
            ->andReturn([
                [
                    'id' => 81,
                    'employee_id' => [35, 'Alice Jones'],
                    'holiday_status_id' => [7, 'Annual Leave'],
                    'state' => 'confirm',
                    'validation_type' => 'manager',
                    'request_date_from' => '2026-06-20',
                    'request_date_to' => '2026-06-22',
                    'number_of_days' => 3.0,
                    'number_of_hours' => 24.0,
                    'notes' => 'Family trip',
                    'create_date' => '2026-06-01 09:00:00',
                    'leave_type_request_unit' => 'day',
                    'can_approve' => true,
                    'can_validate' => false,
                    'can_refuse' => true,
                    'write_date' => '2026-06-01 09:05:00',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs = []): bool {
                return $model === 'hr.leave'
                    && $method === 'search_read'
                    && $args === [[['id', '=', 81]]]
                    && ($kwargs['limit'] ?? null) === 1;
            })
            ->andReturn([
                [
                    'id' => 81,
                    'employee_id' => [35, 'Alice Jones'],
                    'holiday_status_id' => [7, 'Annual Leave'],
                    'state' => 'validate',
                    'validation_type' => 'manager',
                    'request_date_from' => '2026-06-20',
                    'request_date_to' => '2026-06-22',
                    'number_of_days' => 3.0,
                    'number_of_hours' => 24.0,
                    'notes' => 'Family trip',
                    'create_date' => '2026-06-01 09:00:00',
                    'leave_type_request_unit' => 'day',
                    'can_approve' => false,
                    'can_validate' => false,
                    'can_refuse' => false,
                    'write_date' => '2026-06-03 10:00:00',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'action_approve', [[81]])
            ->andReturn(true);

        $service = Mockery::mock(OdooManagerLeaveService::class, [$serviceAccount])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getLocalEmployeeUsers')
            ->once()
            ->with(35)
            ->andReturn(collect([$employeeUser]));

        $updatedLeave = $service->approveLeaveRequest(
            new User(['id' => 7, 'name' => 'Manager', 'odoo_user_id' => 27]),
            81,
            '2026-06-01 09:05:00'
        );

        Notification::assertSentTo(
            $employeeUser,
            LeaveRequestStatusChangedNotification::class,
            fn (LeaveRequestStatusChangedNotification $notification) => true
        );

        $this->assertSame('validate', $updatedLeave['state']);
        $this->assertSame('Approved', $updatedLeave['status_label']);
    }

    public function test_it_rejects_stale_leave_approvals(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.employee', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'company_id' => ['type' => 'many2one'],
                'work_email' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
            ]);

        foreach ([
            ['field' => 'leave_manager_id', 'result' => [[
                'id' => 35,
                'name' => 'Alice Jones',
                'company_id' => [2, 'Clinic'],
                'work_email' => 'alice@example.com',
            ]]],
            ['field' => 'parent_id.user_id', 'result' => []],
            ['field' => 'attendance_manager_id', 'result' => []],
        ] as $relationQuery) {
            $serviceAccount->shouldReceive('executeKw')
                ->once()
                ->withArgs(function (string $model, string $method, array $args) use ($relationQuery): bool {
                    return $model === 'hr.employee'
                        && $method === 'search_read'
                        && $args === [[
                            [$relationQuery['field'], '=', 27],
                            ['active', '=', true],
                        ]];
                })
                ->andReturn($relationQuery['result']);
        }

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.users',
                'search_read',
                [[['id', '=', 27]]],
                ['fields' => ['group_ids'], 'limit' => 1]
            )
            ->andReturn([
                ['group_ids' => []],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'employee_id' => ['type' => 'many2one'],
                'holiday_status_id' => ['type' => 'many2one'],
                'state' => ['type' => 'selection'],
                'validation_type' => ['type' => 'selection'],
                'request_date_from' => ['type' => 'date'],
                'request_date_to' => ['type' => 'date'],
                'number_of_days' => ['type' => 'float'],
                'number_of_hours' => ['type' => 'float'],
                'notes' => ['type' => 'text'],
                'create_date' => ['type' => 'datetime'],
                'leave_type_request_unit' => ['type' => 'selection'],
                'can_approve' => ['type' => 'boolean'],
                'can_validate' => ['type' => 'boolean'],
                'can_refuse' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(fn (string $model, string $method): bool => $model === 'hr.leave' && $method === 'search_read')
            ->andReturn([
                [
                    'id' => 81,
                    'employee_id' => [35, 'Alice Jones'],
                    'holiday_status_id' => [7, 'Annual Leave'],
                    'state' => 'confirm',
                    'validation_type' => 'manager',
                    'request_date_from' => '2026-06-20',
                    'request_date_to' => '2026-06-22',
                    'number_of_days' => 3.0,
                    'number_of_hours' => 24.0,
                    'notes' => 'Family trip',
                    'create_date' => '2026-06-01 09:00:00',
                    'leave_type_request_unit' => 'day',
                    'can_approve' => true,
                    'can_validate' => false,
                    'can_refuse' => true,
                    'write_date' => '2026-06-01 10:00:00',
                ],
            ]);

        $service = new OdooManagerLeaveService($serviceAccount);

        $this->expectException(OdooException::class);
        $this->expectExceptionMessage('This leave request was updated by someone else. Please reload the page before trying again.');

        $service->approveLeaveRequest(
            new User(['id' => 7, 'name' => 'Manager', 'odoo_user_id' => 27]),
            81,
            '2026-06-01 09:05:00'
        );
    }
}
