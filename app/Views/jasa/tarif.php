<?= $this->include('jasa/_tarif_theme') ?>

<?php
$jumlah = count($jasa);
$harga  = array_map(static fn ($j) => (float) $j->harga, $jasa);
$rp     = static fn (float $nilai): string => 'Rp ' . number_format($nilai, 0, ',', '.');
$angka  = static fn (float $nilai): string => $jumlah > 0 ? number_format($nilai, 0, ',', '.') : '—';
?>

<div class="tarif-jasa">
    <header class="tj-head">
        <ol class="tj-crumb">
            <li>Master Jasa</li>
            <li aria-current="page">Tarif Jasa</li>
        </ol>

        <div class="tj-head__row">
            <div>
                <h1>Tarif Jasa</h1>
                <p class="tj-sub">Harga jasa yang dipakai kasir. Jasa tidak punya stok dan tidak masuk nilai persediaan.</p>
            </div>
            <button type="button" class="tj-btn tj-btn--primary" data-bs-toggle="modal" data-bs-target="#tj-tambah">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    aria-hidden="true">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Tambah Jasa
            </button>
        </div>

        <dl class="tj-figures">
            <div class="tj-fig">
                <dt>Total Jasa</dt>
                <dd><?= $angka($jumlah) ?></dd>
            </div>
            <div class="tj-fig">
                <dt>Harga Terendah</dt>
                <dd><?= $jumlah > 0 ? $rp(min($harga)) : '—' ?></dd>
            </div>
            <div class="tj-fig">
                <dt>Harga Tertinggi</dt>
                <dd><?= $jumlah > 0 ? $rp(max($harga)) : '—' ?></dd>
            </div>
            <div class="tj-fig">
                <dt>Rata-rata</dt>
                <dd><?= $jumlah > 0 ? $rp(array_sum($harga) / $jumlah) : '—' ?></dd>
            </div>
        </dl>
    </header>

    <?php if (session()->getFlashdata('sukses')): ?>
        <div class="tj-flash tj-flash--ok" role="status">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" aria-hidden="true">
                <path d="m5 13 4 4L19 7" />
            </svg>
            <?= esc(session()->getFlashdata('sukses')) ?>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('gagal')): ?>
        <div class="tj-flash tj-flash--fail" role="alert">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" aria-hidden="true">
                <path d="M12 8v5M12 16.5v.5" />
                <circle cx="12" cy="12" r="9" />
            </svg>
            <?= esc(session()->getFlashdata('gagal')) ?>
        </div>
    <?php endif; ?>

    <section class="tj-sheet" aria-label="Daftar tarif jasa">
        <?php if ($jumlah > 0): ?>
            <div class="tj-toolbar">
                <div class="tj-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                    <label class="visually-hidden" for="tj-search">Cari kode atau nama jasa</label>
                    <input type="search" id="tj-search" placeholder="Cari kode atau nama jasa" autocomplete="off">
                </div>
                <label class="visually-hidden" for="tj-sort">Urutkan</label>
                <select class="tj-sort" id="tj-sort">
                    <option value="1:asc">Nama A–Z</option>
                    <option value="1:desc">Nama Z–A</option>
                    <option value="2:asc">Fee termurah</option>
                    <option value="2:desc">Fee termahal</option>
                    <option value="3:asc">Harga termurah</option>
                    <option value="3:desc">Harga termahal</option>
                </select>
                <span class="tj-count" id="tj-count"><?= $angka($jumlah) ?> jasa</span>
            </div>

            <table class="tj-table" id="tj-table">
                <caption class="visually-hidden">Daftar tarif jasa beserta HPP dan harga jual</caption>
                <thead>
                    <tr>
                        <th scope="col">Kode</th>
                        <th scope="col">Nama Jasa</th>
                        <th scope="col" class="tj-col-price">HPP</th>
                        <th scope="col" class="tj-col-price">Harga Jual</th>
                        <th scope="col" class="tj-col-action">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jasa as $j): ?>
                        <tr>
                            <td data-label="Kode"><span class="tj-code"><?= esc($j->kode_barang) ?></span></td>
                            <td data-label="Nama Jasa"><span class="tj-name"><?= esc($j->nama_barang) ?></span></td>
                            <td data-label="HPP" class="tj-col-price" data-order="<?= (int) $j->fee_teknisi ?>">
                                <span class="tj-fee"><?= (int) $j->fee_teknisi > 0 ? $rp((float) $j->fee_teknisi) : '—' ?></span>
                            </td>
                            <td data-label="Harga Jual" class="tj-col-price" data-order="<?= (int) $j->harga ?>">
                                <span class="tj-price"><?= $rp((float) $j->harga) ?></span>
                            </td>
                            <td class="tj-col-action">
                                <button type="button" class="tj-edit" data-bs-toggle="modal" data-bs-target="#tj-ubah"
                                    data-id="<?= (int) $j->idbarang ?>" data-nama="<?= esc($j->nama_barang) ?>"
                                    data-harga="<?= (int) $j->harga ?>" data-fee="<?= (int) $j->fee_teknisi ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M4 20h4L20 8l-4-4L4 16v4Z" />
                                    </svg>
                                    Ubah
                                    <span class="visually-hidden">tarif <?= esc($j->nama_barang) ?></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="tj-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                    aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h10" />
                </svg>
                <p>Belum ada jasa. Tambahkan jasa pertama untuk mulai menjualnya di kasir.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Modal tambah jasa -->
