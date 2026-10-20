<?php $isTechnician = $mode === 'technician'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h4 mb-1"><?= $isTechnician ? 'Pekerjaan Teknisi' : 'Antrean Kasir' ?></h2>
        <p class="text-muted mb-0"><?= $isTechnician ? 'Service yang menjadi tugas Anda di cabang ini.' : 'Service yang dikirim teknisi dan menunggu diproses di cabang ini.' ?></p>
    </div>
    <?php if ($isTechnician): ?>
        <a class="btn btn-primary" href="<?= base_url('pekerjaan-teknisi/baru') ?>">+ Buat Service</a>
    <?php endif ?>
</div>

<div class="card"><div class="card-body">
        <div class="alert alert-light border mb-3 <?= empty($items) ? '' : 'd-none' ?>" id="workflow-queue-empty"><?= $isTechnician ? 'Belum ada pekerjaan teknisi yang perlu ditindaklanjuti.' : 'Antrean kasir masih kosong.' ?></div>
        <div class="table-responsive <?= empty($items) ? 'd-none' : '' ?>" id="workflow-queue-wrap">
            <table class="table align-middle" id="workflow-queue-table" data-mode="<?= $isTechnician ? 'technician' : 'cashier' ?>" data-csrf-name="<?= esc(csrf_token()) ?>" data-csrf-hash="<?= esc(csrf_hash()) ?>">
                <thead><tr><th>No. Service</th><th>Pelanggan / Perangkat</th><th>Diagnosis / Rekomendasi</th><th>Teknisi</th><th>Masuk Antrean</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="text-nowrap fw-semibold"><?= esc($item->no_service) ?></td>
                        <td><div class="fw-semibold"><?= esc($item->nama_pelanggan ?? '—') ?></div><small class="text-muted"><?= esc($item->tipe_hp ?? '—') ?> · <?= esc($item->no_hp ?? '—') ?></small></td>
                        <td style="min-width:240px"><div><?= esc($item->keluhan ?? '—') ?></div><small class="text-muted"><?= esc($item->recommendation ?: ($item->return_reason ? 'Perlu koreksi: ' . $item->return_reason : 'Belum ada rekomendasi')) ?></small></td>
                        <td><?= esc($item->nama_teknisi ?? '—') ?></td>
                        <td class="text-nowrap"><?= esc($item->submitted_at ?? $item->created_at) ?></td>
                        <td><span class="badge text-bg-<?= $item->queue_status === 'returned' ? 'warning' : ($item->queue_status === 'processing' ? 'info' : 'secondary') ?>"><?= esc(ucfirst($item->queue_status)) ?></span></td>
                        <td class="text-nowrap">
                        <?php if ($isTechnician): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= base_url('pekerjaan-teknisi/' . $item->idservice) ?>">Buka</a>
                            <?php if ($item->queue_status === 'returned'): ?><small class="d-block text-warning-emphasis mt-1"><?= esc($item->return_reason) ?></small><?php endif ?>
                        <?php else: ?>
                            <?php if ($item->queue_status === 'queued'): ?>
                                <form method="post" action="<?= base_url('antrean-kasir/' . $item->idservice . '/open') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Proses</button></form>
                            <?php elseif ((int) $item->cashier_idakun === (int) $current_actor_id): ?>
                                <a class="btn btn-sm btn-primary" href="<?= base_url('antrean-kasir/' . $item->idservice . '?tab=sparepart') ?>">Lanjutkan</a>
                            <?php else: ?>
                                <span class="text-muted">Sedang diproses: <?= esc($item->nama_kasir ?? 'Kasir lain') ?></span>
                            <?php endif ?>
                        <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
</div></div>

<script>
(function () {
    const table = document.getElementById('workflow-queue-table');
    const mode = table?.dataset.mode;
    const tbody = table?.querySelector('tbody');
    const wrapper = document.getElementById('workflow-queue-wrap');
    const empty = document.getElementById('workflow-queue-empty');
    if (!table || !tbody) return;
    const csrfName = table.dataset.csrfName;
    const csrfHash = table.dataset.csrfHash;
    const baseUrl = '<?= rtrim(base_url(), '/') ?>/';
    const esc = function (value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
        });
    };
    function actionCell(row) {
        const cell = document.createElement('td');
        cell.className = 'text-nowrap';
        if (mode === 'technician') {
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = baseUrl + 'pekerjaan-teknisi/' + encodeURIComponent(row.idservice);
            link.textContent = 'Buka';
            cell.append(link);
            return cell;
        }
        if (row.queue_status === 'queued') {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = baseUrl + 'antrean-kasir/' + encodeURIComponent(row.idservice) + '/open';
            form.className = 'd-inline';
            const token = document.createElement('input');
            token.type = 'hidden'; token.name = csrfName; token.value = csrfHash;
            const button = document.createElement('button');
            button.className = 'btn btn-sm btn-primary'; button.textContent = 'Proses';
            form.append(token, button); cell.append(form);
        } else if (row.is_owned) {
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-primary';
            link.href = baseUrl + 'antrean-kasir/' + encodeURIComponent(row.idservice) + '?tab=sparepart';
            link.textContent = 'Lanjutkan'; cell.append(link);
        } else {
            cell.textContent = 'Sedang diproses: ' + (row.nama_kasir || 'Kasir lain');
        }
        return cell;
    }
    function render(rows) {
        tbody.replaceChildren();
        for (const row of rows) {
            const tr = document.createElement('tr');
            const cells = [
                '<span class="fw-semibold">' + esc(row.no_service) + '</span>',
                '<div class="fw-semibold">' + esc(row.nama_pelanggan || '—') + '</div><small class="text-muted">' + esc(row.tipe_hp || '—') + ' · ' + esc(row.no_hp || '—') + '</small>',
                '<div>' + esc(row.keluhan || '—') + '</div><small class="text-muted">' + esc(row.recommendation || (row.return_reason ? 'Perlu koreksi: ' + row.return_reason : 'Belum ada rekomendasi')) + '</small>',
                esc(row.nama_teknisi || '—'), esc(row.submitted_at || row.created_at),
                '<span class="badge text-bg-' + (row.queue_status === 'returned' ? 'warning' : (row.queue_status === 'processing' ? 'info' : 'secondary')) + '">' + esc(row.queue_status.charAt(0).toUpperCase() + row.queue_status.slice(1)) + '</span>'
            ];
            cells.forEach(function (html, index) { const td = document.createElement('td'); if (index === 0 || index === 4) td.className = 'text-nowrap'; td.innerHTML = html; tr.append(td); });
            tr.append(actionCell(row)); tbody.append(tr);
        }
        wrapper.classList.toggle('d-none', rows.length === 0);
        empty.classList.toggle('d-none', rows.length !== 0);
    }
    async function refreshQueue() {
        try {
            const url = baseUrl + (mode === 'cashier' ? 'antrean-kasir/data' : 'pekerjaan-teknisi/data') + '?mode=' + mode;
            const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (!response.ok) return;
            const result = await response.json();
            if (Array.isArray(result.data)) render(result.data);
        } catch (_) {}
    }
    window.setInterval(refreshQueue, 30000);
})();
</script>
<?php if (!$isTechnician): ?><script>
</script><?php endif ?>
