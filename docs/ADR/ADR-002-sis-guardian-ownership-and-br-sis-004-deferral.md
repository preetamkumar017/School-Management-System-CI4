---
status: Accepted
date: 2026-08-04
deciders: Preetam Sinha
supersedes-in-spirit: Appendix-G Data Dictionary v1.0 (Guardian Module field), Appendix-H ERD v1.1 (Guardian/StudentGuardianLink module placement), BMDD Part-1 Section 6 (archived — Administration shared-service list, as it relates to Guardian; see docs/design/School-ERP-Module-Architecture.md), Appendix-C v1.1 (BR-SIS-004 module filing, pending Examination-module resolution)
---

# ADR-002: SIS owns Guardian/StudentGuardianLink; BR-SIS-004 deferred to Examination

## Context

Phase 4.1 (SIS Requirement & Domain Analysis) surfaced two architectural
inconsistencies in the approved documentation that blocked Phase 4.2 design
from proceeding without a decision:

### Guardian ownership

- Appendix-G (Data Dictionary) and Appendix-H (ERD v1.1) both file `Guardian`
  as `ENT-SYS-003` under `Module: SYS` ("Cross-Cutting / System"), alongside
  `User`, `Role`, `AuditLog`, `Configuration`, `Document`, `ApprovalRequest`.
