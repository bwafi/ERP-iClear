<div class="d-flex align-items-center gap-2 mb-3 text-muted fs-3">
    <i class="bi bi-info-circle"></i>
    Centang bagian yang rusak, lalu tulis keterangan singkat (opsional).
</div>

<form action="<?php echo base_url('service/saveKerusakan') ?>" enctype="multipart/form-data" method="post">
    <?php
    $kerusakan_terpilih = [];
    foreach ($oldkerusakan as $item) {
        $kerusakan_terpilih[$item->fungsi_idfungsi] = $item->keterangan;
    }
    ?>

    <div class="row g-3">
        <?php
        $chunks = array_chunk($fungsi, ceil(count($fungsi) / 2));
        foreach ($chunks as $group) : ?>
            <div class="col-md-6">
                <div class="d-grid gap-2">
                <?php foreach ($group as $row) :
                    $idfungsi = $row->idfungsi;
                    $sudah_dipilih = array_key_exists($idfungsi, $kerusakan_terpilih);
                    $keterangan = $kerusakan_terpilih[$idfungsi] ?? '';
                    $checked = $sudah_dipilih ? 'checked' : '';
                    $display = $sudah_dipilih ? '' : 'display: none;';
                ?>
                    <div class="border rounded-2 p-3 <?= $sudah_dipilih ? 'border-success bg-success-subtle' : '' ?>" id="func-card_<?= esc($idfungsi) ?>">
                        <div class="form-check fs-5 mb-0">
                            <input class="form-check-input me-2 checkbox-fungsi"
                                type="checkbox"
                                name="fungsi[]"
                                value="<?= esc($idfungsi) ?>"
                                id="fungsi_<?= esc($idfungsi) ?>"
                                data-id="<?= esc($idfungsi) ?>"
                                <?= $checked ?>>

                            <label class="form-check-label" for="fungsi_<?= esc($idfungsi) ?>">
                                <?= esc($row->nama_fungsi) ?>
                            </label>
                        </div>

                        <div class="mt-2"
                            id="keterangan_<?= esc($idfungsi) ?>"
                            style="<?= $display ?>">
                            <textarea class="form-control"
                                name="keterangan[<?= esc($idfungsi) ?>]"
                                rows="2"
                                placeholder="Tulis keterangan untuk <?= esc($row->nama_fungsi) ?>"><?= esc($keterangan) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="service-step-footer">
        <div>
            <button type="button" class="btn btn-outline-secondary" id="btn-previous-to-pelanggan">
                <i class="bi bi-arrow-left me-1"></i> Sebelumnya
            </button>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fs-3 d-none d-sm-inline">Disimpan lalu lanjut ke cetak invoice.</span>
            <input hidden type="text" name="idservice_k" value="<?php echo @$idservice ?>">
            <button type="submit" id="btnnextnya" class="btn btn-primary">
                Simpan <i class="bi bi-check2 ms-1"></i>
            </button>
        </div>
    </div>

    <input type="text" hidden id="idpelakan" value="<?php echo @$idservice ?>">
</form>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnnextnya').addEventListener('click', function(e) {
            const idPela = document.getElementById('idpelakan').value;

            if (!idPela || idPela.trim() === '') {
                e.preventDefault(); // cegah form submit
                alert('Silakan pilih pelanggan terlebih dahulu melalui tombol input data pelanggan pada tab pelanggan kemudian tekan tombol simpan!');
                return false;
            }
        });
    });
</script>



<script>
    // Saat dokumen siap
    document.addEventListener("DOMContentLoaded", function() {
        const checkboxes = document.querySelectorAll('.checkbox-fungsi');

        function syncCard(checkbox) {
            const id = checkbox.dataset.id;
            const keteranganDiv = document.getElementById('keterangan_' + id);
            const card = document.getElementById('func-card_' + id);
            if (checkbox.checked) {
                if (keteranganDiv) keteranganDiv.style.display = 'block';
                if (card) {
                    card.classList.add('border-success', 'bg-success-subtle');
                    card.classList.remove('border');
                }
            } else {
                if (keteranganDiv) keteranganDiv.style.display = 'none';
                if (card) {
                    card.classList.remove('border-success', 'bg-success-subtle');
                    card.classList.add('border');
                }
            }
        }

        checkboxes.forEach(function(checkbox) {
            if (checkbox.disabled) return;
            checkbox.addEventListener('change', function() {
                syncCard(checkbox);
            });
        });
    });
</script>

<script>
    document.querySelectorAll('.checkbox-fungsi').forEach(function(checkbox) {
        if (!checkbox.disabled) {
            checkbox.addEventListener('change', function() {
                var id = this.getAttribute('data-id');
                var textarea = document.getElementById('keterangan_' + id);
                textarea.style.display = this.checked ? 'block' : 'none';
            });
        }
    });
</script>

<script>
    document.getElementById('btn-previous-to-pelanggan').addEventListener('click', function() {
        var tabTrigger = new bootstrap.Tab(document.querySelector('#pelanggan-tab'));
        tabTrigger.show();
    });
</script>