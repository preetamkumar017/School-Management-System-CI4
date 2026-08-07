<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/communication/Phase-1-Domain-Model.md — ENT-COM-001.
 * Includes the decided additive status column (ADR-010 §1).
 */
class CreateCircularsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'circular_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'author_id'       => ['type' => 'BIGINT', 'unsigned' => true],
            'post_type'       => [
                'type'       => 'ENUM',
                'constraint' => ['Homework', 'Circular', 'Announcement'],
            ],
            'title'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'body'            => ['type' => 'TEXT'],
            'target_audience' => ['type' => 'VARCHAR', 'constraint' => 50],
            'posted_at'       => ['type' => 'DATETIME'],
            'status'          => [
                'type'       => 'ENUM',
                'constraint' => ['Posted', 'Retracted'],
                'default'    => 'Posted',
            ],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('circular_id', true);
        $this->forge->addKey('target_audience', false, false, 'idx_circulars_target_audience');
        $this->forge->addKey('posted_at', false, false, 'idx_circulars_posted_at');

        // author_id is a cross-module reference to Administration's
        // users table — no DB-level FK.

        $this->forge->createTable('circulars', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('circulars', true);
    }
}
