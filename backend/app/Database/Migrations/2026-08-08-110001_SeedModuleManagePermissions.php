<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md §2 — seeds the new
 * `<module>.manage` permission strings onto every existing real role, in
 * the same migration that Phase 1's Service-layer checks start reading
 * them, so no login's effective access silently drops on deploy.
 *
 * - IT Admin: every module's `.manage` string (13 modules) — unchanged
 *   effective access (already had de facto full access via JWT-only
 *   gating before this ADR).
 * - Finance Team: `fees.manage`.
 * - HR Team: `hr_payroll.manage`.
 * - Employee: untouched — it already carries no manage string, which is
 *   exactly the restricted shape ADR-024 exists to enforce. Not touched
 *   by this migration at all.
 * - The five `SmokeTestRole*` rows: left as-is per ADR-024 §2 — test
 *   fixtures from Stage 6d/6e's manual smoke-testing, not real roles.
 *
 * The old generic `read`/`create`/`update`/`delete` strings are
 * superseded (ADR-024 §2: "replaced, not silently dropped") — IT Admin's
 * permission_set becomes exactly the 13 `.manage` strings; Finance
 * Team/HR Team's generic strings are dropped in favor of their one
 * `.manage` string, matching the new module-scoped model exactly.
 */
class SeedModuleManagePermissions extends Migration
{
    private const IT_ADMIN_PERMISSIONS = [
        'academic.manage',
        'admission.manage',
        'sis.manage',
        'examination.manage',
        'timetable.manage',
        'attendance.manage',
        'fees.manage',
        'hr_payroll.manage',
        'library.manage',
        'transport.manage',
        'communication.manage',
        'administration.manage',
        'reports.manage',
    ];

    public function up(): void
    {
        $this->applyPermissions('IT Admin', self::IT_ADMIN_PERMISSIONS);
        $this->applyPermissions('Finance Team', ['fees.manage']);
        $this->applyPermissions('HR Team', ['hr_payroll.manage']);
    }

    public function down(): void
    {
        // Restores the pre-ADR-024 generic permission sets these three
        // roles carried (see 2026-08-05-195901_CreateRolesTable.php-era
        // seed data / docs/ADR/ADR-024-systemwide-rbac-enforcement.md §2's
        // "Context" table).
        $this->applyPermissions('IT Admin', ['read', 'create', 'update', 'delete']);
        $this->applyPermissions('Finance Team', ['read', 'create']);
        $this->applyPermissions('HR Team', ['read', 'create']);
    }

    /**
     * @param list<string> $permissionSet
     */
    private function applyPermissions(string $roleName, array $permissionSet): void
    {
        $this->db->table('roles')
            ->where('role_name', $roleName)
            ->update(['permission_set' => json_encode($permissionSet)]);
    }
}
