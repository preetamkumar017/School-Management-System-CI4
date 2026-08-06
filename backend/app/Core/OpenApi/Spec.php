<?php

declare(strict_types=1);

namespace App\Core\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Anchor class for the OpenAPI document's top-level metadata — swagger-php
 * scans every annotated class under app/Modules and app/Core and merges
 * them into one spec; this is just where the document-level attributes
 * (title, servers, the shared bearer-auth scheme) live, since they don't
 * belong to any one Controller.
 *
 * Generated via `composer openapi` (App\Core\OpenApi\Generator) — never
 * hand-maintained, per the Company Development Standard §5.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'School ERP API',
    description: 'School ERP — first product on the company\'s CodeIgniter 4 platform. '
        . 'See docs/COMPANY_DEVELOPMENT_STANDARD.md §5 for the response envelope and '
        . 'error-category conventions every endpoint below follows.',
)]
#[OA\Server(url: '/api/v1', description: 'Current API version')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'The standard error envelope (Company Development Standard §7) — every non-2xx response uses this shape.',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'data', nullable: true, example: null),
        new OA\Property(
            property: 'error',
            properties: [
                new OA\Property(property: 'category', type: 'string', enum: [
                    'validation', 'business_rule', 'authorization', 'concurrency', 'rate_limit', 'system',
                ]),
                new OA\Property(property: 'code', type: 'string', example: 'INVALID_CREDENTIALS'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'fields', type: 'object', nullable: true),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'meta',
            properties: [new OA\Property(property: 'request_id', type: 'string', format: 'uuid')],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'UserResponse',
    description: 'Never includes password_hash, under any circumstance or caller role (Company Development Standard §9).',
    properties: [
        new OA\Property(property: 'user_id', type: 'integer'),
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'role_id', type: 'integer'),
        new OA\Property(property: 'owner_type', type: 'string', enum: ['EMPLOYEE', 'STUDENT', 'GUARDIAN']),
        new OA\Property(property: 'owner_ref_id', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'LOCKED', 'DEACTIVATED']),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'RoleRequest',
    description: 'Same shape for create and update (Phase 3).',
    required: ['role_name', 'permission_set'],
    properties: [
        new OA\Property(property: 'role_name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'permission_set', type: 'array', items: new OA\Items(type: 'string')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'RoleResponse',
    properties: [
        new OA\Property(property: 'role_id', type: 'integer'),
        new OA\Property(property: 'role_name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_system_role', type: 'boolean'),
        new OA\Property(property: 'permission_set', type: 'array', items: new OA\Items(type: 'string')),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AuditLogResponse',
    description: 'ip_address is deliberately excluded from this default shape (Phase 3) — a role-scoped variant would be a future addition.',
    properties: [
        new OA\Property(property: 'audit_log_id', type: 'integer'),
        new OA\Property(property: 'entity_name', type: 'string'),
        new OA\Property(property: 'record_id', type: 'integer'),
        new OA\Property(property: 'action', type: 'string', enum: ['CREATE', 'UPDATE', 'DELETE', 'APPROVE', 'REJECT', 'OVERRIDE']),
        new OA\Property(property: 'performed_by', type: 'integer'),
        new OA\Property(property: 'performed_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'old_value', type: 'object', nullable: true),
        new OA\Property(property: 'new_value', type: 'object', nullable: true),
        new OA\Property(property: 'reason', type: 'string', nullable: true),
    ],
    type: 'object',
)]
final class Spec
{
    // No instances — attributes only.
    private function __construct()
    {
    }
}
