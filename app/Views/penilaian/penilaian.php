<!-- Breadcrumb -->
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0"> Penilaian</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>"></a>
                </li>
                <li class="breadcrumb-item" aria-current="page">Penilaian</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Main Card -->
<div class="card w-100 position-relative overflow-hidden">
    <div class="px-4 py-3 border-bottom"></div>

    <div class="px-4 py-3 border-bottom d-flex justify-content-between">
        <div class="d-flex gap-2">
            <form action="<?php echo base_url('export_penilaian') ?>" method="post" enctype="multipart/form-data">
                <button type="submit" class="btn btn-danger"
                    style="margin-left: 20px; display: inline-flex; align-items: center;">
                    <iconify-icon icon="solar:export-broken" width="24" height="24" style="margin-right: 8px;">
                    </iconify-icon>
                    Export
                </button>
                <br><br>
                <label class="ms-3 me-2">Tanggal Awal:</label>
                <input name="tanggal_awal" type="date" id="startDate" class="form-control d-inline"
                    style="width: auto; display: inline-block;" onchange="filterData()">

                <label class="ms-3 me-2">Tanggal Akhir:</label>
                <input name="tanggal_akhir" type="date" id="endDate" class="form-control d-inline"
                    style="width: auto; display: inline-block;" onchange="filterData()">
                
                <label class="ms-3 me-2">Aspek:</label>
                <select id="aspekFilter" class="form-select d-inline"
                    style="width: 200px; display: inline-block;"
                    onchange="filterData()">
                    <option value="">-- Semua Aspek --</option>
                
                    <?php
                    $listAspek = [];
                    foreach ($penilaian as $row) {
                        if (!in_array($row->aspek, $listAspek)) {
                            $listAspek[] = $row->aspek;
                        }
                    }
                
                    sort($listAspek);
                
                    foreach ($listAspek as $aspek): ?>
                        <option value="<?= esc($aspek) ?>">
                            <?= esc(ucwords($aspek)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" onclick="resetFilter()" class="btn btn-sm btn-secondary ms-3">Reset</button>
            </form>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#input-penilaian-modal"
            style="display: inline-flex; align-items: center; height: 50px;">
            <iconify-icon icon="solar:password-minimalistic-input-broken" width="24" height="24"
                style="margin-right: 8px;"></iconify-icon>
            Input
        </button>

    </div>

    <br>
    <div class="mb-3 "><br></div>

    <div class="table-responsive mb-4 px-4">
        <table class="table border text-nowrap mb-0 align-middle" id="zero_config">
            <thead class="text-dark fs-4">
                <tr>
                    <th>Nama Pegawai</th>
                    <th>Aspek</th>
                    <th>Keterangan</th>
                    <th>Input By</th>
                    <th>Skor</th>
                    <th>Tanggal Penilaian</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($penilaian)): ?>
                <?php foreach ($penilaian as $row): ?>
                <tr>
                    <td><?= esc($row->nama_pegawai) ?></td>
                    <td><?= esc($row->aspek) ?></td>
                    <td><?= esc($row->keterangan) ?></td>
                    <td><?= esc($row->nama_input) ?></td>
                    <td><?= esc($row->skor) ?></td>
                    <td><?= esc(date('d-m-Y', strtotime($row->tanggal_penilaian))) ?></td>
                    <td>

                        <button type="button" class="btn btn-danger delete-button" data-bs-toggle="modal"
                            data-bs-target="#delete-penilaian-modal" data-idpenilaian="<?= esc($row->idpenilaian) ?>">
                            <iconify-icon icon="solar:trash-bin-minimalistic-broken" width="24" height="24">
                            </iconify-icon>
                        </button>
                            
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="delete-penilaian-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form action="<?= base_url('delete_penilaian') ?>" method="post">

                <div class="modal-header">
                    <h5 class="modal-title">Hapus Penilaian</h5>
                    <button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>Yakin ingin menghapus data ini?</p>

                    <input type="hidden"
                        name="idpenilaian"
                        id="delete-id_penilaian">
                </div>

                <div class="modal-footer">
                    <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Batal
                    </button>

                    <button type="submit"
                        class="btn btn-danger">
                        Hapus
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- Modal Input penilaian -->
<div class="modal fade" id="input-penilaian-modal" tabindex="-1" aria-labelledby="inputPenilaianModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="<?= base_url('insert_penilaian') ?>" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h4 class="modal-title" id="inputPenilaianModalLabel">Input Data Penilaian</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pegawai_idpegawai" class="form-label">Pegawai</label>
                                <select class="form-select select2" name="pegawai_idpegawai"
                                    id="input-pegawai_idpegawai" required>
                                    <option value="" disabled selected>-- Pilih Pegawai --</option>
                                    <?php foreach ($akun as $row): ?>
                                    <option data-idjabatan="<?= esc($row->ID_JABATAN) ?>"
                                        value="<?= esc($row->ID_AKUN) ?>">
                                        <?= esc($row->NAMA_AKUN) ?> : <?= esc($row->NAMA_JABATAN) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tanggal_penilaian" class="form-label">Tanggal Penilaian</label>
                                <input type="date" class="form-control" name="tanggal_penilaian" id="tanggal_penilaian"
                                    required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="aspek3" class="form-label">Aspek</label>
                                <select class="form-select" name="aspek3"
                                    id="aspek3" required>
                                    <option value="" disabled selected>-- Pilih Aspek --</option>
                                    <?php if (in_array(session()->get('ID_JABATAN'), [35, 41, 1])): ?>
                                        <option value="kehadiran">Kehadiran</option>
                                        <option value="kebersihan">Kebersihan</option>
                                        <option value="kepatuhan sop">Kepatuhan SOP</option>
                                        <option value="seragam">Seragam</option>
                                    <?php endif; ?>

                                    <?php if (in_array(session()->get('ID_JABATAN'), [43])): ?>
                                        <option value="kebersihan">Kebersihan</option>
                                        <option value="kepatuhan sop">Kepatuhan SOP</option>
                                        <option value="seragam">Seragam</option>
                                    <?php endif;?>
                                    
                                    <?php if (in_array(session()->get('ID_JABATAN'), [40, 1, 43])): ?>
                                        <option value="closing">closing</option>
                                        <option value="follow up">Follow Up</option>

                                        <option value="budgeting">Budgeting</option>
                                        <option value="ROAS">ROAS</option>

                                        <option value="Feed PL">Feed PL</option>
                                        <option value="Video">Video</option>
                                        <option value="Feed Mingguan">Feed Mingguan</option>
                                        <option value="story">story</option>
                                        <option value="testimoni">Testimoni</option>
                                        
                                        <option value="bug minor">Bug Minor</option>
                                        <option value="operasional">Operasional</option>
                                        <option value="ecommerce">Ecommerce</option>
                                        <option value="fitur">Fitur</option>
                                    <?php endif; ?>

                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="skor3" class="form-label">Skor</label>
                                <input
                                    type="number"
                                    class="form-control"
                                    name="skor3"
                                    id="skor3"
                                    min="1"
                                    max="5"
                                    step="1"
                                    required>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="row">
                        <div class="col-md-6 slider-container"></div>
                        <div class="col-md-12 input-container"></div>
                    </div>
                    <?php if (session()->get('ID_JABATAN') == 35): ?>
                        *Note : Skor Maksimal 5<br>
                        <li>5(Tepat Waktu)
                        <li>4(Telat 5 Menit)
                        <li>3(Telat 10 Menit)
                        <li>2(Telat 15 menit)
                        <li>1(Telat 20 Menit)
                    <?php else: ?>
                        *Note : Skor Maksimal 5<br>
                        <li>5 (Sangat Baik)</li>
                        <li>4 (Baik)</li>
                        <li>3 (Cukup)</li>
                        <li>2 (Kurang)</li>
                        <li>1 (Sangat Kurang)</li>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#input-penilaian-modal').on('shown.bs.modal', function() {
        $(this).find('.select2').select2({
            dropdownParent: $('#input-penilaian-modal'),
            width: '100%',
        });
    });

    document.querySelector('#zero_config').addEventListener('click', function(e) {

        table.settings()[0].aoColumns[1,2,3,4,5].bSearchable = false;

        const editBtn = e.target.closest('.edit-button');
        if (editBtn) {
            $('.select2').select2({
                dropdownParent: $('#edit-penilaian-modal')
            });
            document.getElementById('edit-idpenilaian').value = editBtn.getAttribute(
                'data-idpenilaian');
            document.getElementById('edit-aspek').value = editBtn.getAttribute('data-aspek');
            document.getElementById('edit-keterangan').value = editBtn.getAttribute('data-keterangan');
            document.getElementById('edit-skor').value = editBtn.getAttribute('data-skor');
            document.getElementById('edit-tanggal_penilaian').value = editBtn.getAttribute(
                'data-tanggal_penilaian');
            const pegawaiId = editBtn.getAttribute('data-pegawai_idpegawai');
            setTimeout(function() {
                $('#edit-pegawai_idpegawai').val(pegawaiId).trigger('change');
            }, 200);
        }
        
    });
});

