---
status: Accepted
date: 2026-08-07
deciders: Preetam Sinha (authority for this specific resolution delegated to design assistant, 2026-08-07 — see Context, same delegation ADR-004 through ADR-017 record)
relates-to: Appendix-C BR-FEE-002 (Finance-Team-Only Refund/Void); ADR-007 §8 (named the gap, restated the codebase's then-consistent no-RBAC posture); ADR-015 (BR-HR-004, the first place this same permission-string pattern was used)
---

# ADR-018: BR-FEE-002 (Finance-Team-only refund/void) — second RBAC enforcement

## Context

ADR-007 §8 named BR-FEE-002 explicitly: `PaymentService::voidPayment`/
`refundPayment` worked for any authenticated caller, restating (not
introducing) this codebase's then-consistent posture of relying on JWT
authentication alone, with no per-action permission checks anywhere.
ADR-015 changed that posture once, for BR-HR-004's leave-balance
override authority, establishing a reusable shape: a permission string
on `Role.permission_set`, checked via `RequestContext::permissionSet()`,
enforced in the Service layer, 403 via `AuthorizationException` on
denial. BR-FEE-002 is the same shape of gap — a named Appendix-E role
restriction ("Finance Team") on a specific, already-identified mutating
action — so this ADR applies ADR-015's exact pattern a second time
rather than inventing a new one.

## Decision

### 1. Reuse ADR-015's shape exactly

New `PaymentService::PERMISSION_VOID_REFUND =
'fees.payment.void_refund'`. `changeStatus()` (the shared private method
behind both `voidPayment()` and `refundPayment()`) now checks
`in_array(self::PERMISSION_VOID_REFUND, RequestContext::permissionSet(), true)`
before anything else runs, throwing `AuthorizationException`
(`VOID_REFUND_NOT_PERMITTED`, 403) on denial — same check shape, same
exception class, same enforcement point (Service, not Controller) as
`LeaveRequestService::decide()`.

### 2. Still no broader RBAC sweep

ADR-015 §3 already declined a general authorization pass; this ADR
declines it again. BR-FEE-002 was picked because — like BR-HR-004 — it
is a specific, already-named-in-Appendix-C authority gap with an obvious
enforcement point, not because a systematic sweep began. Every other
mutating endpoint in every module still relies on authentication alone.
A genuine "wire RBAC into every Appendix-E-named restriction" pass
remains a separate, larger, explicitly-scoped future effort — flagged
to the project owner as such, not started piecemeal under cover of a
single-rule fix.

## Consequences

- `PaymentService::PERMISSION_VOID_REFUND` is the second permission
  string this codebase actually checks (after
  `LeaveRequestService::PERMISSION_OVERRIDE`), reinforcing the
  `RequestContext::permissionSet()` + `AuthorizationException` shape as
  the established pattern for any future single-rule RBAC fix.
- Existing test (`PaymentTest::testVoidingAPaymentDoesNotReopenTheInvoice`)
  updated to grant the new permission explicitly (default test role's
  `['read','create','update','delete']` set doesn't include it). Two new
  tests: a refund success case with the permission granted, and a void
  rejection case without it.
- Appendix-E's remaining named role restrictions across other modules
  (Academic Head approvals, Teacher-only creates, etc., per ADR-007 §8's
  own survey) remain unenforced — unchanged by this ADR, and not
  silently expanded into.
