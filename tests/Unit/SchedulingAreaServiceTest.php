<?php

namespace Tests\Unit;

use App\Services\Odoo\OdooScheduleRecord;
use App\Services\Scheduling\SchedulingAreaService;
use Tests\TestCase;

class SchedulingAreaServiceTest extends TestCase
{
    public function test_it_maps_roles_to_areas_and_marks_coverage_gaps(): void
    {
        $area = new OdooScheduleRecord(['id'=>4,'company_id' => 2, 'odoo_role_id' => 9, 'name' => 'Welcome Desk', 'color' => '#176b5b','coverageRequirements'=>collect([
            new OdooScheduleRecord(['weekday' => 0, 'minimum_people' => 2]),
            new OdooScheduleRecord(['weekday' => 1, 'minimum_people' => 1]),
        ])]);
        $board = ['rows' => [[
            'company_id' => 2, 'role_id' => 9, 'role' => 'Reception',
            'cells' => [
                '2026-07-13' => ['date_value' => '2026-07-13', 'assigned_count' => 1],
                '2026-07-14' => ['date_value' => '2026-07-14', 'assigned_count' => 1],
            ],
        ]]];

        $result = (new SchedulingAreaService())->applyCoverage($board, [$area]);

        $this->assertSame('Welcome Desk', $result['rows'][0]['area_name']);
        $this->assertSame('under', $result['rows'][0]['cells']['2026-07-13']['coverage_status']);
        $this->assertSame(1, $result['rows'][0]['cells']['2026-07-13']['coverage_gap']);
        $this->assertSame('met', $result['rows'][0]['cells']['2026-07-14']['coverage_status']);
        $this->assertSame(1, $result['coverage_summary']['under_cells']);
        $this->assertSame(1, $result['coverage_summary']['missing_people']);
    }
}
