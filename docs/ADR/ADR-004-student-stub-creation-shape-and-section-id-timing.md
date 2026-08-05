---
status: Accepted
date: 2026-08-06
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-06 — see Context)
relates-to: ADR-003 (established who triggers/who persists); resolves DG-SIS-001; supersedes-in-spirit Phase 4.2/4.4/4.5/4.6/4.7 (SIS), Phase 6/7/8/9/10 Open items
---

# ADR-004: `section_id` is nullable at stub creation; concrete SIS stub-creation contract; single-transaction Confirm Enrollment

## Context

ADR-003 settled *who* triggers and *who* persists `Student` stub creation
(Admission triggers, SIS persists) but left several things explicitly
unshaped: `section_id`'s creation-time nullability (DG-SIS-001), the concrete
SIS Service method's input/output shape, the transaction boundary across the
Admission→SIS call, and whether the "compensating behavior" question Phase
6/7/8 flagged even applies. These blocked `StudentService::createStudent`,
`StudentService::changeStatus`'s BR-SIS-003 portion, and the corresponding
Controller endpoints — the single largest readiness gap Phase 9 identified.

DG-SIS-001 sat open through Phase 13 awaiting a Requirement Owner decision.
On 2026-08-06 the Requirement Owner (Preetam Sinha) delegated authority to
resolve this and the related undesigned items, instructing that outstanding
decisions be made rather than left pending. This ADR is that resolution,
reasoned from the documents already approved — it introduces no requirement
that isn't already implied by FR-02, FR-06, and Appendix-G, and where a
choice was genuinely open (not implied either way), that choice is stated
explicitly as a decision, not disguised as a derivation.

Evidence reviewed:

- FR-02's Main Flow (§10) and Post Conditions (§13) list the stub's contents
  as exactly "Admission Number record; stub Student Master record" — nothing
  about Class/Section.
- FR-06's Main Flow (§10) begins "Admin Staff opens the stub student record"
  and its completeness gate (§16) checks Class/Section before the
  Draft→Active transition (BR-SIS-003).
- Appendix-G's `Student` attribute catalogue marks `section_id` Mandatory: Y
  — but Appendix-G's Mandatory column, read against BR-SIS-003's own purpose
  (a completeness check gating a *later* lifecycle transition), most
  naturally means "required for a complete/Active record," not "NOT NULL
  from the moment of insert." `Student.status` already has a `DRAFT` value
  specifically to model "exists, but incomplete" — a reading that requires
  every Mandatory-Y field to be NOT NULL at creation would make `DRAFT`
  meaningless, since nothing distinguishes it from `ACTIVE`.
- Appendix-G's `Application` attribute catalogue (ENT-ADM-001) already
  carries `applicant_name` (Mandatory Y) and `dob` (Mandatory Y) — the exact
  two fields Phase 4.2 flagged as an unaddressed "nullability tension"
  because they remained `NOT NULL` on `Student` with no stated source at
  stub-creation time. Once Admission is the party constructing the stub
  (ADR-003), the source is obvious: these two fields are copied from the
  `Application` that's being confirmed, not left for Admin Staff to fill in
  later. The "tension" was never a real design gap — it only looked like one
  because no document had yet stated where a stub's `full_name`/`dob` come
  from.
- FR-02 §7's precondition references an "Approved" `Application` status that
  doesn't exist in the documented enum (`SUBMITTED`, `VERIFIED`,
  `SHORTLISTED`, `WAITLISTED`, `ADMITTED`, `REJECTED` — confirmed again
  against Appendix-G in `docs/design/admission/Phase-1`). Both `SHORTLISTED`
  and `WAITLISTED` represent an application that has cleared verification and
  is in a positive, pre-admission track — either is what a reasonable person
  would call "approved" in ordinary language, and FR-02 never distinguishes
  between them for the purpose of triggering Confirm Enrollment.
- The Company Development Standard (§4.1) fixes a single MySQL schema with
  module-grouped tables, not schema-per-module — Admission's and SIS's
  tables live in the same database, reachable within one connection.

## Decision

### 1. `section_id` is nullable; DG-SIS-001 is resolved

`students.section_id` is `NULL`-able, default `NULL`. It is not supplied at
stub creation and carries no value until Admin Staff assigns it during FR-06
profile completion (or later, during a genuine section transfer — both use
the same operation, see §3). `BR-SIS-003`'s completeness check enforces
`section_id IS NOT NULL` as a precondition of the `DRAFT → ACTIVE` transition
only — it is a Service-layer check at that transition, not a database
constraint on the column itself.

Answering Phase 12's four clarification questions directly:

- **Q1** (known at creation or later?): Later.
- **Q2** (who supplies it, and how?): Admin Staff, via the same
  section-assignment operation used for later transfers (see §3) — invoked
  during FR-06 profile completion for a stub's first assignment.
- **Q3** (can a stub exist without one?): Yes — that is what `DRAFT` status
  is for.
