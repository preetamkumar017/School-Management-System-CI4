---
status: Approved (Revision 2) — fully finalized
last-updated: 2026-08-06
references: Phase 4.4 Revision 2, ADR-003, ADR-004, DG-SIS-001 (Resolved)
---

# Phase 4.5 — SIS Mapper Design (Revision 2)

Convention: one mapper class per entity (a plain PHP class, `App\Modules\Sis\Mappers`); `toEntity(CreateRequest)` returns a new entity array/object leaving system-assigned/excluded fields at defaults; `updateEntity(UpdateRequest, target)` mutates the target in place; a single-field `update<X>(...)` method per dedicated transition DTO; `toResponse(Entity)` maps every Response DTO field. No reflective/generic copy utility. No Model/database access, no business-rule checks in any mapper — mapping stays a pure data-shape transform, confined to this one class per entity per the Company Development Standard's mapping rule.

## `StudentMapper`

| Method | Status | Notes |
|---|---|---|
| `toEntityFromStub(StudentStubData $data)` | **Finalized (ADR-004)** | Maps `application_id`, `admission_number`, `full_name`, `dob`, `category`, `aadhaar_number` onto a new `Student` entity; `section_id` and `medical_info` left at their `NULL` default, `status` set to `DRAFT`. Renamed from `toEntity(CreateStudentRequest)` to reflect that its input is the internal `StudentStubData` shape (Phase 4.4), not a public request DTO. |
| `updateEntity(UpdateStudentRequest, Student $target)` | Unaffected | Maps full_name, dob, category, aadhaar_number, medical_info. |
| `updateSection(StudentSectionTransferRequest, Student $target)` | Finalized (ADR-004 §4) | Single-field assignment (`section_id`); used identically for both a stub's first assignment and any later transfer. |
| `updateStatus(StudentStatusChangeRequest, Student $target)` | Unaffected | Single-field assignment (status). |
| `toResponse(Student)` | Unaffected | `StudentResponse` received no structural change. |

## `GuardianMapper` — all Unaffected

`toEntity(CreateGuardianRequest)`, `updateEntity(UpdateGuardianRequest, Guardian $target)`, `toResponse(Guardian)` — `Guardian` and its DTOs are unaffected by Phase 4.2 Revision 1 and untouched by Phase 4.4 Revision 1.

## `StudentGuardianLinkMapper` — all Unaffected

`toEntity(StudentGuardianLinkRequest)`, `toResponse(StudentGuardianLink)` — `StudentGuardianLink` and its DTOs are unaffected by Phase 4.2 Revision 1 and untouched by Phase 4.4 Revision 1.

## Conclusion

**Fully finalized.** ADR-004 resolves the one previously-Open method (`toEntityFromStub`, formerly `toEntity(CreateStudentRequest)`). Every method across all three mappers is now Finalized or Unaffected.
