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
