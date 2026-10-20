(function () {
    'use strict';

    if (window.jQuery && window.jQuery.fn.dataTable) {
        window.jQuery.extend(true, window.jQuery.fn.dataTable.defaults, {
            language: {
                emptyTable: 'Belum ada data.',
                zeroRecords: 'Tidak ada data yang cocok. Periksa pencarian atau filter.',
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ baris',
                info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                infoEmpty: 'Belum ada data untuk ditampilkan',
                infoFiltered: '(disaring dari _MAX_ data)',
                loadingRecords: 'Memuat data…',
                processing: 'Sedang memproses…',
                paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' }
            }
        });
    }

    function prepareTables(root) {
        root.querySelectorAll('table.table').forEach(function (table) {
            if (table.closest('.table-responsive, .iclear-table-scroll, .dataTables_scrollBody, .dataTables_scrollHead, .dataTables_scrollFoot')) {
                return;
            }
            if (table.querySelectorAll('thead th').length < 4) {
                return;
            }
            var region = document.createElement('div');
            region.className = 'iclear-table-scroll';
            region.setAttribute('role', 'region');
            region.setAttribute('aria-label', 'Tabel data. Geser ke samping untuk melihat kolom lainnya.');
            region.tabIndex = 0;
            table.parentNode.insertBefore(region, table);
            region.appendChild(table);
        });
    }

    function start() {
        var content = document.getElementById('iclear-content');
        if (!content) {
            return;
        }
        prepareTables(content);
        if (window.jQuery) {
            window.jQuery(document).on('init.dt.iclear draw.dt.iclear shown.bs.modal.iclear', function () {
                prepareTables(content);
            });
        }
        var pending = false;
        new MutationObserver(function () {
            if (pending) {
                return;
            }
            pending = true;
            window.requestAnimationFrame(function () {
                pending = false;
                prepareTables(content);
            });
        }).observe(content, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
