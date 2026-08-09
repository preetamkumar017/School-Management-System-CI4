<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSandwichRuleConfigKey extends Migration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('configurations')->insert([
            'setting_key'   => 'hr_payroll.sandwich_rule_enabled',
            'setting_value' => 'true',
            'data_type'     => 'Boolean',
            'module'        => 'HrPayroll',
            'is_editable'   => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function down(): void
    {
        $this->db->table('configurations')->where('setting_key', 'hr_payroll.sandwich_rule_enabled')->delete();
    }
}
