<style>
    /* Perbaiki posisi panah Select2 domisili agar berada di dalam field */
    select.js-domisili+.select2-container .select2-selection--single {
        height: 38px;
    }

    select.js-domisili+.select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 38px;
    }

    select.js-domisili+.select2-container .select2-selection--single .select2-selection__arrow {
        top: 50%;
        right: 12px;
        height: 38px;
        width: auto;
        transform: translateY(-20%);
    }
</style>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <span class="text-body d-inline-flex align-items-center gap-2">
        <i class="bi bi-person-badge"></i>
        Diinput oleh: <strong><?= $akun->NAMA_AKUN ?></strong>
    </span>
    <button type="button" class="btn btn-warning d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#pelangganModal">
        <iconify-icon icon="mdi:account" width="20" height="20"></iconify-icon>
        Input Data Pelanggan
    </button>
    <input type="hidden" name="id_akun" value="<?= $akun->ID_AKUN ?>">
</div>

<form action="<?php echo base_url('insert/pelanggan_service') ?>" enctype="multipart/form-data" method="post">
    <div class="row g-3">

        <input hidden type="text" name="idservice" value="<?php echo @$idservice ?>">
        <input hidden type="text" id="created_at" value="<?php echo @$old_service_pelanggan->created_at ?>">
        <input type="hidden" name="selectedidpelanggan" id="idpela" value="<?php echo @$old_service_pelanggan->id_pelanggan ?>">

        <!-- Perangkat -->
        <div class="col-md-4">
            <label class="form-label fw-semibold">Tipe HP</label>
            <input type="text" placeholder="" class="form-control" name="tipe_hp">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">IMEI</label>
            <input type="text" value="<?php echo @$old_service_pelanggan->imei ?>" class="form-control" name="imei">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">DP</label>
            <input type="text" value="<?php echo @$old_service_pelanggan->dp_bayar ?>" class="form-control" name="dp_bayar">
        </div>

        <!-- Pelanggan -->
        <div class="col-md-4">
            <label class="form-label fw-semibold">Nama Pelanggan</label>
            <input type="text" class="form-control" id="nama_pelanggan" value="<?php echo @$old_service_pelanggan->nama ?>" readonly>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">No HP</label>
            <input type="text" class="form-control" value="<?php echo @$old_service_pelanggan->no_hp ?>" name="no_hp" id="no_hp" readonly>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Domisili Provinsi</label>
            <select class="form-control form-select js-domisili" id="form_provinsi" name="domisili_provinsi" disabled>
                <option value="">-- Pilih / Cari Provinsi --</option>
                <?php foreach ($provinsi as $p): ?>
                    <option value="<?= esc($p->name) ?>" <?= isset($old_service_pelanggan) && $old_service_pelanggan && $old_service_pelanggan->provinsi == $p->name ? 'selected' : '' ?>>
                        <?= esc($p->name) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Domisili Kabupaten</label>
            <select class="form-control form-select js-domisili" id="form_kabupaten" name="domisili_kabupaten" disabled>
                <option value="">-- Pilih / Cari Kabupaten --</option>
                <?php if (isset($old_service_pelanggan) && $old_service_pelanggan && @$old_service_pelanggan->kabupaten): ?>
                    <option value="<?= esc($old_service_pelanggan->kabupaten) ?>" selected><?= esc($old_service_pelanggan->kabupaten) ?></option>
                <?php endif ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Domisili Kecamatan</label>
            <select class="form-control form-select js-domisili" id="form_kecamatan" name="domisili_kecamatan" disabled>
                <option value="">-- Pilih / Cari Kecamatan --</option>
                <?php if (isset($old_service_pelanggan) && $old_service_pelanggan && @$old_service_pelanggan->kecamatan): ?>
                    <option value="<?= esc($old_service_pelanggan->kecamatan) ?>" selected><?= esc($old_service_pelanggan->kecamatan) ?></option>
                <?php endif ?>
            </select>
        </div>

        <!-- Akses perangkat -->
        <div class="col-md-4">
            <label class="form-label fw-semibold">Passcode</label>
            <div class="input-group">
                <input value="<?php echo @$old_service_pelanggan->passcode ?>" type="password"
                    class="form-control" name="passcode" id="passcode">
                <span class="input-group-text">
                    <i class="fas fa-eye toggle-password" toggle="#passcode" style="cursor: pointer;"></i>
                </span>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Email (iCloud)</label>
            <input type="email" value="<?php echo @$old_service_pelanggan->email_icloud ?>" placeholder="@icloud.com"
                class="form-control" name="email_icloud">
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Password (iCloud)</label>
            <div class="input-group">
                <input type="password" value="<?php echo @$old_service_pelanggan->password_icloud ?>"
                    placeholder="********" class="form-control" name="password_icloud" id="password_icloud">
                <span class="input-group-text">
                    <i class="fas fa-eye toggle-password" toggle="#password_icloud" style="cursor: pointer;"></i>
                </span>
            </div>
        </div>

        <!-- Keluhan -->
        <div class="col-md-6">
            <label class="form-label fw-semibold">Keluhan</label>
            <textarea style="height: 100px;" class="form-control"
                name="keluhan"><?php echo @$old_service_pelanggan->keluhan ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Keterangan</label>
            <textarea style="height: 100px;" type="text" class="form-control"
                name="keterangan"><?php echo @$old_service_pelanggan->keterangan ?></textarea>
        </div>

    </div>

    <!-- Footer -->
    <div class="service-step-footer">
        <div>
            <?php if (!empty($idservice)): ?>
                <span class="text-muted fs-3 d-inline-flex align-items-center gap-1">
                    Selanjutnya: centang kerusakan di tahap &quot;Kerusakan&quot;.
                </span>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" id="btn-simpan" class="btn btn-primary">
                <i class="bi bi-check2 me-1"></i> Simpan
            </button>
            <button type="button" class="btn btn-outline-primary" id="btn-next-to-kerusakan">
                Selanjutnya <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    <div class="modal fade" id="pelangganModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cari Data Pelanggan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Ketik Nama atau No HP:</label>
                        <select id="pelanggan-select" name="selectedidpelanggan" class="form-control select2-ajax" style="width: 100%;">
                            <option value="">Cari pelanggan...</option>
                        </select>
                    </div>

                    <div style="display: flex; justify-content: right; gap: 10px; margin-top: 20px;">
                        <button id="btnPilihPelanggan" type="button" class="btn btn-primary">Pilih</button>
                        <button id="btnTambahPelanggan" type="button" class="btn btn-success">Tambah Baru</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="modalTambahPelanggan" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formTambahPelanggan">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pelanggan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama</label>
                        <input type="text" id="nama" name="nama" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="no_hp" class="form-label">No HP</label>
                        <input type="text" id="no_hp" name="no_hp" class="form-control" required />
                    </div>

                    <div class="mb-3">
                        <label for="domisili_provinsi" class="form-label">Domisili Provinsi</label>
                        <select id="domisili_provinsi" name="provinsi" class="form-select js-domisili" required>
                            <option value="">-- Pilih / Cari Provinsi --</option>
                            <?php foreach ($provinsi as $p): ?>
                                <option value="<?= esc($p->name) ?>"><?= esc($p->name) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="domisili_kabupaten" class="form-label">Domisili Kabupaten</label>
                        <select id="domisili_kabupaten" name="kabupaten" class="form-select js-domisili" required>
                            <option value="">-- Pilih / Cari Kabupaten --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="domisili_kecamatan" class="form-label">Domisili Kecamatan</label>
                        <select id="domisili_kecamatan" name="kecamatan" class="form-select js-domisili" required>
                            <option value="">-- Pilih / Cari Kecamatan --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Simpan Pelanggan</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pelangganModal = new bootstrap.Modal(document.getElementById('pelangganModal'));
        const modalTambah = new bootstrap.Modal(document.getElementById('modalTambahPelanggan'));

        // Select2 searchable untuk input data pelanggan (Form Utama)
        $('#form_provinsi, #form_kabupaten, #form_kecamatan').select2({
            width: '100%'
        });

        function pushOption($sel, val) {
            if (!val) return;
            const escaped = String(val).replace(/"/g, '&quot;');
            if (!$sel.find('option[value="' + escaped + '"]').length) {
                $sel.append($('<option>', {
                    value: val,
                    text: val
                }));
            }
        }

        function setSelectedCustomer(id, nama, noHp, domisiliData) {
            if (!id) return;
            document.getElementById('idpela').value = id;
            document.getElementById('nama_pelanggan').value = nama || '';
            document.getElementById('no_hp').value = noHp || '';

            const d = domisiliData || {};
            pushOption($('#form_provinsi'), d.provinsi);
            pushOption($('#form_kabupaten'), d.kabupaten);
            pushOption($('#form_kecamatan'), d.kecamatan);
            $('#form_provinsi').data('desired', d.provinsi || '');
            $('#form_kabupaten').data('desired', d.kabupaten || '');
            $('#form_kecamatan').data('desired', d.kecamatan || '');
            $('#form_provinsi').val(d.provinsi || '').trigger('change');
            $('#form_kabupaten').val(d.kabupaten || '').trigger('change');
            $('#form_kecamatan').val(d.kecamatan || '').trigger('change');
        }

        function domisiliFromOption(selectedData, selectedOption) {
            let d = selectedData ? {
                provinsi: selectedData.provinsi,
                kabupaten: selectedData.kabupaten,
                kecamatan: selectedData.kecamatan
            } : {};
            if ((!d.provinsi && !d.kabupaten && !d.kecamatan) && selectedOption) {
                d = {
                    provinsi: selectedOption.getAttribute('data-provinsi'),
                    kabupaten: selectedOption.getAttribute('data-kabupaten'),
                    kecamatan: selectedOption.getAttribute('data-kecamatan')
                };
            }
            return d;
        }

        // Inisialisasi Select2 dengan AJAX
        $('#pelanggan-select').select2({
            dropdownParent: $('#pelangganModal'),
            placeholder: '-- Pilih atau Cari Pelanggan --',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: '<?= base_url('service/search_pelanggan') ?>',
                type: 'POST',
                dataType: 'json',
                delay: 200,
                data: function(params) {
                    return {
                        search: params.term || ''
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(function(item) {
                            return {
                                id: item.id_pelanggan,
                                text: item.nama + ' (' + item.no_hp + ')',
                                nama: item.nama,
                                no_hp: item.no_hp,
                                provinsi: item.provinsi,
                                kabupaten: item.kabupaten,
                                kecamatan: item.kecamatan
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // Saat opsi di select dipilih langsung update input
        $('#pelanggan-select').on('select2:select', function(e) {
            const data = e.params.data;
            if (data && data.id) {
                setSelectedCustomer(data.id, data.nama, data.no_hp, data);
            }
        });

        // Tombol "Tambah" di bawah dropdown
        document.getElementById('btnTambahPelanggan').addEventListener('click', function() {
            modalTambah.show();
        });

        // Saat tombol "Pilih" ditekan
        document.getElementById('btnPilihPelanggan').addEventListener('click', function() {
            const selectedData = $('#pelanggan-select').select2('data')[0];
            const selectEl = document.getElementById('pelanggan-select');
            const selectedOption = selectEl.options[selectEl.selectedIndex];

            const id = (selectedData && selectedData.id) ? selectedData.id : (selectEl.value || '');

            if (!id) {
                alert('Silakan pilih pelanggan terlebih dahulu.');
                return;
            }

            let nama = (selectedData ? selectedData.nama : '') || (selectedOption ? selectedOption.getAttribute('data-nama') : '') || '';
            let noHp = (selectedData ? selectedData.no_hp : '') || (selectedOption ? selectedOption.getAttribute('data-nohp') : '') || '';
            const domisiliData = domisiliFromOption(selectedData, selectedOption);

            if (!nama && selectedData && selectedData.text) {
                const parts = selectedData.text.split(' (');
                if (parts.length >= 2) {
                    nama = parts[0].trim();
                    noHp = parts[1].replace(')', '').trim();
                } else {
                    nama = selectedData.text;
                }
            }

            setSelectedCustomer(id, nama, noHp, domisiliData);
            pelangganModal.hide();
        });


        $('#formTambahPelanggan').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                url: '<?php echo base_url('simpan/pelanggan') ?>',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        modalTambah.hide();
                        $('#formTambahPelanggan')[0].reset();

                        const newOption = new Option(
                            response.data.nama + ' (' + response.data.no_hp + ')',
                            response.data.id_pelanggan,
                            true,
                            true
                        );
                        $(newOption).attr('data-nama', response.data.nama);
                        $(newOption).attr('data-nohp', response.data.no_hp);
                        $(newOption).attr('data-provinsi', response.data.provinsi || '');
                        $(newOption).attr('data-kabupaten', response.data.kabupaten || '');
                        $(newOption).attr('data-kecamatan', response.data.kecamatan || '');

                        $('#pelanggan-select').append(newOption).trigger('change');

                        // Set langsung ke form input
                        setSelectedCustomer(
                            response.data.id_pelanggan,
                            response.data.nama,
                            response.data.no_hp,
                            response.data
                        );

                        pelangganModal.hide();
                        alert('Pelanggan berhasil ditambahkan dan dipilih');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan saat menyimpan data.');
                }
            });
        });

        $('#modalTambahPelanggan').on('shown.bs.modal', function() {
            $('#modalTambahPelanggan select.js-domisili').each(function() {
                $(this).select2({
                    dropdownParent: $('#modalTambahPelanggan'),
                    width: '100%'
                });
            });
        });

        $('#modalTambahPelanggan').on('hidden.bs.modal', function() {
            $('#modalTambahPelanggan select.js-domisili').select2('destroy');
            $('#modalTambahPelanggan select').val('').trigger('change');
        });

        // Cascade Domisili (Modal Tambah)
        $('#domisili_provinsi').on('change', function() {
            let provinsi = $(this).val();

            $('#domisili_kabupaten').html('<option value="">Loading...</option>').trigger('change');
            $('#domisili_kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').trigger('change');

            if (provinsi !== '') {
                $.getJSON("<?= base_url('region/kabupaten') ?>/" + encodeURIComponent(provinsi), function(data) {
                    let opt = '<option value="">-- Pilih Kabupaten --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#domisili_kabupaten').html(opt).trigger('change');
                });
            }
        });

        $('#domisili_kabupaten').on('change', function() {
            let kabupaten = $(this).val();

            $('#domisili_kecamatan').html('<option value="">Loading...</option>').trigger('change');

            if (kabupaten !== '') {
                $.getJSON("<?= base_url('region/kecamatan') ?>/" + encodeURIComponent(kabupaten), function(data) {
                    let opt = '<option value="">-- Pilih Kecamatan --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#domisili_kecamatan').html(opt).trigger('change');
                });
            }
        });

        // Cascade Domisili (Form Utama)
        $('#form_provinsi').on('change', function() {
            let provinsi = $(this).val();

            $('#form_kabupaten').html('<option value="">Loading...</option>').trigger('change');
            $('#form_kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').trigger('change');

            if (provinsi !== '') {
                $.getJSON("<?= base_url('region/kabupaten') ?>/" + encodeURIComponent(provinsi), function(data) {
                    let opt = '<option value="">-- Pilih Kabupaten --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#form_kabupaten').html(opt).trigger('change');
                    const desiredKab = $('#form_kabupaten').data('desired');
                    if (desiredKab) {
                        pushOption($('#form_kabupaten'), desiredKab);
                        $('#form_kabupaten').val(desiredKab).trigger('change');
                    }
                });
            }
        });

        $('#form_kabupaten').on('change', function() {
            let kabupaten = $(this).val();

            $('#form_kecamatan').html('<option value="">Loading...</option>').trigger('change');

            if (kabupaten !== '') {
                $.getJSON("<?= base_url('region/kecamatan') ?>/" + encodeURIComponent(kabupaten), function(data) {
                    let opt = '<option value="">-- Pilih Kecamatan --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#form_kecamatan').html(opt).trigger('change');
                    const desiredKec = $('#form_kecamatan').data('desired');
                    if (desiredKec) {
                        pushOption($('#form_kecamatan'), desiredKec);
                        $('#form_kecamatan').val(desiredKec).trigger('change');
                    }
                });
            }
        });
    });
