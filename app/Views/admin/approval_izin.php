<?php
/**
 * Approval izin/sakit/cuti (menu "Approval Presensi").
 *
 * Tampilan mengikuti halaman Riwayat Absen (riwayat/semua_presensi.php):
 * hero + papan ringkasan + kartu filter + kartu tabel. Query, route, dan
 * field form tidak berubah; absensi tidak lewat halaman ini.
 */

$statusMap = [
    0 => ['Menunggu', 'warn'],
    1 => ['Disetujui', 'ok'],
    2 => ['Ditolak', 'bad'],
];

$jumlah = ['total' => count($pengajuan), 'menunggu' => 0, 'disetujui' => 0, 'ditolak' => 0];
foreach ($pengajuan as $item) {
    $s = (int) ($item->status ?? 0);
    if ($s === 0) {
        $jumlah['menunggu']++;
    } elseif ($s === 1) {
        $jumlah['disetujui']++;
    } elseif ($s === 2) {
        $jumlah['ditolak']++;
    }
}

$tanggal = static function ($nilai): string {
    $ts = $nilai ? strtotime((string) $nilai) : false;

    return $ts === false ? '—' : date('d-m-Y', $ts);
};

$iso = static function ($nilai): string {
    $ts = $nilai ? strtotime((string) $nilai) : false;

    return $ts === false ? '' : date('Y-m-d', $ts);
};
?>

<div class="ap-page">
    <header class="ap-hero">
        <div>
            <nav class="ap-crumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li><span aria-current="page">Approval Izin</span></li>
                </ol>
            </nav>
            <h1>Approval Izin / Sakit / Cuti</h1>
            <p>Absensi tidak perlu approval; halaman ini hanya untuk ketidakhadiran. Setujui atau tolak pengajuan di kolom keputusan.</p>
        </div>
        <div class="ap-hero__aside">
            <a class="ap-btn ap-btn--primary" href="<?= base_url('pengajuan_izin') ?>">
                <iconify-icon icon="solar:document-add-linear" width="18" height="18" aria-hidden="true"></iconify-icon>
                Buat Pengajuan
            </a>
        </div>
    </header>

    <section class="ap-board" aria-label="Ringkasan pengajuan">
        <div class="ap-board__cell">
            <div class="ap-board__label">Total pengajuan</div>
            <div class="ap-board__value"><?= $jumlah['total'] ?></div>
        </div>
        <div class="ap-board__cell">
            <div class="ap-board__label">Menunggu</div>
            <div class="ap-board__value is-warn"><?= $jumlah['menunggu'] ?></div>
        </div>
        <div class="ap-board__cell">
            <div class="ap-board__label">Disetujui</div>
            <div class="ap-board__value is-ok"><?= $jumlah['disetujui'] ?></div>
        </div>
        <div class="ap-board__cell">
            <div class="ap-board__label">Ditolak</div>
            <div class="ap-board__value is-bad"><?= $jumlah['ditolak'] ?></div>
        </div>
    </section>

    <section class="ap-card ap-filter" aria-label="Filter pengajuan">
        <div class="ap-filter__fields">
            <div class="ap-field">
                <label for="ap-status">Status</label>
                <select id="ap-status" class="ap-control">
                    <option value="">Semua status</option>
                    <option value="0">Menunggu</option>
                    <option value="1">Disetujui</option>
                    <option value="2">Ditolak</option>
                </select>
            </div>
            <button type="button" id="ap-reset" class="ap-btn ap-btn--ghost">Reset</button>
        </div>
        <p class="ap-filter__hint">
            <iconify-icon icon="solar:info-circle-linear" width="16" height="16" aria-hidden="true"></iconify-icon>
            Pengajuan yang menunggu tampil lebih dulu. Pencarian mencakup pegawai, unit, jenis, alasan, dan balasan.
        </p>
    </section>

    <section class="ap-card ap-table-card">
        <div class="ap-table-card__head">
            <h2>Daftar pengajuan</h2>
            <p>Status menandai keputusan atasan; catatan balasan tersimpan bersama keputusan.</p>
        </div>

        <div class="ap-table-wrap">
            <table class="ap-table" id="approval-izin-table">
                <thead>
                    <tr>
                        <th scope="col">Pegawai</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Jenis</th>
                        <th scope="col">Alasan</th>
                        <th scope="col">Status</th>
                        <th scope="col">Keputusan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pengajuan as $row): ?>
                        <?php
                        $s       = (int) ($row->status ?? 0);
                        $tag     = $statusMap[$s] ?? ['—', 'muted'];
                        $mulai   = $tanggal($row->tanggal_mulai ?? null);
                        $akhir   = $tanggal($row->tanggal_akhir ?? null);
                        $balasan = trim((string) ($row->balasan ?? ''));
                        ?>
                        <tr data-status="<?= $s ?>">
                            <td data-label="Pegawai">
                                <span class="ap-emp">
                                    <span class="ap-emp__name"><?= esc($row->NAMA_AKUN ?? 'Tanpa nama') ?></span>
                                    <span class="ap-emp__unit"><?= esc($row->NAMA_UNIT ?? 'Tanpa unit') ?></span>
                                </span>
                            </td>
                            <td data-label="Tanggal" data-order="<?= esc($iso($row->tanggal_mulai ?? null), 'attr') ?>">
                                <span class="ap-time"><?= esc($mulai) ?></span>
                                <?php if ($akhir !== '—' && $akhir !== $mulai): ?>
                                    <span class="ap-time__note">s.d. <?= esc($akhir) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Jenis">
                                <span class="ap-tag ap-tag--accent"><?= esc($row->jenis_izin ?? '—') ?></span>
                            </td>
                            <td data-label="Alasan">
                                <span class="ap-reason"><?= nl2br(esc((string) $row->alasan)) ?></span>
                                <?php if (! empty($row->file)): ?>
                                    <a class="ap-link" href="<?= base_url('pengajuan_izin/surat/' . (int) $row->idizin) ?>" target="_blank" rel="noopener">
                                        <iconify-icon icon="solar:file-text-linear" width="15" height="15" aria-hidden="true"></iconify-icon>
                                        Lihat surat dokter
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <span class="ap-tag ap-tag--<?= esc($tag[1], 'attr') ?>"><?= esc($tag[0]) ?></span>
                            </td>
                            <td data-label="Keputusan">
                                <?php if ($s === 0): ?>
                                    <form method="post" action="<?= base_url('approval_presensi/putuskan') ?>" class="ap-form">
                                        <input type="hidden" name="idizin" value="<?= (int) $row->idizin ?>">
                                        <input type="text" name="balasan" class="ap-input"
                                            placeholder="Catatan (opsional)" autocomplete="off"
                                            aria-label="Catatan keputusan untuk <?= esc($row->NAMA_AKUN ?? 'pegawai', 'attr') ?>">
                                        <div class="ap-form__actions">
                                            <button type="submit" name="status" value="1" class="ap-btn ap-btn--ok">Setujui</button>
                                            <button type="submit" name="status" value="2" class="ap-btn ap-btn--bad">Tolak</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <?php if ($balasan !== ''): ?>
                                        <p class="ap-reply">
                                            <span class="ap-reply__label">Balasan</span>
                                            <?= nl2br(esc($balasan)) ?>
                                        </p>
                                    <?php endif; ?>
                                    <details class="ap-more">
                                        <summary>Ubah keputusan</summary>
                                        <form method="post" action="<?= base_url('approval_presensi/putuskan') ?>" class="ap-form">
                                            <input type="hidden" name="idizin" value="<?= (int) $row->idizin ?>">
                                            <input type="text" name="balasan" class="ap-input" autocomplete="off"
                                                placeholder="Catatan (opsional)" value="<?= esc($balasan, 'attr') ?>"
                                                aria-label="Catatan keputusan untuk <?= esc($row->NAMA_AKUN ?? 'pegawai', 'attr') ?>">
                                            <div class="ap-form__actions">
                                                <button type="submit" name="status" value="1" class="ap-btn ap-btn--ok">Setujui</button>
                                                <button type="submit" name="status" value="2" class="ap-btn ap-btn--bad">Tolak</button>
                                            </div>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?= $this->include('admin/_approval_izin_theme') ?>

