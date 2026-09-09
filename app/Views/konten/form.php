<style>
    #talentSelect + .select2-container,
    #creativeSelect + .select2-container { width: 100% !important; }
    .select2-container .select2-selection--multiple { min-height: 44px; border: 1px solid #ced4da; border-radius: 8px; padding: 3px 6px; }
    .select2-container .select2-selection__choice { border-radius: 20px; padding: 2px 10px; font-weight: 500; background: #cfe2ff; border-color: #cfe2ff; color: #084298; }
</style>
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <div>
            <h4 class="fw-semibold mb-0"><?= $content ? 'Edit Konten' : 'Tambah Konten' ?></h4>
            <small class="text-muted">KPI Multimedia/Creative — satu content satu pekerjaan/karya.</small>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('konten') ?>">Manajemen Konten</a></li>
                <li class="breadcrumb-item active"><?= $content ? 'Edit' : 'Tambah' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="post" action="<?= base_url('konten/simpan') ?>">
            <input type="hidden" name="id" value="<?= $content->id ?? 0 ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Judul Konten <span class="text-danger">*</span></label>
                    <input type="text" name="judul" class="form-control" required value="<?= esc($content->judul ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Content Type</label>
                    <select name="content_type_id" class="form-select">
                        <option value="">— Pilih —</option>
                        <?php foreach ($contentTypes as $ct) : ?>
                            <option value="<?= $ct->id ?>" <?= ($content->content_type_id ?? null) == $ct->id ? 'selected' : '' ?>><?= esc($ct->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Jenis Konten</label>
                    <select name="jenis_konten" class="form-select">
                        <option value="REGULAR" <?= ($content->jenis_konten ?? 'REGULAR') === 'REGULAR' ? 'selected' : '' ?>>Regular</option>
                        <option value="ADS" <?= ($content->jenis_konten ?? 'REGULAR') === 'ADS' ? 'selected' : '' ?>>Iklan (ADS)</option>
                    </select>
                    <small class="text-muted">KPI Performa menilai konten Iklan (ADS).</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"><?= esc($content->deskripsi ?? '') ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Deadline <span class="text-danger">*</span></label>
                    <input type="date" name="deadline" class="form-control" required value="<?= esc($content->deadline ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target Scope</label>
                    <div class="d-flex gap-4 pt-2">
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="target_scope" value="ALL" <?= ($content->target_scope ?? 'ALL') === 'ALL' ? 'checked' : '' ?>>
                            <span class="form-check-label">ALL (semua unit)</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="target_scope" value="SELECTED" <?= ($content->target_scope ?? '') === 'SELECTED' ? 'checked' : '' ?>>
                            <span class="form-check-label">SELECTED</span>
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Metric Performa (target)</label>
                    <select name="performance_metric_id" class="form-select">
                        <option value="">— Pilih —</option>
                        <?php foreach ($metrics as $m) : ?>
                            <option value="<?= $m->id ?>" <?= ($content->performance_metric_id ?? null) == $m->id ? 'selected' : '' ?>><?= esc($m->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target Performa</label>
                    <input type="number" step="0.01" min="0" name="performance_target" class="form-control" value="<?= esc($content->performance_target ?? '') ?>">
                </div>
            </div>

            <div class="row g-3 mt-2" id="targetUnitsBlock" style="<?= ($content->target_scope ?? 'ALL') === 'SELECTED' ? '' : 'display:none;' ?>">
                <div class="col-12">
                    <label class="form-label">Target Unit (untuk SELECTED)</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($units as $u) : if (!in_array((int)$u->idunit, array_map('intval', $allowedUnits), true)) continue; ?>
                            <label class="form-check">
                                <input class="form-check-input target-unit-check" type="checkbox" name="target_units[]" value="<?= $u->idunit ?>"
                                    <?= in_array((int)$u->idunit, array_map('intval', $selectedUnits), true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc($u->NAMA_UNIT) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold mb-0">Talent <small class="text-muted">(orang yang tampil)</small></label>
                            <span class="badge bg-info-subtle text-info" id="talentCount">0 dipilih</span>
                        </div>
                        <select name="talent_ids[]" id="talentSelect" class="form-select select2" multiple="multiple">
                            <?php foreach ($peoples as $p) : ?>
                                <option value="<?= $p->ID_AKUN ?>" <?= in_array((int)$p->ID_AKUN, array_map('intval', $talentIds), true) ? 'selected' : '' ?>>
                                    <?= esc($p->NAMA_AKUN) ?><?= $p->NAMA_JABATAN ? ' — ' . esc($p->NAMA_JABATAN) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">Boleh pilih banyak, boleh sama dengan Multimedia.</small>
                            <button type="button" class="btn btn-sm btn-outline-secondary clear-picker" data-target="#talentSelect">Bersihkan</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 h-100 bg-light-subtle">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold mb-0">Multimedia/Creative <small class="text-muted">(pembuat/desain)</small></label>
                            <span class="badge bg-primary-subtle text-primary" id="creativeCount">0 dipilih</span>
                        </div>
                        <select name="creative_ids[]" id="creativeSelect" class="form-select select2" multiple="multiple">
                            <?php foreach ($multimediaPeoples as $p) : ?>
                                <option value="<?= $p->ID_AKUN ?>" <?= in_array((int)$p->ID_AKUN, array_map('intval', $creativeIds), true) ? 'selected' : '' ?>>
                                    <?= esc($p->NAMA_AKUN) ?><?= $p->NAMA_JABATAN ? ' — ' . esc($p->NAMA_JABATAN) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">Hanya pegawai jabatan Multimedia/Creative.</small>
                            <button type="button" class="btn btn-sm btn-outline-secondary clear-picker" data-target="#creativeSelect">Bersihkan</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a href="<?= base_url('konten') ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
    $('input[name="target_scope"]').on('change', function() {
        $('#targetUnitsBlock').toggle($(this).val() === 'SELECTED');
    });

    $(function() {
        function updatePickerCount() {
            $('#talentCount').text(($('#talentSelect').val() || []).length + ' dipilih');
            $('#creativeCount').text(($('#creativeSelect').val() || []).length + ' dipilih');
        }
        $('#talentSelect').on('change', updatePickerCount);
        $('#creativeSelect').on('change', updatePickerCount);
        $('.clear-picker').on('click', function() {
            $($(this).data('target')).val(null).trigger('change');
        });
        updatePickerCount();
    });
</script>