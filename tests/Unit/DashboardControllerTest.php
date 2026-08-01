<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Services\Odoo\OdooPlanningService;
use App\Services\Odoo\OdooWeeklyAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    public function test_it_redirects_manager_users_to_the_manager_dashboard(): void
    {
        $planningService = $this->mock(OdooPlanningService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getTodayShiftForUser');
            $mock->shouldNotReceive('getUpcomingShiftsForUser');
        });
        $availabilityService = $this->mock(OdooWeeklyAvailabilityService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getAvailabilityPageData');
        });

        $user = new User([
            'auth_source' => 'odoo',
            'role' => 'manager',
            'odoo_user_id' => 27,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn (): User => $user);

        $response = (new DashboardController())->show($request, $planningService, $availabilityService);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('manager.dashboard'), $response->getTargetUrl());
    }
}
