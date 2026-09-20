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

<div id="pelanggan-section" class="mt-3 mb-3" style="display: flex; justify-content: right;">
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#pelangganModal"
        style="display: inline-flex; align-items: center; margin-bottom: 4px;">
        <iconify-icon icon="mdi:account" width="20" height="20" style="margin-right: 8px;"></iconify-icon>
        Input Data Pelanggan
    </button>
</div>

<form action="<?php echo base_url('update/pelanggan_service') ?>" enctype="multipart/form-data" method="post">
    <div class="row g-3">

        <input hidden type="text" name="idservice" value="<?php echo @$idservice ?>">
        <input hidden type="text" id="created_at" value="<?php echo @$old_service_pelanggan->created_at ?>">


        <div class="col-md-6">
            <label class="form-label">Nama Pelanggan</label>
            <input type="text" class="form-control" id="nama_pelanggan" value="<?php echo @$old_service_pelanggan->nama ?>" readonly>
        </div>
        <!-- <div class="col-md-6">
            <label class="form-label">Kode Faktur</label>
            <input type="text" class="form-control" value="">
        </div> -->

        <div class="col-md-6">
            <label class="form-label">No Hp</label>
            <input type="text" class="form-control" value="<?php echo @$old_service_pelanggan->no_hp ?>" name="no_hp"
                id="no_hp" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label">Domisili Provinsi</label>
            <select class="form-control form-select js-domisili" id="form_provinsi" name="domisili_provinsi" disabled>
                <option value="">-- Pilih / Cari Provinsi --</option>
                <?php foreach ($provinsi as $p): ?>
                    <option value="<?= esc($p->name) ?>" <?= @$old_service_pelanggan->provinsi == $p->name ? 'selected' : '' ?>>
                        <?= esc($p->name) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Domisili Kabupaten</label>
            <select class="form-control form-select js-domisili" id="form_kabupaten" name="domisili_kabupaten" disabled>
                <option value="">-- Pilih / Cari Kabupaten --</option>
                <?php if (@$old_service_pelanggan->kabupaten): ?>
                    <option value="<?= esc($old_service_pelanggan->kabupaten) ?>" selected><?= esc($old_service_pelanggan->kabupaten) ?></option>
                <?php endif ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Domisili Kecamatan</label>
            <select class="form-control form-select js-domisili" id="form_kecamatan" name="domisili_kecamatan" disabled>
                <option value="">-- Pilih / Cari Kecamatan --</option>
                <?php if (@$old_service_pelanggan->kecamatan): ?>
                    <option value="<?= esc($old_service_pelanggan->kecamatan) ?>" selected><?= esc($old_service_pelanggan->kecamatan) ?></option>
                <?php endif ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">DP</label>
            <input type="text" value="<?php echo @$old_service_pelanggan->dp_bayar ?>" class="form-control"
                name="dp_bayar" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label">Imei</label>
            <input type="text" value="<?php echo @$old_service_pelanggan->imei ?>" class="form-control" name="imei">
        </div>
        <div class="col-md-6">
            <label class="form-label">Tipe HP</label>
            <input type="text" value="<?php echo @$old_service_pelanggan->tipe_hp ?>" placeholder="" class="form-control" name="tipe_hp">
        </div>


        <div class="col-md-6">
            <label class="form-label">Passcode</label>
            <div class="input-group">
                <input value="<?php echo @$old_service_pelanggan->passcode ?>" type="password" class="form-control"
                    name="passcode" id="passcode">
                <span class="input-group-text">
                    <i class="fas fa-eye toggle-password" toggle="#passcode" style="cursor: pointer;"></i>
                </span>
            </div>
        </div>


        <div class="col-md-6">
            <label class="form-label">Email (icloud)</label>
            <input type="email" value="<?php echo @$old_service_pelanggan->email_icloud ?>" placeholder="@icloud.com"
                class="form-control" name="email_icloud">
        </div>
        <div class="col-md-6">
            <label class="form-label">Password (icloud)</label>
            <div class="input-group">
                <input type="password" value="<?php echo @$old_service_pelanggan->password_icloud ?>"
                    placeholder="********" class="form-control" name="password_icloud" id="password_icloud">
                <span class="input-group-text">
                    <i class="fas fa-eye toggle-password" toggle="#password_icloud" style="cursor: pointer;"></i>
                </span>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label">Keluhan</label>
            <textarea style="height: 100px;" class="form-control"
                name="keluhan"><?php echo @$old_service_pelanggan->keluhan ?></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Keterangan</label>
            <textarea style="height: 100px;" type="text" class="form-control"
                name="keterangan"><?php echo @$old_service_pelanggan->keterangan ?></textarea>
        </div>


    </div>

    <!-- Buttons -->
    <div class="d-flex justify-content-between mt-4">
        <!-- <button type="button" class="btn btn-outline-secondary">Sebelumnya</button> -->
        <div>
            <button type="submit" id="btn-simpan" class="btn btn-info text-white me-2">Update</button>
            <button type="button" class="btn btn-success" id="btn-next-to-kerusakan">Selanjutnya</button>
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
                    <select id="pelanggan-select" name="selectedidpelanggan" class="select2 form-control"
                        style="width: 100%;">
                        <option disabled selected>Select</option>
                        <?php foreach ($pelanggan as $p): ?>
                            <option value="<?= htmlspecialchars($p->id_pelanggan) ?>"
                                data-nama="<?= htmlspecialchars($p->nama) ?>" data-nohp="<?= htmlspecialchars($p->no_hp) ?>"
                                data-alamat="<?= htmlspecialchars($p->alamat) ?>"
                                data-provinsi="<?= htmlspecialchars($p->provinsi ?? '') ?>"
                                data-kabupaten="<?= htmlspecialchars($p->kabupaten ?? '') ?>"
                                data-kecamatan="<?= htmlspecialchars($p->kecamatan ?? '') ?>">
                                <?= htmlspecialchars($p->nama) ?> : <?= htmlspecialchars($p->no_hp) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>

                    <!-- Tombol di bawah dropdown -->
                    <div style="display: flex; justify-content: right; gap: 10px; margin-top: 20px;">
                        <button id="btnPilihPelanggan" type="button" class="btn btn-primary">Pilih</button>
                        <button id="btnTambahPelanggan" type="button" class="btn btn-success">Tambah</button>
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
                        <label for="modal_provinsi" class="form-label">Domisili Provinsi</label>
                        <select id="modal_provinsi" name="provinsi" class="form-select js-domisili" required>
                            <option value="">-- Pilih / Cari Provinsi --</option>
                            <?php foreach ($provinsi as $p): ?>
                                <option value="<?= esc($p->name) ?>"><?= esc($p->name) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modal_kabupaten" class="form-label">Domisili Kabupaten</label>
                        <select id="modal_kabupaten" name="kabupaten" class="form-select js-domisili" required>
                            <option value="">-- Pilih / Cari Kabupaten --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="modal_kecamatan" class="form-label">Domisili Kecamatan</label>
                        <select id="modal_kecamatan" name="kecamatan" class="form-select js-domisili" required>
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

        // Inisialisasi Select2 dengan dropdownParent agar dropdown muncul di atas modal
        $('#pelanggan-select').select2({
            dropdownParent: $('#pelangganModal')
        });

        // Select2 searchable untuk input data pelanggan (Form Utama)
        $('#form_provinsi, #form_kabupaten, #form_kecamatan').select2({
            width: '100%'
        });

        // Langsung tampilkan tombol pelanggan saat halaman dimuat
        const existingBtn = document.getElementById('pelanggan-button');
        if (!existingBtn) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'pelanggan-button';
            btn.className = 'btn btn-warning mt-2';
            btn.style = 'display: inline-flex; align-items: center; margin-bottom: 4px;';
            btn.innerHTML = `
                <iconify-icon icon="mdi:account" width="20" height="20" style="margin-right: 8px;"></iconify-icon>
                Input Data Pelanggan
            `;

            btn.onclick = () => pelangganModal.show();

            const container = document.querySelector('.table-responsive.mt-3.mb-4');
            if (container) {
                container.appendChild(btn);
            }
        }

        // Tombol "Tambah" di bawah dropdown
        document.getElementById('btnTambahPelanggan').addEventListener('click', function() {
            modalTambah.show();
        });

        // Saat tombol "Pilih" ditekan
        document.getElementById('btnPilihPelanggan').addEventListener('click', function() {
            const select = document.getElementById('pelanggan-select');
            const selectedOption = select.options[select.selectedIndex];

            if (!selectedOption || selectedOption.disabled) {
                alert('Silakan pilih pelanggan terlebih dahulu.');
                return;
            }

            const nama = selectedOption.getAttribute('data-nama');
            const no_hp = selectedOption.getAttribute('data-nohp');

            // Set nilai ke form input
            document.getElementById('nama_pelanggan').value = nama;
            document.getElementById('no_hp').value = no_hp;

            // Set domisili
            setFormDomisili({
                provinsi: selectedOption.getAttribute('data-provinsi'),
                kabupaten: selectedOption.getAttribute('data-kabupaten'),
                kecamatan: selectedOption.getAttribute('data-kecamatan')
            });


            pelangganModal.hide();
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

        function setFormDomisili(d) {
            d = d || {};
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
                            response.data.nama + ' : ' + response.data.no_hp,
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

                        setFormDomisili(response.data);
                        alert('Pelanggan berhasil ditambahkan');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan saat menyimpan data.');
                }
            });
        });

        // Select2 Domisili di modal Tambah Pelanggan
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
        $('#modal_provinsi').on('change', function() {
            let provinsi = $(this).val();
            $('#modal_kabupaten').html('<option value="">Loading...</option>').trigger('change');
            $('#modal_kecamatan').html('<option value="">-- Pilih Kecamatan --</option>').trigger('change');
            if (provinsi !== '') {
                $.getJSON("<?= base_url('region/kabupaten') ?>/" + encodeURIComponent(provinsi), function(data) {
                    let opt = '<option value="">-- Pilih Kabupaten --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#modal_kabupaten').html(opt).trigger('change');
                });
            }
        });

        $('#modal_kabupaten').on('change', function() {
            let kabupaten = $(this).val();
            $('#modal_kecamatan').html('<option value="">Loading...</option>').trigger('change');
            if (kabupaten !== '') {
                $.getJSON("<?= base_url('region/kecamatan') ?>/" + encodeURIComponent(kabupaten), function(data) {
                    let opt = '<option value="">-- Pilih Kecamatan --</option>';
                    $.each(data, function(i, v) {
                        opt += `<option value="${v.name}">${v.name}</option>`;
                    });
                    $('#modal_kecamatan').html(opt).trigger('change');
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
            alert('Harap isi dan simpan pelanggan terlebih dahulu.');
        } else {
            var tabTrigger = new bootstrap.Tab(document.querySelector('#kerusakan-tab'));
            tabTrigger.show();
        }
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dpInput = document.querySelector('input[name="dp_bayar"]');
        if (dpInput) {
            dpInput.addEventListener('input', function() {
                let value = this.value.replace(/[^\d]/g, '');
                this.value = value ? 'Rp. ' + new Intl.NumberFormat('id-ID').format(parseInt(value)) : '';
            });

            // Format on page load
            if (dpInput.value) {
                const raw = dpInput.value.replace(/[^\d]/g, '');
                dpInput.value = 'Rp. ' + new Intl.NumberFormat('id-ID').format(parseInt(raw));
            }
        }

        // Domisili di form utama bersifat read-only (auto dari pelanggan),
        // re-enable sesaat saat submit agar nilainya tetap terkirim ke backend
        $('form[action*="pelanggan_service"]').on('submit', function() {
            $('#form_provinsi, #form_kabupaten, #form_kecamatan').prop('disabled', false);
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const estimasiInput = document.querySelector('input[name="estimasi_biaya"]');
        if (estimasiInput) {
            estimasiInput.addEventListener('input', function() {
                let value = this.value.replace(/[^\d]/g, '');
                this.value = value ? 'Rp. ' + new Intl.NumberFormat('id-ID').format(parseInt(value)) : '';
            });

            // Format on page load
            if (estimasiInput.value) {
                const raw = estimasiInput.value.replace(/[^\d]/g, '');
                estimasiInput.value = 'Rp. ' + new Intl.NumberFormat('id-ID').format(parseInt(raw));
            }
        }
    });
</script>