$(document).ready(function() {
    $('#input-pegawai_idpegawai').on('select2:select', function(e) {
        const idJabatan = $('#input-pegawai_idpegawai').find(':selected').data('idjabatan');
        $.ajax({
            url: `/penilaian/get_template_by_jabatan/${idJabatan}`,
            type: 'GET',
            success: function(res) {
                renderFormPenilaian(res);
            },
            error: function(xhr, status, err) {
                console.error('Gagal mengambil data:', err);
            }
        });
    });

    function renderFormPenilaian(template) {
        const sliderContainer = $('.slider-container').empty();
        const inputContainer = $('.input-container').empty();
        const jumlahMap = <?= json_encode($jumlahMap) ?>;
        const groupedSlider = {};
        const groupedInput = {};

        template.forEach(row => {
            if (row.status == 1) {
                if (!groupedSlider[row.aspek_kpi]) groupedSlider[row.aspek_kpi] = [];
                groupedSlider[row.aspek_kpi].push(row);
            } else if (row.status == 2) {
                if (!groupedInput[row.aspek_kpi]) groupedInput[row.aspek_kpi] = [];
                groupedInput[row.aspek_kpi].push(row);
            }
        });

        for (const aspek in groupedSlider) {
            sliderContainer.append(`<div class="mt-3"><strong>${aspek}</strong></div>`);
            groupedSlider[aspek].forEach(row => {
                sliderContainer.append(`
        <input hidden name="template_ids1[]" value="${row.idtemplate_penilaian}">
        <input hidden name="aspek[]" value="${row.aspek_penilaian}">
        <input hidden name="keterangan[]" value="${row.keterangan_penilaian}">
        <input hidden name="target[]" value="${row.target_penilaian}">
        <input hidden name="bobot[]" value="${row.bobot}">
        <input hidden name="idtempkpi1[]" value="${row.idtemplate_kpi}">
        <div class="mb-3 border rounded p-2">
            <label class="form-label">${row.aspek_penilaian}</label>
            <p class="small text-muted">Target: ${row.target}</p>
            <input type="range" class="form-range" name="skor1[]" min="1" max="5" step="1"
                oninput="document.getElementById('range-value-${row.idtemplate_penilaian}').innerText = this.value">
            <div class="small">Skor: <span id="range-value-${row.idtemplate_penilaian}">3</span></div>
        </div>
        `);
            });
        }

        for (const aspek in groupedInput) {
            inputContainer.append(`<div class="mt-3"><strong>${aspek}</strong></div>`);
            groupedInput[aspek].forEach(row => {
                inputContainer.append(`
            <input hidden name="template_ids2[]" value="${row.idtemplate_penilaian}">
            <input hidden name="aspek[]" value="${row.aspek_penilaian}">
            <input hidden name="keterangan[]" value="${row.keterangan_penilaian}">
            <input hidden name="bobot2[]" value="${row.bobot}">
            <input hidden name="target2[]" value="${row.target}">
            <input hidden name="idtempkpi2[]" value="${row.idtemplate_kpi}">
            <div class="mb-3 border rounded p-2">
                <label class="form-label">${row.aspek_penilaian}</label>
                <p class="small text-muted">Target: ${row.target}</p>
                <p class="small text-muted">Bobot: ${row.bobot} %</p>
                <input type="number" class="form-control" name="skor2[]" 
                    min="0" step="0.01" 
                    value="${jumlahMap[row.idtemplate_penilaian] || ''}" 
                    required>
            </div>
        `);
            });
        }
    }
});

