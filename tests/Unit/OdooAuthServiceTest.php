<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooAuthService;
use App\Services\Odoo\OdooServiceAccount;
use Tests\TestCase;

class OdooAuthServiceTest extends TestCase
{
    public function test_it_authenticates_employees_by_email_and_pin_and_marks_managers_by_groups(): void
    {
        $serviceAccount = $this->mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.employee',
                'fields_get',
                [],
                ['attributes' => ['string', 'type', 'relation']]
            )
            ->andReturn([
                'work_email' => ['type' => 'char'],
                'private_email' => ['type' => 'char'],
                'pin' => ['type' => 'char'],
                'user_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.employee',
                'search_read',
                [[
                    ['work_email', 'ilike', 'manager@example.com'],
                    ['active', '=', true],
                ]],
                [
                    'fields' => ['id', 'name', 'work_email', 'private_email', 'pin', 'user_id', 'resource_id'],
                    'order' => 'id asc',
                    'limit' => 25,
                ]
            )
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Manager User',
                    'work_email' => 'manager@example.com',
                    'private_email' => '',
                    'pin' => '1234',
                    'user_id' => [27, 'Manager User'],
                    'resource_id' => [44, 'Resource'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.users',
                'search_read',
                [[['id', '=', 27]]],
                ['fields' => ['group_ids'], 'limit' => 1]
            )
            ->andReturn([
                ['group_ids' => [7]],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.groups',
                'search_read',
                [[['id', 'in', [7]]]],
                ['fields' => ['name', 'privilege_id']]
            )
            ->andReturn([
                ['name' => 'Officer: Manage all requests', 'privilege_id' => [5, 'Time Off']],
            ]);

        $service = new OdooAuthService($serviceAccount);
        $profile = $service->authenticate('manager@example.com', '1234');

        $this->assertNotNull($profile);
        $this->assertTrue($profile['is_manager']);
        $this->assertSame('manager', $profile['role']);
        $this->assertSame(35, $profile['odoo_employee_id']);
        $this->assertSame(44, $profile['odoo_resource_id']);
    }

    public function test_it_marks_users_as_managers_when_they_have_direct_reports_even_without_manager_groups(): void
    {
        $serviceAccount = $this->mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.employee',
                'fields_get',
                [],
                ['attributes' => ['string', 'type', 'relation']]
            )
            ->andReturn([
                'work_email' => ['type' => 'char'],
                'private_email' => ['type' => 'char'],
                'pin' => ['type' => 'char'],
                'user_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.employee',
                'search_read',
                [[
                    ['work_email', 'ilike', 'lead@example.com'],
                    ['active', '=', true],
                ]],
                [
                    'fields' => ['id', 'name', 'work_email', 'private_email', 'pin', 'user_id', 'resource_id'],
                    'order' => 'id asc',
                    'limit' => 25,
                ]
            )
            ->andReturn([
                [
                    'id' => 91,
                    'name' => 'Team Lead',
                    'work_email' => 'lead@example.com',
                    'private_email' => '',
                    'pin' => '7777',
                    'user_id' => [52, 'Team Lead'],
                    'resource_id' => [99, 'Resource'],
                ],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.users',
                'search_read',
                [[['id', '=', 52]]],
                ['fields' => ['group_ids'], 'limit' => 1]
            )
            ->andReturn([
                ['group_ids' => [3]],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'res.groups',
                'search_read',
                [[['id', 'in', [3]]]],
                ['fields' => ['name', 'privilege_id']]
            )
            ->andReturn([
                ['name' => 'User: Read his own attendances', 'privilege_id' => [2, 'Attendances']],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.employee',
                'search_count',
                [[['parent_id.user_id', '=', 52]]]
            )
            ->andReturn(2);

        $service = new OdooAuthService($serviceAccount);
        $profile = $service->authenticate('lead@example.com', '7777');

        $this->assertNotNull($profile);
        $this->assertTrue($profile['is_manager']);
        $this->assertSame('manager', $profile['role']);
    }

    public function test_it_authenticates_regular_employees_even_without_a_linked_odoo_user_account(): void
    {
        $serviceAccount = $this->mock(OdooServiceAccount::class);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.employee',
                'fields_get',
                [],
                ['attributes' => ['string', 'type', 'relation']]
            )
            ->andReturn([
                'work_email' => ['type' => 'char'],
                'private_email' => ['type' => 'char'],
                'pin' => ['type' => 'char'],
                'user_id' => ['type' => 'many2one'],
                'resource_id' => ['type' => 'many2one'],
                'active' => ['type' => 'boolean'],
            ]);

        $serviceAccount->shouldReceive('executeKw')
            ->once()
            ->with(
                'hr.employee',
                'search_read',
                [[
                    ['work_email', 'ilike', 'employee@example.com'],
                    ['active', '=', true],
                ]],
                [
                    'fields' => ['id', 'name', 'work_email', 'private_email', 'pin', 'user_id', 'resource_id'],
                    'order' => 'id asc',
                    'limit' => 25,
                ]
            )
            ->andReturn([
                [
                    'id' => 35,
                    'name' => 'Odoo Employee',
                    'work_email' => 'employee@example.com',
                    'private_email' => '',
                    'pin' => '2468',
                    'user_id' => false,
                    'resource_id' => [44, 'Resource'],
                ],
            ]);

        $service = new OdooAuthService($serviceAccount);
        $profile = $service->authenticate('employee@example.com', '2468');

        $this->assertNotNull($profile);
        $this->assertNull($profile['odoo_user_id']);
        $this->assertSame(35, $profile['odoo_employee_id']);
        $this->assertFalse($profile['is_manager']);
        $this->assertSame('user', $profile['role']);
    }
}
