<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooServiceAccount;
use App\Services\Odoo\OdooWeeklyAvailabilityService;
use Mockery\MockInterface;
use Tests\TestCase;

class OdooWeeklyAvailabilityServiceTest extends TestCase
{
    public function test_it_keeps_monday_entries_when_odoo_returns_day_key_zero(): void
    {
        $serviceAccount = $this->mock(OdooServiceAccount::class, function (MockInterface $mock): void {
            $mock->shouldReceive('executeKw')
                ->once()
                ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                    return $model === 'hr.employee.weekly.availability'
                        && $method === 'fields_get';
                })
                ->andReturn([
                    'day_of_week' => ['type' => 'selection'],
                    'availability_type' => ['type' => 'selection'],
                    'is_full_day' => ['type' => 'boolean'],
                    'start_time' => ['type' => 'float'],
                    'end_time' => ['type' => 'float'],
                    'time_range_display' => ['type' => 'char'],
                    'summary' => ['type' => 'char'],
                    'employee_id' => ['type' => 'many2one'],
                ]);

            $mock->shouldReceive('executeKw')
                ->once()
                ->withArgs(function (string $model, string $method, array $args, array $kwargs): bool {
                    return $model === 'hr.employee.weekly.availability'
                        && $method === 'search_read'
                        && $args[0][0][0] === 'employee_id'
                        && $args[0][0][2] === 35;
                })
                ->andReturn([
                    [
                        'id' => 11,
                        'day_of_week' => '0',
                        'availability_type' => 'unavailable',
                        'is_full_day' => false,
                        'start_time' => 14.25,
                        'end_time' => 14.5,
                        'time_range_display' => '14:15 to 14:30',
                        'summary' => 'Monday | Unavailable | 14:15 to 14:30',
                    ],
                ]);
        });

        $service = new OdooWeeklyAvailabilityService($serviceAccount);
        $user = new User(['odoo_employee_id' => 35]);

        $pageData = $service->getAvailabilityPageData($user);

        $this->assertSame(1, $pageData['summary']['configured_days']);
        $this->assertSame(1, $pageData['summary']['total_rules']);
        $this->assertSame('Monday', $pageData['days'][0]['label']);
        $this->assertSame(1, $pageData['days'][0]['entry_count']);
        $this->assertSame('Unavailable', $pageData['days'][0]['entries'][0]['availability_label']);
    }
}
