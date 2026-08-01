<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Odoo\OdooAuthService;
use App\Services\Odoo\OdooUserSynchronizer;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\RedirectResponse;
use Mockery;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    public function test_it_uses_the_manager_dashboard_as_the_default_post_login_redirect_for_managers(): void
    {
        $request = Mockery::mock(LoginRequest::class);
        $session = Mockery::mock(Session::class);
        $authService = Mockery::mock(OdooAuthService::class);
        $synchronizer = Mockery::mock(OdooUserSynchronizer::class);

        $request->shouldReceive('authenticate')->once()->with($authService, $synchronizer);
        $request->shouldReceive('session')->andReturn($session);
        $session->shouldReceive('regenerate')->once();
        $request->shouldReceive('user')->andReturn(new User([
            'auth_source' => 'odoo',
            'role' => 'manager',
            'odoo_user_id' => 27,
        ]));

        $response = (new AuthenticatedSessionController())->store($request, $authService, $synchronizer);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.dashboard'), $response->getTargetUrl());
    }

    public function test_it_keeps_the_employee_dashboard_as_the_default_post_login_redirect_for_regular_employees(): void
    {
        $request = Mockery::mock(LoginRequest::class);
        $session = Mockery::mock(Session::class);
        $authService = Mockery::mock(OdooAuthService::class);
        $synchronizer = Mockery::mock(OdooUserSynchronizer::class);

        $request->shouldReceive('authenticate')->once()->with($authService, $synchronizer);
        $request->shouldReceive('session')->andReturn($session);
        $session->shouldReceive('regenerate')->once();
        $request->shouldReceive('user')->andReturn(new User([
            'auth_source' => 'odoo',
            'role' => 'user',
            'odoo_employee_id' => 35,
        ]));

        $response = (new AuthenticatedSessionController())->store($request, $authService, $synchronizer);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(url('/dashboard'), $response->getTargetUrl());
    }
}
