<?= $this->include('penilaian/_penilaian_absen_theme') ?>

<?php
$namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periodeLabel = ($namaBulan[$bulan - 1] ?? (string) $bulan) . ' ' . $tahun;
?>

<div class="pa-page">
    <header class="pa-head">
        <div>
            <nav aria-label="Breadcrumb">
                <ol class="pa-crumb">
                    <li><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li><a href="<?= base_url('/penilaian/kpi') ?>">Penilaian</a></li>
                    <li aria-current="page">Penilaian Harian</li>
                </ol>
            </nav>
            <h1 class="pa-title">Penilaian Harian</h1>
            <p class="pa-sub">
                Isi skor Kebersihan dan Kepatuhan SOP. Nilai Kehadiran dan Seragam terisi otomatis dari absen mobile.
                <span class="pa-formula">Nilai = SUM ÷ (Hari Efektif × 5) × 100</span>
            </p>
        </div>
        <p class="pa-period">
            <strong><?= esc($periodeLabel) ?></strong>
            <span>Periode penilaian</span>
        </p>
    </header>

    <?php if (session()->getFlashdata('success')) : ?>
        <div class="pa-flash pa-flash--ok" role="alert">
            <iconify-icon icon="solar:check-circle-bold" class="fs-5" aria-hidden="true"></iconify-icon>
            <div><?= session()->getFlashdata('success') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')) : ?>
        <div class="pa-flash pa-flash--err" role="alert">
            <iconify-icon icon="solar:danger-triangle-bold" class="fs-5" aria-hidden="true"></iconify-icon>
            <div><?= session()->getFlashdata('error') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>

    <form method="get" class="pa-toolbar" aria-label="Filter penilaian">
        <div class="pa-field">
            <label class="pa-label" for="paBulan">Bulan</label>
            <select id="paBulan" name="bulan" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($i = 1; $i <= 12; $i++) : ?>
                    <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>><?= $namaBulan[$i - 1] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="pa-field">
            <label class="pa-label" for="paTahun">Tahun</label>
            <select id="paTahun" name="tahun" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++) : ?>
                    <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="pa-field pa-field--grow">
            <label class="pa-label" for="filterKaryawan">Pilih karyawan</label>
            <select name="karyawan" id="filterKaryawan" class="form-select form-select-sm select2" style="width: 100%;" onchange="this.form.submit()">
                <?php foreach ($list_karyawan as $k) : ?>
                    <option value="<?= $k['ID_AKUN'] ?>" <?= $selected_karyawan == $k['ID_AKUN'] ? 'selected' : '' ?>>
                        <?= esc($k['NAMA_AKUN']) ?> — <?= esc($k['NAMA_JABATAN'] ?? 'Tanpa Jabatan') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <a href="<?= base_url('/penilaian/absen') ?>" class="pa-btn pa-btn--ghost pa-reset">
            <iconify-icon icon="solar:restart-bold" aria-hidden="true"></iconify-icon> Reset filter
        </a>
    </form>

    <?php if ($target) : ?>
        <section class="pa-board" aria-label="Ringkasan penilaian bulanan">
            <div class="pa-board__top">
                <div>
                    <h2 class="pa-name"><?= esc($target->NAMA_AKUN) ?></h2>
                    <p class="pa-meta">
                        <span class="pa-chip"><?= esc($targetUnitName ?: 'Tanpa unit') ?></span>
                        <span>Periode <?= esc($periodeLabel) ?></span>
                    </p>
                </div>
                <div class="pa-score">
                    <span class="pa-score__label">Skor absensi</span>
                    <span class="pa-score__value"><?= number_format((float) $skor_total2, 2, ',', '.') ?></span>
                    <span class="pa-score__scale">dari 100</span>
                </div>
            </div>
            <div class="pa-comps">
                <?php if (!empty($detail_absen)) : ?>
                    <?php foreach ($detail_absen as $a) : ?>
                        <div class="pa-comp">
                            <span class="pa-comp__label"><?= esc($a['nama']) ?></span>
                            <span class="pa-comp__value"><?= esc((string) $a['nilai']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <?php foreach ($attendanceComponents as $c) : ?>
                        <div class="pa-comp">
                            <span class="pa-comp__label"><?= esc($c->name) ?></span>
                            <span class="pa-comp__value">–</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php $allowedCount = is_array($allowedComponentCodes ?? null) ? count($allowedComponentCodes) : 0; ?>

        <?php if ($allowedCount > 0) : ?>
            <?php
            $paCell = static function ($component, int $day) use (&$existing, &$offDays, &$attendanceDetails): array {
                if (isset($offDays[$day])) {
                    $label = (string) $offDays[$day];

                    return ['state' => 'off', 'label' => $label, 'tone' => '', 'title' => $label . ' — tidak dihitung'];
                }

                if (!isset($existing[$component->id][$day])) {
                    return ['state' => 'empty', 'label' => '–', 'tone' => '', 'title' => ''];
                }

                $value = (int) $existing[$component->id][$day];
                $title = '';

                if ($component->code === 'KEHADIRAN' && isset($attendanceDetails[$day])) {
                    $parts = [];
                    foreach ($attendanceDetails[$day] as $det) {
                        $detail = [];
                        if ($det->shift) {
                            $detail[] = 'Shift: ' . $det->shift;
                            if ($det->shift === 'PS' && $det->session) {
                                $detail[] = '(' . $det->session . ')';
                            }
                        }
                        if ($det->actual_time) {
                            $detail[] = 'Jam: ' . date('H:i', strtotime($det->actual_time));
                        }
                        if ($det->late_minutes !== null) {
                            $detail[] = 'Telat: ' . (int) $det->late_minutes . ' menit';
                        }
                        if ($det->attendance_type === 'IZIN_TELAT') {
                            $detail[] = '(Izin)';
                        }
                        if (!empty($detail)) {
                            $parts[] = implode(', ', $detail);
                        }
                    }
                    if (!empty($parts)) {
                        $title = implode(' | ', $parts);
                    }
                }

                return [
                    'state' => 'score',
                    'label' => (string) $value,
                    'tone'  => $value <= 2 ? 'low' : ($value === 3 ? 'mid' : 'good'),
                    'title' => $title,
                ];
            };
            ?>

            <form method="post" action="<?= base_url('/penilaian/absen/save') ?>" class="pa-sheet">
                <input type="hidden" name="employee_id" value="<?= $target->ID_AKUN ?>">
                <input type="hidden" name="bulan" value="<?= $bulan ?>">
                <input type="hidden" name="tahun" value="<?= $tahun ?>">

                <header class="pa-sheet__head">
                    <div>
                        <h2 class="pa-sheet__title">Input penilaian harian</h2>
                        <p class="pa-sheet__sub">Skor Kebersihan dan Kepatuhan SOP per tanggal.</p>
                    </div>
                    <button type="submit" class="pa-btn pa-btn--primary">
                        <iconify-icon icon="solar:diskette-bold" aria-hidden="true"></iconify-icon> Simpan data
                    </button>
                </header>

                <div class="pa-sheet__body">
                    <div>
                        <label class="pa-label" for="paTanggal">Pilih tanggal</label>
                        <?php
                        $defaultTanggal = (date('Y') == $tahun && (int) date('m') == $bulan)
                            ? date('Y-m-d')
                            : sprintf('%04d-%02d-%02d', $tahun, $bulan, 1);
                        ?>
                        <input type="date" id="paTanggal" class="form-control form-control-sm" name="tanggal"
                            value="<?= $defaultTanggal ?>" required onchange="resetSkorInput()">
                        <p class="pa-help">Tanggal yang akan diinput atau diubah skornya.</p>
                        <?php if (in_array('SERAGAM', (array) $allowedComponentCodes, true)) : ?>
                            <p class="pa-note">
                                Nilai <strong>Seragam</strong> diisi dari centang foto absen di
                                <a href="<?= base_url('semua_riwayat_presensi') ?>">Riwayat Presensi (admin)</a>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="pa-assess">
                        <?php foreach ($attendanceComponents as $c) : ?>
                            <?php if ($c->code === 'KEHADIRAN') continue; ?>
                            <?php if ($c->code === 'SERAGAM') continue; // dinilai dari foto absen ?>
                            <?php if (!in_array($c->code, $allowedComponentCodes, true)) continue; ?>
                            <?php $skorId = 'paSkor' . strtolower($c->code); ?>
                            <div class="pa-field">
                                <label class="pa-label" for="<?= esc($skorId) ?>"><?= esc($c->name) ?></label>
                                <select id="<?= esc($skorId) ?>" class="form-select form-select-sm" name="skor_<?= strtolower($c->code) ?>">
                                    <option value="">- Tidak Diubah -</option>
                                    <?php foreach ([5 => 'Sangat Baik (5)', 4 => 'Baik (4)', 3 => 'Cukup (3)', 2 => 'Kurang (2)', 1 => 'Sangat Kurang (1)', 0 => 'Buruk (0)'] as $v => $label) : ?>
                                        <option value="<?= $v ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <footer class="pa-sheet__foot">
                    <iconify-icon icon="solar:info-circle-bold" aria-hidden="true"></iconify-icon>
                    <p>
                        Aspek yang dikosongkan (-) nilainya tidak akan diubah pada tanggal tersebut. Hari libur/izin dikelola dari absen mobile dan roster, sehingga tidak perlu ditandai di sini.
                    </p>
                </footer>
            </form>

            <?php
            $totalLateMonth = 0;
            if (!empty($attendanceDetails)) {
                foreach ($attendanceDetails as $dayDetails) {
                    foreach ($dayDetails as $det) {
                        $totalLateMonth += (int) ($det->late_minutes ?? 0);
                    }
                }
            }
            ?>

            <section class="pa-ledger" aria-labelledby="paRiwayat">
                <header class="pa-ledger__head">
                    <div>
                        <h2 class="pa-ledger__title" id="paRiwayat">Riwayat nilai &amp; matriks bulanan</h2>
                        <p class="pa-help">Arahkan kursor ke angka matriks untuk melihat detail jam masuk.</p>
                    </div>
                    <div class="pa-facts">
                        <span class="pa-fact">Hari efektif <strong><?= (int) $hariEfektif ?></strong> / <?= (int) $jumlahHari ?></span>
                        <span class="pa-fact">Libur/izin <strong><?= (int) $hariLibur ?></strong> hari</span>
                        <?php if (!empty($attendanceDetails)) : ?>
                            <span class="pa-fact">Akumulasi telat <strong><?= (int) $totalLateMonth ?></strong> menit</span>
                        <?php endif; ?>
                    </div>
                </header>

                <?php $jumlahHari = (int) date('t', strtotime("$tahun-$bulan-01")); ?>

                <div class="pa-table-wrap">
                    <table class="pa-table">
                        <thead>
                            <tr>
                                <th scope="col" class="pa-compname ps-3">Komponen penilaian</th>
                                <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                    <th scope="col" class="pa-dayhead"><?= $d ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceComponents as $c) : ?>
                                <tr>
                                    <th scope="row" class="pa-compname"><?= esc($c->name) ?></th>
                                    <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                        <?php $cell = $paCell($c, $d); ?>
                                        <td class="pa-daycell">
                                            <?php if ($cell['state'] === 'empty') : ?>
                                                <span class="pa-cell pa-cell--empty">–</span>
                                            <?php else : ?>
                                                <span class="pa-cell pa-cell--<?= $cell['state'] === 'off' ? 'off' : $cell['tone'] ?>"<?= $cell['title'] !== '' ? ' data-bs-toggle="tooltip" data-bs-placement="top" title="' . esc($cell['title'], 'attr') . '"' : '' ?>><?= esc($cell['label']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pa-mdays">
                    <?php foreach ($attendanceComponents as $c) : ?>
                        <div class="pa-mcard">
                            <h3 class="pa-mcard__title"><?= esc($c->name) ?></h3>
                            <div class="pa-days">
                                <?php for ($d = 1; $d <= $jumlahHari; $d++) : ?>
                                    <?php $cell = $paCell($c, $d); ?>
                                    <div class="pa-day"<?= $cell['title'] !== '' ? ' title="' . esc($cell['title'], 'attr') . '"' : '' ?>>
                                        <span class="pa-day__n"><?= $d ?></span>
                                        <span class="pa-cell pa-cell--<?= $cell['state'] === 'empty' ? 'empty' : ($cell['state'] === 'off' ? 'off' : $cell['tone']) ?>"><?= esc($cell['label']) ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <footer class="pa-legend">
                    <span class="pa-legend__item"><span class="pa-cell pa-cell--off">OFF</span> Libur</span>
                    <span class="pa-legend__item"><span class="pa-cell pa-cell--off">IZIN</span> Izin / sakit / cuti</span>
                    <span class="pa-legend__item"><span class="pa-cell pa-cell--low">1–2</span> Kurang</span>
                    <span class="pa-legend__item"><span class="pa-cell pa-cell--mid">3</span> Cukup</span>
                    <span class="pa-legend__item"><span class="pa-cell pa-cell--good">4–5</span> Baik / sangat baik</span>
                </footer>
            </section>
        <?php else : ?>
            <section class="pa-empty pa-empty--warn" role="status">
                <iconify-icon icon="solar:shield-keyhole-bold-duotone" aria-hidden="true"></iconify-icon>
                <h2>Akses terbatas</h2>
                <p>Anda tidak berwenang menginput absensi pegawai ini (mode baca saja).</p>
            </section>
        <?php endif; ?>
    <?php else : ?>
        <section class="pa-empty" role="status">
            <iconify-icon icon="solar:user-bold-duotone" aria-hidden="true"></iconify-icon>
            <h2>Belum ada karyawan dipilih</h2>
            <p>Silakan pilih salah satu karyawan melalui filter di atas untuk melihat atau menginput data absensi.</p>
        </section>
    <?php endif; ?>
</div>

<!-- Modal Konfirmasi Timpa Data -->
<?php if (session()->getFlashdata('require_confirmation')) : ?>
    <?php $confirmData = session()->getFlashdata('require_confirmation'); ?>
    <div class="modal fade pa-modal" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">
                        <iconify-icon icon="solar:danger-triangle-bold" class="fs-5 me-1 align-text-bottom" aria-hidden="true"></iconify-icon>
                        Konfirmasi Timpa Data Absensi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <?php $tanggalKonfirmasi = strtotime($confirmData['tanggal']); ?>
                    <p class="mb-3">
                        Tanggal <strong><?= date('d', $tanggalKonfirmasi) ?> <?= $namaBulan[(int) date('n', $tanggalKonfirmasi) - 1] ?> <?= date('Y', $tanggalKonfirmasi) ?></strong> sudah memiliki data penilaian / status (termasuk OFF) sebelumnya. Apakah Anda ingin menimpanya dengan data baru?
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0 align-middle text-center">
                            <thead>
                                <tr>
                                    <th class="text-start ps-2">Komponen</th>
                                    <th>Nilai Lama</th>
                                    <th>Nilai Baru</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($confirmData['comparisons'] as $comp) : ?>
                                    <tr>
                                        <td class="text-start ps-2"><?= esc($comp['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= $comp['old'] ?></span></td>
                                        <td><span class="badge bg-primary"><?= $comp['new'] ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-warning mb-0 mt-3 fs-2 border-0">
                        <iconify-icon icon="solar:info-circle-bold" class="me-1" aria-hidden="true"></iconify-icon> Data yang lama akan ditimpa secara permanen.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <form method="post" action="<?= base_url('/penilaian/absen/save') ?>" class="d-inline">
                        <input type="hidden" name="confirm" value="1">
                        <?php foreach ($confirmData['post_data'] as $key => $value) : ?>
                            <?php if (is_scalar($value)) : ?>
                                <input type="hidden" name="<?= esc($key) ?>" value="<?= esc($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-warning btn-sm px-3">
                            <iconify-icon icon="solar:diskette-bold" class="me-1" aria-hidden="true"></iconify-icon>Ya, Timpa Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            confirmModal.show();
        });
    </script>
<?php endif; ?>

<!-- Script Utama (Reset skor & Tooltips) -->
<script>
    function resetSkorInput() {
        var selects = document.querySelectorAll('select[name^="skor_"]');
        selects.forEach(function(el) {
            el.value = '';
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                html: true,
                trigger: 'hover'
            });
        });
    });
</script>
