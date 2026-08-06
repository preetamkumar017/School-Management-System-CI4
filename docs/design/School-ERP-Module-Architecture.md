---
status: Living reference — amended by ADRs, not edited ad hoc
last-updated: 2026-08-06
supersedes: MDB Part-1 §6 (module tree), BMDD Part-1 §3 (module list) — both archived, Spring-Boot-era
references: RGD v2.0 §12/§13, Appendix-G Data Dictionary v1.0, ADR-001, ADR-002, ADR-003, Company Development Standard
---

# School ERP — Module Architecture & Boundaries

## Purpose

This is the single current-state reference for School ERP's backend module
list, namespace, and entity ownership. It replaces the module trees that used
to live in MDB Part-1 §6 and BMDD Part-1 §3 — both were written for the
discontinued Spring Boot implementation and are now archived in
`docs/_archive/`.

**This document is amended by ADRs, not edited directly for ownership
changes.** When an ADR reassigns an entity or adds a module (as ADR-001 did
for `Academic` and ADR-002 did for `Guardian`), this document's table is
updated to match, and the ADR is cited as the reason. If this table and an
ADR ever disagree, the ADR wins — update this table.

Entity list and IDs (`ENT-*`) are sourced from Appendix-G (Data Dictionary
v1.0), which remains the authoritative business data catalogue. This document
adds only the module/namespace/ownership layer on top of it.

## Cross-module dependency rule

Per the Company Development Standard (§1.1, §3, §11): a module may call
another module's Service class directly — its public methods are the
contract — but never reaches into another module's Model or Entity. Dependency
direction never cycles between two modules; a genuine bidirectional need is
handled through an explicit design decision (see ADR-003 for how Admission↔SIS
was resolved), not by allowing both directions to depend on each other.

## Module table

| Module | Namespace | Owned entities | Design status | Governing ADR(s) |
|---|---|---|---|---|
| Administration | `App\Modules\Administration` | User (`ENT-SYS-001`), Role (`ENT-SYS-002`), AuditLog (`ENT-SYS-004`), Configuration (`ENT-SYS-005`), Document (`ENT-SYS-006`), ApprovalRequest (`ENT-SYS-007`) | **Minimal slice designed** (`User`/`Role`/`AuditLog` only — see `docs/design/administration/`); `Configuration`/`Document`/`ApprovalRequest` not yet designed, deferred per `School-ERP-Development-Roadmap.md` Stage 1 | ADR-002 (confirms no `GuardianService` belongs here) |
| Admission | `App\Modules\Admission` | Application (`ENT-ADM-001`), SeatAllocation (`ENT-ADM-002`) | Fully designed — see `docs/design/admission/Phase-1` through `Phase-7` | ADR-003, ADR-004 |
| Academic | `App\Modules\Academic` | AcademicSession (`ENT-ACAD-001`), Class (`ENT-ACAD-002`), Section (`ENT-ACAD-003`), Subject (`ENT-ACAD-004`), GradingScheme (`ENT-ACAD-005`), ClassSubjectMap (junction) | Designed — see `docs/design/academic/` | ADR-001 |
| SIS (Student Information) | `App\Modules\Sis` | Student (`ENT-SIS-001`), Guardian (`ENT-SYS-003` — reassigned from Administration, see below), StudentGuardianLink (junction) | Fully designed — see `docs/design/sis/` | ADR-002, ADR-003, ADR-004, DG-SIS-001 (Resolved) |
| Attendance | `App\Modules\Attendance` | AttendanceRecord (`ENT-ATT-001`), StaffAttendanceRecord (`ENT-ATT-002`) | Not yet designed | — |
| Timetable | `App\Modules\Timetable` | TimetableEntry (`ENT-TT-001`) | Not yet designed | — |
| Examination | `App\Modules\Examination` | Exam (`ENT-EXM-001`), MarksRecord (`ENT-EXM-002`), ReportCard (`ENT-EXM-003`), PromotionRecord (`ENT-EXM-004`) | Designed — see `docs/design/examination/` | ADR-002 (BR-SIS-004 deferred here, resolved by ADR-005), ADR-005 |
| Fees | `App\Modules\Fees` | FeeHead (`ENT-FEE-001`), FeeStructure (`ENT-FEE-002`), Invoice (`ENT-FEE-003`), Payment (`ENT-FEE-004`), ScholarshipWaiver (`ENT-FEE-005`) | Not yet designed | — |
| Library | `App\Modules\Library` | Book (`ENT-LIB-001`), BookIssue (`ENT-LIB-002`) | Not yet designed | — |
| Transport | `App\Modules\Transport` | Route (`ENT-TRN-001`), Vehicle (`ENT-TRN-002`), TransportAllocation (`ENT-TRN-003`) | Not yet designed | — |
| HR & Payroll | `App\Modules\HrPayroll` | Employee (`ENT-HR-001`), Department (`ENT-HR-002`), Designation (`ENT-HR-003`), PayrollRun (`ENT-HR-004`), LeaveRequest (`ENT-HR-005`) | Not yet designed | — |
| Communication | `App\Modules\Communication` | Circular (`ENT-COM-001`), NotificationLog (`ENT-COM-002`) | Not yet designed | — |
| Reports | `App\Modules\Reports` | None (read-only; aggregates across other modules' Service classes, never their Models directly) | Not yet designed | — |

13 modules total: 11 original business modules (Admission, SIS, Attendance,
Timetable, Examination, Fees, Library, Transport, HR & Payroll, Communication,
Reports) + Administration (shared) + Academic (added by ADR-001).

## Known reassignment: Guardian

Appendix-G's Entity Catalogue Index files `Guardian` (`ENT-SYS-003`) under the
"Cross-Cutting / System" module heading, alongside the six genuine
cross-cutting entities. ADR-002 found this to be a documentation
inconsistency — Guardian has exactly one owning FR/BR pair and a single
non-polymorphic relationship to `Student`, unlike true system entities — and
assigned it to SIS instead. The table above reflects ADR-002's decision, not
Appendix-G's literal module column; Appendix-G itself is not edited by this
document.

## Out-of-scope entities — do not implement without a new approved FR

Appendix-G separately lists seven entities named during requirement gathering
that have **no corresponding approved Functional Requirement** in RGD v2.0:
`Hostel`, `HostelRoom`, `Visitor`, `DisciplineRecord`, `Complaint`,
`RecruitmentRequisition`, `Event`. These are explicitly flagged in Appendix-G
as stubs, not requirements. No module owns them, and none should be created
for them until a real FR exists — treat any request to implement one of these
as a requirement gap, not a design task.
