<?php
/**
 * Dialog ajakan install PWA. Ditampilkan oleh skrip tema
 * `inc/_presensi_mobile_theme.php`; memakai <dialog> native supaya fokus
 * terkunci dan Esc bekerja tanpa JS tambahan.
 */
?>
<dialog class="pwa-install" id="pwa-install" aria-labelledby="pwa-install-title">
    <div class="pwa-install__body">
        <img class="pwa-install__icon" src="<?= base_url('template/assets/pwa/icon-192.png') ?>" width="44" height="44" alt="">
        <h2 class="pwa-install__title" id="pwa-install-title">Pasang Absen iClear</h2>
        <p class="pwa-install__copy" data-pwa-copy>Absen, izin, dan profil langsung dari layar utama, tanpa buka browser.</p>
        <div class="pwa-install__actions">
            <button type="button" class="btn" data-pwa-install>Install</button>
            <button type="button" class="btn is-outline" data-pwa-dismiss>Nanti</button>
        </div>
    </div>
</dialog>