<div class="modal fade tj-modal" id="tj-tambah" tabindex="-1" aria-labelledby="tj-tambah-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= base_url('tarif_jasa/simpan') ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title" id="tj-tambah-label">Tambah Jasa</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="tj-field">
                    <label for="tj-kode">Kode</label>
                    <input type="text" id="tj-kode" name="kode_barang" required autocomplete="off">
                    <p class="tj-hint">Harus unik; dipakai kasir untuk mencari jasa ini.</p>
                </div>
                <div class="tj-field">
                    <label for="tj-nama">Nama Jasa</label>
                    <input type="text" id="tj-nama" name="nama_barang" required autocomplete="off">
                </div>
                <div class="tj-field">
                    <label for="tj-harga">Harga Jual</label>
                    <input type="text" id="tj-harga" name="harga" inputmode="numeric" autocomplete="off" required>
                    <p class="tj-preview" data-preview-for="tj-harga"></p>
                </div>
                <div class="tj-field">
                    <label for="tj-fee">HPP</label>
                    <div class="tj-pair">
                        <input type="text" id="tj-fee" name="harga_beli" inputmode="numeric" autocomplete="off"
                            value="0" placeholder="Nominal (Rp)" aria-label="HPP nominal">
                        <input type="number" id="tj-fee-persen" name="fee_persen" min="0" max="100" step="0.01"
                            inputmode="decimal" placeholder="Persen (%)" aria-label="HPP persen dari harga jual">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="tj-btn tj-btn--ghost" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="tj-btn tj-btn--primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal ubah jasa -->
