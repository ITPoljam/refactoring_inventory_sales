<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Inventory & Sales Management System';
$activePage = $activePage ?? 'dashboard';
$flashes = consume_flashes();

/**
 * Menutup elemen utama halaman dan menampilkan footer aplikasi.
 */
function render_footer(): void
{
    ?>
    </main>

    <footer class="app-footer py-3 mt-4">
        <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <span><strong>Toko Ritel Maju Bersama</strong> &mdash; Sistem Manajemen Persediaan &amp; Penjualan</span>
            <span>Penyimpanan Lokal JSON &bull; PHP Native</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    </body>
    </html>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> &mdash; Toko Ritel Maju Bersama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-store mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">Toko Maju Bersama</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'products' ? 'active' : '' ?>" href="products.php">Data Barang</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'sales' ? 'active' : '' ?>" href="sales.php">Kasir Penjualan</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activePage === 'reports' ? 'active' : '' ?>" href="reports.php">Riwayat &amp; Laporan</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container pb-4">
    <?php foreach ($flashes as $flash): ?>
        <?php
        $alertClass = match ($flash['type'] ?? 'info') {
            'success' => 'alert-success',
            'danger', 'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info',
        };
        ?>
        <div class="alert <?= e($alertClass) ?> alert-dismissible fade show no-print" role="alert">
            <?= e($flash['message'] ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endforeach; ?>

