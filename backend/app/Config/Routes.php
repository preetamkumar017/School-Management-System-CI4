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
