---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-014 record)
relates-to: Appendix-C BR-HR-004 (Leave Balance Validation, override authority); ADR-008 §7 (override authority decided as "HR role, logged"); ADR-011 §5 (named this as one of fourteen out-of-scope Appendix-C items, since no Controller/Service in this codebase enforced role-based authorization at all)
---

# ADR-015: BR-HR-004 override authority — first real RBAC enforcement

## Context

Stage 1+2 built a real login/JWT/Role system: every request already
carries a decoded `role_id` and `permission_set` (a `list<string>` of
permission strings from the `Role.permission_set` JSON column) into
`App\Core\Http\RequestContext`, set once per request by
`JwtAuthFilter`. ADR-008 §7 decided BR-HR-004's override authority — who
may approve a leave request that pushes the computed balance below
zero — as "HR role, logged," reusing the established
`override_reason`/`AuditLog::ACTION_OVERRIDE` pattern. But that
decision was never enforced: `LeaveRequestService::decide()` accepted
any authenticated caller's `override_reason`, checked only that the
string was non-empty. ADR-011 §5 named this gap explicitly while
surveying Appendix-C's Configuration candidates: "this codebase has
never wired role-based authorization into a Controller." This ADR closes
that gap for BR-HR-004, the only place a role-gated action was ever
decided but left unenforced.

This is a narrower ADR than ADR-005 through ADR-014 — one decision, not
a module pass — because the RBAC data model itself (`Role.permission_set`)
was already fully designed and built in Stage 1+2; nothing new needed
designing at the entity level, only the first enforcement wiring.

## Decision

### 1. Permission-string check, not role-name check

`Role.permission_set` is already a JSON array of arbitrary permission
strings, not a fixed role-name enum — no seeded/default role names
(`"HR"`, `"Admin"`) exist anywhere in this codebase; every role is
admin-created data. Gating on a specific permission string
(`hr_payroll.leave.override`, a new constant
`LeaveRequestService::PERMISSION_OVERRIDE`) fits that existing design
directly: an admin grants the permission to whichever role(s) they
consider "HR," rather than this codebase hardcoding a role name that
admin-managed data could rename or never create. This also needs no
extra database query — `permission_set` is already decoded into
`RequestContext` from the JWT on every request.

### 2. Enforcement point: `LeaveRequestService::decide()`, not the Controller

The check sits in the Service, immediately after the existing
"is `override_reason` non-empty" check, only when the projected balance
is actually negative — approving within balance, or rejecting, needs no
permission. A caller without `PERMISSION_OVERRIDE` who supplies
`override_reason` against a negative balance gets a 403
(`AuthorizationException`, `OVERRIDE_NOT_PERMITTED`), not a silent
fallback to the plain `INSUFFICIENT_LEAVE_BALANCE` 422 — the caller
tried to override and was refused, which is a distinct, auditable
outcome from never having tried.

### 3. No broader RBAC sweep

This ADR does not retrofit permission checks onto any other
Controller/Service — every other endpoint in this codebase currently
relies on authentication alone (a valid JWT), matching every prior
stage's scope discipline (ADR-009 §13, ADR-010 precedent) against
speculative additions. BR-HR-004 was the one place a role-gated
decision was already made and documented; this ADR enforces exactly
that one decision, not a general authorization pass.

## Consequences

- `LeaveRequestService::PERMISSION_OVERRIDE` = `'hr_payroll.leave.override'`
  is the first permission string this codebase actually checks — a
  precedent for any future role-gated action to reuse the same
  `RequestContext::permissionSet()` + `AuthorizationException` shape.
- `docs/ADR/ADR-011-configuration-entity-scope-decisions.md`'s BR-HR-004
  entry is unaffected — this is enforcement of an already-decided
  authority, not a `Configuration` row (the authority is "does this role
  have this permission," not a tunable scalar).
- The existing override-success test (`LeaveRequestTest`) needed
  updating: the default test role's `permission_set`
  (`['read','create','update','delete']`) does not include the new
  permission, so it now explicitly creates a role carrying
  `PERMISSION_OVERRIDE` (renamed
  `testApprovalSucceedsOverBalanceWithOverrideReasonAndPermission`). A
  new test, `testApprovalRejectedOverBalanceWithoutOverridePermission`,
  covers the 403 case for a caller without it.
