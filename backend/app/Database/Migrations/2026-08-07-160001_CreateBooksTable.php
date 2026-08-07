<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/library/Phase-1-Domain-Model.md — ENT-LIB-001.
 */
class CreateBooksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'book_id'        => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'barcode'        => ['type' => 'VARCHAR', 'constraint' => 30],
            'title'          => ['type' => 'VARCHAR', 'constraint' => 200],
            'author'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'classification' => [
                'type'       => 'ENUM',
                'constraint' => ['Circulating', 'Reference'],
                'default'    => 'Circulating',
            ],
            'is_available' => ['type' => 'BOOLEAN', 'default' => true],
            'created_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'is_deleted'   => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('book_id', true);
        $this->forge->addUniqueKey('barcode', 'uq_books_barcode');

        $this->forge->createTable('books', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('books', true);
    }
}
