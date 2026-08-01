<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Odoo\OdooEmployeeScheduleEntryService;
use App\Services\Odoo\OdooServiceAccount;
use Carbon\Carbon;
use Mockery\MockInterface;
use Tests\TestCase;

class OdooEmployeeScheduleEntryServiceTest extends TestCase
{
    public function test_manager_range_returns_no_diary_data_when_scheduler_has_no_visible_employees(): void
    {
        $serviceAccount = $this->mock(OdooServiceAccount::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('executeKw');
        });

        $result = (new OdooEmployeeScheduleEntryService($serviceAccount))->getForManagerRange(
            Carbon::parse('2026-07-20'),
            Carbon::parse('2026-07-26'),
            []
        );

        $this->assertSame([
            'entries' => [],
            'by_employee_date' => [],
            'by_date' => [],
            'count' => 0,
        ], $result);
    }

    public function test_it_reads_employee_diary_entries_from_odoo(): void
    {
        $serviceAccount = $this->mock(OdooServiceAccount::class, function (MockInterface $mock): void {
            $mock->shouldReceive('executeKw')
                ->once()
                ->withArgs(fn (string $model, string $method): bool => $model === 'hr.employee.schedule.entry' && $method === 'fields_get')
                ->andReturn($this->fields());
            $mock->shouldReceive('executeKw')
                ->once()
                ->withArgs(function (string $model, string $method, array $args): bool {
                    return $model === 'hr.employee.schedule.entry'
                        && $method === 'search_read'
                        && $args[0][0] === ['employee_id', '=', 35];
                })
                ->andReturn([[
                    'id' => 81,
                    'employee_id' => [35, 'Ada Employee'],
                    'entry_date' => '2026-07-28',
                    'entry_type' => 'unavailable',
                    'title' => 'Medical appointment',
                    'note' => 'Available after lunch',
                    'is_full_day' => false,
                    'start_time' => 9.0,
                    'end_time' => 12.5,
                    'active' => true,
                    'write_date' => '2026-07-20 08:00:00',
                ]]);
        });
        $service = new OdooEmployeeScheduleEntryService($serviceAccount);
        $user = new User(['odoo_employee_id' => 35]);

        $entries = $service->getForUserMonth($user, Carbon::parse('2026-07-01'));

        $this->assertCount(1, $entries);
        $this->assertSame('Medical appointment', $entries[0]['title']);
        $this->assertSame('09:00 AM – 12:30 PM', $entries[0]['time_label']);
        $this->assertSame(35, $entries[0]['employee_id']);
    }

    public function test_it_creates_the_diary_entry_in_odoo(): void
    {
        $serviceAccount = $this->mock(OdooServiceAccount::class, function (MockInterface $mock): void {
            $mock->shouldReceive('executeKw')
                ->once()
                ->withArgs(fn (string $model, string $method): bool => $model === 'hr.employee.schedule.entry' && $method === 'fields_get')
                ->andReturn($this->fields());
            $mock->shouldReceive('executeKw')
                ->once()
                ->with('hr.employee.schedule.entry', 'create', [[
                    'employee_id' => 35,
                    'entry_date' => '2026-07-28',
                    'entry_type' => 'unavailable',
                    'title' => 'Medical appointment',
                    'note' => 'Available after lunch',
                    'is_full_day' => false,
                    'start_time' => 9.0,
                    'end_time' => 12.5,
                    'active' => true,
                ]])
                ->andReturn(81);
        });
        $service = new OdooEmployeeScheduleEntryService($serviceAccount);

        $entryId = $service->createEntry(new User(['odoo_employee_id' => 35]), [
            'entry_date' => '2026-07-28',
            'entry_type' => 'unavailable',
            'title' => 'Medical appointment',
            'notes' => 'Available after lunch',
            'is_all_day' => false,
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $this->assertSame(81, $entryId);
    }

    /** @return array<string,array<string,string>> */
    private function fields(): array
    {
        return [
            'employee_id' => ['type' => 'many2one'],
            'entry_date' => ['type' => 'date'],
            'entry_type' => ['type' => 'selection'],
            'title' => ['type' => 'char'],
            'note' => ['type' => 'text'],
            'is_full_day' => ['type' => 'boolean'],
            'start_time' => ['type' => 'float'],
            'end_time' => ['type' => 'float'],
            'time_range_display' => ['type' => 'char'],
            'active' => ['type' => 'boolean'],
            'write_date' => ['type' => 'datetime'],
        ];
    }
}
