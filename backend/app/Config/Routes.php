<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// docs/design/administration/Phase-5-Controller-Design.md
$routes->group('api/v1/auth', ['namespace' => 'App\Modules\Administration\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('login', 'AuthController::login');
    $routes->post('refresh', 'AuthController::refresh');
    $routes->post('logout', 'AuthController::logout');
    $routes->post('logout-all', 'AuthController::logoutAll');
    $routes->post('change-password', 'AuthController::changePassword');
});

$routes->group('api/v1/administration', ['namespace' => 'App\Modules\Administration\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('users', 'UserController::create');
    $routes->patch('users/(:num)', 'UserController::update/$1');
    $routes->post('users/(:num)/status', 'UserController::changeStatus/$1');
    $routes->get('users/(:num)', 'UserController::show/$1');
    $routes->get('users', 'UserController::index');

    $routes->post('roles', 'RoleController::create');
    $routes->patch('roles/(:num)', 'RoleController::update/$1');
    $routes->delete('roles/(:num)', 'RoleController::delete/$1');
    $routes->get('roles/(:num)', 'RoleController::show/$1');
    $routes->get('roles', 'RoleController::index');

    $routes->get('audit-logs/by-entity/(:segment)/(:num)', 'AuditLogController::byEntity/$1/$2');
    $routes->get('audit-logs/by-user/(:num)', 'AuditLogController::byUser/$1');

    // docs/design/administration/Phase-7-Configuration-Design.md
    $routes->patch('configurations/(:segment)', 'ConfigurationController::update/$1');
    $routes->get('configurations/(:segment)', 'ConfigurationController::show/$1');
    $routes->get('configurations', 'ConfigurationController::index');

    // docs/design/administration/Phase-8-Document-Design.md
    $routes->get('documents/(:num)/download', 'DocumentController::download/$1');
    $routes->get('documents/(:num)', 'DocumentController::show/$1');
    $routes->get('documents', 'DocumentController::index');
});

// docs/design/academic/Phase-5-Controller-Design.md
$routes->group('api/v1/academic', ['namespace' => 'App\Modules\Academic\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('sessions', 'AcademicSessionController::create');
    $routes->patch('sessions/(:num)', 'AcademicSessionController::update/$1');
    $routes->post('sessions/(:num)/status', 'AcademicSessionController::changeStatus/$1');
    $routes->get('sessions/current', 'AcademicSessionController::current');
    $routes->get('sessions/(:num)', 'AcademicSessionController::show/$1');
    $routes->get('sessions', 'AcademicSessionController::index');

    $routes->post('classes', 'ClassController::create');
    $routes->patch('classes/(:num)', 'ClassController::update/$1');
    $routes->get('classes/(:num)', 'ClassController::show/$1');
    $routes->get('classes', 'ClassController::index');

    $routes->post('sections', 'SectionController::create');
    $routes->patch('sections/(:num)', 'SectionController::update/$1');
    $routes->get('sections/(:num)', 'SectionController::show/$1');
    $routes->get('sections', 'SectionController::index');

    $routes->post('subjects', 'SubjectController::create');
    $routes->patch('subjects/(:num)', 'SubjectController::update/$1');
    $routes->get('subjects/(:num)', 'SubjectController::show/$1');
    $routes->get('subjects', 'SubjectController::index');

    $routes->post('grading-schemes', 'GradingSchemeController::create');
    $routes->patch('grading-schemes/(:num)', 'GradingSchemeController::update/$1');
    $routes->get('grading-schemes/(:num)', 'GradingSchemeController::show/$1');
    $routes->get('grading-schemes', 'GradingSchemeController::index');

    $routes->post('class-subject-map', 'ClassSubjectMapController::create');
    $routes->delete('class-subject-map/(:num)/(:num)', 'ClassSubjectMapController::delete/$1/$2');
    $routes->get('class-subject-map/by-class/(:num)', 'ClassSubjectMapController::byClass/$1');
});

