<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGeographicMasterTables extends Migration
{
    public function up(): void
    {
        // 1. Create geo_states table
        $this->forge->addField([
            'state_id'   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'code'       => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('state_id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('geo_states', true);

        // 2. Create geo_districts table
        $this->forge->addField([
            'district_id'=> ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'state_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('district_id', true);
        $this->forge->addUniqueKey(['state_id', 'name']);
        $this->forge->addForeignKey('state_id', 'geo_states', 'state_id', 'CASCADE', 'RESTRICT', 'fk_geo_districts_states');
        $this->forge->createTable('geo_districts', true);

        // 3. Create geo_blocks table
        $this->forge->addField([
            'block_id'   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'district_id'=> ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'is_deleted' => ['type' => 'BOOLEAN', 'default' => false],
            'deleted_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('block_id', true);
        $this->forge->addUniqueKey(['district_id', 'name']);
        $this->forge->addForeignKey('district_id', 'geo_districts', 'district_id', 'CASCADE', 'RESTRICT', 'fk_geo_blocks_districts');
        $this->forge->createTable('geo_blocks', true);

        // Seed default Indian geographic hierarchy data
        $states = [
            'Delhi' => [
                'New Delhi' => ['Connaught Place', 'Chanakyapuri'],
                'South Delhi' => ['Saket', 'Hauz Khas']
            ],
            'Maharashtra' => [
                'Mumbai' => ['Mumbai City', 'Mumbai Suburban'],
                'Pune' => ['Pune City', 'Haveli']
            ],
            'Karnataka' => [
                'Bengaluru Urban' => ['Bengaluru North', 'Bengaluru South'],
                'Mysuru' => ['Mysuru City', 'Nanjangud']
            ],
            'Tamil Nadu' => [
                'Chennai' => ['Chennai North', 'Chennai South'],
                'Coimbatore' => ['Coimbatore North', 'Coimbatore South']
            ],
            'Chhattisgarh' => [
                'Raipur' => ['Dharsiva', 'Tilda', 'Arang', 'Abhanpur'],
                'Sakti' => ['Sakti', 'Malkharoda', 'Jaijaipur', 'Dabhra'],
                'Sarangarh-Bilaigarh' => ['Sarangarh', 'Bilaigarh', 'Baramkela']
            ]
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($states as $stateName => $districts) {
            $this->db->table('geo_states')->insert([
                'name' => $stateName,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            $stateId = $this->db->insertID();

            foreach ($districts as $districtName => $blocks) {
                $this->db->table('geo_districts')->insert([
                    'state_id' => $stateId,
                    'name' => $districtName,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
                $districtId = $this->db->insertID();

                foreach ($blocks as $blockName) {
                    $this->db->table('geo_blocks')->insert([
                        'district_id' => $districtId,
                        'name' => $blockName,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('geo_blocks', true);
        $this->forge->dropTable('geo_districts', true);
        $this->forge->dropTable('geo_states', true);
    }
}