</script>


<script>
    document.getElementById('btn-next-to-kerusakan').addEventListener('click', function() {
        var idservice = document.querySelector('input[name="idservice"]').value;

        if (!idservice) {
            alert('Harap pilih pelanggan dan simpan pelanggan terlebih dahulu.');
        } else {
            var tabTrigger = new bootstrap.Tab(document.querySelector('#kerusakan-tab'));
            tabTrigger.show();
        }
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btn-simpan').addEventListener('click', function(e) {
            const idPela = document.getElementById('idpela').value;

            if (!idPela || idPela.trim() === '') {
                e.preventDefault(); // cegah form submit
                alert('Silakan pilih pelanggan terlebih dahulu melalui tombol input data pelanggan!');
                return false;
            }
        });

        // Domisili di form utama bersifat read-only (auto dari pelanggan),
        // re-enable sesaat saat submit agar nilainya tetap terkirim ke backend
        $('form[action*="pelanggan_service"]').on('submit', function() {
            $('#form_provinsi, #form_kabupaten, #form_kecamatan').prop('disabled', false);
        });
    });
</script>


<script>
    document.querySelectorAll(".toggle-password").forEach(function(el) {
        el.addEventListener("click", function() {
            const input = document.querySelector(this.getAttribute("toggle"));
            const type = input.getAttribute("type") === "password" ? "text" : "password";
            input.setAttribute("type", type);
            this.classList.toggle("fa-eye");
            this.classList.toggle("fa-eye-slash");
        });
    });
</script>