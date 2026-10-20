<div class="d-flex align-items-center gap-2 mb-3 text-muted fs-3">
    <i class="bi bi-info-circle"></i>
    Centang bagian yang rusak, lalu tulis keterangan singkat (opsional).
</div>

<form id="service-kerusakan-form" action="<?php echo base_url('service/saveKerusakan') ?>" enctype="multipart/form-data" method="post" <?= ($workflow_mode ?? null) === 'technician' ? '' : 'target="_blank"' ?>>
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
            <input hidden type="text" name="idservice_k" value="<?php echo @$idservice ?>">
            <?php if (($workflow_mode ?? null) === 'technician'): ?>
                <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
                    <label class="form-label mb-0 flex-grow-1">Rekomendasi untuk Kasir
                        <textarea class="form-control mt-1" name="recommendation" rows="2" placeholder="Contoh: ganti baterai; jelaskan alasan singkat."><?= esc($workflow->recommendation ?? '') ?></textarea>
                    </label>
                    <button type="submit" name="send_to_cashier" value="1" id="btnnextnya" class="btn btn-success" aria-live="polite">
                        Kirim ke Kasir <i class="bi bi-send ms-1"></i>
                    </button>
                    <button type="submit" class="btn btn-outline-primary">Simpan Pemeriksaan</button>
                </div>
            <?php else: ?>
                <span class="text-muted fs-3 d-none d-sm-inline">Data disimpan, lalu invoice service dibuka.</span>
                <button type="submit" id="btnnextnya" class="btn btn-primary" aria-live="polite">
                    Simpan &amp; Cetak Invoice <i class="bi bi-printer ms-1"></i>
                </button>
            <?php endif ?>
        </div>
    </div>

    <input type="text" hidden id="idpelakan" value="<?php echo @$idservice ?>">
</form>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('service-kerusakan-form');
        const nextButton = document.getElementById('btnnextnya');
        let isSubmitting = false;

        if (form && nextButton) {
            form.addEventListener('submit', function(event) {
                const idPelanggan = document.getElementById('idpelakan')?.value || '';
                if (!idPelanggan.trim()) {
                    event.preventDefault();
                    alert('Silakan pilih pelanggan terlebih dahulu melalui tab Pelanggan.');
                    return;
                }

                if (isSubmitting) {
                    event.preventDefault();
                    return;
                }

                isSubmitting = true;
                const submitter = event.submitter || nextButton;
                submitter.disabled = true;
                submitter.setAttribute('aria-busy', 'true');
                submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>'
                    + (submitter.name === 'send_to_cashier' ? 'Mengirim ke Kasir...' : 'Menyimpan pemeriksaan...');
            });
        }
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
