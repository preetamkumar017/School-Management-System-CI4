---
status: Approved (Original)
last-updated: 2026-08-06
references: Phase 1 — Domain Model, Phase 2 — Model Design
---

# Phase 3 — Academic DTO Design

Convention: one Response DTO per entity; a `Create…Request`/`Update…Request` pair with CI4 Validation rules. Plain PHP classes in `App\Modules\Academic\DTOs`, per the Company Development Standard's response-envelope rule (DTOs only, never the raw Entity). Mapping between DTO and Entity is handled inline within each Service method — Academic's entities are simple enough (no field renames, no derived fields beyond what's noted below) that a dedicated Mapper class per entity would be pure ceremony; this is a deliberate scope reduction from the pattern SIS used, per the Company Development Standard's guidance to avoid unnecessary layering where it doesn't earn its keep.

## `CreateAcademicSessionRequest` / `UpdateAcademicSessionRequest`

| Field | Validation rule |
|---|---|
| session_name | `required`, format `YYYY-YY` |
| start_date | `required`, valid date, before `end_date` |
| end_date | `required`, valid date, after `start_date` |

`status` excluded from both — set to `PLANNED` at creation, changed only through a dedicated `AcademicSessionStatusChangeRequest` (below), never a plain field edit, since forward-only lifecycle transitions are a Service-layer concern per the Company Development Standard.

## `AcademicSessionStatusChangeRequest`

| Field | Validation rule |
|---|---|
| status | `required`, in `{PLANNED, ACTIVE, CLOSED, ARCHIVED}` |

Forward-only transition, enforced in the Service layer (BR-SIS-001).

## `AcademicSessionResponse`

Fields: `academic_session_id`, `session_name`, `start_date`, `end_date`, `status`.

## `CreateClassRequest` / `UpdateClassRequest`

| Field | Validation rule |
|---|---|
| class_name | `required`, max length 20 |
| sequence_order | `required`, integer |

## `ClassResponse`

Fields: `class_id`, `class_name`, `sequence_order`.

## `CreateSectionRequest` / `UpdateSectionRequest`

| Field | Validation rule |
|---|---|
| class_id | `required` |
| section_name | `required`, max length 10 |
| capacity | `required`, positive integer |

`class_id` is immutable after creation in every documented flow that references `Section` — no approved document describes moving a `Section` to a different `Class`; if that becomes a real need later, it is a new dedicated transition DTO, not a plain field edit on `UpdateSectionRequest`.

## `SectionResponse`

Fields: `section_id`, `class_id`, `section_name`, `capacity`.

## `CreateSubjectRequest` / `UpdateSubjectRequest`

| Field | Validation rule |
|---|---|
| subject_name | `required`, non-empty |
| subject_code | `required`, max length 10, unique |

## `SubjectResponse`

Fields: `subject_id`, `subject_name`, `subject_code`.

## `CreateGradingSchemeRequest`

| Field | Validation rule |
|---|---|
| scheme_name | `required`, max length 50 |
| board_type | `required`, in `{CBSE, ICSE, STATE_BOARD}` |
| grade_band_json | `required`, valid JSON, non-overlapping ascending bands (Service-layer structural check) |

## `UpdateGradingSchemeRequest` — decided (Phase 4)

| Field | Validation rule |
|---|---|
| board_type | `required`, in `{CBSE, ICSE, STATE_BOARD}` |
| grade_band_json | `required`, valid JSON, non-overlapping ascending bands |

Same field list as create. Per Phase 4's decision: this mutates the existing row only if no closed `Exam` references it; otherwise the Service layer rejects the update and the caller creates a new `GradingScheme` instead (a new `scheme_name`). No separate versioning shape exists — this DTO never produces a new row itself.

## `GradingSchemeResponse`

Fields: `grading_scheme_id`, `scheme_name`, `board_type`, `grade_band_json`.

## `ClassSubjectMapRequest`

| Field | Validation rule |
|---|---|
| class_id | `required` |
| subject_id | `required` |

Single request class — the mapping has no independently editable multi-field lifecycle. Duplicate-mapping rejection is a Service-layer check.

## `ClassSubjectMapResponse`

Fields: `class_id`, `subject_id`.
