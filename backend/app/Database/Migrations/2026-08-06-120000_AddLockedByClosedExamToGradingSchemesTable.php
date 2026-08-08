<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-005-examination-module-scope-decisions.md §10 — additive
 * column so Academic can answer GradingSchemeService::updateGradingScheme's
 * immutability check without depending on Examination (would cycle back
 * against Examination's own dependency on Academic). Examination sets this
 * flag by calling GradingSchemeService::lockSchemeReferencedByClosedExam
 * when it closes an Exam — the dependency stays one-way.
 */
class AddLockedByClosedExamToGradingSchemesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('grading_schemes', [
            'locked_by_closed_exam' => [
                'type'    => 'BOOLEAN',
                'default' => false,
                'after'   => 'grade_band_json',
            ],
        ]);
    }

    public function down(): void
    {
        /** @var \CodeIgniter\Database\BaseConnection $db */
        $db = $this->db;

        if ($db->tableExists('grading_schemes')) {
            $this->forge->dropColumn('grading_schemes', 'locked_by_closed_exam');
        }
    }
}
