# Manager & Finance View-All on Penilaian Harian — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Manager (34) and Finance (0) see every active employee plus themselves on `/penilaian/absen`, with read-only warnings for employees they cannot evaluate.

**Architecture:** Extend `AbsenEmployeeScopeService` with a view-all role check and an all-employee query. `PenilaianKPI::penilaian_absen` uses that list for roles 0/34 and reuses the existing `readOnlyView` flag from #232. `save_absen` keeps its write guards and reuses the read-only message.

**Tech Stack:** CodeIgniter 4.4, PHP 7.4/8.x, PHPUnit 9, MySQLi/SQLite test DB, Bootstrap 5.

**Spec:** `docs/superpowers/specs/2026-11-22-manager-finance-absen-view-all-design.md`

## Global Constraints

- View-all roles are `ID_JABATAN = 0` (Finance) and `34` (Manager).
- "All employees" means active (`STATUS_PEGAWAI = 1`), non-deleted, across all units, including the viewer.
- No schema, route, permission, or evaluator-matrix change.
- Do not broaden `PenilaianKPI::isInScope`; it is shared by other pages and write guards.
- `allowedComponentCodes` remains the editability source; `save_absen` keeps `isInScope`/`canEvaluateComponent`.
- Roles other than 0/34 (and the existing SPV path) must not change.
- No commit unless the user explicitly asks.

## Review Focus

1. Finance/Manager must not lose their existing editable targets (Finance: HQ + Manager; Manager: Finance/SPV/Kadiv/IT) — test the list includes those and editability is untouched.
2. Inactive and deleted employees must stay hidden — covered by the service test.
3. `?karyawan=` outside the all-employee list must not open data — controller reuses the `$allowedViewIds` guard.
4. Read-only targets must show the warning and hide the save form — existing view test.
5. Other roles must keep the old list and empty state — existing view test plus unchanged controller branch.

---

### Task 1: View-all service methods

**Files:**
- Modify: `app/Services/Kpi/AbsenEmployeeScopeService.php`
- Test: `tests/unit/AbsenEmployeeScopeServiceTest.php`

**Interfaces:**
- Produces: `AbsenEmployeeScopeService::isViewAllRole(int $role): bool` — true for 0 and 34.
- Produces: `AbsenEmployeeScopeService::daftarKaryawanSemua(): array` — active, non-deleted employees with `ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN, NAMA_JABATAN, NAMA_UNIT`, ordered by `ID_UNIT ASC`, `NAMA_AKUN ASC`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/unit/AbsenEmployeeScopeServiceTest.php`:

```php
public function testDaftarKaryawanSemuaMengembalikanPegawaiAktifLintasUnit(): void
{
    $rows = (new AbsenEmployeeScopeService($this->db))->daftarKaryawanSemua();
    $ids = array_map(static fn ($r) => (int) $r['ID_AKUN'], $rows);
    sort($ids);

    // 405 nonaktif dan 406 terhapus tidak boleh muncul; 400 (viewer) ikut.
    $this->assertSame([400, 401, 402, 403, 404], $ids);
}

public function testIsViewAllRoleHanyaUntukFinanceDanManager(): void
{
    $this->assertTrue(AbsenEmployeeScopeService::isViewAllRole(0));
    $this->assertTrue(AbsenEmployeeScopeService::isViewAllRole(34));
    $this->assertFalse(AbsenEmployeeScopeService::isViewAllRole(40));
    $this->assertFalse(AbsenEmployeeScopeService::isViewAllRole(35));
    $this->assertFalse(AbsenEmployeeScopeService::isViewAllRole(1));
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/AbsenEmployeeScopeServiceTest.php`
Expected: FAIL with "Call to undefined method" / method not found.

- [ ] **Step 3: Implement the methods**

In `AbsenEmployeeScopeService`:

- Add `public const VIEW_ALL_ROLES = [0, 34];`.
- `isViewAllRole(int $role): bool` returns `in_array($role, self::VIEW_ALL_ROLES, true)`.
- `daftarKaryawanSemua(): array` reuses the same select/joins/active filter as `daftarKaryawanSpv` but without the unit/self filter, ordered by `ID_UNIT ASC`, `NAMA_AKUN ASC`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/AbsenEmployeeScopeServiceTest.php`
Expected: PASS (5 tests).

---

### Task 2: Controller wiring, docs, and verification

**Files:**
- Modify: `app/Controllers/PenilaianKPI.php` (`penilaian_absen`, `save_absen`)
- Modify: `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md`

**Interfaces:**
- Consumes: `AbsenEmployeeScopeService::isViewAllRole()`, `daftarKaryawanSemua()`.

- [ ] **Step 1: Wire `penilaian_absen`**

- After `$isSpv = $myRole === 40;` add `$isViewAll = \App\Services\Kpi\AbsenEmployeeScopeService::isViewAllRole($myRole);`.
- Change the list branch to:
  - `if ($isSpv) { daftarKaryawanSpv(...) }`
  - `elseif ($isViewAll) { $list_karyawan = $absenScope->daftarKaryawanSemua(); }`
  - `else { existing matrix builder }`
- Validate `?karyawan=` against `$allowedViewIds` for `$isSpv || $isViewAll`.
- Target gate: keep `isInScope`; accept the target when `($isSpv || $isViewAll)` and the ID is in `$allowedViewIds`.
- Pass `'readOnlyView' => $isSpv || $isViewAll`.

- [ ] **Step 2: Wire `save_absen` message**

Keep the existing `isInScope` gate. When the target exists and `isViewAllRole($myRole)` is true, or the existing SPV `canView(...)` check passes, use the message:
`Anda hanya dapat melihat data pegawai ini, tidak dapat mengisi penilaian.`

- [ ] **Step 3: Update PRD Modul 08**

Add rule 26 after rule 25: Manager (34) and Finance (0) see all active employees across units plus themselves on `/penilaian/absen`; employees they cannot evaluate are read-only with the "Mode baca saja" warning; `save_absen` remains guarded; other roles unchanged. Reference `AbsenEmployeeScopeService`, `PenilaianKPI::penilaian_absen/save_absen`, and the service/view tests.

- [ ] **Step 4: Run the targeted suite**

Run each file separately (PHPUnit 9 only runs the first file argument):

```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/AbsenEmployeeScopeServiceTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenReadOnlyViewTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenOrderTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenKehadiranFormTest.php
```

Expected: all PASS.

- [ ] **Step 5: Syntax and diff checks**

```bash
php -l app/Services/Kpi/AbsenEmployeeScopeService.php
php -l app/Controllers/PenilaianKPI.php
git diff --check
```

Expected: no syntax errors, diff check clean.

- [ ] **Step 6: Report**

Report changed files, tests and results, and the remaining caveat: no commit was made. Do not claim success without actual output.
