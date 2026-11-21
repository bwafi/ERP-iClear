# SPV Branch Filter on Service Pages — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let SPV filter `/proses_service`, `/riwayat_service`, and `/dp_service` by the branches mapped to them in `spv_units`.

**Architecture:** A small scope service resolves the SPV branch IDs. Controllers validate the requested filter against that list and pass a single unit ID or a list of IDs to models, which filter with `IN (...)`. Views show the existing unit dropdown when the role may filter.

**Tech Stack:** CodeIgniter 4.4.8, PHP 8, MySQLi, PHPUnit, Bootstrap/DataTables.

**Spec:** `docs/superpowers/specs/2026-11-21-spv-service-unit-filter-design.md`

## Global Constraints

- Issue #231.
- Do not change refund/koreksi DP authorization.
- Do not change schema.
- Server must never trust `unitFilter`; validate against the SPV branch list.
- Keep other roles' behavior unchanged.
- Tests use the isolated test database from `phpunit.xml`.

## Review Focus

- An SPV with no `spv_units` rows must fall back to the session unit, not to all rows.
- An SPV sending a unit ID outside the branch list must not see that unit.
- A role with `ID_UNIT = 0` must not fall through to "all units".
- Unit names containing a comma must still resolve as a single unit.
- The DataTables total must match the scoped data, not the company total.

---

### Task 1: Multi-unit filtering in `ModelService`

**Files:**
- Modify: `app/Models/ModelService.php:256` (`resolveUnitFilter` area), `:280`, `:364`, `:849`, `:959`
- Test: `tests/unit/ModelServiceUnitFilterListTest.php`

**Interfaces:**
- Produces: `ModelService::resolveUnitFilterIds($unitFilter): array<int>` — accepts `''`, `'2'`, `'1,2'`, `[1,2]`, a unit name, or `-1`; returns normalized non-zero IDs.
- Produces: `getRiwayatServiceTotal($unitFilter = '')`, `ProsesServiceAktifTotal($unitFilter = '')`.

- [ ] **Step 1: Write the failing test**

Create `tests/unit/ModelServiceUnitFilterListTest.php` with tables `service`, `pelanggan`, `unit`; rows in units 1, 2, 3. Assert:
- `resolveUnitFilterIds('')` returns `[]`
- `resolveUnitFilterIds('2')` returns `[2]`
- `resolveUnitFilterIds('1,2')` returns `[1,2]`
- `resolveUnitFilterIds([1,2])` returns `[1,2]`
- `resolveUnitFilterIds('Unit Satu')` returns `[1]`
- `resolveUnitFilterIds('-1')` returns `[-1]`
- `getRiwayatServiceServerSide(..., [1,2])` returns only units 1 and 2
- `getRiwayatServiceTotal([1,2])` returns 2
- `ProsesServiceAktifServerSide(..., [-1])` returns no rows
- `ProsesServiceAktifTotal([1,2])` counts only active services in units 1 and 2

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ModelServiceUnitFilterListTest.php`
Expected: FAIL because `resolveUnitFilterIds` does not exist.

- [ ] **Step 3: Implement `resolveUnitFilterIds` and use it**

In `ModelService`, add `resolveUnitFilterIds($unitFilter): array` next to `resolveUnitFilter()`:
- array input → `array_map('intval')`, drop zeros, unique
- comma-separated numeric string → same
- single signed integer (`/^-?\d+$/`) → `[int]` unless zero
- unit name → `resolveUnitFilter()` result
- empty/unknown → `[]`

Replace the single-unit `where` blocks in `getRiwayatServiceServerSide()` and
`ProsesServiceAktifServerSide()` with a shared `IN (?, ?, ...)` placeholder
block using the returned IDs. Keep `resolveUnitFilter()` unchanged for other
callers.

Update `getRiwayatServiceTotal($unitFilter = '')` and
`ProsesServiceAktifTotal($unitFilter = '')` to apply the same `IN` filter before
`countAllResults()`; empty IDs keep the existing all-rows behavior.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/ModelServiceUnitFilterListTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ModelService.php tests/unit/ModelServiceUnitFilterListTest.php
git commit -m "feat(service): support multi-unit filters in service listings (refs #231)"
```

