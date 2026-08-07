---
status: Approved (Original)
last-updated: 2026-08-07
references: Appendix-G ENT-SYS-005, Appendix-C v1.1 §3.5, ADR-011
---

# Phase 7 — Configuration Entity, Model, DTO, Service, Controller Design

Combined into one doc (unlike a full new module's Phase 1-5 split) —
this is a small, single-entity addition to Administration, not a new
module.

## Entity: `Configuration` (ENT-SYS-005, table `configurations`)

Extends `App\Core\BaseEntity`.

| Field | Type | Null | Default | Constraint / BR |
|---|---|---|---|---|
| setting_key | VARCHAR(100) | N | – | Unique, dot-notation namespaced |
| setting_value | VARCHAR(500) | N | – | Parsed per `data_type` |
| data_type | enum (`String`, `Number`, `Boolean`, `Date`) | N | – | |
| module | VARCHAR(30) | N | – | Owning module name |
| is_editable | BOOLEAN | N | TRUE | |

Unique constraint: `setting_key`.

### Lifecycle

Created at implementation (seeded by migration, ADR-011 §2) → Modified
by IT Admin as policy changes → versioned via `AuditLog`, never deleted.

## `ConfigurationModel`

| Method | Purpose |
|---|---|
| `findByKey(string $key): ?Configuration` | The one lookup every consuming Service's typed accessor is built on |

## DTOs

`UpdateConfigurationRequest`: `setting_value`. `ConfigurationResponse`:
`setting_id`, `setting_key`, `setting_value`, `data_type`, `module`,
`is_editable`.

## `ConfigurationService`

| Operation | Reason |
|---|---|
| `getNumber(string $key): float` | Typed read for `data_type = Number` settings — every consuming Service in ADR-011 §4 uses this, not raw `setting_value` parsing. |
| `getString(string $key): string` | Typed read for `data_type = String`. |
| `getBoolean(string $key): bool` | Typed read for `data_type = Boolean`. |
| `updateByKey(string $key, UpdateConfigurationRequest): ConfigurationResponse` | Rejects `CONFIGURATION_NOT_EDITABLE` if `is_editable = false` (ADR-011 §3); otherwise updates `setting_value`. |
| `getConfiguration(string $key): ConfigurationResponse` | Plain read. |
| `listByModule(string $module): array` | A module's own settings. |

`getNumber`/`getString`/`getBoolean` throw `BusinessRuleException`
(`CONFIGURATION_NOT_FOUND`) if the key is missing — every key this pass
depends on is seeded by migration, so this should only ever surface a
real deployment/seeding gap, not a normal runtime path.

## Controller — base path `/api/v1/administration/configurations`

| Endpoint | Purpose |
|---|---|
| `PATCH /{key}` | Update a setting's value (IT Admin) — no `POST /`, per ADR-011 §2. |
| `GET /{key}` | Plain read. |
| `GET /?module=` | A module's settings. |

## Conclusion

Every consuming Service named in ADR-011 §4 switches from a private
class constant to `ConfigurationService::getNumber()` in this same pass
— the constants are removed, not left dead alongside the new lookup.
