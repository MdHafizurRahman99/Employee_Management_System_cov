<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\Odoo\OdooAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'pin_code' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->mock(OdooAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->twice()->andReturn(true);
            $mock->shouldReceive('authenticate')->once()->andReturnNull();
        });

        $this->post('/login', [
            'email' => $user->email,
            'pin_code' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_odoo_users_can_authenticate_using_the_login_screen(): void
    {
        $this->mock(OdooAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('authenticate')->once()->andReturn([
                'odoo_user_id' => 27,
                'odoo_employee_id' => 35,
                'odoo_resource_id' => 44,
                'name' => 'Odoo Employee',
                'email' => 'employee@example.com',
                'role' => 'user',
            ]);
        });

        $response = $this->post('/login', [
            'email' => 'employee@example.com',
            'pin_code' => '2468',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);

        $this->assertDatabaseHas('users', [
            'email' => 'employee@example.com',
            'auth_source' => 'odoo',
            'odoo_user_id' => 27,
            'odoo_employee_id' => 35,
            'odoo_resource_id' => 44,
        ]);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
