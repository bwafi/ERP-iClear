<div class="card shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body d-flex align-items-center justify-content-between p-4">
        <h4 class="fw-semibold mb-0">Stok Opname</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a class="text-muted text-decoration-none" href="<?= base_url('/') ?>">Stok</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Stok Opname</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card w-100 position-relative overflow-hidden">
    <div class="card-body">
        <div class="mb-2">
            <h5 class="mb-0">Stok Opname Tabs</h5>
        </div>
        <p class="mb-3 card-subtitle">
            Gunakan tab untuk melihat data Stok Opname.
        </p>

        <!-- Nav tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link d-flex active" data-bs-toggle="tab" href="#draft" role="tab">
                    <i class="bi bi-pencil-square fs-5"></i>
                    <span class="d-none d-md-block ms-2">Draft</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex" data-bs-toggle="tab" href="#final" role="tab">
                    <i class="bi bi-clipboard-check fs-5"></i>
                    <span class="d-none d-md-block ms-2">Fixed</span>
                </a>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="draft" role="tabpanel">
                <?php echo view('stok/table/stok_opnamedraft_table') ?>
            </div>
            <div class="tab-pane fade" id="final" role="tabpanel">
                <?php echo view('stok/table/stok_opname_table') ?>
            </div>
        </div>
    </div>
</div>
















<!-- <script>
    let tableElement;

    function loadTable(table) {
        fetch(`<?= base_url('stokopname/loadtable?table=') ?>${table}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('table-container').innerHTML = html;

                tableElement = $('#zero_config').DataTable({
                    paging: true,
                    searching: true,
                    ordering: true,
                    responsive: true,
                    pageLength: 10,
                    initComplete: function() {
                        const unitColumn = this.api().column(2);
                        const unitSet = new Set();

                        unitColumn.data().each(function(d) {
                            unitSet.add(d.trim());
                        });

                        const select = document.getElementById('filterUnit');
                        select.innerHTML = `<option value="">Semua Unit</option>`;
                        unitSet.forEach(unit => {
                            const option = document.createElement('option');
                            option.value = unit;
                            option.textContent = unit;
                            select.appendChild(option);
                        });
                    }
                });


                document.querySelectorAll('.currency').forEach(function(el) {
                    new Cleave(el, {
                        numeral: true,
                        numeralThousandsGroupStyle: 'thousand'
                    });
                });
            })
            .catch(error => console.error('Error loading table:', error));
    }





    window.onload = function() {
        const filterUnitSelect = document.getElementById('filterUnit');
        if (filterUnitSelect && filterUnitSelect.options.length > 1) {
            filterUnitSelect.selectedIndex = 1;
        }

        filterByUnit();
    };

    function filterByUnit() {
        const selectedUnit = document.getElementById('filterUnit').value || '';
        if (tableElement) {
            tableElement.column(2).search(selectedUnit).draw();
        }
    }


    window.addEventListener('DOMContentLoaded', () => loadTable('tabledaraft'));
</script>  -->
