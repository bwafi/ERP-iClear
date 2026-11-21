# SPV Team View on Penilaian Harian — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let SPV (40) see every active employee in their `spv_units` plus themselves on `/penilaian/absen`, with unauthorized employees visible read-only and a clear warning.

**Architecture:** A small `AbsenEmployeeScopeService` resolves the SPV branch list and the view-scope decision. `PenilaianKPI::penilaian_absen` uses it for the employee list and target validation; `save_absen` keeps the existing write guard and adds a clearer message. The view moves the daily ledger outside the editable-only branch so it renders read-only when no component is authorized.

**Tech Stack:** CodeIgniter 4.4, PHP 7.4/8.x, PHPUnit 9, MySQLi/SQLite test DB, Bootstrap 5.

**Spec:** `docs/superpowers/specs/2026-11-21-spv-penilaian-absen-readonly-design.md`

## Global Constraints

- No schema change, route change, or new permission.
- Do not broaden `PenilaianKPI::isInScope`; it is shared by other pages and write guards.
- SPV is `ID_JABATAN = 40`; branch mapping is `spv_units.spv_id = akun.ID_AKUN`, `unit_id = unit.idunit`.
- Fallback when SPV has no mapping: session `ID_UNIT`.
- Non-SPV behavior must not change.
- Refund/koreksi, payroll, KPI scoring, and evaluator matrix are untouched.
- No commit unless the user explicitly asks.

## Review Focus

1. SPV with no `spv_units` mapping must fall back to the session unit, not all employees. Test in Task 1.
2. `?karyawan=` outside the SPV area must be ignored and must not expose data. Test in Task 1.
3. POST to `save_absen` for an unauthorized target must be rejected server-side. Test in Task 1.
4. Unauthorized target must show the warning and read-only grid, with no save form. Test in Task 2.
5. Non-SPV roles must keep the existing list and edit behavior. Test in Task 1 and Task 2.

---

### Task 1: SPV scope service and controller wiring

**Files:**
- Create: `app/Services/Kpi/AbsenEmployeeScopeService.php`
- Modify: `app/Controllers/PenilaianKPI.php` (`penilaian_absen`, `save_absen`)
- Test: `tests/unit/AbsenEmployeeScopeServiceTest.php`

**Interfaces:**
- Produces: `AbsenEmployeeScopeService::__construct($db = null)`
- Produces: `AbsenEmployeeScopeService::spvUnitIds(int $spvId, int $unitSesi): array` — int list, fallback `[$unitSesi]`, empty when no unit.
- Produces: `AbsenEmployeeScopeService::daftarKaryawanSpv(int $spvId, int $unitSesi): array` — rows with `ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN, NAMA_JABATAN, NAMA_UNIT`.
- Produces: `AbsenEmployeeScopeService::canView(int $viewerRole, int $viewerId, int $employeeId, int $employeeUnit, array $allowedUnitIds): bool` — true for role 40 when employee is self or in allowed units.

- [ ] **Step 1: Write the failing service test**

Create `tests/unit/AbsenEmployeeScopeServiceTest.php` with a schema for `unit`, `akun`, `jabatan`, `spv_units`, then assert:

```php
public function testSpvMelihatTimDiDuaUnitBinaanDanDirinya(): void
{
    $rows = (new AbsenEmployeeScopeService($this->db))->daftarKaryawanSpv(400, 1);
    $ids = array_map(static fn ($r) => (int) $r['ID_AKUN'], $rows);
    sort($ids);

    $this->assertSame([400, 401, 402, 404], $ids);
}

public function testSpvTanpaMappingMemakaiUnitSesi(): void
{
    $this->db->table('spv_units')->where('spv_id', 400)->delete();
    $rows = (new AbsenEmployeeScopeService($this->db))->daftarKaryawanSpv(400, 1);
    $ids = array_map(static fn ($r) => (int) $r['ID_AKUN'], $rows);
    sort($ids);

    $this->assertSame([400, 401, 404], $ids);
}

public function testCanViewMenolakUnitDiLuarBinaan(): void
{
    $this->assertTrue(AbsenEmployeeScopeService::canView(40, 400, 402, 2, [1, 2]));
    $this->assertFalse(AbsenEmployeeScopeService::canView(40, 400, 403, 3, [1, 2]));
    $this->assertTrue(AbsenEmployeeScopeService::canView(40, 400, 400, 3, [1, 2]));
    $this->assertFalse(AbsenEmployeeScopeService::canView(35, 350, 401, 1, [1, 2]));
}
```

