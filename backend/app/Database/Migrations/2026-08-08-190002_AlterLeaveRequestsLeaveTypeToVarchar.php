<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Converts leave_requests.leave_type from ENUM to VARCHAR(20)
 * so that dynamic school-specific leave type codes can be stored.
 * Existing data (CL, SL, EL, ML, DL, LWP) is preserved as-is.
 */
class AlterLeaveRequestsLeaveTypeToVarchar extends Migration
{
    public function up(): void
    {
        // MySQL: MODIFY COLUMN preserves existing data
        $this->db->query(
            "ALTER TABLE `leave_requests` MODIFY COLUMN `leave_type` VARCHAR(20) NOT NULL"
        );
    }

    public function down(): void
    {
        // Revert only if all values are one of the original ENUM members
        $this->db->query(
            "ALTER TABLE `leave_requests` MODIFY COLUMN `leave_type` ENUM('CL','SL','EL','ML','LWP','DL') NULL"
        );
    }
}