// docs/design/admission/Phase-5-Controller-Design.md
$routes->group('api/v1/admission', ['namespace' => 'App\Modules\Admission\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('applications', 'ApplicationController::create');
    $routes->post('applications/(:num)/verify', 'ApplicationController::verify/$1');
    $routes->post('applications/(:num)/shortlist', 'ApplicationController::shortlist/$1');
    $routes->post('applications/(:num)/waitlist', 'ApplicationController::waitlist/$1');
    $routes->post('applications/(:num)/reject', 'ApplicationController::reject/$1');
    $routes->post('applications/(:num)/confirm-enrollment', 'ApplicationController::confirmEnrollment/$1');
    $routes->post('applications/release-expired-holds', 'ApplicationController::releaseExpiredHolds');
    $routes->get('applications/(:num)', 'ApplicationController::show/$1');
    $routes->get('applications', 'ApplicationController::index');

    $routes->post('seat-allocations', 'SeatAllocationController::create');
    $routes->patch('seat-allocations/(:num)', 'SeatAllocationController::update/$1');
    $routes->get('seat-allocations/(:num)', 'SeatAllocationController::show/$1');
    $routes->get('seat-allocations', 'SeatAllocationController::index');
});

// docs/design/sis/Phase-4.7-Controller-Design.md
$routes->group('api/v1/sis', ['namespace' => 'App\Modules\Sis\Controllers'], static function (RouteCollection $routes): void {
    // No POST / on students — createStudentStub has no public endpoint
    // (ADR-004 §3), reachable only via Admission's Confirm Enrollment.
    $routes->patch('students/(:num)', 'StudentController::update/$1');
    $routes->post('students/(:num)/section-transfer', 'StudentController::sectionTransfer/$1');
    $routes->post('students/(:num)/status', 'StudentController::changeStatus/$1');
    $routes->get('students/(:num)', 'StudentController::show/$1');
    $routes->get('students', 'StudentController::index');

    $routes->post('guardians', 'GuardianController::create');
    $routes->patch('guardians/(:num)', 'GuardianController::update/$1');
    $routes->get('guardians/(:num)', 'GuardianController::show/$1');

    $routes->post('student-guardian-links', 'StudentGuardianLinkController::create');
    $routes->delete('student-guardian-links/(:num)/(:num)', 'StudentGuardianLinkController::delete/$1/$2');
    $routes->get('student-guardian-links/by-student/(:num)', 'StudentGuardianLinkController::byStudent/$1');
});

// docs/design/examination/Phase-5-Controller-Design.md
$routes->group('api/v1/examination', ['namespace' => 'App\Modules\Examination\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('exams', 'ExamController::create');
    $routes->post('exams/(:num)/activate', 'ExamController::activate/$1');
    $routes->post('exams/(:num)/lock', 'ExamController::lock/$1');
    $routes->get('exams/(:num)', 'ExamController::show/$1');
    $routes->get('exams', 'ExamController::index');

    $routes->post('marks-records', 'MarksRecordController::create');
    $routes->post('marks-records/(:num)/lock', 'MarksRecordController::lock/$1');
    $routes->post('marks-records/(:num)/reevaluate', 'MarksRecordController::reevaluate/$1');
    $routes->get('marks-records/(:num)', 'MarksRecordController::show/$1');
    $routes->get('marks-records', 'MarksRecordController::index');

    $routes->post('report-cards/publish', 'ReportCardController::publish');
    $routes->post('report-cards/(:num)/generate-pdf', 'ReportCardController::generatePdf/$1');
    $routes->get('report-cards/(:num)', 'ReportCardController::show/$1');
    $routes->get('report-cards', 'ReportCardController::index');

    $routes->post('promotions', 'PromotionController::create');
    $routes->get('promotions/(:num)', 'PromotionController::show/$1');
    $routes->get('promotions', 'PromotionController::index');
});

// docs/design/timetable/Phase-3-Service-Controller-Design.md
$routes->group('api/v1/timetable', ['namespace' => 'App\Modules\Timetable\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('entries', 'TimetableEntryController::create');
    $routes->post('entries/(:num)/publish', 'TimetableEntryController::publish/$1');
    $routes->post('entries/(:num)/revise', 'TimetableEntryController::revise/$1');
    $routes->get('entries/(:num)', 'TimetableEntryController::show/$1');
    $routes->get('entries', 'TimetableEntryController::index');
    $routes->get('entries/(:num)/eligible-substitutes', 'SubstitutionController::eligibleSubstitutes/$1');

    $routes->post('subject-teacher-eligibilities', 'SubjectTeacherEligibilityController::create');
    $routes->get('subject-teacher-eligibilities', 'SubjectTeacherEligibilityController::index');

    $routes->post('substitutions', 'SubstitutionController::create');
    $routes->get('substitutions/(:num)', 'SubstitutionController::show/$1');
});

