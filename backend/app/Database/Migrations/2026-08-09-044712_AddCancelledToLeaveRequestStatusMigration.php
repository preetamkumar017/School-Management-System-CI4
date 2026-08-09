<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCancelledToLeaveRequestStatusMigration extends Migration
{
    public function up(): void
    {
        $fields = [
            'status' => [
                'type'       => "ENUM('Pending','Approved','Rejected','Cancelled')",
                'null'       => false,
                'default'    => 'Pending',
            ],
        ];
        $this->forge->modifyColumn('leave_requests', $fields);
    }

    public function down(): void
    {
        $fields = [
            'status' => [
                'type'       => "ENUM('Pending','Approved','Rejected')",
                'null'       => false,
                'default'    => 'Pending',
            ],
        ];
        $this->forge->modifyColumn('leave_requests', $fields);
    }
}
