# KPI Omzet Global Threshold Toko → HO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Skor `OMZET_GLOBAL` (Manager 34 & Kepala Divisi 43) menjadi 0 di bawah Σ target toko dan linear sampai 100 di Σ target HO.

**Architecture:** Ubah fungsi murni `scoreOmzetGlobal()` di `DigitalMarketingKpiService` dan tambah agregator target toko/HO di service yang sama. `ManagerKpiService` sudah mendelegasikan komponen ini, jadi tidak berubah. Plumbing `target_toko` lewat `KpiCalculationService` hanya untuk keterangan tampilan.

**Tech Stack:** PHP 8 / CodeIgniter 4.4.8, PHPUnit (`phpunit.xml`, database test `erp_test`), Bootstrap 5 view.

**Spec:** `docs/superpowers/specs/2026-11-24-kpi-omzet-global-threshold-design.md`

## Global Constraints

- Jangan mengubah komponen omzet lain, bobot, `kpi_targets`, atau schema.
- Jangan `git commit` sampai user menginstruksikan (AGENTS.md D).
- Rumus: `targetHo <= 0` → `null`; `actual >= targetHo` → `100.0`; `actual <= targetToko` → `0.0`; `targetHo - targetToko <= 0` → `100.0`; selain itu `round((actual - targetToko) / (targetHo - targetToko) * 100, 4)`.
- Σ target toko = `OMSET_TOKO` `position_id NULL`; Σ target HO = `OMSET_CABANG` `position_id 40`; keduanya hanya `salesUnits()` (non-HO, non-trial).
- `omzetTarget()` tetap mengembalikan Σ target HO.
- Test DB hanya lewat `vendor/bin/phpunit -c phpunit.xml` (database `erp_test`).

## Review Focus

- Σ target toko = 0 tetapi HO > 0: skor harus proporsional terhadap HO, bukan selalu 0.
- Target HO ≤ 0 / tidak dikonfigurasi: hasil `null` (dikecualikan), bukan 0.
- Omzet tepat sama dengan target toko: skor 0 (batas bawah inklusif).
- Unit trial: tidak menambah Σ target toko maupun Σ target HO.
- Komponen KPI selain `OMZET_GLOBAL` tidak menampilkan keterangan "min. Rp" karena `target_toko` null.

---

### Task 1: Rumus threshold di `scoreOmzetGlobal`

**Files:**
- Modify: `app/Services/Marketing/DigitalMarketingKpiService.php:77-88` (fungsi skor), `:17-21` (docblock rumus)
- Test: `tests/unit/DigitalMarketingKpiTest.php:27-45`

**Interfaces:**
- Consumes: —
- Produces: `DigitalMarketingKpiService::scoreOmzetGlobal(float $actual, float $targetToko, float $targetHo): ?float`

- [ ] **Step 1: Ganti test Omzet Global dengan boundary threshold**

Replace the three existing Omzet Global tests in `tests/unit/DigitalMarketingKpiTest.php`
(`testOmzetGlobalProportionalToTarget`, `testOmzetGlobalAtOrAboveTargetIsHundred`,
`testOmzetGlobalInvalidTargetReturnsNull`) with:

```php
public function testOmzetGlobalZeroAtOrBelowStoreTarget(): void
{
    $this->assertSame(0.0, DigitalMarketingKpiService::scoreOmzetGlobal(250_000_000, 300_000_000, 338_000_000));
    $this->assertSame(0.0, DigitalMarketingKpiService::scoreOmzetGlobal(300_000_000, 300_000_000, 338_000_000));
    $this->assertSame(0.0, DigitalMarketingKpiService::scoreOmzetGlobal(-10_000_000, 300_000_000, 338_000_000));
}

public function testOmzetGlobalLinearBetweenStoreAndHo(): void
{
    $this->assertSame(25.0, DigitalMarketingKpiService::scoreOmzetGlobal(309_500_000, 300_000_000, 338_000_000));
    $this->assertSame(50.0, DigitalMarketingKpiService::scoreOmzetGlobal(319_000_000, 300_000_000, 338_000_000));
}

public function testOmzetGlobalZeroStoreTargetFallsBackToHoRatio(): void
{
    $this->assertSame(50.0, DigitalMarketingKpiService::scoreOmzetGlobal(169_000_000, 0.0, 338_000_000));
}

public function testOmzetGlobalAtOrAboveHoIsHundred(): void
{
    $this->assertSame(100.0, DigitalMarketingKpiService::scoreOmzetGlobal(338_000_000, 300_000_000, 338_000_000));
    $this->assertSame(100.0, DigitalMarketingKpiService::scoreOmzetGlobal(400_000_000, 300_000_000, 338_000_000));
}

public function testOmzetGlobalStoreTargetAtOrAboveHoCapsAtHo(): void
{
    $this->assertSame(0.0, DigitalMarketingKpiService::scoreOmzetGlobal(337_000_000, 338_000_000, 338_000_000));
    $this->assertSame(100.0, DigitalMarketingKpiService::scoreOmzetGlobal(338_000_000, 338_000_000, 338_000_000));
}

public function testOmzetGlobalInvalidHoReturnsNull(): void
{
    $this->assertNull(DigitalMarketingKpiService::scoreOmzetGlobal(100_000_000, 0.0, 0.0));
    $this->assertNull(DigitalMarketingKpiService::scoreOmzetGlobal(100_000_000, 300_000_000, 0.0));
}
```

Also update the class docblock lines `tests/unit/DigitalMarketingKpiTest.php:12` to:
`*   - Omzet Global: 0 di bawah target toko, linear sampai target HO, capai HO = 100.`

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/DigitalMarketingKpiTest.php --filter OmzetGlobal`
Expected: FAIL — `ArgumentCountError` / `Too few arguments` karena fungsi masih menerima 2 argumen.

- [ ] **Step 3: Implement rumus baru**

In `app/Services/Marketing/DigitalMarketingKpiService.php` replace `scoreOmzetGlobal`
with:

```php
/**
 * Skor Omzet Global bertingkat: 0 bila <= Target Toko, linear sampai 100
 * di Target HO. Target HO <= 0 → null (target belum dikonfigurasi).
 */
