<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<div class="card"><div class="card-body">
    <h4>Barang dari Klaim Garansi Customer</h4>
    <p>Pilih nomor service dan klaim. Barang serta nota asal ditarik dari klaim; stok jual tidak berkurang lagi.</p>
    <form method="get" action="<?= base_url('input_barang_rusak') ?>">
        <label class="form-label" for="damage-claim">Service / Klaim</label>
        <select class="form-select mb-3" id="damage-claim" name="claim_id" required>
            <option value="">Pilih klaim customer</option>
            <?php foreach ($claimChoices ?? [] as $claim): ?>
                <option value="<?= (int) $claim->idriwayat_claim_garansi ?>"><?= esc($claim->no_service) ?> — Klaim #<?= (int) $claim->idriwayat_claim_garansi ?></option>
            <?php endforeach ?>
        </select>
        <button class="btn btn-primary">Buka Barang dari Klaim</button>
    </form>
</div></div>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Kerusakan Stok Biasa</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Barang Rusak</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Barang Rusak</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom">
    </div>

    <div class="card-body px-4 pt-4 pb-2 d-flex justify-content-between align-items-start mb-1">
        <div class="d-flex gap-2">
        </div>

        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-barang-modal"
            style="display: inline-flex; align-items: center;">
            <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                style="margin-right: 8px;"></iconify-icon>Input
        </button>

    </div>
    <!-- FORM DINAMIS HASIL PILIHAN -->
    <div class="mt-4">
        <h5 style="padding-left: 20px;">Barang Terpilih:</h5>
        <form id="formBarangTerpilih" method="post" enctype="multipart/form-data" action="<?= base_url('insert_barang_rusak') ?>">
            <select hidden name="unit_idunit" id="unitFilter3" class="form-select" style="width: auto; display: inline-block;">
                <option value="">Semua Unit</option>
                <?php foreach ($unit as $u): ?>
                    <option value="<?= esc($u->idunit) ?>"
                        <?= (session('ID_UNIT') == $u->idunit) ? 'selected' : '' ?>>
                        <?= esc($u->NAMA_UNIT) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="selected-items"></div>
            <div class="text-end mt-3">
                <button type="submit" id="btnSimpan" class="btn btn-success" style="display:none; margin-top: 20px; margin-bottom: 20px;">
                    Simpan Data Barang Rusak
                </button>
            </div>
        </form>
    </div>

</div>





<!-- MODAL PILIH BARANG -->
<div class="modal fade" id="input-barang-modal" tabindex="-1" aria-labelledby="inputbarangModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-fullscreen" role="document">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center">
                <h4 class="modal-title" id="inputbarangModalLabel">Pilih Data Barang</h4>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">


                <p style="color: red; font-style: italic; font-size: small;">* Masukan Kode Suplier Ke Kolom Pencarian Untuk Mencari</p>

                <div class="mb-3">
                    <label style="padding-right: 40px;" for="unitFilter" class="form-label"> Unit: </label>
                    <select hidden id="unitFilter" class="form-select" style="width: auto; display: inline-block;">
                        <option value="">Semua Unit</option>
                        <?php foreach ($unit as $u): ?>
                            <option value="<?= esc($u->idunit) ?>"
                                <?= (session('ID_UNIT') == $u->idunit) ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select disabled id="unitFilter2" class="form-select" style="width: auto; display: inline-block;">
                        <option value="">Semua Unit</option>
                        <?php foreach ($unit as $u): ?>
                            <option value="<?= esc($u->idunit) ?>"
                                <?= (session('ID_UNIT') == $u->idunit) ? 'selected' : '' ?>>
                                <?= esc($u->NAMA_UNIT) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>


                </div>






                <table class="table table-bordered text-nowrap mb-0 align-middle" id="table_barang">
                    <thead class="text-dark fs-4">
                        <tr>
                            <th><input type="checkbox" id="checkAll"></th>
                            <th>No Nota Suplier</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Imei</th>
                            <th>Suplier</th>
                            <th>Tanggal Pembelian</th>
                            <th>Jumlah</th>
                            <th>unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($detail_pembelian)): ?>
                            <?php foreach ($detail_pembelian as $row): ?>
                                <tr data-unit="<?= esc($row->unit_idunit) ?>">
                                    <td>
                                        <input type="checkbox" class="check-item"
                                            data-no_batch="<?= esc($row->no_batch) ?>"
                                            data-kode_barang="<?= esc($row->kode_barang) ?>"
                                            data-nama_barang="<?= esc($row->nama_barang) ?>"
                                            data-imei="<?= esc($row->imei ?: 'Tidak ada IMEI') ?>"
                                            data-nama_suplier="<?= esc($row->nama_suplier) ?>"
                                            data-tanggal="<?= esc($row->tanggal) ?>"
                                            data-jumlah="<?= esc($row->jumlah) ?>"
                                            data-unit="<?= esc($row->unit_idunit) ?>"
                                            data-idbarang="<?= esc($row->barang_idbarang) ?>"
                                            data-idpembelian="<?= esc($row->pembelian_idpembelian) ?>">


                                    </td>
                                    <td><?= esc($row->no_batch) ?></td>
                                    <td><?= esc($row->kode_barang) ?></td>
                                    <td><?= esc($row->nama_barang) ?></td>
                                    <td><?= esc($row->imei) ?: '<span class="text-muted">Tidak ada IMEI</span>' ?></td>
                                    <td><?= esc($row->nama_suplier) ?></td>
                                    <td><?= esc($row->tanggal) ?></td>
                                    <td><?= esc($row->jumlah) ?></td>
                                    <td><?= esc($row->unit_idunit) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">Tidak ada data barang rusak</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btnPilih">Pilih</button>
            </div>
        </div>
    </div>
