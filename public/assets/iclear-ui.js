(function () {
    'use strict';

    if (window.jQuery && window.jQuery.fn.dataTable) {
        var tableLanguage = {
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
                paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
                aria: { sortAscending: ': urutkan naik', sortDescending: ': urutkan turun' }
            }
        };
        window.jQuery.extend(true, window.jQuery.fn.dataTable.defaults, tableLanguage);
        if (document.querySelector('.iclear-data-table')) {
            window.jQuery.extend(window.jQuery.fn.dataTable.defaults, {
                dom: '<"iclear-table-tools"lf><"iclear-table-scroll"rt><"iclear-table-footer"ip>',
                autoWidth: false,
                responsive: false
            });
        }
        if (window.iclearEarlyTableDefaults) {
            window.jQuery.extend(true, window.iclearEarlyTableDefaults, tableLanguage);
        }
        delete window.iclearEarlyTableDefaults;
    }

    window.IclearTables = {
        detail: function (text, summary) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'iclear-cell-detail';
            button.dataset.fullText = String(text == null ? '' : text);
            button.textContent = summary || String(text == null ? '' : text);
            button.setAttribute('aria-label', 'Baca lengkap: ' + button.textContent);
            return button.outerHTML;
        },
        filter: function (tableId, dateColumn, statusId) {
            var table = document.getElementById(tableId);
            if (!table || !window.jQuery.fn.dataTable.isDataTable(table)) {
                return;
            }
            if (!table.dataset.filterRegistered) {
                window.jQuery.fn.dataTable.ext.search.push(function (settings, data, index) {
                    if (settings.nTable !== table) {
                        return true;
                    }
                    var parts = String(data[dateColumn] || '').trim().split('-');
                    var date = parts.length === 3 ? parts[2] + '-' + parts[1] + '-' + parts[0] : '';
                    var start = document.getElementById('startDate').value;
                    var end = document.getElementById('endDate').value;
                    var status = statusId ? document.getElementById(statusId).value : '';
                    var row = settings.aoData[index].nTr;
                    return (!start || date >= start) && (!end || date <= end)
                        && (!status || (row && row.dataset.status === status));
                });
                table.dataset.filterRegistered = 'true';
            }
            window.jQuery(table).DataTable().draw();
        }
    };

    function prepareTables(root) {
        root.querySelectorAll('.iclear-table-scroll').forEach(function (region) {
            region.tabIndex = 0;
            region.setAttribute('role', 'region');
            region.setAttribute('aria-label', 'Tabel data. Geser ke samping untuk melihat kolom lainnya.');
        });
        root.querySelectorAll('table.iclear-data-table').forEach(function (table) {
            var columns = (table.dataset.compactColumns || '').split(',').filter(Boolean).map(Number);
            table.querySelectorAll('tbody tr').forEach(function (row) {
                columns.forEach(function (index) {
                    var cell = row.cells[index];
                    if (cell && cell.colSpan === 1 && !cell.querySelector('button, a, input')) {
                        var text = cell.textContent.trim();
                        if (text) {
                            cell.innerHTML = window.IclearTables.detail(text);
                        }
                    }
                });
            });
        });
        root.querySelectorAll('table.table').forEach(function (table) {
            if (table.classList.contains('iclear-data-table')) {
                return;
            }
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
        content.addEventListener('click', function (event) {
            var button = event.target.closest('.iclear-cell-detail');
            if (!button) {
                return;
            }
            var dialog = document.getElementById('iclear-cell-dialog');
            if (!dialog) {
                dialog = document.createElement('dialog');
                dialog.id = 'iclear-cell-dialog';
                dialog.className = 'iclear-cell-dialog';
                dialog.setAttribute('aria-label', 'Detail teks lengkap');
                var heading = document.createElement('h4');
                heading.textContent = 'Detail lengkap';
                var text = document.createElement('div');
                text.className = 'iclear-detail-text';
                var close = document.createElement('button');
                close.type = 'button';
                close.className = 'btn btn-secondary';
                close.textContent = 'Tutup';
                close.addEventListener('click', function () { dialog.close(); });
                dialog.append(heading, text, close);
                document.body.appendChild(dialog);
            }
            dialog.querySelector('.iclear-detail-text').textContent = button.dataset.fullText;
            dialog.showModal();
        });
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