<div class="modal fade tj-modal" id="tj-ubah" tabindex="-1" aria-labelledby="tj-ubah-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= base_url('tarif_jasa/ubah') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="idbarang" id="tj-ubah-id">
            <div class="modal-header">
                <h2 class="modal-title" id="tj-ubah-label">Ubah Jasa</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="tj-field">
                    <label for="tj-ubah-nama">Nama Jasa</label>
                    <input type="text" id="tj-ubah-nama" name="nama_barang" required autocomplete="off">
                </div>
                <div class="tj-field">
                    <label for="tj-ubah-harga">Harga Jual</label>
                    <input type="text" id="tj-ubah-harga" name="harga" inputmode="numeric" autocomplete="off" required>
                    <p class="tj-preview" data-preview-for="tj-ubah-harga"></p>
                </div>
                <div class="tj-field">
                    <label for="tj-ubah-fee">HPP</label>
                    <div class="tj-pair">
                        <input type="text" id="tj-ubah-fee" name="harga_beli" inputmode="numeric" autocomplete="off"
                            value="0" placeholder="Nominal (Rp)" aria-label="HPP nominal">
                        <input type="number" id="tj-ubah-fee-persen" name="fee_persen" min="0" max="100" step="0.01"
                            inputmode="decimal" placeholder="Persen (%)" aria-label="HPP persen dari harga jual">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="tj-btn tj-btn--ghost" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="tj-btn tj-btn--primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(function () {
        var angkaDari = function (nilai) {
            var digit = String(nilai == null ? '' : nilai).replace(/\D/g, '');
            return digit === '' ? 0 : Number(digit);
        };

        var formatRupiah = function (nilai) {
            var digit = String(nilai == null ? '' : nilai).replace(/\D/g, '');
            return digit === '' ? '' : new Intl.NumberFormat('id-ID').format(digit);
        };

        var rupiah = function (nilai) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(angkaDari(nilai));
        };

        var pasangFormat = function (input) {
            if (!input) return;
            input.addEventListener('input', function () {
                var digitSebelum = input.value.slice(0, input.selectionStart).replace(/\D/g, '').length;
                var hasil = formatRupiah(input.value);
                input.value = hasil;

                var posisi = hasil.length;
                if (digitSebelum === 0) {
                    posisi = 0;
                } else {
                    var terlihat = 0;
                    for (var i = 0; i < hasil.length; i++) {
                        if (/\d/.test(hasil.charAt(i))) terlihat++;
                        if (terlihat === digitSebelum) {
                            posisi = i + 1;
                            break;
                        }
                    }
                }
                input.setSelectionRange(posisi, posisi);
            });
        };

        var pasangPratinjau = function (input, target) {
            var el = document.querySelector(target);
            if (!input || !el) return;

            var tulis = function () {
                el.textContent = input.value ? rupiah(input.value) : '';
            };

            input.addEventListener('input', tulis);
            tulis();
            return tulis;
        };

        var pasangFee = function (harga, nominal, persen, tulisNominal) {
            if (!harga || !nominal || !persen) return null;

            var dariPersen = function () {
                var h = angkaDari(harga.value);
                var p = persen.value === '' ? NaN : Number(persen.value);
                if (isNaN(p)) return;

                nominal.value = formatRupiah(Math.round(h * p / 100));
                if (tulisNominal) tulisNominal();
            };

            persen.addEventListener('input', dariPersen);
            nominal.addEventListener('input', function () {
                persen.value = '';
                if (tulisNominal) tulisNominal();
            });
            harga.addEventListener('input', function () {
                if (persen.value !== '') dariPersen();
            });

            return dariPersen;
        };

        ['tj-harga', 'tj-fee', 'tj-ubah-harga', 'tj-ubah-fee'].forEach(function (id) {
            pasangFormat(document.getElementById(id));
        });

        pasangPratinjau(document.getElementById('tj-harga'), "[data-preview-for='tj-harga']");
        var tulisTambahFee = pasangPratinjau(document.getElementById('tj-fee'), "[data-preview-for='tj-fee']");
        var tulisUbahFee = pasangPratinjau(document.getElementById('tj-ubah-fee'), "[data-preview-for='tj-ubah-fee']");
        var tulisUbah = pasangPratinjau(document.getElementById('tj-ubah-harga'), "[data-preview-for='tj-ubah-harga']");

        pasangFee(document.getElementById('tj-harga'), document.getElementById('tj-fee'),
            document.getElementById('tj-fee-persen'), tulisTambahFee);
        pasangFee(document.getElementById('tj-ubah-harga'), document.getElementById('tj-ubah-fee'),
            document.getElementById('tj-ubah-fee-persen'), tulisUbahFee);

        $('#tj-ubah').on('show.bs.modal', function (event) {
            var btn = $(event.relatedTarget);
            $('#tj-ubah-id').val(btn.data('id'));
            $('#tj-ubah-nama').val(btn.data('nama'));
            $('#tj-ubah-harga').val(formatRupiah(btn.data('harga')));
            $('#tj-ubah-fee').val(formatRupiah(btn.data('fee')));
            $('#tj-ubah-fee-persen').val('');
            if (tulisUbah) tulisUbah();
            if (tulisUbahFee) tulisUbahFee();
        });

        if (!document.getElementById('tj-table') || !$.fn.DataTable) return;

        var table = $('#tj-table').DataTable({
            order: [[1, 'asc']],
            pageLength: 25,
            lengthChange: false,
            columnDefs: [{ orderable: false, targets: 4 }],
            dom: "<'tj-table-wrap't><'tj-dt-foot'ip>",
            language: {
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ jasa',
                infoEmpty: 'Tidak ada jasa',
                infoFiltered: '(dari _MAX_)',
                zeroRecords: 'Tidak ada jasa yang cocok',
                paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
            }
        });

        var hitung = function () {
            $('#tj-count').text(new Intl.NumberFormat('id-ID').format(table.rows({ search: 'applied' }).count()) + ' jasa');
        };

        $('#tj-search').on('input', function () {
            table.search(this.value).draw();
            hitung();
        });

        $('#tj-sort').on('change', function () {
            var pilihan = String(this.value).split(':');
            table.order([Number(pilihan[0]), pilihan[1]]).draw();
        });

        hitung();
    });
</script>
