<?php

namespace App\Modules\Attendance\Services;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Modules\Attendance\Models\StaffAttendanceRecordModel;
use App\Modules\Attendance\Entities\StaffPunch;
use App\Modules\Administration\Services\ConfigurationService;

class AttendanceCalculationEngineTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $namespace = 'App';

    public function testRecalculateCalculatesTotalHoursAndOvertime()
    {
        $engine = \Config\Services::attendanceCalculationEngine(false);
        $model = new StaffAttendanceRecordModel();

        // 1. Employee arrives at 08:00:00 and leaves at 18:00:00 (10 hours)
        // Standard is 8 hours, so 2 hours OT.

        // First enable overtime in configs
        $db = \Config\Database::connect();
        $db->table('configurations')->where('setting_key', 'attendance.overtime_enabled')->update(['setting_value' => 'true']);

        $punches = [
            new StaffPunch(['punch_time' => '2026-08-10 08:00:00', 'punch_type' => 'In']),
            new StaffPunch(['punch_time' => '2026-08-10 18:00:00', 'punch_type' => 'Out']),
        ];

        $engine->recalculate(1, '2026-08-10', $punches);

        $record = $model->findByEmployeeDate(1, '2026-08-10');
        $this->assertNotNull($record);
        $this->assertEquals(10.0, (float) $record->total_hours);
        $this->assertEquals(2.0, (float) $record->overtime_hours);
        $this->assertEquals('Present', $record->state);
    }
}
