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
#[OA\Schema(
    schema: 'FeeHeadRequest',
    description: 'Same shape for create and update.',
    required: ['fee_head_name', 'is_taxable'],
    properties: [
        new OA\Property(property: 'fee_head_name', type: 'string'),
        new OA\Property(property: 'is_taxable', type: 'boolean'),
        new OA\Property(property: 'gst_rate', type: 'number', format: 'float', nullable: true, description: 'Required (0-28) when is_taxable is true.'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'FeeHeadResponse',
    properties: [
        new OA\Property(property: 'fee_head_id', type: 'integer'),
        new OA\Property(property: 'fee_head_name', type: 'string'),
        new OA\Property(property: 'is_taxable', type: 'boolean'),
        new OA\Property(property: 'gst_rate', type: 'number', format: 'float', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'FeeStructureCreateRequest',
    required: ['class_id', 'fee_head_id', 'academic_session_id', 'category', 'amount'],
    properties: [
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'fee_head_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'category', type: 'string', enum: ['GENERAL', 'RTE']),
        new OA\Property(property: 'amount', type: 'number', format: 'float'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'FeeStructureUpdateRequest',
    description: 'class_id/fee_head_id/academic_session_id/category are immutable post-creation — absent here.',
    required: ['amount'],
    properties: [new OA\Property(property: 'amount', type: 'number', format: 'float')],
    type: 'object',
)]
#[OA\Schema(
    schema: 'FeeStructureResponse',
    properties: [
        new OA\Property(property: 'fee_structure_id', type: 'integer'),
        new OA\Property(property: 'class_id', type: 'integer'),
        new OA\Property(property: 'fee_head_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'category', type: 'string', enum: ['GENERAL', 'RTE']),
        new OA\Property(property: 'amount', type: 'number', format: 'float'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'InvoiceGenerateRequest',
    required: ['student_id', 'academic_session_id', 'due_date'],
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'InvoiceResponse',
    description: 'total_amount is computed server-side, never client-supplied (ADR-007 §1).',
    properties: [
        new OA\Property(property: 'invoice_id', type: 'integer'),
        new OA\Property(property: 'invoice_no', type: 'string', example: 'INV-2026-04501'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'academic_session_id', type: 'integer'),
        new OA\Property(property: 'total_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date'),
        new OA\Property(property: 'status', type: 'string', enum: ['UNPAID', 'PARTIALLY_PAID', 'PAID', 'DEFAULTER', 'CANCELLED']),
        new OA\Property(property: 'is_locked', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaymentRecordRequest',
    required: ['invoice_id', 'amount_paid', 'payment_mode'],
    properties: [
        new OA\Property(property: 'invoice_id', type: 'integer'),
        new OA\Property(property: 'amount_paid', type: 'number', format: 'float'),
        new OA\Property(property: 'payment_mode', type: 'string', enum: ['ONLINE', 'CASH', 'CHEQUE', 'BANK_TRANSFER']),
        new OA\Property(property: 'gateway_transaction_ref', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaymentResponse',
    properties: [
        new OA\Property(property: 'payment_id', type: 'integer'),
        new OA\Property(property: 'invoice_id', type: 'integer'),
        new OA\Property(property: 'amount_paid', type: 'number', format: 'float'),
        new OA\Property(property: 'payment_mode', type: 'string', enum: ['ONLINE', 'CASH', 'CHEQUE', 'BANK_TRANSFER']),
        new OA\Property(property: 'gateway_transaction_ref', type: 'string', nullable: true),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'status', type: 'string', enum: ['SUCCESS', 'FAILED', 'REFUNDED', 'VOIDED']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ScholarshipWaiverCreateRequest',
    required: ['student_id', 'fee_head_id', 'waiver_type', 'waiver_amount'],
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'fee_head_id', type: 'integer'),
        new OA\Property(property: 'waiver_type', type: 'string', enum: ['RTE', 'MERIT', 'SIBLING', 'STAFF_WARD']),
        new OA\Property(property: 'waiver_amount', type: 'number', format: 'float'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ScholarshipWaiverResponse',
    properties: [
        new OA\Property(property: 'scholarship_waiver_id', type: 'integer'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'fee_head_id', type: 'integer'),
        new OA\Property(property: 'waiver_type', type: 'string', enum: ['RTE', 'MERIT', 'SIBLING', 'STAFF_WARD']),
        new OA\Property(property: 'waiver_amount', type: 'number', format: 'float'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DepartmentRequest',
    description: 'Same shape for create and update.',
    required: ['department_name'],
    properties: [new OA\Property(property: 'department_name', type: 'string')],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DepartmentResponse',
    properties: [
        new OA\Property(property: 'department_id', type: 'integer'),
        new OA\Property(property: 'department_name', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DesignationRequest',
    description: 'Same shape for create and update.',
    required: ['designation_name'],
    properties: [new OA\Property(property: 'designation_name', type: 'string')],
    type: 'object',
)]
#[OA\Schema(
    schema: 'DesignationResponse',
    properties: [
        new OA\Property(property: 'designation_id', type: 'integer'),
        new OA\Property(property: 'designation_name', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EmployeeCreateRequest',
    required: ['employee_code', 'full_name', 'department_id', 'designation_id', 'joining_date', 'salary_structure_json'],
    properties: [
        new OA\Property(property: 'employee_code', type: 'string'),
        new OA\Property(property: 'full_name', type: 'string'),
        new OA\Property(property: 'department_id', type: 'integer'),
        new OA\Property(property: 'designation_id', type: 'integer'),
        new OA\Property(property: 'joining_date', type: 'string', format: 'date'),
        new OA\Property(property: 'salary_structure_json', type: 'object', example: ['basic' => 40000]),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EmployeeUpdateRequest',
    description: 'employee_code/joining_date are immutable post-creation — absent here. Setting exit_date triggers BR-HR-002.',
    required: ['full_name', 'department_id', 'designation_id', 'salary_structure_json'],
    properties: [
        new OA\Property(property: 'full_name', type: 'string'),
        new OA\Property(property: 'department_id', type: 'integer'),
        new OA\Property(property: 'designation_id', type: 'integer'),
        new OA\Property(property: 'salary_structure_json', type: 'object', example: ['basic' => 42000]),
        new OA\Property(property: 'exit_date', type: 'string', format: 'date', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'EmployeeResponse',
    properties: [
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'employee_code', type: 'string'),
        new OA\Property(property: 'full_name', type: 'string'),
        new OA\Property(property: 'department_id', type: 'integer'),
        new OA\Property(property: 'designation_id', type: 'integer'),
        new OA\Property(property: 'joining_date', type: 'string', format: 'date'),
        new OA\Property(property: 'exit_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'salary_structure_json', type: 'object'),
        new OA\Property(property: 'status', type: 'string', enum: ['Active', 'Exited']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PayrollRunCreateRequest',
    description: 'deductions_json is caller-supplied, not auto-calculated (ADR-008 §8).',
    required: ['employee_id', 'pay_period', 'gross_pay', 'deductions_json'],
    properties: [
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'pay_period', type: 'string', example: '2026-07', description: 'YYYY-MM'),
        new OA\Property(property: 'gross_pay', type: 'number', format: 'float'),
        new OA\Property(property: 'deductions_json', type: 'object', example: ['PF' => 1800]),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PayrollRunResponse',
    properties: [
        new OA\Property(property: 'payroll_run_id', type: 'integer'),
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'pay_period', type: 'string'),
        new OA\Property(property: 'gross_pay', type: 'number', format: 'float'),
        new OA\Property(property: 'deductions_json', type: 'object'),
        new OA\Property(property: 'net_pay', type: 'number', format: 'float'),
        new OA\Property(property: 'status', type: 'string', enum: ['Draft', 'Approved', 'Processed']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LeaveRequestCreateRequest',
    required: ['employee_id', 'leave_type', 'start_date', 'end_date'],
    properties: [
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'leave_type', type: 'string', enum: ['CL', 'SL', 'EL']),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LeaveRequestDecideRequest',
    description: 'override_reason is required only if approval would take the balance below zero (BR-HR-004).',
    required: ['decision'],
    properties: [
        new OA\Property(property: 'decision', type: 'string', enum: ['Approved', 'Rejected']),
        new OA\Property(property: 'override_reason', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'LeaveRequestResponse',
    properties: [
        new OA\Property(property: 'leave_request_id', type: 'integer'),
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'leave_type', type: 'string', enum: ['CL', 'SL', 'EL']),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date'),
        new OA\Property(property: 'status', type: 'string', enum: ['Pending', 'Approved', 'Rejected']),
        new OA\Property(property: 'approver_id', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StaffAttendanceRecordCreateRequest',
    required: ['employee_id', 'attendance_date', 'state'],
    properties: [
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'attendance_date', type: 'string', format: 'date'),
        new OA\Property(property: 'state', type: 'string', enum: ['Present', 'On Leave', 'Unauthorized']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StaffAttendanceRecordResponse',
    properties: [
        new OA\Property(property: 'staff_attendance_id', type: 'integer'),
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'attendance_date', type: 'string', format: 'date'),
        new OA\Property(property: 'state', type: 'string', enum: ['Present', 'On Leave', 'Unauthorized']),
        new OA\Property(property: 'is_reconciled', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StaffAttendanceReconcileRequest',
    description: 'BR-ATT-005 — cross-checks the range against Leave.',
    required: ['employee_id', 'from_date', 'to_date'],
    properties: [
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'from_date', type: 'string', format: 'date'),
        new OA\Property(property: 'to_date', type: 'string', format: 'date'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'StaffAttendanceClosePeriodRequest',
    description: 'BR-HR-001 — pushes a one-way closure record into HR & Payroll (ADR-008 §4).',
    required: ['employee_id', 'pay_period'],
    properties: [
        new OA\Property(property: 'employee_id', type: 'integer'),
        new OA\Property(property: 'pay_period', type: 'string', example: '2026-07', description: 'YYYY-MM'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BookCreateRequest',
    required: ['barcode', 'title', 'classification'],
    properties: [
        new OA\Property(property: 'barcode', type: 'string'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'author', type: 'string', nullable: true),
        new OA\Property(property: 'classification', type: 'string', enum: ['Circulating', 'Reference']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BookUpdateRequest',
    description: 'barcode is immutable post-creation — absent here.',
    required: ['title', 'classification'],
    properties: [
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'author', type: 'string', nullable: true),
        new OA\Property(property: 'classification', type: 'string', enum: ['Circulating', 'Reference']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BookResponse',
    properties: [
        new OA\Property(property: 'book_id', type: 'integer'),
        new OA\Property(property: 'barcode', type: 'string'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'author', type: 'string', nullable: true),
        new OA\Property(property: 'classification', type: 'string', enum: ['Circulating', 'Reference']),
        new OA\Property(property: 'is_available', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BookIssueCreateRequest',
    required: ['book_id', 'borrower_type', 'borrower_ref_id', 'due_date'],
    properties: [
        new OA\Property(property: 'book_id', type: 'integer'),
        new OA\Property(property: 'borrower_type', type: 'string', enum: ['Student', 'Employee']),
        new OA\Property(property: 'borrower_ref_id', type: 'integer'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'BookIssueResponse',
    properties: [
        new OA\Property(property: 'book_issue_id', type: 'integer'),
        new OA\Property(property: 'book_id', type: 'integer'),
        new OA\Property(property: 'borrower_type', type: 'string', enum: ['Student', 'Employee']),
        new OA\Property(property: 'borrower_ref_id', type: 'integer'),
        new OA\Property(property: 'issue_date', type: 'string', format: 'date'),
        new OA\Property(property: 'due_date', type: 'string', format: 'date'),
        new OA\Property(property: 'return_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'fine_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'status', type: 'string', enum: ['Issued', 'Returned', 'Lost']),
        new OA\Property(property: 'replacement_charge_amount', type: 'number', format: 'float'),
        new OA\Property(property: 'fine_settled', type: 'boolean'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'VehicleCreateRequest',
    required: ['registration_no', 'capacity'],
    properties: [
        new OA\Property(property: 'registration_no', type: 'string'),
        new OA\Property(property: 'capacity', type: 'integer'),
        new OA\Property(property: 'gps_device_id', type: 'string', nullable: true),
        new OA\Property(property: 'license_valid_until', type: 'string', format: 'date', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'VehicleUpdateRequest',
    description: 'registration_no is immutable post-creation — absent here.',
    required: ['capacity'],
    properties: [
        new OA\Property(property: 'capacity', type: 'integer'),
        new OA\Property(property: 'gps_device_id', type: 'string', nullable: true),
        new OA\Property(property: 'license_valid_until', type: 'string', format: 'date', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'VehicleResponse',
    properties: [
        new OA\Property(property: 'vehicle_id', type: 'integer'),
        new OA\Property(property: 'registration_no', type: 'string'),
        new OA\Property(property: 'capacity', type: 'integer'),
        new OA\Property(property: 'gps_device_id', type: 'string', nullable: true),
        new OA\Property(property: 'license_valid_until', type: 'string', format: 'date', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'RouteCreateRequest',
    required: ['route_name', 'stops_json', 'capacity'],
    properties: [
        new OA\Property(property: 'route_name', type: 'string'),
        new OA\Property(property: 'stops_json', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'capacity', type: 'integer'),
        new OA\Property(property: 'vehicle_id', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'RouteUpdateRequest',
    description: 'route_name is immutable post-creation — absent here.',
    required: ['stops_json', 'capacity'],
    properties: [
        new OA\Property(property: 'stops_json', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'capacity', type: 'integer'),
        new OA\Property(property: 'vehicle_id', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'RouteResponse',
    properties: [
        new OA\Property(property: 'route_id', type: 'integer'),
        new OA\Property(property: 'route_name', type: 'string'),
        new OA\Property(property: 'stops_json', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'capacity', type: 'integer'),
        new OA\Property(property: 'vehicle_id', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TransportAllocationCreateRequest',
    required: ['student_id', 'route_id', 'stop_name', 'emergency_contact'],
    properties: [
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'route_id', type: 'integer'),
        new OA\Property(property: 'stop_name', type: 'string'),
        new OA\Property(property: 'emergency_contact', type: 'string', description: '10-digit numeric (BR-TRN-004)'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'TransportAllocationResponse',
    properties: [
        new OA\Property(property: 'transport_allocation_id', type: 'integer'),
        new OA\Property(property: 'student_id', type: 'integer'),
        new OA\Property(property: 'route_id', type: 'integer'),
        new OA\Property(property: 'stop_name', type: 'string'),
        new OA\Property(property: 'emergency_contact', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['Active', 'Waitlisted', 'De-allocated']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CircularCreateRequest',
    required: ['author_id', 'post_type', 'title', 'body', 'target_audience'],
    properties: [
        new OA\Property(property: 'author_id', type: 'integer'),
        new OA\Property(property: 'post_type', type: 'string', enum: ['Homework', 'Circular', 'Announcement']),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'body', type: 'string'),
        new OA\Property(property: 'target_audience', type: 'string', example: 'Class 6-A'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CircularResponse',
    properties: [
        new OA\Property(property: 'circular_id', type: 'integer'),
        new OA\Property(property: 'author_id', type: 'integer'),
        new OA\Property(property: 'post_type', type: 'string', enum: ['Homework', 'Circular', 'Announcement']),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'body', type: 'string'),
        new OA\Property(property: 'target_audience', type: 'string'),
        new OA\Property(property: 'posted_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'status', type: 'string', enum: ['Posted', 'Retracted']),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'NotificationLogCreateRequest',
    required: ['recipient_type', 'recipient_ref_id', 'channel', 'trigger_event'],
    properties: [
        new OA\Property(property: 'recipient_type', type: 'string', enum: ['Guardian', 'Employee', 'User']),
        new OA\Property(property: 'recipient_ref_id', type: 'integer'),
        new OA\Property(property: 'channel', type: 'string', enum: ['SMS', 'Email', 'Push']),
        new OA\Property(property: 'trigger_event', type: 'string', example: 'BR-ATT-004 absence'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'NotificationLogFailRequest',
    required: ['failure_reason'],
    properties: [new OA\Property(property: 'failure_reason', type: 'string')],
    type: 'object',
)]
#[OA\Schema(
    schema: 'NotificationLogResponse',
    properties: [
        new OA\Property(property: 'notification_log_id', type: 'integer'),
        new OA\Property(property: 'recipient_type', type: 'string', enum: ['Guardian', 'Employee', 'User']),
        new OA\Property(property: 'recipient_ref_id', type: 'integer'),
        new OA\Property(property: 'channel', type: 'string', enum: ['SMS', 'Email', 'Push']),
        new OA\Property(property: 'trigger_event', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['Queued', 'Dispatched', 'Delivered', 'Failed']),
        new OA\Property(property: 'dispatched_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'failure_reason', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SummaryResponse',
    description: 'Composed entirely from other modules\' already-existing list-all Service methods (ADR-010 §7).',
    properties: [
        new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'total_users', type: 'integer'),
        new OA\Property(property: 'total_classes', type: 'integer'),
        new OA\Property(property: 'total_academic_sessions', type: 'integer'),
        new OA\Property(property: 'total_departments', type: 'integer'),
        new OA\Property(property: 'total_designations', type: 'integer'),
        new OA\Property(property: 'total_employees', type: 'integer'),
        new OA\Property(property: 'total_books', type: 'integer'),
        new OA\Property(property: 'books_available', type: 'integer'),
        new OA\Property(property: 'total_vehicles', type: 'integer'),
        new OA\Property(property: 'total_routes', type: 'integer'),
        new OA\Property(property: 'total_fee_heads', type: 'integer'),
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
