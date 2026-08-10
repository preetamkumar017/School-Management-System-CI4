<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateAcademicMasterTables extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();
        $db->resetDataCache();

        // 1. Create subject_categories table if not exists
        if (!$db->tableExists('subject_categories')) {
            $this->forge->addField([
                'subject_category_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'category_name'        => ['type' => 'VARCHAR', 'constraint' => 50],
                'category_code'        => ['type' => 'VARCHAR', 'constraint' => 20],
                'description'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
                'is_deleted'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'deleted_by'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('subject_category_id', true);
            $this->forge->addUniqueKey('category_name', 'uq_subject_categories_name');
            $this->forge->addUniqueKey('category_code', 'uq_subject_categories_code');
            $this->forge->createTable('subject_categories', true);

            // Seed some default subject categories
            $db->table('subject_categories')->insertBatch([
                ['category_name' => 'Core', 'category_code' => 'CORE', 'description' => 'Mandatory foundational academic subjects', 'created_at' => date('Y-m-d H:i:s')],
                ['category_name' => 'Elective', 'category_code' => 'ELECTIVE', 'description' => 'Optional subjects chosen by students', 'created_at' => date('Y-m-d H:i:s')],
                ['category_name' => 'Co-curricular', 'category_code' => 'CO_CURRICULAR', 'description' => 'Non-academic physical, creative or life skills subjects', 'created_at' => date('Y-m-d H:i:s')],
                ['category_name' => 'Language', 'category_code' => 'LANGUAGE', 'description' => 'Language and communication subjects', 'created_at' => date('Y-m-d H:i:s')],
                ['category_name' => 'Optional', 'category_code' => 'OPTIONAL', 'description' => 'Additional non-mandatory subjects', 'created_at' => date('Y-m-d H:i:s')],
            ]);
        }

        // 2. Modify subjects table to add category reference, language flag, and stream applicability
        if (!$db->fieldExists('subject_category_id', 'subjects')) {
            $streamType = $db->DBDriver === 'MySQLi' ? 'ENUM' : 'VARCHAR';
            $streamConstraint = $db->DBDriver === 'MySQLi' ? ['ALL', 'SCIENCE', 'COMMERCE', 'ARTS', 'NONE'] : 20;

            if ($db->DBDriver === 'MySQLi') {
                $this->forge->addColumn('subjects', [
                    'subject_category_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'subject_code'],
                    'is_language_subject' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'subject_category_id'],
                    'stream_applicability' => [
                        'type'       => $streamType,
                        'constraint' => $streamConstraint,
                        'default'    => 'ALL',
                        'after'      => 'is_language_subject',
                    ],
                ]);
                $subjectsTable = $db->prefixTable('subjects');
                $categoriesTable = $db->prefixTable('subject_categories');
                $db->query("ALTER TABLE {$subjectsTable} ADD CONSTRAINT fk_subjects_subject_categories FOREIGN KEY (subject_category_id) REFERENCES {$categoriesTable}(subject_category_id) ON DELETE RESTRICT ON UPDATE RESTRICT");
            } else {
                $this->forge->addColumn('subjects', [
                    'subject_category_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                ]);
                $this->forge->addColumn('subjects', [
                    'is_language_subject' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                ]);
                $this->forge->addColumn('subjects', [
                    'stream_applicability' => [
                        'type'       => $streamType,
                        'constraint' => $streamConstraint,
                        'default'    => 'ALL',
                    ],
                ]);
            }
        }

        // 3. Re-create class_subject_map table with new columns if dropped or modify if exists
        $this->forge->dropTable('class_subject_map', true);
        $this->forge->addField([
            'academic_session_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'class_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'subject_id'          => ['type' => 'BIGINT', 'unsigned' => true],
            'is_mandatory'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->forge->addPrimaryKey(['academic_session_id', 'class_id', 'subject_id']);
        $this->forge->addForeignKey('academic_session_id', 'academic_sessions', 'academic_session_id', 'RESTRICT', 'RESTRICT', 'fk_class_subject_map_academic_sessions');
        $this->forge->addForeignKey('class_id', 'classes', 'class_id', 'RESTRICT', 'RESTRICT', 'fk_class_subject_map_classes');
        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', 'RESTRICT', 'RESTRICT', 'fk_class_subject_map_subjects');
        $this->forge->createTable('class_subject_map', true);

        // 4. Create class_board_framework_map table
        if (!$db->tableExists('class_board_framework_map')) {
            $this->forge->addField([
                'class_board_map_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'academic_session_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'class_id'            => ['type' => 'BIGINT', 'unsigned' => true],
                'framework_id'        => ['type' => 'INT', 'unsigned' => true],
                'created_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'created_at'          => ['type' => 'DATETIME', 'null' => true],
                'updated_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'updated_at'          => ['type' => 'DATETIME', 'null' => true],
                'is_deleted'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'deleted_by'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('class_board_map_id', true);
            $this->forge->addUniqueKey(['academic_session_id', 'class_id'], 'uq_class_board_map_session_class');
            $this->forge->addForeignKey('academic_session_id', 'academic_sessions', 'academic_session_id', 'RESTRICT', 'RESTRICT', 'fk_class_board_map_sessions');
            $this->forge->addForeignKey('class_id', 'classes', 'class_id', 'RESTRICT', 'RESTRICT', 'fk_class_board_map_classes');
            $this->forge->addForeignKey('framework_id', 'academic_frameworks', 'framework_id', 'RESTRICT', 'RESTRICT', 'fk_class_board_map_frameworks');
            $this->forge->createTable('class_board_framework_map', true);
        }

        // 5. Create teacher_class_subject_map table
        if (!$db->tableExists('teacher_class_subject_map')) {
            $this->forge->addField([
                'teacher_class_subject_map_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'academic_session_id'          => ['type' => 'BIGINT', 'unsigned' => true],
                'class_id'                     => ['type' => 'BIGINT', 'unsigned' => true],
                'section_id'                   => ['type' => 'BIGINT', 'unsigned' => true],
                'subject_id'                   => ['type' => 'BIGINT', 'unsigned' => true],
                'employee_id'                  => ['type' => 'BIGINT', 'unsigned' => true],
                'created_by'                   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'created_at'                   => ['type' => 'DATETIME', 'null' => true],
                'updated_by'                   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'updated_at'                   => ['type' => 'DATETIME', 'null' => true],
                'is_deleted'                   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'deleted_by'                   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'deleted_at'                   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('teacher_class_subject_map_id', true);
            $this->forge->addUniqueKey(['academic_session_id', 'section_id', 'subject_id'], 'uq_teacher_map_session_section_subject');
            $this->forge->addForeignKey('academic_session_id', 'academic_sessions', 'academic_session_id', 'RESTRICT', 'RESTRICT', 'fk_teacher_map_sessions');
            $this->forge->addForeignKey('class_id', 'classes', 'class_id', 'RESTRICT', 'RESTRICT', 'fk_teacher_map_classes');
            $this->forge->addForeignKey('section_id', 'sections', 'section_id', 'RESTRICT', 'RESTRICT', 'fk_teacher_map_sections');
            $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', 'RESTRICT', 'RESTRICT', 'fk_teacher_map_subjects');
            $this->forge->createTable('teacher_class_subject_map', true);
        }
    }

    public function down(): void
    {
        $db = \Config\Database::connect();
        $db->resetDataCache();

        $this->forge->dropTable('teacher_class_subject_map', true);
        $this->forge->dropTable('class_board_framework_map', true);
        $this->forge->dropTable('class_subject_map', true);

        // Recreate original class_subject_map
        $this->forge->addField([
            'class_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'subject_id' => ['type' => 'BIGINT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey(['class_id', 'subject_id']);
        $this->forge->addForeignKey('class_id', 'classes', 'class_id', 'RESTRICT', 'RESTRICT', 'fk_class_subject_map_classes');
        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', 'RESTRICT', 'RESTRICT', 'fk_class_subject_map_subjects');
        $this->forge->createTable('class_subject_map', true);

        $db = \Config\Database::connect();
        if ($db->fieldExists('subject_category_id', 'subjects')) {
            if ($db->DBDriver === 'MySQLi') {
                try {
                    $subjectsTable = $db->prefixTable('subjects');
                    $db->query("ALTER TABLE {$subjectsTable} DROP FOREIGN KEY fk_subjects_subject_categories");
                } catch (\Throwable $e) {
                    // Suppress if constraint doesn't exist
                }
            }
            try {
                $this->forge->dropColumn('subjects', ['subject_category_id', 'is_language_subject', 'stream_applicability']);
            } catch (\Throwable $e) {
                // Suppress if columns don't exist or already dropped
            }
        }

        $this->forge->dropTable('subject_categories', true);
    }
}