// docs/design/attendance/Phase-3-Service-Controller-Design.md
$routes->group('api/v1/attendance', ['namespace' => 'App\Modules\Attendance\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('records', 'AttendanceController::create');
    $routes->post('records/(:num)/lock', 'AttendanceController::lock/$1');
    $routes->post('records/(:num)/correct', 'AttendanceController::correct/$1');
    $routes->get('records/percentage', 'AttendanceController::percentage');
    $routes->get('records/(:num)', 'AttendanceController::show/$1');
    $routes->get('records', 'AttendanceController::index');

    // docs/design/hr-payroll/Phase-3-Service-Controller-Design.md (ADR-008 §3)
    $routes->post('staff-attendance', 'StaffAttendanceRecordController::create');
    $routes->post('staff-attendance/reconcile', 'StaffAttendanceRecordController::reconcile');
    $routes->post('staff-attendance/close-period', 'StaffAttendanceRecordController::closePeriod');
    $routes->get('staff-attendance', 'StaffAttendanceRecordController::index');
});

// docs/design/fees/Phase-3-Service-Controller-Design.md
$routes->group('api/v1/fees', ['namespace' => 'App\Modules\Fees\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('fee-heads', 'FeeHeadController::create');
    $routes->patch('fee-heads/(:num)', 'FeeHeadController::update/$1');
    $routes->get('fee-heads/(:num)', 'FeeHeadController::show/$1');
    $routes->get('fee-heads', 'FeeHeadController::index');

    $routes->post('fee-structures', 'FeeStructureController::create');
    $routes->patch('fee-structures/(:num)', 'FeeStructureController::update/$1');
    $routes->get('fee-structures/(:num)', 'FeeStructureController::show/$1');
    $routes->get('fee-structures', 'FeeStructureController::index');

    $routes->post('invoices', 'InvoiceController::create');
    $routes->post('invoices/(:num)/apply-late-fee', 'InvoiceController::applyLateFee/$1');
    $routes->post('invoices/(:num)/flag-defaulter', 'InvoiceController::flagDefaulter/$1');
    $routes->post('invoices/(:num)/generate-pdf', 'InvoiceController::generatePdf/$1');
    $routes->get('invoices/(:num)', 'InvoiceController::show/$1');
    $routes->get('invoices', 'InvoiceController::index');

    $routes->post('payments', 'PaymentController::create');
    $routes->post('payments/(:num)/void', 'PaymentController::void/$1');
    $routes->post('payments/(:num)/refund', 'PaymentController::refund/$1');
    $routes->get('payments/(:num)', 'PaymentController::show/$1');
    $routes->get('payments', 'PaymentController::index');

    $routes->post('scholarship-waivers', 'ScholarshipWaiverController::create');
    $routes->get('scholarship-waivers/(:num)', 'ScholarshipWaiverController::show/$1');
    $routes->get('scholarship-waivers', 'ScholarshipWaiverController::index');
});

// docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
$routes->group('api/v1/hr-payroll', ['namespace' => 'App\Modules\HrPayroll\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('departments', 'DepartmentController::create');
    $routes->patch('departments/(:num)', 'DepartmentController::update/$1');
    $routes->get('departments/(:num)', 'DepartmentController::show/$1');
    $routes->get('departments', 'DepartmentController::index');

    $routes->post('designations', 'DesignationController::create');
    $routes->patch('designations/(:num)', 'DesignationController::update/$1');
    $routes->get('designations/(:num)', 'DesignationController::show/$1');
    $routes->get('designations', 'DesignationController::index');

    $routes->post('employees', 'EmployeeController::create');
    $routes->patch('employees/(:num)', 'EmployeeController::update/$1');
    $routes->get('employees/(:num)', 'EmployeeController::show/$1');
    $routes->get('employees', 'EmployeeController::index');

    $routes->post('payroll-runs', 'PayrollRunController::create');
    $routes->post('payroll-runs/(:num)/approve', 'PayrollRunController::approve/$1');
    $routes->post('payroll-runs/(:num)/process', 'PayrollRunController::process/$1');
    $routes->post('payroll-runs/(:num)/generate-payslip', 'PayrollRunController::generatePayslip/$1');
    $routes->get('payroll-runs/(:num)', 'PayrollRunController::show/$1');
    $routes->get('payroll-runs', 'PayrollRunController::index');

    $routes->post('leave-requests', 'LeaveRequestController::create');
    $routes->post('leave-requests/(:num)/decide', 'LeaveRequestController::decide/$1');
    $routes->get('leave-requests/(:num)', 'LeaveRequestController::show/$1');
    $routes->get('leave-requests', 'LeaveRequestController::index');
});

// docs/design/library/Phase-3-Service-Controller-Design.md
$routes->group('api/v1/library', ['namespace' => 'App\Modules\Library\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('books', 'BookController::create');
    $routes->patch('books/(:num)', 'BookController::update/$1');
    $routes->get('books/(:num)', 'BookController::show/$1');
    $routes->get('books', 'BookController::index');

    $routes->post('book-issues', 'BookIssueController::create');
    $routes->post('book-issues/(:num)/return', 'BookIssueController::returnBook/$1');
    $routes->post('book-issues/(:num)/report-lost', 'BookIssueController::reportLost/$1');
    $routes->post('book-issues/(:num)/settle-fine', 'BookIssueController::settleFine/$1');
    $routes->get('book-issues/(:num)', 'BookIssueController::show/$1');
    $routes->get('book-issues', 'BookIssueController::index');
});

// docs/design/transport/Phase-3-Service-Controller-Design.md
$routes->group('api/v1/transport', ['namespace' => 'App\Modules\Transport\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('vehicles', 'VehicleController::create');
    $routes->patch('vehicles/(:num)', 'VehicleController::update/$1');
    $routes->get('vehicles/(:num)', 'VehicleController::show/$1');
    $routes->get('vehicles', 'VehicleController::index');

    $routes->post('routes', 'RouteController::create');
    $routes->patch('routes/(:num)', 'RouteController::update/$1');
    $routes->get('routes/(:num)', 'RouteController::show/$1');
    $routes->get('routes', 'RouteController::index');

    $routes->post('allocations', 'TransportAllocationController::create');
    $routes->post('allocations/(:num)/deallocate', 'TransportAllocationController::deallocate/$1');
    $routes->post('allocations/(:num)/change-route', 'TransportAllocationController::changeRoute/$1');
    $routes->get('allocations/(:num)', 'TransportAllocationController::show/$1');
    $routes->get('allocations', 'TransportAllocationController::index');
});

// docs/design/communication/Phase-3-Service-Controller-Design.md
$routes->group('api/v1/communication', ['namespace' => 'App\Modules\Communication\Controllers'], static function (RouteCollection $routes): void {
    $routes->post('circulars', 'CircularController::create');
    $routes->post('circulars/(:num)/retract', 'CircularController::retract/$1');
    $routes->get('circulars/(:num)', 'CircularController::show/$1');
    $routes->get('circulars', 'CircularController::index');

    $routes->post('notification-logs', 'NotificationLogController::create');
    $routes->post('notification-logs/(:num)/dispatch', 'NotificationLogController::dispatch/$1');
    $routes->post('notification-logs/(:num)/deliver', 'NotificationLogController::deliver/$1');
    $routes->post('notification-logs/(:num)/fail', 'NotificationLogController::fail/$1');
    $routes->get('notification-logs/(:num)', 'NotificationLogController::show/$1');
    $routes->get('notification-logs', 'NotificationLogController::index');
});

// docs/design/reports/Phase-1-Service-Controller-Design.md
$routes->group('api/v1/reports', ['namespace' => 'App\Modules\Reports\Controllers'], static function (RouteCollection $routes): void {
    $routes->get('summary', 'ReportsController::summary');
});