<script>
    (function () {
        $(function () {
            const statusSelect = document.getElementById('ap-status');

            $.fn.dataTable.ext.search.push(function (settings, data, index) {
                if (settings.nTable.id !== 'approval-izin-table') {
                    return true;
                }
                const tr = settings.aoData[index] && settings.aoData[index].nTr;
                return !statusSelect.value || (tr && tr.getAttribute('data-status') === statusSelect.value);
            });

            const table = $('#approval-izin-table').DataTable({
                order: [],
                pageLength: 15,
                lengthMenu: [[10, 15, 25, 50, -1], [10, 15, 25, 50, 'Semua']],
                columnDefs: [{ targets: 5, orderable: false, searchable: false }],
                dom: "<'ap-dt-toolbar'<'ap-dt-len'l><'ap-dt-search'f>>t<'ap-dt-foot'<'ap-dt-info'i><'ap-dt-page'p>>",
                language: {
                    search: '',
                    searchPlaceholder: 'Cari nama, jenis, atau alasan…',
                    lengthMenu: 'Tampil _MENU_ baris',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ pengajuan',
                    infoEmpty: 'Tidak ada pengajuan',
                    infoFiltered: '(dari _MAX_ total pengajuan)',
                    zeroRecords: 'Tidak ada pengajuan yang cocok dengan filter.',
                    emptyTable: 'Belum ada pengajuan izin, sakit, atau cuti.',
                    paginate: { previous: '‹', next: '›' }
                }
            });

            statusSelect.addEventListener('change', function () { table.draw(); });
            document.getElementById('ap-reset').addEventListener('click', function () {
                statusSelect.value = '';
                table.search('').draw();
            });

            table.table().container().querySelector('.dataTables_filter input')
                .setAttribute('aria-label', 'Cari pengajuan');
            table.table().container().querySelector('.dataTables_length select')
                .setAttribute('aria-label', 'Jumlah baris per halaman');
        });
    })();
</script>
