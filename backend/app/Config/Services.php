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
use App\Modules\Admission\Models\ApplicationModel;
use App\Modules\Admission\Models\SeatAllocationModel;
use App\Modules\Admission\Services\ApplicationService;
use App\Modules\Admission\Services\SeatAllocationService;
use App\Modules\Administration\Models\AuditLogModel;
use App\Modules\Administration\Models\RefreshTokenModel;
use App\Modules\Administration\Models\RoleModel;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\AuthService;
use App\Modules\Administration\Services\RoleService;
use App\Modules\Administration\Services\UserService;
use App\Modules\Attendance\Models\AttendanceRecordModel;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Examination\Models\ExamModel;
use App\Modules\Examination\Models\MarksRecordModel;
use App\Modules\Examination\Models\PromotionRecordModel;
use App\Modules\Examination\Models\ReportCardModel;
use App\Modules\Examination\Services\ExamService;
use App\Modules\Examination\Services\MarksRecordService;
use App\Modules\Examination\Services\PromotionService;
use App\Modules\Examination\Services\ReportCardService;
use App\Modules\Sis\Mappers\GuardianMapper;
use App\Modules\Sis\Mappers\StudentGuardianLinkMapper;
use App\Modules\Sis\Mappers\StudentMapper;
use App\Modules\Sis\Models\GuardianModel;
use App\Modules\Sis\Models\StudentGuardianLinkModel;
use App\Modules\Sis\Models\StudentModel;
use App\Modules\Sis\Services\GuardianService;
use App\Modules\Sis\Services\StudentGuardianLinkService;
use App\Modules\Sis\Services\StudentService;
use App\Modules\Timetable\Models\TimetableEntryModel;
use App\Modules\Timetable\Services\TimetableEntryService;
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

    public static function applicationService(bool $getShared = true): ApplicationService
    {
        if ($getShared) {
            return static::getSharedInstance('applicationService');
        }

        return new ApplicationService(new ApplicationModel(), new SeatAllocationModel(), static::auditService());
    }

    public static function seatAllocationService(bool $getShared = true): SeatAllocationService
    {
        if ($getShared) {
            return static::getSharedInstance('seatAllocationService');
        }

        return new SeatAllocationService(new SeatAllocationModel(), static::auditService());
    }

    public static function studentService(bool $getShared = true): StudentService
    {
        if ($getShared) {
            return static::getSharedInstance('studentService');
        }

        return new StudentService(
            new StudentModel(),
            new StudentGuardianLinkModel(),
            new StudentMapper(),
            static::auditService(),
        );
    }

    public static function guardianService(bool $getShared = true): GuardianService
    {
        if ($getShared) {
            return static::getSharedInstance('guardianService');
        }

        return new GuardianService(new GuardianModel(), new GuardianMapper(), static::auditService());
    }

    public static function studentGuardianLinkService(bool $getShared = true): StudentGuardianLinkService
    {
        if ($getShared) {
            return static::getSharedInstance('studentGuardianLinkService');
        }

        return new StudentGuardianLinkService(
            new StudentGuardianLinkModel(),
            new StudentModel(),
            new GuardianModel(),
            new StudentGuardianLinkMapper(),
            static::auditService(),
        );
    }

    public static function examService(bool $getShared = true): ExamService
    {
        if ($getShared) {
            return static::getSharedInstance('examService');
        }

        return new ExamService(
            new ExamModel(),
            new MarksRecordModel(),
            new ReportCardModel(),
            static::auditService(),
        );
    }

    public static function marksRecordService(bool $getShared = true): MarksRecordService
    {
        if ($getShared) {
            return static::getSharedInstance('marksRecordService');
        }

        return new MarksRecordService(
            new MarksRecordModel(),
            new ExamModel(),
            static::examService(),
            static::auditService(),
        );
    }

    public static function reportCardService(bool $getShared = true): ReportCardService
    {
        if ($getShared) {
            return static::getSharedInstance('reportCardService');
        }

        return new ReportCardService(new ReportCardModel(), new ExamModel(), static::auditService());
    }

    public static function promotionService(bool $getShared = true): PromotionService
    {
        if ($getShared) {
            return static::getSharedInstance('promotionService');
        }

        return new PromotionService(new PromotionRecordModel(), static::auditService());
    }

    public static function timetableEntryService(bool $getShared = true): TimetableEntryService
    {
        if ($getShared) {
            return static::getSharedInstance('timetableEntryService');
        }

        return new TimetableEntryService(new TimetableEntryModel(), static::auditService());
    }

    public static function attendanceService(bool $getShared = true): AttendanceService
    {
        if ($getShared) {
            return static::getSharedInstance('attendanceService');
        }

        return new AttendanceService(new AttendanceRecordModel(), static::auditService());
    }
}