Seed data: units 1, 2, 3; SPV 400 in unit 1; employees 401 (unit 1), 402 (unit 2), 403 (unit 3, out of area), 404 (unit 2, inactive? keep active; use a separate inactive 405 to assert it is excluded). Mapping `(400,1),(400,2)`.

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/AbsenEmployeeScopeServiceTest.php`
Expected: FAIL with class not found.

- [ ] **Step 3: Implement `AbsenEmployeeScopeService`**

Create the service with:
- `spvUnitIds`: guard `tableExists('spv_units')`, read `spv_id`, dedupe positive ints, fallback `[$unitSesi]` when positive, else `[]`.
- `daftarKaryawanSpv`: active `akun` (`STATUS_PEGAWAI = 1`, `deleted` null/0), join `jabatan`/`unit`, filter `ID_UNIT IN unitIds OR ID_AKUN = spvId`; when unitIds empty, only self. Order by `ID_UNIT ASC`.
- `canView`: role must be 40; self true; otherwise `in_array($employeeUnit, array_map('intval', $allowedUnitIds), true)`.

- [ ] **Step 4: Run the service test to verify it passes**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/AbsenEmployeeScopeServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Wire `penilaian_absen`**

In `PenilaianKPI::penilaian_absen`:
- Add `$isSpv = $myRole === 40;`.
- Instantiate `$absenScope = new \App\Services\Kpi\AbsenEmployeeScopeService($this->db);`.
- If `$isSpv`, set `$list_karyawan = $absenScope->daftarKaryawanSpv($myId, $myUnit);` and skip the existing matrix builder. Otherwise keep the existing builder exactly as is.
- Compute `$scopeUnitIds = $isSpv ? $absenScope->spvUnitIds($myId, $myUnit) : [];`.
- Before defaulting the selected employee, if `$isSpv` and `?karyawan=` is set, load it and reset to 0 unless `AbsenEmployeeScopeService::canView(...)` is true.
- When loading `$target`, for SPV require `canView(...)`; for non-SPV keep `isInScope`.

- [ ] **Step 6: Wire `save_absen`**

- Read `$myUnit` and `$myId` from `$me`.
- Keep `if (!$target || !$this->isInScope($me, $target))`.
- For SPV targets that pass `canView(...)` but fail `isInScope`, use the message: `Anda hanya dapat melihat data pegawai ini, tidak dapat mengisi penilaian.`
- Keep the existing message for other cases.

- [ ] **Step 7: Run targeted tests**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/unit/AbsenEmployeeScopeServiceTest.php tests/PenilaianAbsenOrderTest.php tests/PenilaianAbsenKehadiranFormTest.php`
Expected: PASS.

---

### Task 2: Read-only warning and daily grid

**Files:**
- Modify: `app/Views/penilaian/penilaian_absen.php`
- Modify: `app/Views/penilaian/_penilaian_absen_theme.php`
- Test: `tests/PenilaianAbsenReadOnlyViewTest.php`

**Interfaces:**
- Consumes: view data already passed by `penilaian_absen` (`target`, `allowedComponentCodes`, `attendanceComponents`, `existing`, `offDays`, `attendanceDetails`, `jumlahHari`, `hariEfektif`, `hariLibur`, `bulan`, `tahun`, `list_karyawan`, `selected_karyawan`, `targetUnitName`, `detail_absen`, `skor_total2`).
- Produces: read-only rendering when `count($allowedComponentCodes) === 0`.

- [ ] **Step 1: Write the failing view test**

Create `tests/PenilaianAbsenReadOnlyViewTest.php`:

