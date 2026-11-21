# SPV Team View on Penilaian Harian — Design

**Date:** 2026-11-21
**Issue:** #232

## Intent

SPV (`ID_JABATAN = 40`) supervises branches through `spv_units`, but
`/penilaian/absen` only lists employees whose jabatan is in the evaluation
matrix. For SPV that means only Kepala Toko (41). The rest of the branch team is
invisible, even though SPV should be able to see their attendance data.

Show the SPV the whole team in their supervised units. For employees the SPV is
not authorized to evaluate, keep the data visible read-only and warn that the
SPV cannot fill it.

## Goals

- SPV sees all active employees in `spv_units` plus themselves.
- An unauthorized employee can still be selected and viewed.
- Unauthorized view shows a "mode baca saja" warning and the daily grid,
  without the date input or save button.
- Authorized employees (Kepala Toko) keep the existing edit flow.
- `save_absen` still rejects writes for unauthorized targets, with a clearer
  message.
- Other roles keep their current behavior.

## Non-goals

- No schema change, route change, or new permission.
- No change to the evaluator matrix (`EvaluatorAuthorizationService`).
- No change to `isInScope` globally; it is used by other pages and write guards.
- No change to refund/koreksi, payroll, or KPI scoring.

## Constraints

- `isInScope` is shared by `index`, `insert_penilaian`, `guardLegacyPenilaian`,
  `penilaian_absen`, and `save_absen`. Broadening it would change other pages.
- `allowedComponentCodes` is already the server-side source of truth for
  editability and is computed with `canEvaluateComponent`.
- The daily grid markup currently lives inside the editable-only branch.

## Approaches

1. **Broaden `isInScope` for SPV.** Rejected: it would affect other pages and
   write guards, and would blur view scope with write authorization.
2. **Page-specific view scope + existing read-only flag.** Chosen: build the SPV
   list from `spv_units`, validate the selected target against that list, and
   let `allowedComponentCodes` drive the read-only UI. `save_absen` keeps using
   `isInScope`/`canEvaluateComponent`.
3. **Separate read-only page or route.** Rejected: duplicates the UI and splits
   one workflow across two pages.

## Design

### Employee list

In `PenilaianKPI::penilaian_absen`, add an SPV branch before the existing
matrix branch:

- `$isSpv = $myRole === 40`.
- For SPV, filter `akun` with `ID_UNIT IN $scopeUnits` **or**
  `ID_AKUN = $myId`, still requiring `STATUS_PEGAWAI = 1` and not deleted.
- `$scopeUnits` comes from `getScopeUnits(40, $myUnit, $myId)` (already reads
  `spv_units`, fallback session unit).
- Non-SPV branches stay unchanged.

### Target selection

- Build `$allowedViewIds` from the final `$list_karyawan`.
- For SPV, if `?karyawan=` is not in `$allowedViewIds`, ignore it and fall back
  to the first valid employee.
- For SPV, a target is viewable when its ID is in `$allowedViewIds`.
- For non-SPV, keep `isInScope`.

### Read-only UI

- `allowedComponentCodes` stays the editability source. For an unauthorized
  employee it is empty.
- Move the daily ledger (`pa-ledger`) outside the editable-only conditional so
  it always renders when a target exists.
- If `allowedCount > 0`: render the existing form and ledger.
- If `allowedCount === 0`: render a compact `pa-flash--warn` banner ("Mode baca
  saja — Anda tidak berwenang mengisi penilaian pegawai ini. Data tetap dapat
  dilihat.") and the ledger, with no date input, selects, or save button.
- Add a `.pa-flash--warn` style using the existing `--pa-warn` /
  `--pa-warn-soft` tokens.

### Write guard

- `save_absen` keeps `isInScope` as the first gate and
  `canEvaluateComponent` per component.
- When the target is in the SPV's area but not writable, return a clear error:
  "Anda hanya dapat melihat data pegawai ini, tidak dapat mengisi penilaian."
- No write path is added for unauthorized targets.

## Testing

- Pure helper test for the SPV view-scope decision (in-area, out-of-area, self,
  non-SPV).
- View render test: unauthorized target renders the warning + ledger and no save
  form; authorized target renders the form and no warning.
- Targeted controller test if feasible for the SPV list and `?karyawan=` guard.

## Left to the builder

- Exact helper name and placement.
- Whether the warning uses `pa-flash--warn` or another existing token.
- Test harness details (SQLite vs MySQL) following the repo's test config.
