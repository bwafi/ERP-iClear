<form action="<?php echo base_url('update_insert/service/savePembayaran') ?>" id="form_pemPembayaran" enctype="multipart/form-data" method="post">
    <div class="mt-3">

        <div class="col-md-6">
            <label for="metode_bayar" class="form-label">Metode Pembayaran</label>
            <select id="metode_bayar" name="metode_bayar" class="form-control">
                <option disabled selected>Pilih Metode</option>
                <option value="tunai">Tunai</option>
                <option value="transfer">Transfer</option>
                <option value="tunai_transfer">Tunai + Transfer</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Service Staff</label>
            <select name="service_by_pembayaran" class="form-control form-control-lg">
                <option value="" disabled <?= empty($old_service_pelanggan->service_by) ? 'selected' : '' ?>>-- Pilih Service Staff --</option>
                <?php foreach ($teknisi as $a): ?>
                    <option value="<?= $a->ID_AKUN ?>" <?= @$old_service_pelanggan->service_by == $a->ID_AKUN ? 'selected' : '' ?>>
                        <?= $a->NAMA_AKUN ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="text" hidden name="idservice_p" value="<?php echo @$idservice ?>" id="">

        <div class="mb-3">
            <label class="form-label fw-semibold">No Service</label>
            <input type="text" name="no_service_pembayaran" value="<?php echo @$old_service_pelanggan->no_service ?>"
                class="form-control form-control-lg" readonly>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Diskon</label>
            <input type="text" name="diskon_pembayaran" id="diskon_pembayaran" readonly
                class="form-control form-control-lg" value="Rp 0">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">DP</label>
            <input type="text" name="dp_bayar" id="dp_bayar" readonly class="form-control form-control-lg"
                value="<?php echo @$old_service_pelanggan->dp_bayar ?>">
        </div>


        <div class="mb-3">
            <label class="form-label fw-semibold">Total Harga</label>
            <input type="text" name="total_harga_pembayaran" id="total_harga_pembayaran"
                class="form-control form-control-lg" readonly value="Rp 0">
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Total Harga Akhir</label>
            <input type="text" name="total_harga_pembayaran_akhir" id="total_harga_pembayaran_akhir"
                class="form-control form-control-lg" readonly value="Rp 0">
        </div>

        <div class="mb-3 tunai-section">
            <label class="form-label fw-semibold">Bayar Tunai</label>
            <input type="text" name="bayar_pembayaran" id="bayar" class="form-control form-control-lg"
                oninput="handleBayarInput()" placeholder="Rp 0">
        </div>

        <div class="row mb-3">
            <div class="col-12">
                <label class="form-label">Pembayaran Lama</label>
                <div id="pembayaran_lama">

                    <div class="row bank-row mb-2">

                        <div style="display: flex; justify-content: space-between;">

                            <div class="col-md-4">
                                <input readonly type="text" value="Tunai" class="form-control tunai1-amount_lama" name="idbanklama">
                            </div>
                            <div class="col-md-4">
                                <input readonly type="text"
                                    id="tunai-amount-lama"
                                    class="form-control tunai-amount-lama"
                                    name="tunai_amout_lama"
                                    value="Rp <?= number_format($old_service_pelanggan->bayar_tunai, 0, ',', '.') ?>">
                            </div>

                        </div>
                        <br><br><br>
                        <?php foreach ($pembayaran_lama as $lama): ?>
                            <div style="display: flex; justify-content: space-between;">

                                <div class="col-md-4">
                                    <input readonly type="text" value="<?= $lama->bank_idbank ?>" class="form-control bank-amount" name="idbanklama">
                                </div>
                                <div class="col-md-4">
                                    <input readonly type="text"
                                        class="form-control bank-amount-lama"
                                        name="bank_amout_lama"
                                        value="Rp <?= number_format($lama->jumlah, 0, ',', '.') ?>">
                                </div>

                            </div>
                            <br><br><br>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>


            <div class="row mb-3 transfer-section">
                <div class="col-12">
                    <label class="form-label">Pembayaran via Bank</label>
                    <div id="bank-payment-container">
                        <!-- Baris bank akan ditambahkan secara dinamis -->
                    </div>
                    <button type="button" class="btn btn-sm btn-primary mt-2" id="tambah-bank">
                        + Tambah Bank
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Kembalian</label>
                <input type="text" name="kembalian" id="kembalian" class="form-control form-control-lg" readonly
                    value="Rp 0">
            </div>
        </div>


        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-light btn-lg me-2" id="btn-previous-to-sparepart">Sebelumnya</button>
            <button type="submit" id="submitSemuaForm" class="btn btn-success btn-lg">Submit</button>
        </div>
