<?php

declare(strict_types=1);

namespace Config;

use App\Core\Auth\JwtManager;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSubjectMapModel;
use App\Modules\Academic\Models\GradingSchemeModel;
use App\Modules\Academic\Models\SectionModel;
use App\Modules\Academic\Models\SubjectModel;
use App\Modules\Academic\Services\AcademicSessionService;
use App\Modules\Academic\Services\ClassService;
use App\Modules\Academic\Services\ClassSubjectMapService;
use App\Modules\Academic\Services\GradingSchemeService;
use App\Modules\Academic\Services\SectionService;
use App\Modules\Academic\Services\SubjectService;
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

    public static function academicSessionService(bool $getShared = true): AcademicSessionService
    {
        if ($getShared) {
            return static::getSharedInstance('academicSessionService');
        }

        return new AcademicSessionService(new AcademicSessionModel(), static::auditService());
    }

    public static function classService(bool $getShared = true): ClassService
    {
        if ($getShared) {
            return static::getSharedInstance('classService');
        }

        return new ClassService(new ClassModel(), static::auditService());
    }

    public static function sectionService(bool $getShared = true): SectionService
    {
        if ($getShared) {
            return static::getSharedInstance('sectionService');
        }

        return new SectionService(new SectionModel(), new ClassModel(), static::auditService());
    }

    public static function subjectService(bool $getShared = true): SubjectService
    {
        if ($getShared) {
            return static::getSharedInstance('subjectService');
        }

        return new SubjectService(new SubjectModel(), static::auditService());
    }

    public static function gradingSchemeService(bool $getShared = true): GradingSchemeService
    {
        if ($getShared) {
            return static::getSharedInstance('gradingSchemeService');
        }

        return new GradingSchemeService(new GradingSchemeModel(), static::auditService());
    }

    public static function classSubjectMapService(bool $getShared = true): ClassSubjectMapService
    {
        if ($getShared) {
            return static::getSharedInstance('classSubjectMapService');
        }

        return new ClassSubjectMapService(
            new ClassSubjectMapModel(),
            new ClassModel(),
            new SubjectModel(),
            static::auditService(),
        );
    }
}
