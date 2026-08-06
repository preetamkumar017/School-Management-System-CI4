<?php

declare(strict_types=1);

namespace App\Core\RBAC;

/**
 * Pure permission-set check, no database access — reads the permission
 * list straight from the already-validated JWT payload (Company
 * Development Standard §9: a Role permission change takes effect on the
 * user's next login/refresh, not retroactively on an already-issued
 * access token — see docs/design/administration/Phase-4).
 */
class PermissionChecker
{
    /**
     * @param list<string> $permissionSet
     */
    public static function has(array $permissionSet, string $permission): bool
    {
        return in_array($permission, $permissionSet, true);
    }

    /**
     * @param list<string> $permissionSet
     * @param list<string> $required
     */
    public static function hasAny(array $permissionSet, array $required): bool
    {
        return array_intersect($required, $permissionSet) !== [];
    }

    /**
     * @param list<string> $permissionSet
     * @param list<string> $required
     */
    public static function hasAll(array $permissionSet, array $required): bool
    {
        return array_intersect($required, $permissionSet) === $required;
    }
}