```php
private function render(array $allowed): string
{
    return (string) view('penilaian/penilaian_absen', [
        'list_karyawan' => [],
        'selected_karyawan' => 401,
        'target' => (object) ['ID_AKUN' => 401, 'NAMA_AKUN' => 'Budi'],
        'targetUnitName' => 'Unit 1',
        'allowedComponentCodes' => $allowed,
        'kpi' => [],
        'detail_absen' => [],
        'skor_total2' => 0,
        'attendanceComponents' => [
            (object) ['id' => 1, 'code' => 'KEHADIRAN', 'name' => 'Kehadiran'],
            (object) ['id' => 2, 'code' => 'KEBERSIHAN', 'name' => 'Kebersihan'],
            (object) ['id' => 3, 'code' => 'SERAGAM', 'name' => 'Seragam'],
            (object) ['id' => 4, 'code' => 'KEPATUHAN_SOP', 'name' => 'Kepatuhan SOP'],
        ],
        'existing' => [],
        'offDays' => [],
        'hariEfektif' => 20,
        'hariLibur' => 0,
        'jumlahHari' => 30,
        'attendanceDetails' => [],
        'bulan' => 11,
        'tahun' => 2026,
    ]);
}

public function testTanpaKomponenMenampilkanPeringatanDanGridTanpaForm(): void
{
    $html = $this->render([]);
    $this->assertStringContainsString('Mode baca saja', $html);
    $this->assertStringContainsString('pa-ledger', $html);
    $this->assertStringContainsString('Kebersihan', $html);
    $this->assertStringNotContainsString('/penilaian/absen/save', $html);
}

public function testDenganKomponenTetapMenampilkanForm(): void
{
    $html = $this->render(['KEBERSIHAN']);
    $this->assertStringContainsString('/penilaian/absen/save', $html);
    $this->assertStringNotContainsString('Mode baca saja', $html);
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenReadOnlyViewTest.php`
Expected: FAIL because the read-only branch does not render the ledger.

- [ ] **Step 3: Refactor the view**

- Move `$allowedCount`, the `$paCell` closure, `$totalLateMonth`, and `$jumlahHari` above the `if ($allowedCount > 0)`.
- Keep the form inside `if ($allowedCount > 0)`.
- Replace the old `else` warning with a compact `pa-flash pa-flash--warn` banner: "Mode baca saja. Anda tidak berwenang mengisi penilaian pegawai ini. Data tetap dapat dilihat."
- Move the `pa-ledger` section outside the inner conditional so it renders for both editable and read-only.
- Remove the duplicate `$jumlahHari` assignment inside the ledger.

- [ ] **Step 4: Add the warning style**

In `_penilaian_absen_theme.php`, after `.pa-flash--err`:

```css
.pa-flash--warn {
    border-color: transparent;
    background: var(--pa-warn-soft);
    color: var(--pa-warn);
}
```

- [ ] **Step 5: Run the view test**

Run: `vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenReadOnlyViewTest.php tests/PenilaianAbsenKehadiranFormTest.php`
Expected: PASS.

---

### Task 3: Documentation and full verification

**Files:**
- Modify: `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md`
- Modify: `docs/architecture/kpi-business-rules.md` only if the page-level rule is described there.
- Test: existing targeted suite.

- [ ] **Step 1: Update PRD Modul 08**

Add a rule under the `/penilaian/absen` section: SPV (40) sees all active employees in `spv_units` plus self; unauthorized employees are view-only with a warning; `save_absen` remains guarded; non-SPV behavior unchanged. Reference `AbsenEmployeeScopeService`, `PenilaianKPI::penilaian_absen/save_absen`, and the new tests.

- [ ] **Step 2: Run the targeted test suite**

Run:
```bash
vendor/bin/phpunit -c phpunit.xml.dist tests/unit/AbsenEmployeeScopeServiceTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenReadOnlyViewTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenOrderTest.php
vendor/bin/phpunit -c phpunit.xml.dist tests/PenilaianAbsenKehadiranFormTest.php
```
Expected: all PASS.

- [ ] **Step 3: Syntax and diff checks**

Run:
```bash
php -l app/Services/Kpi/AbsenEmployeeScopeService.php
php -l app/Controllers/PenilaianKPI.php
php -l app/Views/penilaian/penilaian_absen.php
php -l tests/unit/AbsenEmployeeScopeServiceTest.php
php -l tests/PenilaianAbsenReadOnlyViewTest.php
git diff --check
```
Expected: no syntax errors, diff check clean.

- [ ] **Step 4: Report**

Report changed files, tests run and results, and the remaining caveat: no commit was made. Do not claim the work is complete without actual test output.
