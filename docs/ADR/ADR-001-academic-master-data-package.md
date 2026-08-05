---
status: Accepted
date: 2026-08-04
deciders: Preetam Sinha
supersedes-in-spirit: BMDD Part-1 Section 3 / MDB Part-1 Section 6 (both archived, Spring-Boot-era — see docs/design/School-ERP-Module-Architecture.md, the current module reference)
---

# ADR-001: Academic Master Data gets its own `Academic` module

## Context

BMDD Part-1 Section 3 and MDB Part-1 Section 6 (both since archived — Spring
Boot-era documents, superseded by `docs/design/School-ERP-Module-Architecture.md`)
both enumerated a fixed backend module tree at the time: the 11 business
modules from RGD v2.0 Section 12 (Admission, Student Information, Attendance,
Timetable, Examination, Fees, Library, Transport, HR & Payroll, Communication,
Reports) plus one shared `Administration` module (Audit, Config, Approval,
Document, Notification, User, Role). Neither document mentioned an `Academic`
module, and Appendix-K Section 18's module-wise API catalogue still has no
Academic subsection either.

At the same time:

- RGD v2.0 Section 13 ("Master Data List") separately lists Academic Year
  Master, Class/Section Master, and Subject Master, each with a stated Data
  Owner (Academic Head) — distinct from the Section 12 business-module list.
- Appendix-G (Data Dictionary) and Appendix-H (ERD v1.1) both model this as a
  full entity group under `Module: ACAD`, entity prefix `ENT-ACAD`:
  `AcademicSession` (ENT-ACAD-001), `Class` (ENT-ACAD-002), `Section`
  (ENT-ACAD-003), `Subject` (ENT-ACAD-004), `GradingScheme` (ENT-ACAD-005),
  plus the `ClassSubjectMap` junction table.
- DDD Part-2 Section 15 (archived along with the rest of DDD; historical
  citation only) stated each of these has a Business Owner (Academic Head)
  "authorized to create and update records" — implying real CRUD behind a
  Service/Model layer, not static seed data.
- DDD Part-2 Section 19 (Entity Dependency Matrix, same archived source)
  showed Academic Master Data is depended on by multiple modules: Admission
  (Class), Examination (Class, Subject, GradingScheme, AcademicSession), Fees
  (AcademicSession), HR & Payroll (indirectly, via Attendance) — the same
  multi-module ownership shape as `Role`, which *did* get an explicit home in
  `Administration`.
- The already-planned Admission module (Phase 2) assumes an Academic
  module will exist: Admission's own `AcademicSessionService` is documented
  as "owned by the not-yet-implemented Academic module," and
  `Application.classAppliedId` / `SeatAllocation.classId` /
  `SeatAllocation.academicSessionId` are documented as forward references
  to Academic-module entities.

## Decision

Treat the absence of an `Academic` module in BMDD Part-1 §3 / MDB Part-1 §6
(both since archived) as a **derivation gap**, not an intentional exclusion:
those two documents appear to have copied RGD §12's business-module list without
cross-referencing RGD §13's master-data list for master data that (like
`Role`) is owned by more than one consuming module and therefore needs its
own module rather than nesting inside a single owner.

`Academic` is added as a 12th backend module (`App\Modules\Academic`),
structurally a peer to the existing 11 modules and to `Administration`,
using the same internal layering fixed by the Company Development Standard
(Controller → Service → Model/Entity, DTOs as plain PHP classes/arrays
where needed) — the same layering already anticipated for `Admission`. It
owns the five `ENT-ACAD-*` entities and the `ClassSubjectMap` junction
table. Other modules depend on it only by calling its Service classes
directly — never its Models/Entities — per the Company Development
Standard's cross-module rule (§1.1, §11: "Cross-module calls go through the
other module's Service only, never its Model/Entity"), the same pattern
`Admission`'s `AcademicSessionService` placeholder already anticipates.

`Academic` carries no FR range of its own (Appendix-G already marks
`AcademicSession` "FR: N/A — implicit foundational master data"), consistent
with how it's classified as master data rather than a workflow module.

## Consequences

- BMDD Part-1 §3 and MDB Part-1 §6's module trees are archived and no longer
  the reference; `docs/design/School-ERP-Module-Architecture.md` is the
  current source of truth for the `Academic` module's existence and the full
  module list.
- Appendix-K §18 still has no Academic API-group subsection. The Academic
  module's Controller layer will need endpoint definitions that don't yet
  exist in Appendix-K; those should be treated as a documentation
  follow-up, not blocking implementation, since Appendix-G/H already specify
  the entities and fields those endpoints operate on.
- Future modules that also depend on Academic Master Data (Examination,
  Fees, HR & Payroll) should consume it the same way Admission does: by
  calling `Academic`'s Service classes directly, never its Models/Entities.
