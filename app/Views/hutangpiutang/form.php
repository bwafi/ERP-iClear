<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Input Hutang Piutang</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('hutangpiutang/dashboard') ?>">Hutang Piutang</a></li>
                <li class="breadcrumb-item active">Input</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?= view('hutangpiutang/_input_form', [
            'input_types' => $input_types ?? [],
            'pelanggan' => $pelanggan ?? [],
            'pegawai' => $pegawai ?? [],
            'suplier' => $suplier ?? [],
            'teknisi' => $teknisi ?? [],
            'units' => $units ?? [],
            'unit_id' => $unit_id ?? null,
            'in_modal' => false,
        ]) ?>
    </div>
</div>
