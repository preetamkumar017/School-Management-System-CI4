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
    $routes->get('report-cards/(:num)', 'ReportCardController::show/$1');
    $routes->get('report-cards', 'ReportCardController::index');

    $routes->post('promotions', 'PromotionController::create');
    $routes->get('promotions/(:num)', 'PromotionController::show/$1');
    $routes->get('promotions', 'PromotionController::index');
});
