<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBoardFrameworkTables extends Migration
{
    public function up(): void
    {
        // 1. Create geo_boards table
        $this->forge->addField([
            'board_id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'short_name'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'board_type'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'country'            => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'India'],
            'state_applicability'=> ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            'description'        => ['type' => 'TEXT', 'null' => true],
            'created_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'         => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('board_id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->addUniqueKey('short_name');
        $this->forge->createTable('geo_boards', true);

        // 2. Create board_affiliations table
        $this->forge->addField([
            'affiliation_id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'board_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'academic_session_id'=> ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'affiliation_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'validity_start'     => ['type' => 'DATE', 'null' => true],
            'validity_end'       => ['type' => 'DATE', 'null' => true],
            'status'             => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVE'],
            'created_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'         => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('affiliation_id', true);
        $this->forge->addUniqueKey('academic_session_id', 'uq_board_affiliations_session');
        $this->forge->addForeignKey('board_id', 'geo_boards', 'board_id', 'CASCADE', 'RESTRICT', 'fk_board_aff_boards');
        $this->forge->addForeignKey('academic_session_id', 'academic_sessions', 'academic_session_id', 'CASCADE', 'RESTRICT', 'fk_board_aff_sessions');
        $this->forge->createTable('board_affiliations', true);

        // 3. Create academic_frameworks table
        $this->forge->addField([
            'framework_id'       => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'               => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'board_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'grading_scheme_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'level_divisions'    => ['type' => 'JSON', 'null' => false],
            'educational_tracks'  => ['type' => 'JSON', 'null' => true],
            'pass_criteria_json' => ['type' => 'JSON', 'null' => true],
            'grace_marks_policy' => ['type' => 'JSON', 'null' => true],
            'subject_requirements'=>['type' => 'JSON', 'null' => true],
            'language_requirements'=>['type' => 'JSON', 'null' => true],
            'version'            => ['type' => 'INT', 'default' => 1],
            'approval_status'    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'DRAFT'],
            'rejection_reason'   => ['type' => 'TEXT', 'null' => true],
            'approved_by'        => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'approved_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'         => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('framework_id', true);
        $this->forge->addForeignKey('board_id', 'geo_boards', 'board_id', 'CASCADE', 'RESTRICT', 'fk_acad_frameworks_boards');
        $this->forge->addForeignKey('grading_scheme_id', 'grading_schemes', 'grading_scheme_id', 'SET NULL', 'RESTRICT', 'fk_acad_frameworks_schemes');
        $this->forge->createTable('academic_frameworks', true);

        // 4. Create framework_session_mappings table
        $this->forge->addField([
            'mapping_id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'framework_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'academic_session_id'=> ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'created_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'         => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'         => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('mapping_id', true);
        $this->forge->addUniqueKey(['framework_id', 'academic_session_id'], 'uq_framework_session');
        $this->forge->addForeignKey('framework_id', 'academic_frameworks', 'framework_id', 'CASCADE', 'RESTRICT', 'fk_fw_session_mapping_fw');
        $this->forge->addForeignKey('academic_session_id', 'academic_sessions', 'academic_session_id', 'CASCADE', 'RESTRICT', 'fk_fw_session_mapping_session');
        $this->forge->createTable('framework_session_mappings', true);

        // Insert global configuration settings
        $now = date('Y-m-d H:i:s');
        $this->db->table('configurations')->insertBatch([
            [
                'setting_key'   => 'administration.board_approver_designation',
                'setting_value' => 'Principal',
                'data_type'     => 'String',
                'module'        => 'Administration',
                'is_editable'   => true,
                'created_at'    => $now,
                'updated_at'    => $now
            ],
            [
                'setting_key'   => 'administration.board_alternate_approver_designation',
                'setting_value' => 'Vice Principal',
                'data_type'     => 'String',
                'module'        => 'Administration',
                'is_editable'   => true,
                'created_at'    => $now,
                'updated_at'    => $now
            ]
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('framework_session_mappings', true);
        $this->forge->dropTable('academic_frameworks', true);
        $this->forge->dropTable('board_affiliations', true);
        $this->forge->dropTable('geo_boards', true);

        $this->db->table('configurations')
            ->whereIn('setting_key', ['administration.board_approver_designation', 'administration.board_alternate_approver_designation'])
            ->delete();
    }
}