- **Q4** (which source document is right?): Neither is wrong; Appendix-G's
  "Mandatory: Y" describes a complete-record requirement enforced by
  BR-SIS-003 at the Active transition, not a from-creation database
  constraint. No source document requires correction.

### 2. `full_name`/`dob` are populated from the confirmed `Application`

The stub-creation call carries `full_name` and `dob` copied directly from the
`Application` being confirmed (`Application.applicant_name` →
`Student.full_name`, `Application.dob` → `Student.dob`). Both remain `NOT
NULL` on `Student` exactly as Phase 4.2 originally specified — no schema
change results from this ADR, only a stated source for values Phase 4.2 left
unaddressed. The "nullability tension" Phase 9 §B2 flagged is closed: it was
never a real gap, only an undocumented data source.

### 3. Concrete SIS stub-creation contract

`StudentService::createStudentStub(array $data): array` (internal, called
only from `AdmissionService`'s Confirm Enrollment orchestration — see §5;
**not** a public API endpoint; `StudentController` exposes no `POST /`
create route).

Required input: `application_id`, `admission_number`, `full_name`, `dob`,
`category`. Optional input: `aadhaar_number`. Explicitly **not** part of the
input: `section_id` (§1), `medical_info` (remains `NULL` until FR-06
completion, unaffected by this ADR).

Output: the created `student_id`, together with the persisted field values —
resolving Phase 7 §8's Open item ("whether output includes the created
Student's identifier") as yes, it does; there is no reason to withhold it and
Admission's own orchestration benefits from having it.

`StudentService::changeStatus`'s `DRAFT → ACTIVE` transition performs the
BR-SIS-003 completeness check (now well-defined: `full_name`, `dob`,
`section_id` all non-null) and the BR-SIS-006 guardian check (unaffected,
already designed). This is no longer Open.

### 4. Section assignment is one operation, not two

`StudentSectionTransferRequest` (Phase 4.4) is used for **both** a stub's
first section assignment and any later transfer — resolving the dual-use
question Phase 4.4/4.6 left open. Both are, structurally, "set this
student's current section, subject to BR-SIS-005's capacity check against
the destination section" — there is no behavioral difference between
"assigning" an absent value and "changing" a present one that justifies two
operations.

### 5. Transaction boundary: single local transaction, no compensating action needed

Because Admission's and SIS's tables share one MySQL schema (Company
Development Standard §4.1), the entire Confirm Enrollment sequence — seat
capacity/RTE re-validation, `SeatAllocation` counter increment (pessimistic
row lock, per `docs/design/admission/Phase-4`), `StudentService::createStudentStub`,
and the `Application.status → ADMITTED` transition — runs inside **one**
database transaction, opened and committed by `AdmissionService`'s Confirm
Enrollment method. If `createStudentStub` throws for any reason, the whole
transaction rolls back: the seat-count increment and the status transition
never persist. This resolves Phase 6 §5 / Phase 7 §9–10 / Phase 8 §7's Open
items together — a single local transaction makes "compensating behavior on
SIS-side failure" a non-issue, since there is nothing to compensate for
after an atomic rollback. A distributed transaction or saga/compensating-
action pattern is unnecessary here and is not adopted.

### 6. Confirm Enrollment's trigger precondition

FR-02 §7's "Application status is 'Approved'" is read as referring to either
`SHORTLISTED` or `WAITLISTED` — both represent an application past
verification and on a positive track toward admission; FR-02 does not
distinguish between them for this purpose, and nothing in the approved
documentation suggests a candidate must have been `SHORTLISTED` specifically
(as opposed to later confirmed off a waitlist) to be admitted. Confirm
Enrollment is **not** a new operation distinct from existing Admitted-decision
handling — it is the existing `SHORTLISTED`/`WAITLISTED` → `ADMITTED`
transition on `Application` (`docs/design/admission/Phase-4`'s
`ApplicationService`), extended with Admission Number generation, seat/RTE
re-validation, and the SIS stub-creation call described above. No separate
"Confirm Enrollment" endpoint exists apart from this transition.

## Consequences

- `docs/design/sis/DG-SIS-001.md` is marked Resolved, citing this ADR.
- `docs/design/sis/Phase-13-...md`'s Decision section is completed, citing
  this ADR as the resolution record (the Requirement Owner delegated the
  decision rather than answering the four questions directly; the answers in
  §1 above are what would have gone in that section).
- `docs/design/sis/Phase-4.2` through `Phase-4.7`, `Phase-4-Closure-Report`,
  and `Phase-5-Implementation-Plan` are revised to remove every Open item
  this ADR resolves. `docs/design/admission/Phase-6`,
  `docs/design/sis/Phase-7`, `docs/design/Phase-8/9/10` are revised the same
  way.
- `StudentController` never gets a public create endpoint — this is a
  meaningful change from what several Open drafts assumed (an independent
  SIS creation endpoint reachable directly); it does not exist and should not
  be added without a new ADR revisiting this decision.
- The `GradingScheme` versioning question (Academic Phase 4/5) and the
  data-retention/archival threshold (`School-ERP-Database-Supplement.md`)
  are separate open items, not addressed by this ADR.
