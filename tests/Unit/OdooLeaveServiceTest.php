<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooLeaveService;
use App\Services\Odoo\OdooServiceAccount;
use Mockery;
use Tests\TestCase;

class OdooLeaveServiceTest extends TestCase
{
    public function test_it_normalizes_leave_types_and_requests_for_the_page(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave.type', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
                'request_unit' => ['type' => 'selection'],
                'requires_allocation' => ['type' => 'boolean'],
                'has_valid_allocation' => ['type' => 'boolean'],
                'leave_validation_type' => ['type' => 'selection'],
                'sequence' => ['type' => 'integer'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.leave.type'
                    && $method === 'search_read'
                    && $args === [[['active', '=', true]]]
                    && ($kwargs['order'] ?? null) === 'sequence asc, name asc';
            })
            ->andReturn([
                [
                    'id' => 7,
                    'name' => 'Annual Leave',
                    'request_unit' => 'day',
                    'requires_allocation' => true,
                    'has_valid_allocation' => true,
                    'leave_validation_type' => 'manager',
                ],
                [
                    'id' => 8,
                    'name' => 'Study Leave',
                    'request_unit' => 'hour',
                    'requires_allocation' => true,
                    'has_valid_allocation' => false,
                    'leave_validation_type' => 'both',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'holiday_status_id' => ['type' => 'many2one'],
                'state' => ['type' => 'selection'],
                'request_date_from' => ['type' => 'date'],
                'request_date_to' => ['type' => 'date'],
                'number_of_days' => ['type' => 'float'],
                'number_of_hours' => ['type' => 'float'],
                'notes' => ['type' => 'text'],
                'create_date' => ['type' => 'datetime'],
                'can_cancel' => ['type' => 'boolean'],
                'leave_type_request_unit' => ['type' => 'selection'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                return $model === 'hr.leave'
                    && $method === 'search_read'
                    && $args === [[['employee_id', '=', 35]]]
                    && ($kwargs['limit'] ?? null) === 50;
            })
            ->andReturn([
                [
                    'id' => 21,
                    'holiday_status_id' => [7, 'Annual Leave'],
                    'state' => 'confirm',
                    'request_date_from' => '2026-06-15',
                    'request_date_to' => '2026-06-17',
                    'number_of_days' => 3.0,
                    'number_of_hours' => 24.0,
                    'notes' => 'Family trip',
                    'create_date' => '2026-06-01 09:15:00',
                    'can_cancel' => true,
                    'leave_type_request_unit' => 'day',
                ],
                [
                    'id' => 22,
                    'holiday_status_id' => [8, 'Study Leave'],
                    'state' => 'validate',
                    'request_date_from' => '2026-06-20',
                    'request_date_to' => '2026-06-20',
                    'number_of_days' => 0.0,
                    'number_of_hours' => 2.5,
                    'notes' => '',
                    'create_date' => '2026-06-02 10:00:00',
                    'can_cancel' => false,
                    'leave_type_request_unit' => 'hour',
                ],
            ]);

        $service = new OdooLeaveService($serviceAccount);
        $result = $service->getLeaveRequestPageData(new User(['odoo_employee_id' => 35]));

        $this->assertCount(2, $result['leaveTypes']);
        $this->assertSame('Annual Leave', $result['leaveTypes'][0]['name']);
        $this->assertSame('Day Based', $result['leaveTypes'][0]['request_unit_label']);
        $this->assertNull($result['leaveTypes'][0]['availability_note']);
        $this->assertSame('Hourly', $result['leaveTypes'][1]['request_unit_label']);
        $this->assertSame(
            'This leave type needs available balance or an approved allocation in Odoo. Without that, the request may be rejected.',
            $result['leaveTypes'][1]['availability_note']
        );

        $this->assertCount(2, $result['leaveRequests']);
        $this->assertSame('Pending', $result['leaveRequests'][0]['status_label']);
        $this->assertTrue($result['leaveRequests'][0]['can_cancel']);
        $this->assertSame('3.00 days', $result['leaveRequests'][0]['duration_label']);
        $this->assertSame('Approved', $result['leaveRequests'][1]['status_label']);
        $this->assertSame('2.50 hours', $result['leaveRequests'][1]['duration_label']);
    }

    public function test_it_submits_a_day_based_leave_request_to_odoo(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave.type', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
                'request_unit' => ['type' => 'selection'],
                'requires_allocation' => ['type' => 'boolean'],
                'has_valid_allocation' => ['type' => 'boolean'],
                'leave_validation_type' => ['type' => 'selection'],
                'sequence' => ['type' => 'integer'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.leave.type'
                    && $method === 'search_read'
                    && $args === [[['active', '=', true]]];
            })
            ->andReturn([
                [
                    'id' => 7,
                    'name' => 'Annual Leave',
                    'request_unit' => 'day',
                    'requires_allocation' => true,
                    'has_valid_allocation' => true,
                    'leave_validation_type' => 'manager',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.leave',
                'search_count',
                [[
                    ['employee_id', '=', 35],
                    ['state', 'not in', ['refuse', 'cancel']],
                    ['request_date_from', '<=', '2026-06-12'],
                    ['request_date_to', '>=', '2026-06-10'],
                ]]
            )
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.leave',
                'create',
                [[
                    'employee_id' => 35,
                    'holiday_status_id' => 7,
                    'request_date_from' => '2026-06-10',
                    'request_date_to' => '2026-06-12',
                    'notes' => 'Medical appointment',
                    'name' => 'Medical appointment',
                ]]
            )
            ->andReturn(55);

        $service = new OdooLeaveService($serviceAccount);
        $leaveId = $service->submitLeaveRequest(new User(['odoo_employee_id' => 35]), [
            'leave_type_id' => 7,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'reason' => 'Medical appointment',
        ]);

        $this->assertSame(55, $leaveId);
    }

    public function test_it_attaches_planning_bridge_metadata_when_supported_by_odoo(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave.type', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
                'request_unit' => ['type' => 'selection'],
                'requires_allocation' => ['type' => 'boolean'],
                'has_valid_allocation' => ['type' => 'boolean'],
                'leave_validation_type' => ['type' => 'selection'],
                'sequence' => ['type' => 'integer'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method, array $args): bool {
                return $model === 'hr.leave.type'
                    && $method === 'search_read'
                    && $args === [[['active', '=', true]]];
            })
            ->andReturn([
                [
                    'id' => 7,
                    'name' => 'Annual Leave',
                    'request_unit' => 'day',
                    'requires_allocation' => true,
                    'has_valid_allocation' => true,
                    'leave_validation_type' => 'manager',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.leave',
                'search_count',
                [[
                    ['employee_id', '=', 35],
                    ['state', 'not in', ['refuse', 'cancel']],
                    ['request_date_from', '<=', '2026-06-10'],
                    ['request_date_to', '>=', '2026-06-10'],
                ]]
            )
            ->andReturn(0);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'planning_slot_id' => ['type' => 'many2one'],
                'planning_slot_title' => ['type' => 'char'],
                'planning_role_name' => ['type' => 'char'],
                'planning_company_name' => ['type' => 'char'],
                'planning_start_datetime' => ['type' => 'datetime'],
                'planning_end_datetime' => ['type' => 'datetime'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('planning.slot', 'search_count', [[['id', '=', 91]]])
            ->andReturn(1);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.leave',
                'create',
                [[
                    'employee_id' => 35,
                    'holiday_status_id' => 7,
                    'request_date_from' => '2026-06-10',
                    'request_date_to' => '2026-06-10',
                    'notes' => 'Unavailable for assigned shift',
                    'name' => 'Unavailable for assigned shift',
                    'planning_slot_id' => 91,
                    'planning_slot_title' => 'Morning Shift',
                    'planning_role_name' => 'Receptionist',
                    'planning_company_name' => 'Clinic',
                    'planning_start_datetime' => '2026-06-10 03:00:00',
                    'planning_end_datetime' => '2026-06-10 11:00:00',
                ]]
            )
            ->andReturn(56);

        $service = new OdooLeaveService($serviceAccount);
        $leaveId = $service->submitLeaveRequest(new User(['odoo_employee_id' => 35]), [
            'leave_type_id' => 7,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-10',
            'reason' => 'Unavailable for assigned shift',
            'source_shift_id' => '91',
            'source_shift_title' => 'Morning Shift',
            'source_shift_role' => 'Receptionist',
            'source_shift_company' => 'Clinic',
            'source_shift_start_at' => '2026-06-10 03:00:00',
            'source_shift_end_at' => '2026-06-10 11:00:00',
        ]);

        $this->assertSame(56, $leaveId);
    }

    public function test_it_rejects_overlapping_day_based_requests_before_submission(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave.type', 'fields_get', [], ['attributes' => ['string', 'type', 'relation']])
            ->andReturn([
                'name' => ['type' => 'char'],
                'active' => ['type' => 'boolean'],
                'request_unit' => ['type' => 'selection'],
                'requires_allocation' => ['type' => 'boolean'],
                'has_valid_allocation' => ['type' => 'boolean'],
                'leave_validation_type' => ['type' => 'selection'],
                'sequence' => ['type' => 'integer'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->withArgs(function (string $model, string $method): bool {
                return $model === 'hr.leave.type' && $method === 'search_read';
            })
            ->andReturn([
                [
                    'id' => 7,
                    'name' => 'Annual Leave',
                    'request_unit' => 'day',
                    'requires_allocation' => false,
                    'has_valid_allocation' => true,
                    'leave_validation_type' => 'manager',
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with('hr.leave', 'search_count', Mockery::type('array'))
            ->andReturn(1);

        $service = new OdooLeaveService($serviceAccount);

        $this->expectException(OdooException::class);
        $this->expectExceptionMessage('This leave request overlaps with an existing request for the selected dates.');

        $service->submitLeaveRequest(new User(['odoo_employee_id' => 35]), [
            'leave_type_id' => 7,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'reason' => 'Family event',
        ]);
    }

    public function test_it_cancels_a_pending_leave_request(): void
    {
        $serviceAccount = Mockery::mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.leave',
                'search_read',
                [[
                    ['id', '=', 21],
                    ['employee_id', '=', 35],
                ]],
                [
                    'fields' => ['id', 'state', 'can_cancel'],
                    'limit' => 1,
                ]
            )
            ->andReturn([
                [
                    'id' => 21,
                    'state' => 'confirm',
                    'can_cancel' => true,
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.leave',
                'write',
                [[21], ['state' => 'cancel']]
            )
            ->andReturn(true);

        $service = new OdooLeaveService($serviceAccount);

        $service->cancelLeaveRequest(new User(['odoo_employee_id' => 35]), 21);

        $this->assertTrue(true);
    }
}
