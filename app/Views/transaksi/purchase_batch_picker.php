<script>
(() => {
    if (window.servicePurchaseBatchPicker) return;
    const cache = new Map();
    const populate = async checkbox => {
        const scope = checkbox.closest('.service-sp-choice, tr');
        const panel = scope?.querySelector('.service-origin-panel');
        const select = scope?.querySelector('.origin-choice');
        if (!select) return;
        if (panel) panel.hidden = !checkbox.checked;
        if (!checkbox.checked || select.dataset.loaded === 'yes') return;
        const id = checkbox.dataset.id;
        const persisted = document.getElementById('row-' + id)?.querySelector('.origin-batch, .replacement-batch')?.value;
        select.dataset.loading = 'yes';
        select.replaceChildren(new Option('Memuat supplier / nota…', ''));
        try {
            if (!cache.has(id)) cache.set(id, fetch('<?= base_url('service/purchase-batches') ?>/' + encodeURIComponent(id)).then(response => {
                if (!response.ok) throw new Error('Supplier gagal dimuat');
                return response.json();
            }));
            const batches = await cache.get(id);
            select.replaceChildren(new Option(batches.length ? 'Pilih supplier / nota barang yang diambil' : 'Belum diketahui — tidak ada batch asal tercatat', ''));
            for (const batch of batches) {
                select.add(new Option(`${batch.nama_suplier || 'Supplier tidak tercatat'} / ${batch.no_nota_supplier || batch.no_batch || '-'} · Batch #${batch.iddetail_pembelian} · Sisa ${batch.available_quantity ?? batch.jumlah}`, batch.iddetail_pembelian));
            }
            if (persisted && !Array.from(select.options).some(option => option.value === persisted)) {
                select.add(new Option('Pertahankan batch tercatat #' + persisted, persisted));
            }
            select.value = checkbox.dataset.purchaseDetailId || persisted || '';
            checkbox.dataset.purchaseDetailId = select.value;
            select.dataset.knownBatches = String(batches.length);
            select.dataset.loaded = 'yes';
        } catch (error) {
            cache.delete(id);
            select.replaceChildren(new Option('Supplier gagal dimuat. Tutup lalu coba lagi.', ''));
            select.dataset.loaded = 'no';
        } finally {
            select.dataset.loading = 'no';
        }
    };
    document.addEventListener('change', event => {
        if (event.target.matches('.sparepart-check')) populate(event.target);
        if (event.target.matches('.origin-choice')) {
            const checkbox = event.target.closest('.service-sp-choice, tr')?.querySelector('.sparepart-check');
            if (checkbox) {
                checkbox.dataset.purchaseDetailId = event.target.value;
                const finalSelect = document.getElementById('row-' + checkbox.dataset.id)?.querySelector('.origin-batch, .replacement-batch');
                if (finalSelect && !finalSelect.disabled) {
                    if (event.target.value && !Array.from(finalSelect.options).some(option => option.value === event.target.value)) {
                        finalSelect.add(new Option(event.target.selectedOptions[0].textContent, event.target.value));
                    }
                    finalSelect.value = event.target.value;
                }
            }
            event.target.setCustomValidity('');
        }
    });
    document.addEventListener('shown.bs.modal', event => {
        event.target.querySelectorAll('.sparepart-check:checked').forEach(populate);
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('#add-selected-sparepart')) return;
        for (const checkbox of document.querySelectorAll('#sparepartModal .sparepart-check:checked')) {
            if (document.getElementById('row-' + checkbox.dataset.id)) continue;
            const select = checkbox.closest('.service-sp-choice, tr')?.querySelector('.origin-choice');
            if (select && (select.dataset.loading === 'yes' || select.dataset.loaded !== 'yes'
                || (Number(select.dataset.knownBatches) > 0 && !select.value))) {
                event.preventDefault();
                event.stopImmediatePropagation();
                select.setCustomValidity('Pilih supplier / nota sesuai barang fisik. Jika gagal dimuat, tutup dan buka kembali pilihan sparepart.');
                select.reportValidity();
                return;
            }
        }
    }, true);
    window.servicePurchaseBatchPicker = {populate};
})();
</script>