- Unlike those six genuine cross-cutting entities — each either FR-agnostic
  or spanning multiple modules' FR/BR ranges via a polymorphic FK — Guardian
  has exactly one Related FR (`FR-06`, itself inside Student Information's
  own `FR-06`–`FR-09` range per BMDD Part-1 Section 3, archived) and exactly one
  Related BR (`BR-SIS-006`, filed under Appendix-C's own heading "Module:
  Student Information Management"), a Business Owner of Admin Staff
  (matching Student, not the IT-Admin ownership typical of true SYS
  entities), and a single dedicated (non-polymorphic) M:N relationship to
  Student only.
- BMDD Part-1 Section 6 (archived) named the Administration module's shared services
  exhaustively — `AuditLogService`, `ConfigurationService`,
  `ApprovalRequestService`, `DocumentService`, `NotificationService`,
  `UserService`, `RoleService` — and never names a `GuardianService`. The
  backend architecture document that actually governs module boundaries
  provisions no home for Guardian in Administration at all.
- This is the same derivation-gap shape ADR-001 already identified for
  Academic Master Data: a data-dictionary/ERD classification that was never
  cross-checked against the entity's actual FR/BR exclusivity and the
  backend architecture's own module/service enumeration.

### BR-SIS-004

- BR-SIS-004 ("Historical Record Immutability") is filed under Appendix-C's
  "Module: Student Information Management" and cites `FR-08` ("Maintain
  historical academic records across academic years") as its Related FR.
- SIS's sole entity, `Student` (`ENT-SIS-001`), is Master data with no
  academic-session-scoped field, no historical snapshot, and no "locked"
  state — there is nothing on Student that could "belong to a closed
  academic year" in the sense BR-SIS-004 describes.
- The entity that actually fulfills FR-08 is `PromotionRecord`
  (`ENT-EXM-004`, Module `EXM`, Owner Academic Head): its Purpose field
  reads "Enforces and documents BR-SIS-001", its Retention Period reads
  "Permanent (historical academic record, part of FR-08)", and its Related
  Business Rule is `BR-SIS-001` — never `BR-SIS-004`.
- No entity card in Appendix-G or Appendix-H lists BR-SIS-004 as its
  governing Business Rule. It appears only inside two generic cross-cutting
  round-ups — `AuditLog`'s "all immutability-related rules" list and
  `ApprovalRequest`'s override-workflow list — neither of which is an
  outcome-owning entity for the rule.
- Its three named sibling immutability rules (`BR-ATT-002` →
  `AttendanceRecord.is_locked`, `BR-EXM-003` → `MarksRecord` Locked state,
  `BR-HR-007` → Payslip/`PayrollRun`) each attach to a real Transaction-
  category entity in their own module. BR-SIS-004 has no such counterpart,
  because SIS owns no transactional/historical entity at all.

## Decision

### Guardian ownership

`Guardian` (`ENT-SYS-003`) and the `StudentGuardianLink` junction table are
treated as **SIS-owned concepts**, structurally a peer of `Student` inside
the `App\Modules\Sis` module, using the same internal layering already
established by `Admission` and `Academic` (Entity → Model → Service →
Controller, Model acting as repository, per the Company Development
Standard).

The `Module = SYS` classification in Appendix-G/Appendix-H is treated as a
documentation inconsistency, overridden by this project-architecture
decision, for the same reason ADR-001 overrode BMDD Part-1 Section 3's (archived)
silence on Academic Master Data: the entity's real FR/BR exclusivity and
the backend architecture's own service enumeration do not support the
data-dictionary tag.

`BR-SIS-006` (Guardian-Student Linkage Mandatory) is enforced **entirely
inside the `Sis` module**, against the `Student.status → Active` transition,
using `Sis`'s own Models for both `Student` and `Guardian` data.

**No cross-module `GuardianService` will exist.** Guardian is not a
dependency any other module reaches; it is internal to `Sis`, on the same
footing as `Student`.

### BR-SIS-004

BR-SIS-004 (Historical Record Immutability) is acknowledged as a
documentation inconsistency: it is filed under the SIS module but has no
enforceable subject entity anywhere in the approved entity catalogue, since
SIS's only entity is Master-category `Student`, not a historical/
transactional record.

BR-SIS-004 is **not implemented or redesigned during the SIS module build**.
It is carried forward as a **deferred architectural item**, to be resolved
during the **Examination module** (where `PromotionRecord`, `MarksRecord`,
and the other entities capable of governing "historical academic records"
actually exist). No workaround or placeholder enforcement is substituted
for it in `Sis`.

## Consequences

- Appendix-G's Guardian entity card (`Module: SYS`) and Appendix-H's
  corresponding ERD placement are out of date the moment this ADR is
  accepted; `docs/design/School-ERP-Module-Architecture.md` is the current
  source of truth for Guardian's and `StudentGuardianLink`'s module
  ownership, the same relationship ADR-001 established for `Academic`.
- The Administration module's shared-service list (formerly BMDD Part-1
  Section 6, now archived; carried forward into
  `docs/design/School-ERP-Module-Architecture.md`) remains accurate as
  written — it never claimed a `GuardianService`. It should, however, be
  read alongside this ADR so that future contributors do not infer a
  missing service and attempt to add one.
- The `Sis` module's Service layer enforces BR-SIS-006 unilaterally, with
  no other module able to create, update, or query Guardian data directly —
  any future module needing guardian/contact information (e.g.,
  Communication, for notification recipients) calls `Sis`'s own Service
  class directly, the same Service-only cross-module rule ADR-001 already
  fixed for Academic Master Data (Company Development Standard: "never on
  another module's Model/Entity directly").
- BR-SIS-004 remains formally numbered and catalogued under Appendix-C's
  SIS section; this ADR does not renumber or move it. It is simply excluded
  from the SIS module's Phase 4 implementation scope. Its resolution
  (correct module reassignment, correct governing entity, or Appendix-C
  correction) is explicitly deferred to the Examination module's own
  requirement analysis phase, not decided here.
- Phase 4.2 (SIS Design) proceeds on the basis that SIS owns two entities —
  `Student` and `Guardian` — plus the `StudentGuardianLink` junction table,
  and enforces four Business Rules directly (`BR-SIS-002`, `BR-SIS-003`,
  `BR-SIS-005`, `BR-SIS-006`), with `BR-SIS-004` explicitly out of scope.
  BR-SIS-001 remains enforced according to the approved module boundaries.
  Any Examination-module interaction will be defined during the Examination
  module design.
