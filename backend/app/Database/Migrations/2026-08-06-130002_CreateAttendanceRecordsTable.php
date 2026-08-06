<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/attendance/Phase-1-Domain-Model.md — ENT-ATT-001.
 */
class CreateAttendanceRecordsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'attendance_record_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'student_id'           => ['type' => 'BIGINT', 'unsigned' => true],
            'timetable_entry_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'attendance_date'      => ['type' => 'DATE'],
            'state'                => ['type' => 'ENUM', 'constraint' => ['PRESENT', 'ABSENT', 'LATE']],
            'marked_by'            => ['type' => 'BIGINT', 'unsigned' => true],
            'is_locked'            => ['type' => 'BOOLEAN', 'default' => false],
            'created_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'           => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('attendance_record_id', true);
        $this->forge->addUniqueKey(
            ['student_id', 'timetable_entry_id', 'attendance_date'],
            'uq_attendance_records_student_entry_date',
        );
        $this->forge->addKey('timetable_entry_id', false, false, 'idx_attendance_records_timetable_entry_id');

        // student_id (SIS), timetable_entry_id (Timetable), marked_by
        // (Administration) are all cross-module references — no DB-level
        // FK across module boundaries.

        $this->forge->createTable('attendance_records', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('attendance_records', true);
    }
}
