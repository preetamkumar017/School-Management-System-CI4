<?php

declare(strict_types=1);

namespace Config;

use App\Core\Auth\JwtManager;
use App\Core\Authz\ModuleAuthorizer;
use App\Core\Excel\ExcelRenderer;
use App\Core\Pdf\PdfRenderer;
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
use App\Modules\Administration\Models\ConfigurationModel;
use App\Modules\Administration\Models\DocumentModel;
use App\Modules\Administration\Models\RefreshTokenModel;
use App\Modules\Administration\Models\RoleModel;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\AuthService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Administration\Services\DocumentService;
use App\Modules\Administration\Services\RoleService;
use App\Modules\Administration\Services\UserService;
use App\Modules\Attendance\Models\AttendanceRecordModel;
use App\Modules\Attendance\Models\StaffAttendanceRecordModel;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\StaffAttendanceService;
use App\Modules\Communication\Gateways\EmailGatewayInterface;
use App\Modules\Communication\Gateways\Msg91\Msg91Gateway;
use App\Modules\Communication\Gateways\SmsGatewayInterface;
use App\Modules\Communication\Models\CircularModel;
use App\Modules\Communication\Models\NotificationLogModel;
use App\Modules\Communication\Services\CircularService;
use App\Modules\Communication\Services\NotificationLogService;
use App\Modules\Examination\Models\ExamModel;
use App\Modules\Examination\Models\MarksRecordModel;
use App\Modules\Examination\Models\PromotionRecordModel;
use App\Modules\Examination\Models\ReportCardModel;
use App\Modules\Examination\Services\ExamService;
use App\Modules\Examination\Services\MarksRecordService;
use App\Modules\Examination\Services\PromotionService;
use App\Modules\Examination\Services\ReportCardService;
use App\Modules\Fees\Models\FeeHeadModel;
use App\Modules\Fees\Models\FeeStructureModel;
use App\Modules\Fees\Models\InvoiceLineItemModel;
use App\Modules\Fees\Models\InvoiceModel;
use App\Modules\Fees\Models\PaymentModel;
use App\Modules\Fees\Models\ScholarshipWaiverModel;
use App\Modules\Fees\Services\FeeHeadService;
use App\Modules\Fees\Services\FeeStructureService;
use App\Modules\Fees\Services\InvoiceService;
use App\Modules\Fees\Services\PaymentService;
use App\Modules\Fees\Services\ScholarshipWaiverService;
use App\Modules\HrPayroll\Models\AttendanceClosureModel;
use App\Modules\HrPayroll\Models\DepartmentModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\HrPayroll\Models\HolidayModel;
use App\Modules\HrPayroll\Models\LeaveRequestModel;
use App\Modules\HrPayroll\Models\LeaveTypeModel;
use App\Modules\HrPayroll\Models\OnboardingChecklistModel;
use App\Modules\HrPayroll\Models\PayrollRunModel;
use App\Modules\HrPayroll\Services\DepartmentService;
use App\Modules\HrPayroll\Services\DesignationService;
use App\Modules\HrPayroll\Services\EmployeeService;
use App\Modules\HrPayroll\Services\HolidayService;
use App\Modules\HrPayroll\Services\LeaveRequestService;
use App\Modules\HrPayroll\Services\LeaveTypeService;
use App\Modules\HrPayroll\Services\OnboardingChecklistService;
use App\Modules\HrPayroll\Services\PayrollRunService;
use App\Modules\Library\Models\BookIssueModel;
use App\Modules\Library\Models\BookModel;
use App\Modules\Library\Models\ReservationModel;
use App\Modules\Library\Services\BookIssueService;
use App\Modules\Library\Services\BookService;
use App\Modules\Library\Services\ReservationService;
use App\Modules\Reports\Services\ReportsService;
use App\Modules\Sis\Mappers\GuardianMapper;
use App\Modules\Sis\Mappers\StudentGuardianLinkMapper;
use App\Modules\Sis\Mappers\StudentMapper;
use App\Modules\Sis\Models\GuardianModel;
use App\Modules\Sis\Models\StudentGuardianLinkModel;
use App\Modules\Sis\Models\StudentModel;
use App\Modules\Sis\Services\GuardianService;
use App\Modules\Sis\Services\StudentGuardianLinkService;
use App\Modules\Sis\Services\StudentService;
use App\Modules\Timetable\Models\SubjectTeacherEligibilityModel;
use App\Modules\Timetable\Models\SubstitutionModel;
use App\Modules\Timetable\Models\TimetableEntryModel;
use App\Modules\Timetable\Services\SubjectTeacherEligibilityService;
use App\Modules\Timetable\Services\SubstitutionService;
use App\Modules\Timetable\Services\TimetableEntryService;
use App\Modules\Transport\Models\DriverModel;
use App\Modules\Transport\Models\RouteModel;
use App\Modules\Transport\Models\TransportAllocationModel;
use App\Modules\Transport\Models\TripModel;
use App\Modules\Transport\Models\VehicleModel;
use App\Modules\Transport\Services\DriverService;
use App\Modules\Transport\Services\RouteService;
use App\Modules\Transport\Services\TransportAllocationService;
use App\Modules\Transport\Services\TripService;
use App\Modules\Transport\Services\VehicleService;
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

        return new AuditService(new AuditLogModel(), static::moduleAuthorizer());
    }

    /**
     * docs/ADR/ADR-024-systemwide-rbac-enforcement.md §3.
     */
    public static function moduleAuthorizer(bool $getShared = true): ModuleAuthorizer
    {
        if ($getShared) {
            return static::getSharedInstance('moduleAuthorizer');
        }

        return new ModuleAuthorizer(new UserModel());
    }

    public static function configurationService(bool $getShared = true): ConfigurationService
    {
        if ($getShared) {
            return static::getSharedInstance('configurationService');
        }

        return new ConfigurationService(new ConfigurationModel(), static::auditService());
    }

    public static function documentService(bool $getShared = true): DocumentService
    {
        if ($getShared) {
            return static::getSharedInstance('documentService');
        }

        return new DocumentService(new DocumentModel(), static::auditService());
    }

    public static function pdfRenderer(bool $getShared = true): PdfRenderer
    {
        if ($getShared) {
            return static::getSharedInstance('pdfRenderer');
        }

        return new PdfRenderer();
    }

    /**
     * docs/ADR/ADR-022-reports-dashboard.md §b — the Excel-rendering
     * counterpart to pdfRenderer(), same shared/non-shared shape.
     */
    public static function excelRenderer(bool $getShared = true): ExcelRenderer
    {
        if ($getShared) {
            return static::getSharedInstance('excelRenderer');
        }

        return new ExcelRenderer();
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

        return new UserService(new UserModel(), static::auditService(), static::authService(), static::moduleAuthorizer());
    }

    public static function roleService(bool $getShared = true): RoleService
    {
        if ($getShared) {
            return static::getSharedInstance('roleService');
        }

        return new RoleService(new RoleModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function academicSessionService(bool $getShared = true): AcademicSessionService
    {
        if ($getShared) {
            return static::getSharedInstance('academicSessionService');
        }

        return new AcademicSessionService(new AcademicSessionModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function classService(bool $getShared = true): ClassService
    {
        if ($getShared) {
            return static::getSharedInstance('classService');
        }

        return new ClassService(new ClassModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function sectionService(bool $getShared = true): SectionService
    {
        if ($getShared) {
            return static::getSharedInstance('sectionService');
        }

        return new SectionService(new SectionModel(), new ClassModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function subjectService(bool $getShared = true): SubjectService
    {
        if ($getShared) {
            return static::getSharedInstance('subjectService');
        }

        return new SubjectService(new SubjectModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function gradingSchemeService(bool $getShared = true): GradingSchemeService
    {
        if ($getShared) {
            return static::getSharedInstance('gradingSchemeService');
        }

        return new GradingSchemeService(new GradingSchemeModel(), static::auditService(), static::moduleAuthorizer());
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
            static::moduleAuthorizer(),
        );
    }

    public static function applicationService(bool $getShared = true): ApplicationService
    {
        if ($getShared) {
            return static::getSharedInstance('applicationService');
        }

        return new ApplicationService(new ApplicationModel(), new SeatAllocationModel(), static::auditService(), static::configurationService(), static::moduleAuthorizer());
    }

    public static function seatAllocationService(bool $getShared = true): SeatAllocationService
    {
        if ($getShared) {
            return static::getSharedInstance('seatAllocationService');
        }

        return new SeatAllocationService(new SeatAllocationModel(), static::auditService(), static::moduleAuthorizer());
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
            static::documentService(),
            static::pdfRenderer(),
            static::moduleAuthorizer(),
        );
    }

    public static function guardianService(bool $getShared = true): GuardianService
    {
        if ($getShared) {
            return static::getSharedInstance('guardianService');
        }

        return new GuardianService(new GuardianModel(), new GuardianMapper(), static::auditService(), static::moduleAuthorizer());
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
            static::moduleAuthorizer(),
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
            static::moduleAuthorizer(),
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
            static::configurationService(),
            static::moduleAuthorizer(),
        );
    }

    public static function reportCardService(bool $getShared = true): ReportCardService
    {
        if ($getShared) {
            return static::getSharedInstance('reportCardService');
        }

        return new ReportCardService(new ReportCardModel(), new ExamModel(), static::auditService(), static::documentService(), static::pdfRenderer(), static::moduleAuthorizer());
    }

    public static function promotionService(bool $getShared = true): PromotionService
    {
        if ($getShared) {
            return static::getSharedInstance('promotionService');
        }

        return new PromotionService(new PromotionRecordModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function timetableEntryService(bool $getShared = true): TimetableEntryService
    {
        if ($getShared) {
            return static::getSharedInstance('timetableEntryService');
        }

        return new TimetableEntryService(new TimetableEntryModel(), static::auditService(), static::configurationService(), static::moduleAuthorizer());
    }

    public static function subjectTeacherEligibilityService(bool $getShared = true): SubjectTeacherEligibilityService
    {
        if ($getShared) {
            return static::getSharedInstance('subjectTeacherEligibilityService');
        }

        return new SubjectTeacherEligibilityService(new SubjectTeacherEligibilityModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function substitutionService(bool $getShared = true): SubstitutionService
    {
        if ($getShared) {
            return static::getSharedInstance('substitutionService');
        }

        return new SubstitutionService(
            new SubstitutionModel(),
            new TimetableEntryModel(),
            new SubjectTeacherEligibilityModel(),
            static::auditService(),
            static::moduleAuthorizer(),
        );
    }

    public static function attendanceService(bool $getShared = true): AttendanceService
    {
        if ($getShared) {
            return static::getSharedInstance('attendanceService');
        }

        return new AttendanceService(new AttendanceRecordModel(), static::auditService(), static::configurationService(), static::moduleAuthorizer());
    }

    public static function staffAttendanceService(bool $getShared = true): StaffAttendanceService
    {
        if ($getShared) {
            return static::getSharedInstance('staffAttendanceService');
        }

        return new StaffAttendanceService(new StaffAttendanceRecordModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function feeHeadService(bool $getShared = true): FeeHeadService
    {
        if ($getShared) {
            return static::getSharedInstance('feeHeadService');
        }

        return new FeeHeadService(new FeeHeadModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function feeStructureService(bool $getShared = true): FeeStructureService
    {
        if ($getShared) {
            return static::getSharedInstance('feeStructureService');
        }

        return new FeeStructureService(new FeeStructureModel(), new FeeHeadModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function invoiceService(bool $getShared = true): InvoiceService
    {
        if ($getShared) {
            return static::getSharedInstance('invoiceService');
        }

        return new InvoiceService(new InvoiceModel(), new InvoiceLineItemModel(), new ScholarshipWaiverModel(), new FeeHeadModel(), static::auditService(), static::configurationService(), static::documentService(), static::pdfRenderer(), static::moduleAuthorizer());
    }

    public static function paymentService(bool $getShared = true): PaymentService
    {
        if ($getShared) {
            return static::getSharedInstance('paymentService');
        }

        return new PaymentService(new PaymentModel(), new InvoiceModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function scholarshipWaiverService(bool $getShared = true): ScholarshipWaiverService
    {
        if ($getShared) {
            return static::getSharedInstance('scholarshipWaiverService');
        }

        return new ScholarshipWaiverService(new ScholarshipWaiverModel(), new FeeHeadModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function departmentService(bool $getShared = true): DepartmentService
    {
        if ($getShared) {
            return static::getSharedInstance('departmentService');
        }

        return new DepartmentService(new DepartmentModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function designationService(bool $getShared = true): DesignationService
    {
        if ($getShared) {
            return static::getSharedInstance('designationService');
        }

        return new DesignationService(new DesignationModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function employeeService(bool $getShared = true): EmployeeService
    {
        if ($getShared) {
            return static::getSharedInstance('employeeService');
        }

        return new EmployeeService(
            new EmployeeModel(),
            new DepartmentModel(),
            new DesignationModel(),
            new AttendanceClosureModel(),
            static::auditService(),
            static::moduleAuthorizer(),
        );
    }

    public static function onboardingChecklistService(bool $getShared = true): OnboardingChecklistService
    {
        if ($getShared) {
            return static::getSharedInstance('onboardingChecklistService');
        }

        return new OnboardingChecklistService(
            new OnboardingChecklistModel(),
            new EmployeeModel(),
            static::pdfRenderer(),
            static::moduleAuthorizer(),
        );
    }

    public static function payrollRunService(bool $getShared = true): PayrollRunService
    {
        if ($getShared) {
            return static::getSharedInstance('payrollRunService');
        }

        return new PayrollRunService(new PayrollRunModel(), new EmployeeModel(), new AttendanceClosureModel(), static::auditService(), static::documentService(), static::pdfRenderer(), static::moduleAuthorizer());
    }

    public static function leaveRequestService(bool $getShared = true): LeaveRequestService
    {
        if ($getShared) {
            return static::getSharedInstance('leaveRequestService');
        }

        return new LeaveRequestService(new LeaveRequestModel(), new EmployeeModel(), new HolidayModel(), new LeaveTypeModel(), static::auditService(), static::configurationService(), static::moduleAuthorizer());
    }

    public static function leaveTypeService(bool $getShared = true): LeaveTypeService
    {
        if ($getShared) {
            return static::getSharedInstance('leaveTypeService');
        }

        return new LeaveTypeService(new LeaveTypeModel());
    }

    public static function holidayService(bool $getShared = true): HolidayService
    {
        if ($getShared) {
            return static::getSharedInstance('holidayService');
        }

        return new HolidayService(new HolidayModel());
    }

    public static function bookService(bool $getShared = true): BookService
    {
        if ($getShared) {
            return static::getSharedInstance('bookService');
        }

        return new BookService(new BookModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function bookIssueService(bool $getShared = true): BookIssueService
    {
        if ($getShared) {
            return static::getSharedInstance('bookIssueService');
        }

        return new BookIssueService(new BookIssueModel(), new BookModel(), static::auditService(), static::configurationService(), static::reservationService(), static::moduleAuthorizer());
    }

    public static function reservationService(bool $getShared = true): ReservationService
    {
        if ($getShared) {
            return static::getSharedInstance('reservationService');
        }

        return new ReservationService(new ReservationModel(), new BookModel(), static::auditService(), static::configurationService(), static::notificationLogService(), static::moduleAuthorizer());
    }

    public static function vehicleService(bool $getShared = true): VehicleService
    {
        if ($getShared) {
            return static::getSharedInstance('vehicleService');
        }

        return new VehicleService(new VehicleModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function routeService(bool $getShared = true): RouteService
    {
        if ($getShared) {
            return static::getSharedInstance('routeService');
        }

        return new RouteService(new RouteModel(), new VehicleModel(), static::auditService(), new DriverModel(), static::moduleAuthorizer());
    }

    public static function transportAllocationService(bool $getShared = true): TransportAllocationService
    {
        if ($getShared) {
            return static::getSharedInstance('transportAllocationService');
        }

        return new TransportAllocationService(new TransportAllocationModel(), new RouteModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function driverService(bool $getShared = true): DriverService
    {
        if ($getShared) {
            return static::getSharedInstance('driverService');
        }

        return new DriverService(new DriverModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function tripService(bool $getShared = true): TripService
    {
        if ($getShared) {
            return static::getSharedInstance('tripService');
        }

        return new TripService(new TripModel(), new RouteModel(), new DriverModel(), new VehicleModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function circularService(bool $getShared = true): CircularService
    {
        if ($getShared) {
            return static::getSharedInstance('circularService');
        }

        return new CircularService(new CircularModel(), static::auditService(), static::moduleAuthorizer());
    }

    public static function notificationLogService(bool $getShared = true): NotificationLogService
    {
        if ($getShared) {
            return static::getSharedInstance('notificationLogService');
        }

        return new NotificationLogService(
            new NotificationLogModel(),
            static::auditService(),
            new GuardianModel(),
            new StudentGuardianLinkModel(),
            static::smsGateway(),
            static::emailGateway(),
            static::moduleAuthorizer(),
        );
    }

    /**
     * docs/ADR/ADR-021-communication-sms-email-gateway.md §a/§b —
     * NotificationLogService depends on SmsGatewayInterface, never the
     * concrete Msg91Gateway directly. Swapping vendors later means
     * changing only this one factory method.
     */
    public static function smsGateway(bool $getShared = true): SmsGatewayInterface
    {
        if ($getShared) {
            return static::getSharedInstance('smsGateway');
        }

        return new Msg91Gateway(config('Notification'));
    }

    public static function emailGateway(bool $getShared = true): EmailGatewayInterface
    {
        if ($getShared) {
            return static::getSharedInstance('emailGateway');
        }

        return new Msg91Gateway(config('Notification'));
    }

    public static function reportsService(bool $getShared = true): ReportsService
    {
        if ($getShared) {
            return static::getSharedInstance('reportsService');
        }

        return new ReportsService(static::pdfRenderer(), static::excelRenderer(), static::moduleAuthorizer());
    }
}
