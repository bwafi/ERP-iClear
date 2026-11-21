# SPV Branch Filter on Service Pages — Design

**Date:** 2026-11-21
**Issue:** #231

## Intent

SPV (`ID_JABATAN = 40`) supervises branches through `spv_units`, but
`/proses_service`, `/riwayat_service`, and `/dp_service` currently lock them to
their session unit. Give SPV a unit filter limited to the branches they
supervise.

## Goals

- SPV sees a unit dropdown on all three pages, populated from `spv_units`.
- "Semua unit" means every branch the SPV supervises.
- Server validates the requested unit against the SPV's branch list.
- Other roles keep their current behavior.

## Non-goals

- No change to refund/koreksi DP authorization. Non-HO refund stays limited to
  the session unit; SPV sees the existing "Refund hanya unit sendiri" note for
  other branches.
- No schema change.
- No change to the role sets for non-SPV roles (including the existing
  `proses_service` / role 42 discrepancy documented in PRD Module 04).

## Decisions

- Branch source: `spv_units.spv_id = session ID_AKUN`, fallback to session
  `ID_UNIT` when no mapping exists.
- Dropdown only lists operational units (`is_operational`), matching the
  operational-selector rule.
- Invalid or empty `unitFilter` from an SPV falls back to all supervised
  branches. A sentinel `-1` is used when the SPV has no valid unit at all, so
  the query returns no rows instead of leaking all rows.
- DataTables `recordsTotal` is scoped to the effective unit scope so the
  "dari X" count does not expose the company-wide total.

## Approach

1. `App\Services\Service\ServiceUnitScopeService` resolves the SPV branch IDs
   and the dropdown list.
2. `Riwayat_Service` uses it in the three page methods and three AJAX methods.
3. `ModelService` and `ModelServiceDp` accept a list of unit IDs and filter with
   `IN (...)`.
4. Views use a `can_filter_units` flag so SPV gets the enabled dropdown while
   other unit-only roles stay locked.
5. Regression tests cover SPV consolidation, single-branch filtering, and
   out-of-scope rejection.
