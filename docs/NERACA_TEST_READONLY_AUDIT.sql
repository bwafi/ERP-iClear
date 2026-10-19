-- Read-only audit for the exact TEST cutoff and unit scope under review.
-- Set @as_of_date to the previously reported cutoff; do not infer it from today.
-- Set @unit_id to one unit, or NULL for the current combined view.
SET @as_of_date = 'YYYY-MM-DD';
SET @unit_id = NULL;

START TRANSACTION READ ONLY;

-- Confirm the queried schema and server version before reviewing any rows.
SELECT DATABASE() AS schema_name, @@version AS server_version, @@read_only AS server_read_only;

-- Trial-balance movement through cutoff, per unit and account.
SELECT id_unit, no_akun, SUM(debet) AS total_debet, SUM(kredit) AS total_kredit,
       SUM(debet - kredit) AS saldo_debit_normal
FROM jurnal
WHERE tanggal <= @as_of_date AND (@unit_id IS NULL OR id_unit = @unit_id)
GROUP BY id_unit, no_akun
ORDER BY id_unit, no_akun;

-- Sources whose combined debit and credit differ; preserve source identifiers.
SELECT id_unit, tabel_referensi, id_referensi, COUNT(*) AS jumlah_baris,
       SUM(debet) AS total_debet, SUM(kredit) AS total_kredit,
       SUM(debet - kredit) AS selisih
FROM jurnal
WHERE tanggal <= @as_of_date AND (@unit_id IS NULL OR id_unit = @unit_id)
  AND NULLIF(TRIM(COALESCE(tabel_referensi, '')), '') IS NOT NULL
  AND NULLIF(TRIM(COALESCE(id_referensi, '')), '') IS NOT NULL
GROUP BY id_unit, tabel_referensi, id_referensi
HAVING SUM(debet) <> SUM(kredit)
ORDER BY id_unit, tabel_referensi, id_referensi;

-- Individual rows without complete source references; no descriptions/customer data.
SELECT idjurnal, tanggal, id_unit, no_akun, debet, kredit,
       tabel_referensi, id_referensi
FROM jurnal
WHERE tanggal <= @as_of_date AND (@unit_id IS NULL OR id_unit = @unit_id)
  AND (NULLIF(TRIM(COALESCE(tabel_referensi, '')), '') IS NULL
       OR NULLIF(TRIM(COALESCE(id_referensi, '')), '') IS NULL)
ORDER BY tanggal, id_unit, idjurnal;

-- Missing or malformed account codes and codes absent from the chart of accounts.
SELECT j.idjurnal, j.tanggal, j.id_unit, j.no_akun, j.debet, j.kredit,
       j.tabel_referensi, j.id_referensi,
       CASE WHEN coa.no_akun IS NULL THEN 'TIDAK ADA DI MASTER'
            WHEN j.no_akun NOT REGEXP '^[0-9]{10}$' THEN 'FORMAT TIDAK VALID'
            ELSE 'KODE KOSONG/TIDAK VALID' END AS masalah
FROM jurnal j
LEFT JOIN no_akun coa ON coa.no_akun = j.no_akun
WHERE j.tanggal <= @as_of_date AND (@unit_id IS NULL OR j.id_unit = @unit_id)
  AND (j.no_akun IS NULL OR TRIM(j.no_akun) = ''
       OR j.no_akun NOT REGEXP '^[0-9]{10}$' OR coa.no_akun IS NULL)
ORDER BY j.tanggal, j.id_unit, j.idjurnal;

-- Opening-balance references by period and unit; inspect details only after matching
-- the original report's scope. This does not assume every source named saldo_awal
-- is a true opening balance.
SELECT id_unit, tabel_referensi, id_referensi, MIN(tanggal) AS tanggal_awal,
       MAX(tanggal) AS tanggal_akhir, COUNT(*) AS jumlah_baris,
       SUM(debet) AS total_debet, SUM(kredit) AS total_kredit
FROM jurnal
WHERE tanggal <= @as_of_date AND (@unit_id IS NULL OR id_unit = @unit_id)
  AND LOWER(COALESCE(tabel_referensi, '')) LIKE '%saldo%awal%'
GROUP BY id_unit, tabel_referensi, id_referensi
ORDER BY id_unit, tanggal_awal;

-- Current inventory ledger vs current stock and inventory GL by unit.
-- Historical ledger valuation is intentionally not inferred from created_at.
SELECT b.id_unit, COUNT(DISTINCT b.idbarang) AS barang_fisik,
       SUM(b.stok_akhir) AS qty_stok_barang,
       SUM(COALESCE(l.quantity, 0)) AS qty_ledger,
       SUM(COALESCE(l.value, 0)) AS nilai_ledger,
       SUM(b.stok_akhir - COALESCE(l.quantity, 0)) AS selisih_qty
FROM stok_barang b
JOIN barang p ON p.idbarang = b.idbarang AND p.jenis_item = 'fisik'
LEFT JOIN inventory_ledger_balances l
  ON l.item_id = b.idbarang AND l.unit_id = b.id_unit
WHERE (@unit_id IS NULL OR b.id_unit = @unit_id)
GROUP BY b.id_unit
ORDER BY b.id_unit;

SELECT j.id_unit, GROUP_CONCAT(DISTINCT j.no_akun ORDER BY j.no_akun) AS akun_persediaan,
       SUM(j.debet - j.kredit) AS nilai_buku_persediaan
FROM jurnal j
JOIN no_akun coa ON coa.no_akun = j.no_akun AND coa.jenis_akun = 'Persediaan'
WHERE j.tanggal <= @as_of_date AND (@unit_id IS NULL OR j.id_unit = @unit_id)
GROUP BY j.id_unit
ORDER BY j.id_unit;

COMMIT;
