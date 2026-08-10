<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSchoolProfilesTable extends Migration
{
    public function up(): void
    {
        // 1. Alter documents table to add 'SchoolProfile' to owner_type ENUM
        $this->db->query("ALTER TABLE documents MODIFY COLUMN owner_type ENUM('Application', 'Student', 'Invoice', 'ReportCard', 'PayrollRun', 'SchoolProfile') NOT NULL");

        // 2. Create school_profiles table
        $this->forge->addField([
            'school_id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'school_name'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'short_name'                => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'school_code'               => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'address_line1'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'address_line2'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'city'                      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'state'                     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'district'                  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'block'                     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'pin_code'                  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false],
            'country'                   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false, 'default' => 'India'],
            'school_type'               => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'school_levels_offered'     => ['type' => 'JSON', 'null' => false],
            'management_type'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'medium_of_instruction'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'residential_status'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'board_affiliation_ref'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'board_affiliation_number'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'recognition_number'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'affiliation_validity_start'=> ['type' => 'DATE', 'null' => true],
            'affiliation_validity_end'  => ['type' => 'DATE', 'null' => true],
            'udise_code'                => ['type' => 'VARCHAR', 'constraint' => 11, 'null' => true],
            'state_board_code'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'principal_employee_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'principal_name'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'principal_email'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'principal_phone'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'school_email'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'school_phone'              => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'emergency_contact'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'primary_logo_id'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'document_logo_id'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'document_header_text'      => ['type' => 'TEXT', 'null' => true],
            'document_footer_text'      => ['type' => 'TEXT', 'null' => true],
            'created_by'                => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'                => ['type' => 'DATETIME', 'null' => true],
            'updated_by'                => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'                => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'                => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'                => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'                => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('school_id', true);
        $this->forge->addForeignKey('principal_employee_id', 'employees', 'employee_id', 'SET NULL', 'RESTRICT', 'fk_school_profiles_employees');
        $this->forge->addForeignKey('primary_logo_id', 'documents', 'document_id', 'SET NULL', 'RESTRICT', 'fk_school_profiles_primary_logo');
        $this->forge->addForeignKey('document_logo_id', 'documents', 'document_id', 'SET NULL', 'RESTRICT', 'fk_school_profiles_document_logo');

        $this->forge->createTable('school_profiles', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('school_profiles', true);
        $this->db->table('documents')->where('owner_type', 'SchoolProfile')->delete();
        $this->db->query("ALTER TABLE documents MODIFY COLUMN owner_type ENUM('Application', 'Student', 'Invoice', 'ReportCard', 'PayrollRun') NOT NULL");
    }
}
