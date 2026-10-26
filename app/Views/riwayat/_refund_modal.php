<div class="modal fade rsg-modal" id="modalRefundGaransi" tabindex="-1" aria-labelledby="modalRefundGaransiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="rsg-modal__title">
                    <span class="rsg-modal__icon">
                        <iconify-icon icon="solar:wad-of-money-linear" width="22" height="22"></iconify-icon>
                    </span>
                    <div>
                        <h5 class="modal-title" id="modalRefundGaransiLabel">Refund Garansi</h5>
                        <p>Refund uang service yang sudah dibayar dan tentukan kondisi sparepart pengganti.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="refund-garansi-content">
                    <p class="text-center text-muted py-4 mb-0">Memuat form refund...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="rsg-btn rsg-btn--ghost" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    const refundDetailUrl = <?= json_encode(base_url('garansi_service/refund/detail'), JSON_UNESCAPED_SLASHES) ?>;

    $(document).on('click', '.btn-refund', function() {
        const idservice = Number(this.dataset.idservice);
        const content = document.getElementById('refund-garansi-content');

        content.innerHTML = '<div class="rsg-modal__loading"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memuat form refund...</div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRefundGaransi')).show();

        fetch(refundDetailUrl + '/' + idservice, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) { content.innerHTML = data.html; })
            .catch(function() {
                content.innerHTML = '<div class="alert alert-danger mb-0">Gagal memuat form refund.</div>';
            });
    });

    $(document).on('change', 'input[name="metode"]', function() {
        const bankFields = document.getElementById('refundBankFields');
        if (!bankFields) return;
        bankFields.classList.toggle('d-none', this.value !== 'bank');
    });
});
</script>
