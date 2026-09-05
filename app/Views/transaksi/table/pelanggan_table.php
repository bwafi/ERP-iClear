<div id="pelanggan-section" class="mt-3 mb-3" style="display: flex; justify-content: right;">
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#pelangganModal"
        style="display: inline-flex; align-items: center; margin-bottom: 4px;">
        <iconify-icon icon="mdi:account" width="20" height="20" style="margin-right: 8px;"></iconify-icon>
        Input Data Pelanggan
    </button>
</div>

<form action="<?php echo base_url('insert/pelanggan_service') ?>" enctype="multipart/form-data" method="post">
    <div class="row g-3">

        <input hidden type="text" name="idservice" value="<?php echo @$idservice ?>">
        <input hidden type="text" id="created_at" value="<?php echo @$old_service_pelanggan->created_at ?>">
        <input type="hidden" name="selectedidpelanggan" id="idpela" value="<?php echo @$old_service_pelanggan->id_pelanggan ?>">

        <div class="col-md-12">
            <label class="form-label">Nama Staff</label>

            <input type="text" class="form-control" 
                value="<?= $akun->NAMA_AKUN ?>" readonly>

            <input type="hidden" name="id_akun" 
                value="<?= $akun->ID_AKUN ?>">
        </div>

        <!--<div class="col-md-12">-->
        <!--    <label class="form-label" for="unitFilter">Nama Unit:</label> <br>-->
        <!--                <select name="unit" id="unitFilter" class="form-select"-->
        <!--                    onchange="filterKategori()">-->
        <!--                    <option value="">Semua Unit</option>-->
                             <?php
                            // $selectedUnit = session('ID_UNIT');
                            // foreach ($unit as $row) {
                            //     $selected = ($row->idunit == $selectedUnit) ? 'selected' : '';
                            //     echo '<option value="' . esc($row->idunit) . '" ' . $selected . '>' . esc($row->NAMA_UNIT) . '</option>';
                            // }
                             ?>
        <!--                </select>-->
        <!--</div>-->
        
        <div class="col-md-6">
            <label class="form-label">Nama Pelanggan</label>

            <input type="text" class="form-control" id="nama_pelanggan" value="<?php echo @$old_service_pelanggan->nama ?>">
        </div>
        <!-- <div class="col-md-6">
            <label class="form-label">Kode Faktur</label>
            <input type="text" class="form-control" value="">
        </div> -->

        <div class="col-md-6">
            <label class="form-label">No Hp</label>
            <input type="text" class="form-control" value="<?php echo @$old_service_pelanggan->no_hp ?>" name="no_hp"
                id="no_hp">
        </div>

        <div class="col-md-6">
            <label class="form-label">DP</label>
            <input type="text" value="<?php echo @$old_service_pelanggan->dp_bayar ?>" class="form-control" name="dp_bayar">
        </div>

        <div class="col-md-6">
            <label class="form-label">Imei</label>
            <input type="text" value="<?php echo @$old_service_pelanggan->imei ?>" class="form-control" name="imei">
        </div>
        <div class="col-md-6">
            <label class="form-label">Tipe HP</label>
            <input type="text" placeholder="" class="form-control" name="tipe_hp">
        </div>


        <div class="col-md-6">
            <label class="form-label">Passcode</label>
            <div class="input-group">
                <input value="<?php echo @$old_service_pelanggan->passcode ?>" type="password"
                    class="form-control" name="passcode" id="passcode">
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

        <!-- <div class="col-md-6"> 
            <label class="form-label">Gudang</label>
            <select class="form-select" name="gudang">
                <option selected>---Pilih Gudang---</option>
                
            </select>
        </div> -->

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
            <button type="submit" id="btn-simpan" class="btn btn-info text-white me-2">Simpan</button>
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
                    <div class="mb-3">
                        <label class="form-label">Ketik Nama atau No HP:</label>
                        <select id="pelanggan-select" name="selectedidpelanggan" class="form-control select2-ajax" style="width: 100%;">
                            <option value="">Cari pelanggan...</option>
                        </select>
                    </div>

                    <!-- Tombol di bawah dropdown -->
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
                        <label for="nik" class="form-label">NIK</label>
                        <input type="text" id="nik" name="nik" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama</label>
                        <input type="text" id="nama" name="nama" class="form-control" required />
                    </div>
                    <div class="mb-3">
                        <label for="no_hp" class="form-label">No HP</label>
                        <input type="text" id="no_hp" name="no_hp" class="form-control" required />
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

        function setSelectedCustomer(id, nama, noHp) {
            if (!id) return;
            document.getElementById('idpela').value = id;
            document.getElementById('nama_pelanggan').value = nama || '';
            document.getElementById('no_hp').value = noHp || '';
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
                                no_hp: item.no_hp
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
                let nama = data.nama || '';
                let noHp = data.no_hp || '';
                if (!nama && data.text) {
                    const parts = data.text.split(' (');
                    if (parts.length >= 2) {
                        nama = parts[0].trim();
                        noHp = parts[1].replace(')', '').trim();
                    } else {
                        nama = data.text;
                    }
                }
                setSelectedCustomer(data.id, nama, noHp);
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

            if (!nama && selectedData && selectedData.text) {
                const parts = selectedData.text.split(' (');
                if (parts.length >= 2) {
                    nama = parts[0].trim();
                    noHp = parts[1].replace(')', '').trim();
                } else {
                    nama = selectedData.text;
                }
            }

            setSelectedCustomer(id, nama, noHp);
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
                        
                        $('#pelanggan-select').append(newOption).trigger('change');
                        
                        // Set langsung ke form input
                        setSelectedCustomer(response.data.id_pelanggan, response.data.nama, response.data.no_hp);
                        
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
                // Atau bisa pakai SweetAlert jika kamu pakai
                return false;
            }
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