---

### Task 2: SPV scope service and DP Service

**Files:**
- Create: `app/Services/Service/ServiceUnitScopeService.php`
- Modify: `app/Controllers/Riwayat_Service.php:982-1094`
- Modify: `app/Models/ModelServiceDp.php:106-211`
- Modify: `app/Views/riwayat/dp_service.php:30-52`, `:416-435`
- Test: `tests/DpServiceUnitFilterTest.php`

**Interfaces:**
- Produces: `ServiceUnitScopeService::isSpv(): bool`
- Produces: `ServiceUnitScopeService::spvUnitIds(): array<int>` (fallback session unit, `[]` only when neither exists)
- Produces: `ServiceUnitScopeService::spvUnits(): array<object>` (operational units for the dropdown)
- Produces: `ServiceUnitScopeService::resolveSpvFilter($requested): array<int>` (valid single branch, else all branches, else `[-1]`)
- Produces: `ModelServiceDp::datatable(..., int|array|null $unitId, ...)`.

- [ ] **Step 1: Extend the failing feature test**

In `tests/DpServiceUnitFilterTest.php`:
- add `spv_units` to the drop/create lists
- seed jabatan `40`, akun `400` (unit 1), mapping `400 → units 1 and 2`, and a third unit with a service + DP row
- add tests:
  - SPV with `unitFilter = ''` gets rows from units 1 and 2 only, and `recordsTotal` equals that scoped count
  - SPV with `unitFilter = 2` gets unit 2 only
  - SPV with `unitFilter = 3` gets units 1 and 2 only
  - `/dp_service` renders an enabled `dpUnitFilter` containing branches 1 and 2 but not 3

Run: `vendor/bin/phpunit -c phpunit.xml tests/DpServiceUnitFilterTest.php`
Expected: FAIL because SPV is still locked to unit 1.

- [ ] **Step 2: Implement `ServiceUnitScopeService`**

Create the service with the four methods above. Read `ID_AKUN` and `ID_UNIT`
from the session, query `spv_units` (guard `tableExists`), and fall back to the
session unit. `spvUnits()` loads units by ID and keeps those with
`(int) ($unit->is_operational ?? 1) === 1`, sorted by `NAMA_UNIT`.
`resolveSpvFilter()` returns `[$requested]` only when it is in `spvUnitIds()`;
otherwise it returns `spvUnitIds()` or `[-1]` when empty.

- [ ] **Step 3: Wire `dp_service()` and `dp_service_ajax()`**

- `dp_service()`: add `$isSpv`; `$canFilterUnits = $isHO || $isSpv`; pass
  `can_filter_units`; use `spvUnits()` for the SPV dropdown; keep the refund
  bank list based on the full unit list so own-unit refunds still work.
- `dp_service_ajax()`: for SPV use `resolveSpvFilter()`; other branches keep the
  current HO/session-unit logic.
- `ModelServiceDp::datatable()`: normalize `int|array|null` into a non-empty ID
  list and apply `whereIn('s.unit_idunit', $ids)` to the total, filtered, and
  data builders. `-1` must remain a real ID so it yields zero rows.

- [ ] **Step 4: Update the DP view flag**

In `dp_service.php`, derive `$canFilter = !empty($can_filter_units) || $isHO`
and use it for the `disabled` attribute, the "Semua Unit" option, and the reset
handler.

- [ ] **Step 5: Run the feature test**

Run: `vendor/bin/phpunit -c phpunit.xml tests/DpServiceUnitFilterTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Service/ServiceUnitScopeService.php app/Controllers/Riwayat_Service.php app/Models/ModelServiceDp.php app/Views/riwayat/dp_service.php tests/DpServiceUnitFilterTest.php
git commit -m "feat(service): let SPV filter DP service by supervised branch (refs #231)"
```

