<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserRoleHelpersTest extends TestCase
{
    public function test_manager_helper_methods_distinguish_odoo_managers_from_regular_employees(): void
    {
        $manager = new User([
            'auth_source' => 'odoo',
            'role' => 'manager',
            'odoo_user_id' => 27,
        ]);

        $employee = new User([
            'auth_source' => 'odoo',
            'role' => 'user',
            'odoo_employee_id' => 28,
        ]);

        $admin = new User([
            'auth_source' => 'local',
            'role' => 'admin',
        ]);

        $this->assertTrue($manager->isOdooUser());
        $this->assertTrue($manager->isOdooManager());
        $this->assertTrue($manager->isManagerLike());

        $this->assertTrue($employee->isOdooUser());
        $this->assertFalse($employee->isOdooManager());
        $this->assertFalse($employee->isManagerLike());

        $this->assertFalse($admin->isOdooUser());
        $this->assertFalse($admin->isOdooManager());
        $this->assertTrue($admin->isManagerLike());
    }
}