public static function scoreOmzetGlobal(float $actual, float $targetToko, float $targetHo): ?float
{
    if ($targetHo <= 0) {
        return null;
    }
    if ($actual >= $targetHo) {
        return 100.0;
    }
    if ($actual <= $targetToko) {
        return 0.0;
    }
    $range = $targetHo - $targetToko;
    if ($range <= 0) {
        return 100.0;
    }

    return round(($actual - $targetToko) / $range * 100, 4);
}
```

Update docblock komponen di baris 19-21 menjadi:

```php
 *   OMZET_GLOBAL         50  Global omzet perusahaan
 *                             Target Toko (batas bawah) = SUM(OMSET_TOKO)
 *                             Target HO (batas atas)    = SUM(OMSET_CABANG posisi 40)
 *                             actual <= Toko → 0; >= HO → 100; selain itu linear.
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/DigitalMarketingKpiTest.php --filter OmzetGlobal`
Expected: PASS (6 tests).

- [ ] **Step 5: Lint**

Run: `php -l app/Services/Marketing/DigitalMarketingKpiService.php`
Expected: `No syntax errors detected`.

---

### Task 2: Agregator target toko/HO + regression unit trial

**Files:**
- Modify: `app/Services/Marketing/DigitalMarketingKpiService.php:340-359` (`omzetTarget`), `:369-387` (`componentData`)
- Test: `tests/unit/KpiOmsetTrialUnitTest.php:90-126`

**Interfaces:**
- Consumes: `scoreOmzetGlobal(float, float, float): ?float` dari Task 1.
- Produces: `DigitalMarketingKpiService::omzetTargets(string $context, ?string $date): array{toko:float,ho:float}`; `componentData()` mengembalikan kunci tambahan `target_toko:?float`.

- [ ] **Step 1: Tambah fixture dan test regresi unit trial**

In `tests/unit/KpiOmsetTrialUnitTest.php`, change the component insert (line 90-92) to
`insertBatch` with a second component:

```php
$this->db->table('kpi_components')->insertBatch([
    ['id' => self::COMPONENT, 'code' => 'OMSET_CABANG', 'name' => 'Omset Cabang'],
    ['id' => 9002, 'code' => 'OMSET_TOKO', 'name' => 'Omset Toko'],
]);
```

Add OMSET_TOKO targets to the `kpi_targets` insertBatch (line 93-96):

```php
['kpi_component_id' => 9002, 'unit_id' => self::UNIT_PROB, 'position_id' => null, 'context' => 'default', 'target_value' => 60000000, 'effective_from' => '2024-01-01'],
['kpi_component_id' => 9002, 'unit_id' => self::UNIT_GENTENG, 'position_id' => null, 'context' => 'default', 'target_value' => 999000000, 'effective_from' => '2024-01-01'],
```

Add the test after `testTargetOmzetGlobalMembuangTargetUnitTrial`:

```php
public function testTargetOmzetTokoDanHoMembuangUnitTrial(): void
{
    $targets = (new DigitalMarketingKpiService())->omzetTargets('default', '2026-11-15');

    $this->assertSame(60000000.0, $targets['toko'], 'target toko unit trial tidak boleh masuk');
    $this->assertSame(100000000.0, $targets['ho'], 'target HO unit trial tidak boleh masuk');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/KpiOmsetTrialUnitTest.php --filter testTargetOmzetTokoDanHoMembuangUnitTrial`
Expected: FAIL — `Call to undefined method ...::omzetTargets()`.

- [ ] **Step 3: Implement `omzetTargets()` dan pakai di `componentData()`**

In `DigitalMarketingKpiService`, add before `omzetTarget()`:

```php
/**
 * Target omzet global: batas bawah (toko) dan batas atas (HO) dari
 * kpi_targets unit non-trial/non-HO.
 *
 * @return array{toko:float,ho:float}
 */
public function omzetTargets(string $context, ?string $date): array
{
    $targets = ['toko' => 0.0, 'ho' => 0.0];

    $tokoComp = $this->db->table('kpi_components')->where('code', 'OMSET_TOKO')->get()->getRow();
    if ($tokoComp) {
        foreach ($this->salesUnits() as $unit) {
            $row = $this->targetModel->getTargetByKpiAndUnit((int) $tokoComp->id, $unit, $context, $date);
            if ($row) {
                $targets['toko'] += (float) $row->target_value;
            }
        }
    }

    $cabangComp = $this->db->table('kpi_components')->where('code', 'OMSET_CABANG')->get()->getRow();
    if ($cabangComp) {
        foreach ($this->salesUnits() as $unit) {
            $row = $this->targetModel->getTargetByKpiAndUnit((int) $cabangComp->id, $unit, $context, $date, 40);
            if ($row) {
                $targets['ho'] += (float) $row->target_value;
            }
        }
    }

    return ['toko' => round($targets['toko'], 2), 'ho' => round($targets['ho'], 2)];
}
```

Replace the body of `omzetTarget()` with:

```php
return $this->omzetTargets($context, $date)['ho'];
```

In `componentData()` case `OMZET_GLOBAL`, compute `$targets = $this->omzetTargets($context, $date);`
and return:

```php
return [
    'achievement' => self::scoreOmzetGlobal((float) $actual, $targets['toko'], $targets['ho']),
    'target'      => $targets['ho'],
    'target_toko' => $targets['toko'],
    'actual'      => round($actual, 2),
    'shortfall'   => round(max($targets['ho'] - $actual, 0), 2),
    'source'      => 'Omzet Global ERP (penjualan)',
];
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/KpiOmsetTrialUnitTest.php`
Expected: PASS (semua test di file itu, termasuk `testTargetOmzetGlobalMembuangTargetUnitTrial`).

Also run: `vendor/bin/phpunit -c phpunit.xml tests/unit/DigitalMarketingKpiTest.php`
Expected: PASS.

- [ ] **Step 5: Update PRD Modul 08**

In `docs/prd/modul/PRD_MODUL_08_KPI_Penilaian.md` replace butir 13 with:

```markdown
13. `OMZET_GLOBAL` (Manager 34 & Kepala Divisi 43, bobot 50) memakai skor bertingkat: 0 bila omzet ≤ Σ target `OMSET_TOKO` (posisi NULL) unit non-trial, linear sampai 100 di Σ target `OMSET_CABANG` posisi 40 unit non-trial, dan 100 bila omzet ≥ Σ target HO. Σ target HO ≤ 0 → null. — `DigitalMarketingKpiService::scoreOmzetGlobal/omzetTargets/omzetTarget`
```

Add to section 6 Acceptance Criteria, after the "KPI omzet lintas cabang" line:

```markdown
- [ ] KPI Omzet Global Manager/Kadiv bernilai 0 di bawah Σ target toko, linear sampai Σ target HO, dan 100 di atasnya; unit trial tidak masuk omzet maupun target.
```

- [ ] **Step 6: Lint**

Run: `php -l app/Services/Marketing/DigitalMarketingKpiService.php && php -l tests/unit/KpiOmsetTrialUnitTest.php`
Expected: dua kali `No syntax errors detected`.

---

### Task 3: Tampilkan target toko di `/penilaian_kinerja`

**Files:**
- Modify: `app/Services/Kpi/KpiCalculationService.php:617-626` (jalur Digital) dan `:667-678` (jalur Manager), `:681-697` (item)
- Modify: `app/Views/penilaian/penilaian_kinerja.php:581-599`

**Interfaces:**
- Consumes: `componentData()['target_toko']` dari Task 2.
- Produces: item KPI punya kunci `target_toko:?float`; view menampilkan `min. Rp …` hanya bila terisi.

- [ ] **Step 1: Teruskan `target_toko` dari data service ke item**

Di `KpiCalculationService::calculateForEmployee()`, pada blok `elseif ($digitalData !== null)`
(sekitar baris 617) tambahkan ke array `$targetInfo`:

```php
"target_toko" => $digitalData["target_toko"] ?? null,
```

Pada blok `elseif ($managerData !== null)` (sekitar baris 667) tambahkan:

```php
"target_toko" => $managerData["target_toko"] ?? null,
```

Pada array `$items[]` (sekitar baris 681) tambahkan setelah `"target"`:

```php
"target_toko" => $targetInfo["target_toko"] ?? null,
```

- [ ] **Step 2: Tampilkan batas bawah di view**

In `app/Views/penilaian/penilaian_kinerja.php`, inside the Target cell after the
`<?php if ($isCurrency): ?> … <?php endif; ?>` badge block (line 584-590), add:

```php
<?php if (!empty($kpi['target_toko'])): ?>
    <span class="d-block text-muted small">min. Rp <?= number_format((float)$kpi['target_toko'], 0, ',', '.') ?></span>
<?php endif; ?>
```

- [ ] **Step 3: Verifikasi hanya komponen omzet global yang terpengaruh**

Run: `grep -n "target_toko" app/Services/Kpi/KpiCalculationService.php app/Views/penilaian/penilaian_kinerja.php`
Expected: 3 kemunculan di service (dua sumber + item) dan 1 di view.

Run: `php -l app/Services/Kpi/KpiCalculationService.php && php -l app/Views/penilaian/penilaian_kinerja.php`
Expected: dua kali `No syntax errors detected`.

- [ ] **Step 4: Cek manual halaman**

Buka `/penilaian_kinerja` untuk Manager (34) atau Kadiv (43) pada periode November 2026.
Expected: baris **Omzet Global Perusahaan** menampilkan Target HO `Rp 338.000.000` dengan
baris kecil `min. Rp …` (Σ target toko), dan skor mengikuti threshold. Baris KPI lain
tidak menampilkan baris `min.`.

- [ ] **Step 5: Jalankan pemeriksaan akhir**

Run: `vendor/bin/phpunit -c phpunit.xml tests/unit/DigitalMarketingKpiTest.php tests/unit/KpiOmsetTrialUnitTest.php`
Expected: PASS.

Run: `git diff --check`
Expected: tidak ada output.

---

## Catatan eksekusi

- Jangan commit sampai diminta. Setelah implementasi + test, pindahkan issue #239 ke
  **Testing** dan komentari hasil test.
- Setelah verifikasi manual user, pindahkan ke **Done**.
