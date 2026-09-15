<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>403 &mdash; Akses Ditolak</title>
    <link rel="shortcut icon" type="image/png" href="<?= base_url('template/assets/images/logo_iclear.png') ?>">
    <link rel="stylesheet" href="<?= base_url('template/assets/css/styles.css') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6f7fb;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -4px;
            color: #e67e22;
        }
        .error-icon {
            font-size: 3.5rem;
            color: #e67e22;
        }
        .error-url {
            word-break: break-all;
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: .5rem;
            padding: .6rem 1rem;
            font-family: SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .85rem;
            color: #6c757d;
        }
        .error-box {
            max-width: 620px;
        }
    </style>
</head>
<body>
    <div class="text-center p-4 error-box">
        <?php $uri = function_exists('current_url') ? current_url() : ''; ?>

        <div class="error-code"><?= (int) $code ?></div>
        <div class="error-icon"><i class="bi bi-shield-lock"></i></div>

        <h1 class="h3 fw-bold mt-3 mb-2">Akses Ditolak</h1>
        <p class="text-muted mb-3">
            Akun kamu tidak memiliki izin untuk membuka halaman ini. Hubungi admin jika kamu merasa seharusnya bisa mengaksesnya.
        </p>

        <?php if (!empty($uri)) : ?>
            <div class="error-url mb-3"><?= esc($uri) ?></div>
        <?php endif; ?>

        <?php if (ENVIRONMENT !== 'production' && !empty($message)) : ?>
            <div class="alert alert-warning text-start small"><?= esc($message) ?></div>
        <?php endif; ?>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="javascript:history.back()" class="btn btn-primary">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <a href="<?= base_url() ?>" class="btn btn-outline-secondary">
                <i class="bi bi-house-door me-1"></i> Beranda
            </a>
        </div>
    </div>
</body>
</html>