---

### Task 3: Proses Service and Riwayat Service

**Files:**
- Modify: `app/Controllers/Riwayat_Service.php:89-148`, `:913-979`
- Modify: `app/Views/riwayat/proses_service.php:1-65`
- Modify: `app/Views/riwayat/service.php:20-35`, `:450-455`
- Test: `tests/DpServiceUnitFilterTest.php`, `tests/ProsesServiceViewTest.php`

**Interfaces:**
- Consumes: `ServiceUnitScopeService` from Task 2 and the model signatures from Task 1.

- [ ] **Step 1: Add failing feature tests**

In `tests/DpServiceUnitFilterTest.php`, add:
- SPV `/proses_service` renders the `unitFilter` select and the AJAX endpoint
  returns active services from units 1 and 2 only
- SPV `/riwayat_service` renders an enabled select and the AJAX endpoint
  returns units 1 and 2 only when no filter is sent
- SPV sending unit 3 to either AJAX endpoint never returns unit 3

Run: `vendor/bin/phpunit -c phpunit.xml tests/DpServiceUnitFilterTest.php`
Expected: FAIL because SPV is still locked to unit 1.

- [ ] **Step 2: Wire the page and AJAX methods**

- `index()`: add `$isSpv`; use `spvUnits()` for the SPV dropdown; pass
  `can_filter_units`.
- `proses_service()`: same, keeping the operational-only HO list.
- `riwayat_service_ajax()` and `proses_service_ajax()`: for SPV replace the
  forced session unit with `resolveSpvFilter($request->getPost('unitFilter'))`;
  pass the same value to the listing and total methods.
- For non-SPV roles with `ID_UNIT <= 0`, pass `-1` instead of `0` so an invalid
  session cannot fall through to "all units".

- [ ] **Step 3: Update the views**

- `proses_service.php`: `$showUnitFilter` uses `can_filter_units` falling back to
  `is_ho`.
- `service.php`: replace the local `$isHO` gate with
  `$canFilter = !empty($can_filter_units) || $isHO`; keep own-unit
  preselection only when the role cannot filter.

- [ ] **Step 4: Run the feature and view tests**

Run: `vendor/bin/phpunit -c phpunit.xml tests/DpServiceUnitFilterTest.php tests/ProsesServiceViewTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Controllers/Riwayat_Service.php app/Views/riwayat/proses_service.php app/Views/riwayat/service.php tests/DpServiceUnitFilterTest.php tests/ProsesServiceViewTest.php
git commit -m "feat(service): let SPV filter proses and riwayat service by branch (refs #231)"
```

---

### Task 4: Documentation and verification

**Files:**
- Modify: `docs/prd/modul/PRD_MODUL_04_Service_Garansi.md:58`

- [ ] **Step 1: Update the PRD rule**

Extend the "Filter unit Service" rule: SPV (`40`) may filter only the branches
in `spv_units` (fallback session unit); "Semua Unit" means all supervised
branches; refund/koreksi authorization is unchanged. Mark the evidence with the
controller/service/model paths.

- [ ] **Step 2: Run static checks**

Run:
```bash
php -l app/Services/Service/ServiceUnitScopeService.php
php -l app/Controllers/Riwayat_Service.php
php -l app/Models/ModelService.php
php -l app/Models/ModelServiceDp.php
git diff --check
```

- [ ] **Step 3: Run the focused suites**

Run:
```bash
vendor/bin/phpunit -c phpunit.xml tests/DpServiceUnitFilterTest.php tests/ProsesServiceViewTest.php tests/unit/ModelServiceUnitFilterListTest.php tests/unit/ModelServiceDpListingTest.php
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add docs/prd/modul/PRD_MODUL_04_Service_Garansi.md
git commit -m "docs(prd): document SPV branch filter on service pages (refs #231)"
```
