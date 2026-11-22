# Manager & Finance View-All on Penilaian Harian — Design

**Date:** 2026-11-22
**Issue:** #233

## Intent

Manager (`ID_JABATAN = 34`) and Finance (`ID_JABATAN = 0`) are cross-unit roles.
They should be able to view every active employee on `/penilaian/absen`,
including themselves. When they have no right to evaluate a given employee,
the page must show the data read-only with a warning, exactly like the SPV
view from #232.

## Goals

- Role 0 and 34 see all active employees across all units plus themselves.
- A target they cannot evaluate is selectable and viewable read-only.
- Read-only targets show the "Mode baca saja" banner and the daily grid,
  without the date input or save button.
- Targets they can evaluate keep the existing edit flow.
- `save_absen` still rejects writes outside their authorization.
- Other roles keep their current behavior.

## Non-goals

- No schema, route, permission, or evaluator-matrix change.
- No change to `isInScope` globally.
- No change to KPI scoring, payroll, or refund/koreksi flows.
- Roles 1 and 2 already see all employees and are left untouched.

## Constraints

- `isInScope` is shared by other pages and write guards; broadening it would
  change them.
- `allowedComponentCodes` is already the server-side editability source and
  comes from `canEvaluateComponent`.
- `getScopeUnits()` already returns `null` (all units) for roles 0, 1, 2, 34.
- The SPV read-only path added in #232 already exists and can be reused.

## Approaches

1. **Broaden `isInScope` for 0/34.** Rejected: it would grant other pages and
   write guards a broader scope than intended.
2. **Extend `AbsenEmployeeScopeService` with a view-all branch.** Chosen:
   page-specific view scope, list validated server-side, editability still
   driven by `allowedComponentCodes`.
3. **Separate read-only page.** Rejected: duplicates the UI and splits one
   workflow.

## Design

### Service

Extend `App\Services\Kpi\AbsenEmployeeScopeService`:

- `isViewAllRole(int $role): bool` — true for `0` and `34`.
- `daftarKaryawanSemua(): array` — all active, non-deleted employees, joined
  with `jabatan`/`unit`, ordered by unit then name. Includes every role, so
  the viewer appears naturally.

### Controller (`penilaian_absen`)

- `$isViewAll = AbsenEmployeeScopeService::isViewAllRole($myRole);`
- Employee list:
  - SPV (40): `daftarKaryawanSpv()` (unchanged).
  - View-all (0/34): `daftarKaryawanSemua()`.
  - Others: existing matrix builder (unchanged).
- `$allowedViewIds` is built from the final list and used to validate
  `?karyawan=`, same as SPV.
- Target gate: keep `isInScope`, but for view-all roles accept the target when
  its ID is in `$allowedViewIds`.
- Pass `readOnlyView => $isSpv || $isViewAll`.

### Controller (`save_absen`)

- Keep the existing `isInScope` gate and per-component authorization.
- When the target is in the viewer's list but not writable, return
  "Anda hanya dapat melihat data pegawai ini, tidak dapat mengisi penilaian."
  for roles 0/34 as well as 40.

### View

No structural change: `readOnlyView` already drives the warning and the
read-only ledger. Roles 0/34 simply opt into it.

## Testing

- Service test: `daftarKaryawanSemua()` returns all active employees (across
  units, including self) and excludes inactive/deleted rows.
- Service test: `isViewAllRole()` is true for 0/34, false for 40/35/1.
- Existing view test already covers the read-only banner/ledger and the
  non-SPV empty state.
- Targeted tests: service, read-only view, order, kehadiran form.

## Left to the builder

- Exact method names and ordering.
- Whether `canView` grows a role-agnostic variant or the controller uses list
  membership plus `isViewAllRole` for the `save_absen` message.
- Test harness details (SQLite default config).