</form>
<script>
    function parseRupiahToNumber(rp) {
        if (!rp) return 0;
        let clean = rp.replace(/[^0-9]/g, '');
        return parseInt(clean) || 0;
    }

    function formatRupiah(angka) {
        if (!angka) return 'Rp 0';
        return 'Rp ' + angka.toLocaleString('id-ID');
    }

    function hitungTotalPembayaran() {
        const bayarTunai = parseRupiahToNumber(document.getElementById('bayar').value);
        const bayarTunaiLama = parseRupiahToNumber(document.getElementById('tunai-amount-lama').value);
        let totalTransfer = 0;
        let totalTransferlama = 0;

        document.querySelectorAll('.bank-amount').forEach(input => {
            totalTransfer += parseRupiahToNumber(input.value);
        });

        // Tambahkan pembayaran lama dari database
        document.querySelectorAll('.bank-amount-lama').forEach(input => {
            totalTransferlama += parseRupiahToNumber(input.value);
        });

        return bayarTunai + totalTransfer + totalTransferlama + bayarTunaiLama;
    }

    function hitungKembalian() {
        const totalHargaStr = document.getElementById('total_harga_pembayaran_akhir').value;
        const totalHarga = parseRupiahToNumber(totalHargaStr);

        const totalBayar = hitungTotalPembayaran();

        let kembalian = totalBayar - totalHarga;
        if (kembalian < 0) kembalian = 0;

        document.getElementById('kembalian').value = formatRupiah(kembalian);
    }

    function handleBayarInput() {
        const bayarInput = document.getElementById('bayar');
        let numberValue = parseRupiahToNumber(bayarInput.value);
        bayarInput.value = formatRupiah(numberValue);
        bayarInput.setSelectionRange(bayarInput.value.length, bayarInput.value.length);
        hitungKembalian();
    }
</script>

<script>
    $(document).ready(function() {
        $('.tunai-section, .transfer-section').hide();

        $('#metode_bayar').on('change', function() {
            var metode = $(this).val();
            if (metode === 'tunai') {
                $('.tunai-section').show();
                $('.transfer-section').hide();
            } else if (metode === 'transfer') {
                $('.tunai-section').hide();
                $('.transfer-section').show();
            } else if (metode === 'tunai_transfer') {
                $('.tunai-section').show();
                $('.transfer-section').show();
            } else {
                $('.tunai-section, .transfer-section').hide();
            }
        });

        function tambahBarisBank() {
            const container = $('#bank-payment-container');
            const index = container.children('.bank-row').length;

            const newRow = $(`
            <div class="row bank-row mb-2">
                <div class="col-md-6">
                    <select name="bank[${index}][id]" class="select2 form-control bank-select" style="width: 100%;" required>
                        <option disabled selected>Pilih Bank</option>
                        <?php foreach ($bank as $p): ?>
                            <option value="<?= htmlspecialchars($p->idbank) ?>">
                                <?= htmlspecialchars($p->nama_bank) . ' ' . ($p->atas_nama)?> : <?= htmlspecialchars($p->norek) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control bank-amount" name="bank[${index}][jumlah]" value="Rp 0">
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-danger btn-sm hapus-bank">&times;</button>
                </div>
            </div>
            `);

            container.append(newRow);

            newRow.find('.bank-amount').on('input', function() {
                const numeric = this.value.replace(/[^\d]/g, '');
                this.value = formatRupiah(parseInt(numeric || 0)); // <- pakai formatRupiah
                hitungKembalian();
            });

            newRow.find('.hapus-bank').on('click', function() {
                $(this).closest('.bank-row').remove();
                hitungKembalian();
            });

            newRow.find('.bank-select').select2({
                dropdownParent: $('body')
            });
        }

        $('#tambah-bank').on('click', tambahBarisBank);

        $('#metode_bayar').on('change', function() {
            if ($(this).val() === 'transfer' || $(this).val() === 'tunai_transfer') {
                if ($('#bank-payment-container .bank-row').length === 0) {
                    tambahBarisBank();
                }
            }
        });

        $('#bank_idbank').select2({
            dropdownParent: $('body')
        });
    });
</script>


<script>
    document.getElementById('btn-previous-to-sparepart').addEventListener('click', function() {
        var tabTrigger = new bootstrap.Tab(document.querySelector('#sparepart-tab'));
        tabTrigger.show();
    });
</script>