// ==================== DATE FILTER FUNCTION ====================
function filterData() {

    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const aspek = document.getElementById('aspekFilter').value.toLowerCase();

    const rows = document.querySelectorAll('#zero_config tbody tr');

    rows.forEach(row => {

        let tampil = true;

        // ================= Filter Tanggal =================
        if (startDate && endDate) {

            const dateCell = row.cells[5];

            if (dateCell) {

                const dateParts = dateCell.innerText.trim().split('-');
                const rowDate = new Date(`${dateParts[2]}-${dateParts[1]}-${dateParts[0]}`);

                const start = new Date(startDate);
                const end = new Date(endDate);

                if (rowDate < start || rowDate > end) {
                    tampil = false;
                }

            }

        }

        // ================= Filter Aspek =================
        if (aspek !== '') {

            const aspekCell = row.cells[1].innerText.trim().toLowerCase();

            if (aspekCell !== aspek) {
                tampil = false;
            }

        }

        row.style.display = tampil ? '' : 'none';

    });

}

// ==================== RESET FILTER ====================
function resetFilter() {

    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('aspekFilter').value = '';

    document.querySelectorAll('#zero_config tbody tr').forEach(row => {
        row.style.display = '';
    });

}
</script>
<script>
$(document).ready(function () {

    // hapus wrapper datatable sebelumnya
    if ($.fn.DataTable.isDataTable('#zero_config')) {
        $('#zero_config').DataTable().destroy();
    }

    // hapus elemen UI datatable yang dobel
    $('#zero_config').siblings('.dataTables_wrapper').remove();

    // init ulang
    $('#zero_config').DataTable({
        destroy: true,
        retrieve: true,
        columnDefs: [
            {
                targets: [1,2,3,4,5,6],
                searchable: false
            }
        ]
    });

});
</script>
<script>
    $(document).on('click', '.delete-button', function () {

    const idpenilaian = $(this).data('idpenilaian');

    $('#delete-id_penilaian').val(idpenilaian);

    console.log(idpenilaian); // cek apakah masuk
});
</script>
<script>
const skorInput = document.getElementById('skor3');

