<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — supporting table,
 * not an Appendix-G business entity. Exists to satisfy the Company
 * Development Standard §9 requirement that refresh-token state be
 * server-tracked for explicit revocation.
 */
class CreateRefreshTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'refresh_token_id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'          => ['type' => 'BIGINT', 'unsigned' => true],
            'token_hash'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'expires_at'       => ['type' => 'DATETIME'],
            'revoked_at'       => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME'],
        ]);

        $this->forge->addKey('refresh_token_id', true);
        $this->forge->addKey('user_id', false, false, 'idx_refresh_tokens_user_id');
        $this->forge->addKey('token_hash', false, false, 'idx_refresh_tokens_token_hash');

        $this->forge->addForeignKey('user_id', 'users', 'user_id', 'RESTRICT', 'RESTRICT', 'fk_refresh_tokens_users');

        $this->forge->createTable('refresh_tokens', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('refresh_tokens', true);
    }
}