</div>

<!-- // -->

<!-- SCRIPT -->


<script>
    $(document).ready(function() {

        // Fungsi untuk cek apakah ada item, lalu tampil/sembunyikan tombol simpan
        function toggleSimpanButton() {
            if ($('#selected-items .item-row').length > 0) {
                $('#btnSimpan').show();
            } else {
                $('#btnSimpan').hide();
            }
        }

        // Centang semua
        $('#checkAll').on('change', function() {
            $('.check-item').prop('checked', $(this).prop('checked'));
        });

        // Tombol Pilih
        $('#btnPilih').on('click', function() {
            $('#selected-items').empty(); // Kosongkan form dinamis sebelumnya

            $('.check-item:checked').each(function() {
                const no_batch = $(this).data('no_batch');
                const kode_barang = $(this).data('kode_barang');
                const nama_barang = $(this).data('nama_barang');
                const imei = $(this).data('imei');
                const nama_suplier = $(this).data('nama_suplier');
                const tanggal = $(this).data('tanggal');
                const jumlah = $(this).data('jumlah');
                const idbarang = $(this).data('idbarang');
                const idpembelian = $(this).data('idpembelian');

                const html = `
                    <div class="card mb-3 p-3 border rounded item-row">
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label">No Nota Suplier</label>
                                <input type="text" class="form-control" name="no_batch[]" value="${no_batch}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Kode Barang</label>
                                <input type="text" class="form-control" name="kode_barang[]" value="${kode_barang}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Nama Barang</label>
                                <input type="text" class="form-control" name="nama_barang[]" value="${nama_barang}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">IMEI</label>
                                <input type="text" class="form-control" name="imei[]" value="${imei}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Suplier</label>
                                <input type="text" class="form-control" name="nama_suplier[]" value="${nama_suplier}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal Pembelian</label>
                                <input type="text" class="form-control" name="tanggal[]" value="${tanggal}" readonly>
                            </div>
                        </div>


                        <div class="row g-2 mt-2">
                            <div class="col-md-2">
                                <label class="form-label">ID Barang</label>
                                <input type="text" class="form-control" name="idbarang[]" value="${idbarang}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">ID Pembelian</label>
                                <input type="text" class="form-control" name="idpembelian[]" value="${idpembelian}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Jumlah (Total)</label>
                                <input type="number" class="form-control" name="jumlah_total[]" value="${jumlah}" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-danger">Jumlah Rusak</label>
                                <input type="number" class="form-control jumlah-rusak" name="jumlah_rusak[]" min="1" max="${jumlah}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tanggal Rusak</label>
                                <input type="date" class="form-control" name="tanggal_rusak[]" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-remove w-100">Hapus</button>
                            </div>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-md-4">
                                <label class="form-label">Asal fisik barang</label>
                                <select class="form-select" name="asal_dokumen[]">
                                    <option value="terverifikasi">Nota pembelian ini terbukti sebagai asal</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="hidden" name="claim_id[]" value="">
                                <p>Untuk stok rusak di toko. Barang yang diterima dari customer dicatat lewat pilihan klaim di bagian atas.</p>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Keterangan</label>
                                <textarea class="form-control" name="keterangan[]" style="height:100px;" placeholder="Tulis keterangan barang rusak..."></textarea>
                            </div>
                        </div>
                    </div>
                `;

                $('#selected-items').append(html);
            });

            // Tutup modal setelah memilih
            $('#input-barang-modal').modal('hide');

            // Cek tombol simpan
            toggleSimpanButton();
        });

        // Hapus baris form dinamis
        $(document).on('click', '.btn-remove', function() {
            $(this).closest('.item-row').remove();
            toggleSimpanButton(); // Cek lagi setelah hapus
        });

        // Validasi jumlah rusak tidak boleh melebihi jumlah total
        $(document).on('input', '.jumlah-rusak', function() {
            const max = parseInt($(this).attr('max'));
            const val = parseInt($(this).val());
            if (val > max) {
                $(this).addClass('is-invalid');
                $(this).val(max);
            } else {
                $(this).removeClass('is-invalid');
            }
        });
    });
</script>



<script>
    $(document).ready(function() {
        // Inisialisasi DataTable
        let table = $('#table_barang').DataTable();

        // Ambil unit yang sedang dipilih (default dari session)
        const defaultUnit = $('#unitFilter').val();

        // Jalankan filter awal jika defaultUnit ada
        if (defaultUnit) {
            table.column(8).search('^' + defaultUnit + '$', true, false).draw();
        }

        // Tambahkan event listener untuk perubahan dropdown
        $('#unitFilter').on('change', function() {
            const selectedUnit = $(this).val();
            if (selectedUnit) {
                table.column(8).search('^' + selectedUnit + '$', true, false).draw();
            } else {
                table.column(8).search('').draw();
            }
        });
    });
</script>
