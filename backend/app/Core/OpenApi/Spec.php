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
#[OA\Schema(
    schema: 'AcademicSessionRequest',
    description: 'Same shape for create and update (Phase 3). status is set to PLANNED at creation and changed only via POST /{id}/status.',
    required: ['session_name', 'start_date', 'end_date'],
    properties: [
        new OA\Property(property: 'session_name', type: 'string', example: '2026-27'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AcademicSessionResponse',
    properties: [
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'session_name', type: 'string'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date'),
        new OA\Property(property: 'status', type: 'string', enum: ['PLANNED', 'ACTIVE', 'CLOSED', 'ARCHIVED']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ClassRequest',
    description: 'Same shape for create and update (Phase 3).',
    required: ['class_name', 'sequence_order'],
    properties: [
        new OA\Property(property: 'class_name', type: 'string'),
        new OA\Property(property: 'sequence_order', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ClassResponse',
    properties: [
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'class_name', type: 'string'),
        new OA\Property(property: 'sequence_order', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SectionCreateRequest',
    required: ['class_id', 'section_name', 'capacity'],
    properties: [
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'section_name', type: 'string'),
        new OA\Property(property: 'capacity', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SectionUpdateRequest',
    description: 'class_id is immutable after creation (Phase 3) — absent here.',
    required: ['section_name', 'capacity'],
    properties: [
        new OA\Property(property: 'section_name', type: 'string'),
        new OA\Property(property: 'capacity', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SectionResponse',
    properties: [
        new OA\Property(property: 'section_id', type: 'integer'),
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'section_name', type: 'string'),
        new OA\Property(property: 'capacity', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SubjectRequest',
    description: 'Same shape for create and update (Phase 3).',
    required: ['subject_name', 'subject_code'],
    properties: [
        new OA\Property(property: 'subject_name', type: 'string'),
        new OA\Property(property: 'subject_code', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SubjectResponse',
    properties: [
        new OA\Property(property: 'subject_id', type: 'integer'),
        new OA\Property(property: 'subject_name', type: 'string'),
        new OA\Property(property: 'subject_code', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GradingSchemeCreateRequest',
    required: ['scheme_name', 'board_type', 'grade_band_json'],
    properties: [
        new OA\Property(property: 'scheme_name', type: 'string'),
        new OA\Property(property: 'board_type', type: 'string', enum: ['CBSE', 'ICSE', 'STATE_BOARD']),
        new OA\Property(property: 'grade_band_json', type: 'object', example: ['A1' => '91-100', 'A2' => '81-90']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GradingSchemeUpdateRequest',
    description: 'scheme_name is deliberately absent (Phase 4) — once locked by a closed exam, a new scheme is created instead of renaming this one.',
    required: ['board_type', 'grade_band_json'],
    properties: [
        new OA\Property(property: 'board_type', type: 'string', enum: ['CBSE', 'ICSE', 'STATE_BOARD']),
        new OA\Property(property: 'grade_band_json', type: 'object', example: ['A1' => '91-100', 'A2' => '81-90']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GradingSchemeResponse',
    properties: [
        new OA\Property(property: 'grading_scheme_id', type: 'integer'),
        new OA\Property(property: 'scheme_name', type: 'string'),
        new OA\Property(property: 'board_type', type: 'string', enum: ['CBSE', 'ICSE', 'STATE_BOARD']),
        new OA\Property(property: 'grade_band_json', type: 'object'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ClassSubjectMapRequest',
    required: ['class_id', 'subject_id'],
    properties: [
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'subject_id', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ClassSubjectMapResponse',
    properties: [
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'subject_id', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ApplicationCreateRequest',
    required: ['applicant_name', 'dob', 'class_applied_id', 'category'],
    properties: [
        new OA\Property(property: 'applicant_name', type: 'string'),
        new OA\Property(property: 'dob', type: 'string', format: 'date'),
        new OA\Property(property: 'class_applied_id', type: 'integer'),
        new OA\Property(property: 'aadhaar_number', type: 'string', nullable: true, description: '12 digits, Verhoeff checksum-valid.'),
        new OA\Property(property: 'category', type: 'string', enum: ['GENERAL', 'RTE']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ApplicationResponse',
    description: 'The SUBMITTED/VERIFIED/... -> ADMITTED transition (FR-02 Confirm Enrollment) is implemented in Stage 5 alongside SIS.',
    properties: [
        new OA\Property(property: 'application_id', type: 'integer'),
        new OA\Property(property: 'application_reference_no', type: 'string', example: 'APP-2026-10023'),
        new OA\Property(property: 'applicant_name', type: 'string'),
        new OA\Property(property: 'dob', type: 'string', format: 'date'),
        new OA\Property(property: 'class_applied_id', type: 'integer'),
        new OA\Property(property: 'aadhaar_number', type: 'string', nullable: true),
        new OA\Property(property: 'category', type: 'string', enum: ['GENERAL', 'RTE']),
        new OA\Property(property: 'status', type: 'string', enum: ['SUBMITTED', 'VERIFIED', 'SHORTLISTED', 'WAITLISTED', 'ADMITTED', 'REJECTED']),
        new OA\Property(property: 'submitted_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'decided_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SeatAllocationCreateRequest',
    required: ['class_id', 'academic_session_id', 'total_capacity', 'rte_quota_capacity'],
    properties: [
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'total_capacity', type: 'integer'),
        new OA\Property(property: 'rte_quota_capacity', type: 'integer', description: 'Must not exceed 25% of total_capacity.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SeatAllocationUpdateRequest',
    description: 'class_id/academic_session_id are immutable after creation — absent here.',
    required: ['total_capacity', 'rte_quota_capacity'],
    properties: [
        new OA\Property(property: 'total_capacity', type: 'integer'),
        new OA\Property(property: 'rte_quota_capacity', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SeatAllocationResponse',
    properties: [
        new OA\Property(property: 'seat_allocation_id', type: 'integer'),
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'total_capacity', type: 'integer'),
        new OA\Property(property: 'rte_quota_capacity', type: 'integer'),
        new OA\Property(property: 'seats_filled', type: 'integer'),
        new OA\Property(property: 'rte_seats_filled', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StudentUpdateRequest',
    description: 'section_id/application_id are excluded — application_id is immutable, section_id moves through POST .../section-transfer.',
    required: ['full_name', 'dob', 'category'],
    properties: [
        new OA\Property(property: 'full_name', type: 'string'),
        new OA\Property(property: 'dob', type: 'string', format: 'date'),
        new OA\Property(property: 'category', type: 'string', enum: ['GENERAL', 'RTE']),
        new OA\Property(property: 'aadhaar_number', type: 'string', nullable: true),
        new OA\Property(property: 'medical_info', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StudentResponse',
    description: 'No public create endpoint exists — a Student stub is created only via Admission\'s Confirm Enrollment (ADR-004 §3).',
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'admission_number', type: 'string'),
        new OA\Property(property: 'full_name', type: 'string'),
        new OA\Property(property: 'dob', type: 'string', format: 'date'),
        new OA\Property(property: 'aadhaar_number', type: 'string', nullable: true),
        new OA\Property(property: 'section_id', type: 'integer', nullable: true, description: 'Null while status is DRAFT and no section has been assigned yet.'),
        new OA\Property(property: 'application_id', type: 'integer'),
        new OA\Property(property: 'category', type: 'string', enum: ['GENERAL', 'RTE']),
        new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'ACTIVE', 'PROMOTED', 'EXITED', 'ARCHIVED']),
        new OA\Property(property: 'medical_info', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GuardianRequest',
    description: 'Same shape for create and update.',
    required: ['full_name', 'relationship', 'mobile_number'],
    properties: [
        new OA\Property(property: 'full_name', type: 'string'),
        new OA\Property(property: 'relationship', type: 'string', enum: ['FATHER', 'MOTHER', 'GUARDIAN', 'OTHER']),
        new OA\Property(property: 'mobile_number', type: 'string'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'GuardianResponse',
    properties: [
        new OA\Property(property: 'guardian_id', type: 'integer'),
        new OA\Property(property: 'full_name', type: 'string'),
        new OA\Property(property: 'relationship', type: 'string', enum: ['FATHER', 'MOTHER', 'GUARDIAN', 'OTHER']),
        new OA\Property(property: 'mobile_number', type: 'string'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StudentGuardianLinkRequest',
    required: ['student_id', 'guardian_id'],
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'guardian_id', type: 'integer'),
        new OA\Property(property: 'is_primary_contact', type: 'boolean', default: false),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StudentGuardianLinkResponse',
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'guardian_id', type: 'integer'),
        new OA\Property(property: 'is_primary_contact', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ExamCreateRequest',
    required: ['exam_name', 'class_id', 'academic_session_id', 'grading_scheme_id', 'exam_date'],
    properties: [
        new OA\Property(property: 'exam_name', type: 'string'),
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'grading_scheme_id', type: 'integer'),
        new OA\Property(property: 'exam_date', type: 'string', format: 'date'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ExamResponse',
    properties: [
        new OA\Property(property: 'exam_id', type: 'integer'),
        new OA\Property(property: 'exam_name', type: 'string'),
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'grading_scheme_id', type: 'integer'),
        new OA\Property(property: 'exam_date', type: 'string', format: 'date'),
        new OA\Property(property: 'status', type: 'string', enum: ['CONFIGURED', 'ACTIVE', 'LOCKED', 'CLOSED']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'MarksRecordCreateRequest',
    required: ['exam_id', 'student_id', 'subject_id', 'max_marks'],
    properties: [
        new OA\Property(property: 'exam_id', type: 'integer'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'subject_id', type: 'integer'),
        new OA\Property(property: 'marks_obtained', type: 'number', format: 'float', nullable: true, description: 'NULL = absent.'),
        new OA\Property(property: 'max_marks', type: 'number', format: 'float'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'MarksRecordResponse',
    properties: [
        new OA\Property(property: 'marks_record_id', type: 'integer'),
        new OA\Property(property: 'exam_id', type: 'integer'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'subject_id', type: 'integer'),
        new OA\Property(property: 'marks_obtained', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'max_marks', type: 'number', format: 'float'),
        new OA\Property(property: 'is_flagged', type: 'boolean'),
        new OA\Property(property: 'is_locked', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ReportCardResponse',
    description: 'Data record only, no PDF (ADR-005 §9).',
    properties: [
        new OA\Property(property: 'report_card_id', type: 'integer'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'exam_id', type: 'integer'),
        new OA\Property(property: 'grade_summary', type: 'object', example: ['subject_14' => 'A1']),
        new OA\Property(property: 'gpa', type: 'number', format: 'float'),
        new OA\Property(property: 'class_rank', type: 'integer', nullable: true),
        new OA\Property(property: 'is_published', type: 'boolean'),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PromotionRecordCreateRequest',
    description: 'academic_closure_confirmed is excluded — system-computed from the source AcademicSession\'s status (ADR-005 §3).',
    required: ['student_id', 'from_session_id', 'to_session_id', 'from_class_id', 'to_class_id', 'fee_closure_confirmed'],
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'from_session_id', type: 'integer'),
        new OA\Property(property: 'to_session_id', type: 'integer'),
        new OA\Property(property: 'from_class_id', type: 'integer'),
        new OA\Property(property: 'to_class_id', type: 'integer'),
        new OA\Property(property: 'fee_closure_confirmed', type: 'boolean', description: 'Caller-attested — Fees module does not exist yet (ADR-005 §3).'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PromotionRecordResponse',
    properties: [
        new OA\Property(property: 'promotion_record_id', type: 'integer'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'from_session_id', type: 'integer'),
        new OA\Property(property: 'to_session_id', type: 'integer'),
        new OA\Property(property: 'from_class_id', type: 'integer'),
        new OA\Property(property: 'to_class_id', type: 'integer'),
        new OA\Property(property: 'academic_closure_confirmed', type: 'boolean'),
        new OA\Property(property: 'fee_closure_confirmed', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TimetableEntryRequest',
    description: 'Same shape for create and revise (BR-TT-005).',
    required: ['section_id', 'subject_id', 'employee_id', 'day_of_week', 'period_no'],
    properties: [
        new OA\Property(property: 'section_id', type: 'integer'),
        new OA\Property(property: 'subject_id', type: 'integer'),
        new OA\Property(property: 'employee_id', type: 'integer', description: 'Not validated against an Employee table — HR & Payroll does not exist yet (ADR-006 §1).'),
        new OA\Property(property: 'day_of_week', type: 'string', enum: ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY']),
        new OA\Property(property: 'period_no', type: 'integer'),
        new OA\Property(property: 'room_id', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TimetableEntryResponse',
    properties: [
        new OA\Property(property: 'timetable_entry_id', type: 'integer'),
        new OA\Property(property: 'section_id', type: 'integer'),
        new OA\Property(property: 'subject_id', type: 'integer'),
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'day_of_week', type: 'string', enum: ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY']),
        new OA\Property(property: 'period_no', type: 'integer'),
        new OA\Property(property: 'room_id', type: 'string', nullable: true),
        new OA\Property(property: 'version_no', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'PUBLISHED']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AttendanceRecordCreateRequest',
    required: ['student_id', 'timetable_entry_id', 'attendance_date', 'state'],
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'timetable_entry_id', type: 'integer'),
        new OA\Property(property: 'attendance_date', type: 'string', format: 'date'),
        new OA\Property(property: 'state', type: 'string', enum: ['PRESENT', 'ABSENT', 'LATE']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AttendanceRecordResponse',
    properties: [
        new OA\Property(property: 'attendance_record_id', type: 'integer'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'timetable_entry_id', type: 'integer'),
        new OA\Property(property: 'attendance_date', type: 'string', format: 'date'),
        new OA\Property(property: 'state', type: 'string', enum: ['PRESENT', 'ABSENT', 'LATE']),
        new OA\Property(property: 'marked_by', type: 'integer'),
        new OA\Property(property: 'is_locked', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AttendancePercentageResponse',
    description: 'Read-model, not a persisted entity (FR-13/BR-ATT-006).',
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'from_date', type: 'string', format: 'date'),
        new OA\Property(property: 'to_date', type: 'string', format: 'date'),
        new OA\Property(property: 'percentage', type: 'number', format: 'float'),
        new OA\Property(property: 'is_exam_eligibility_at_risk', type: 'boolean'),
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
