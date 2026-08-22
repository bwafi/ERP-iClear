<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Datamaster Stok Awal</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Datamaster</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Stok Awal</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <?php
    $menu = [
        'BATERAI',
        'SPEAKER',
        'PORTCAS',
        'PUP',
        'LCD',
        'KAMERA',
        'BACKGLAS',
        'JASA',
        'HOUSING',
        'FLEX',
        'MESIN',
        'ACC',
        'HP',
        'RESTORE',
        'CLEANING',
    ];
    ?>

    <?php foreach ($menu as $item): ?>
    <div class="px-4 py-3 border-bottom">
        <a href="<?= base_url('input_stokawal/'.$item) ?>" class="btn btn-primary">
            <?= $item ?>
        </a>
    </div>
    <?php endforeach ?>
</div>