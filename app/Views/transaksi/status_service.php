<?php setlocale(LC_TIME, 'id_ID.UTF-8'); ?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lacak Servis iPhone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f9f9f9;
        }

        .container {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            max-width: 500px;
            margin: auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            margin-top: 0;
        }

        .info,
        .status {
            margin-bottom: 20px;
        }

        .label {
            font-weight: bold;
            color: #666;
        }

        .value {
            margin-bottom: 10px;
        }

        .step {
            border-left: 4px solid #ccc;
            padding-left: 10px;
            margin-bottom: 15px;
            position: relative;
        }

        .step::before {
            content: "";
            position: absolute;
            left: -10px;
            top: 4px;
            width: 12px;
            height: 12px;
            background: #ccc;
            border-radius: 50%;
        }

        .step.active::before {
            background: #4CAF50;
        }

        .step.active {
            border-color: #4CAF50;
        }

        .step time {
            font-size: 0.9em;
            color: #888;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php $garansiInfo = \App\Services\Service\WarrantyTerm::describe($service); ?>
        <h2>Lacak Servis iPhone</h2>
        <div class="info">
            <div class="label">Nomor Servis</div>
            <div class="value"><?= $service->no_service ?><?= $service->status_service ?></div>
            <div class="label">Nama Pelanggan</div>
            <div class="value"><?= $service->nama ?></div>
            <div class="label">Jenis Servis</div>
            <div class="value"><?= $service->keluhan ?></div>
            <div class="label">Tanggal Service</div>
            <div class="value"><?= $service->created_at ?></div>
            <div class="label">Garansi</div>
            <div class="value"><?= esc($garansiInfo['label']) ?> · Mulai <?= esc($garansiInfo['start_date'] ?: 'belum dimulai') ?> · Berakhir <?= esc($garansiInfo['end_date'] ?: ($service->garansi_opsi_snapshot === 'selamanya' ? 'tanpa kedaluwarsa' : '-')) ?> · <?= esc($garansiInfo['status']) ?></div>
        </div>
        <div class="status">
            <div class="label">Status</div>

            <div class="step <?= ($service->status_service >= 1) ? 'active' : '' ?>">
                <div><strong>
                        <?php
                        $tanggal = $service->created_at ?? null;
                        echo ($tanggal && $tanggal != '0000-00-00 00:00:00' && strtotime($tanggal) > 0)
                            ? strftime('%A, %d %B %Y %H:%M:%S', strtotime($tanggal))
                            : '.......';
                        ?>
                    </strong></div>
                <div>Permintaan servis diterima</div>
            </div>

            <div class="step <?= ($service->status_service >= 2) ? 'active' : '' ?>">
                <div><strong>
                        <?php
                        $tanggal = isset($proses_status) && is_object($proses_status) ? $proses_status->tanggal_mulai_proses : null;
                        echo ($tanggal && $tanggal != '0000-00-00 00:00:00' && strtotime($tanggal) > 0)
                            ? strftime('%A, %d %B %Y %H:%M:%S', strtotime($tanggal))
                            : '.......';
                        ?>
                    </strong></div>
                <div>Dalam proses perbaikan</div>
            </div>

            <div class="step <?= ($service->status_service >= 3) ? 'active' : '' ?>">
                <div><strong>
                        <?php
                        $tanggal = $service->tanggal_bisa_diambil ?? null;
                        echo ($tanggal && $tanggal != '0000-00-00 00:00:00' && strtotime($tanggal) > 0)
                            ? strftime('%A, %d %B %Y %H:%M:%S', strtotime($tanggal))
                            : '.......';
                        ?>
                    </strong></div>
                <div>Siap Diambil</div>
            </div>

            <div class="step <?= ($service->status_service >= 4) ? 'active' : '' ?>">
                <div><strong>
                        <?php
                        $tanggal = $service->tanggal_selesai ?? null;
                        echo ($tanggal && $tanggal != '0000-00-00 00:00:00' && strtotime($tanggal) > 0)
                            ? strftime('%A, %d %B %Y %H:%M:%S', strtotime($tanggal))
                            : '.......';
                        ?>
                    </strong></div>
                <div>Selesai - Sudah diambil</div>
            </div>



        </div>
</body>

</html>
