<?php

namespace Tests\Unit;

use App\Http\Controllers\EmployeeAvailabilityController;
use App\Models\User;
use App\Services\Odoo\OdooException;
use App\Services\Odoo\OdooWeeklyAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mockery\MockInterface;
use Tests\TestCase;

class EmployeeAvailabilityControllerTest extends TestCase
{
    public function test_it_returns_weekly_availability_page_data_for_the_view(): void
    {
        $this->mock(OdooWeeklyAvailabilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAvailabilityPageData')
                ->once()
                ->withArgs(fn (User $user): bool => $user->odoo_employee_id === 35)
                ->andReturn([
                    'days' => [
                        [
                            'key' => '0',
                            'label' => 'Monday',
                            'short_label' => 'Mon',
                            'entries' => [[
                                'id' => 11,
                                'availability_type' => 'available',
                                'availability_label' => 'Available',
                                'availability_class' => 'success',
                                'is_full_day' => true,
                                'time_label' => 'Full day',
                            ]],
                            'entry_count' => 1,
                            'has_rules' => true,
                            'status_label' => 'Open all day',
                            'status_class' => 'success',
                        ],
                    ],
                    'summary' => [
                        'configured_days' => 1,
                        'total_rules' => 1,
                        'available_rules' => 1,
                        'unavailable_rules' => 0,
                        'full_day_rules' => 1,
                    ],
                    'entries' => [[
                        'id' => 11,
                    ]],
                ]);
        });

        $view = $this->controller()->index(
            $this->requestWithUser('GET', '/employee/availability', [], $this->employeeUser()),
            app(OdooWeeklyAvailabilityService::class)
        );

        $data = $view->getData();

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('admin.employee-availability.index', $view->getName());
        $this->assertSame(1, $data['availabilitySummary']['configured_days']);
        $this->assertCount(1, $data['availabilityDays']);
        $this->assertTrue($data['hasAvailabilityIdentity']);
    }

    public function test_it_exposes_availability_page_errors_without_throwing(): void
    {
        $this->mock(OdooWeeklyAvailabilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAvailabilityPageData')
                ->once()
                ->andThrow(new OdooException('Weekly availability is temporarily unavailable.'));
        });

        $view = $this->controller()->index(
            $this->requestWithUser('GET', '/employee/availability', [], $this->employeeUser()),
            app(OdooWeeklyAvailabilityService::class)
        );

        $this->assertSame('Weekly availability is temporarily unavailable.', $view->getData()['odooAvailabilityError']);
    }

    public function test_it_redirects_after_a_successful_availability_creation(): void
    {
        $this->mock(OdooWeeklyAvailabilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createAvailability')
                ->once()
                ->withArgs(function (User $user, array $payload): bool {
                    return $user->odoo_employee_id === 35
                        && $payload['day_of_week'] === '0'
                        && $payload['availability_type'] === 'available'
                        && $payload['is_full_day'] === false
                        && $payload['start_time'] === 9.0
                        && $payload['end_time'] === 17.0;
                })
                ->andReturn(101);
        });

        $response = $this->controller()->store(
            $this->requestWithUser('POST', '/employee/availability', [
                'day_of_week' => '0',
                'availability_type' => 'available',
                'is_full_day' => '0',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ], $this->employeeUser()),
            app(OdooWeeklyAvailabilityService::class)
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.availability.index'), $response->getTargetUrl());
    }

    public function test_it_redirects_after_a_successful_availability_update(): void
    {
        $this->mock(OdooWeeklyAvailabilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('updateAvailability')
                ->once()
                ->withArgs(function (User $user, int $availabilityId, array $payload): bool {
                    return $user->odoo_employee_id === 35
                        && $availabilityId === 11
                        && $payload['day_of_week'] === '2'
                        && $payload['availability_type'] === 'unavailable'
                        && $payload['is_full_day'] === true;
                });
        });

        $response = $this->controller()->update(
            $this->requestWithUser('POST', '/employee/availability/11/update', [
                'availability_entry_id' => '11',
                'day_of_week' => '2',
                'availability_type' => 'unavailable',
                'is_full_day' => '1',
            ], $this->employeeUser()),
            app(OdooWeeklyAvailabilityService::class),
            11
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.availability.index'), $response->getTargetUrl());
    }

    public function test_it_redirects_after_a_successful_availability_delete(): void
    {
        $this->mock(OdooWeeklyAvailabilityService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deleteAvailability')
                ->once()
                ->withArgs(fn (User $user, int $availabilityId): bool => $user->odoo_employee_id === 35 && $availabilityId === 11);
        });

        $response = $this->controller()->destroy(
            $this->requestWithUser('POST', '/employee/availability/11/delete', [], $this->employeeUser()),
            app(OdooWeeklyAvailabilityService::class),
            11
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('employee.availability.index'), $response->getTargetUrl());
    }

    private function controller(): EmployeeAvailabilityController
    {
        return new EmployeeAvailabilityController();
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
