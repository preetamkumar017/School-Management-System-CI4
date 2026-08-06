<?php

declare(strict_types=1);

namespace Config;

use App\Core\Auth\JwtManager;
use App\Modules\Administration\Models\AuditLogModel;
use App\Modules\Administration\Models\RefreshTokenModel;
use App\Modules\Administration\Models\RoleModel;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\AuthService;
use App\Modules\Administration\Services\RoleService;
use App\Modules\Administration\Services\UserService;
use CodeIgniter\Config\BaseService;

/**
 * CI4 has no auto-wiring container — Service classes are constructed here
 * once, manually, and resolved elsewhere via service('name'). This is the
 * one place App\Modules\Administration's Services get their Model
 * dependencies wired up.
 */
class Services extends BaseService
{
    public static function jwtManager(bool $getShared = true): JwtManager
    {
        if ($getShared) {
            return static::getSharedInstance('jwtManager');
        }

        return new JwtManager(config(Auth::class));
    }

    public static function auditService(bool $getShared = true): AuditService
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        return new AuditService(new AuditLogModel());
    }

    public static function authService(bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        return new AuthService(
            new UserModel(),
            new RoleModel(),
            new RefreshTokenModel(),
            static::jwtManager(),
        );
    }

    public static function userService(bool $getShared = true): UserService
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        return new UserService(new UserModel(), static::auditService(), static::authService());
    }

    public static function roleService(bool $getShared = true): RoleService
    {
        if ($getShared) {
            return static::getSharedInstance('roleService');
        }

        return new RoleService(new RoleModel(), static::auditService());
    }
}