// cegah minus & karakter aneh
skorInput.addEventListener('keydown', function(e) {

    // blok:
    // minus
    // e
    // +
    // .
    if (
        e.key === '-' ||
        e.key === 'e' ||
        e.key === '+' ||
        e.key === '.'
    ) {
        e.preventDefault();
    }

});

// validasi nilai
skorInput.addEventListener('input', function() {

    let value = parseInt(this.value);

    if (value < 1) {
        this.value = 1;
    }

    if (value > 5) {
        this.value = 5;
    }

});

</script>
<script>
$(document).ready(function () {
    const idJabatanSession = <?= json_encode(session()->get('ID_JABATAN')) ?>;

    if (idJabatanSession == 40){
        $('#input-pegawai_idpegawai').on('change', function () {

            const idJabatan = $(this).find(':selected').data('idjabatan');
            const aspek = $('#aspek3');

            aspek.html('<option value="" disabled selected>-- Pilih Aspek --</option>');
            
            if (idJabatan == 41) {

                aspek.append(`
                    <option value="kebersihan">Kebersihan</option>
                    <option value="kepatuhan sop">Kepatuhan SOP</option>
                    <option value="seragam">Seragam</option>
                `);

            }

            if (idJabatan == 42) {

                aspek.append(`
                    <option value="kebersihan">Kebersihan</option>
                    <option value="kepatuhan sop">Kepatuhan SOP</option>
                    <option value="seragam">Seragam</option>
                    <option value="closing">Closing</option>
                    <option value="follow up">Follow Up</option>
                `);

            }   

            // jabatan 40
            else if (idJabatan == 43) {

                aspek.append(`
                    <option value="kebersihan">Kebersihan</option>
                    <option value="kepatuhan sop">Kepatuhan SOP</option>
                    <option value="seragam">Seragam</option>
                    <option value="budgeting">Budgeting</option>
                    <option value="ROAS">ROAS</option>
                `);

            }

            else if (idJabatan == 44) {

                aspek.append(`
                    <option value="kebersihan">Kebersihan</option>
                    <option value="kepatuhan sop">Kepatuhan SOP</option>
                    <option value="seragam">Seragam</option>
                    <option value="Feed PL">Feed PL</option>
                    <option value="Video">Video</option>
                    <option value="Feed Mingguan">Feed Mingguan</option>
                    <option value="story">Story</option>
                    <option value="testimoni">Testimoni</option>
                `);

            }

            else if (idJabatan == 45) {

                aspek.append(`
                    <option value="kebersihan">Kebersihan</option>
                    <option value="kepatuhan sop">Kepatuhan SOP</option>
                    <option value="seragam">Seragam</option>
                    <option value="bug minor">Bug Minor</option>
                    <option value="operasional">Operasional</option>
                    <option value="ecommerce">Ecommerce</option>
                    <option value="fitur">Fitur</option>
                `);

            }

        });
    }

});
</script>