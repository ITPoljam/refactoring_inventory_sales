<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$productError = null;
$transactionError = null;
$products = load_products($productError);
$transactions = load_transactions($transactionError);


function calc_dash_stats(array $p, array $t): array
{
    $s1 = 0;
    $s2 = 0;
    $lw = [];

    foreach ($p as $item) {
        $st = (int) ($item['stock'] ?? 0);
        $s1 += $st;
        if ($st < LOW_STOCK_LIMIT) {
            $s2++;
            $lw[] = $item;
        }
    }

    $g = 0;
    $d = 0;
    $n = 0;
    foreach ($t as $x) {
        $g += (int) ($x['subtotal'] ?? 0);
        $d += (int) ($x['discount'] ?? 0);
        $n += (int) ($x['total'] ?? 0);
    }

    return [
        'cnt_p' => count($p),
        'sum_st' => $s1,
        'cnt_lw' => $s2,
        'cnt_t' => count($t),
        'gross' => $g,
        'disc' => $d,
        'net' => $n,
        'lw_items' => $lw,
    ];
}

$stats = calc_dash_stats($products, $transactions);
$recentTransactions = array_slice(array_reverse($transactions), 0, 5);

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($productError !== null): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Kesalahan Data Barang:</strong> <?= e($productError) ?>
    </div>
<?php endif; ?>

<?php if ($transactionError !== null): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Kesalahan Data Transaksi:</strong> <?= e($transactionError) ?>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center page-header gap-3">
    <div>
        <h1>Dashboard Operasional Toko</h1>
        <p class="text-secondary mb-0">Ringkasan persediaan barang dan transaksi penjualan harian.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="sales.php" class="btn btn-primary">+ Transaksi Baru</a>
        <a href="products.php" class="btn btn-outline-secondary">Kelola Barang</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-clean stat-card h-100">
            <div class="card-body">
                <div class="stat-label">Jumlah Jenis Barang</div>
                <div class="stat-value"><?= e($stats['cnt_p']) ?> Barang</div>
                <div class="small text-secondary mt-1">
                    <?= e($stats['cnt_lw']) ?> barang stok menipis (&lt; <?= e(LOW_STOCK_LIMIT) ?>)
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-clean stat-card stat-info h-100">
            <div class="card-body">
                <div class="stat-label">Total Stok Tersedia</div>
                <div class="stat-value"><?= e(number_format($stats['sum_st'], 0, ',', '.')) ?> Unit</div>
                <div class="small text-secondary mt-1">Akumulasi seluruh stok di rak &amp; gudang</div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-clean stat-card stat-warning h-100">
            <div class="card-body">
                <div class="stat-label">Jumlah Transaksi</div>
                <div class="stat-value"><?= e($stats['cnt_t']) ?> Transaksi</div>
                <div class="small text-secondary mt-1">Total potongan diskon: <?= e(format_rupiah($stats['disc'])) ?></div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card card-clean stat-card stat-success h-100">
            <div class="card-body">
                <div class="stat-label">Ringkasan Penjualan Bersih</div>
                <div class="stat-value"><?= e(format_rupiah($stats['net'])) ?></div>
                <div class="small text-secondary mt-1">Penjualan kotor: <?= e(format_rupiah($stats['gross'])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card card-clean h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Peringatan Stok Menipis (&lt; <?= e(LOW_STOCK_LIMIT) ?> Unit)</span>
                <a href="reports.php#low-stock" class="btn btn-sm btn-outline-primary">Laporan Lengkap</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($stats['lw_items'])): ?>
                    <div class="p-4 text-center text-secondary">
                        Seluruh barang memiliki jumlah stok aman.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Kode</th>
                                    <th>Nama Barang</th>
                                    <th class="text-end">Sisa Stok</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['lw_items'] as $lowItem): ?>
                                    <tr>
                                        <td class="ps-3"><span class="code-badge"><?= e($lowItem['code'] ?? '') ?></span></td>
                                        <td><?= e($lowItem['name'] ?? '') ?></td>
                                        <td class="text-end">
                                            <?php if ((int) ($lowItem['stock'] ?? 0) === 0): ?>
                                                <span class="badge text-bg-danger">Habis (0)</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-warning"><?= e($lowItem['stock'] ?? 0) ?> unit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="products.php?edit=<?= urlencode((string) ($lowItem['code'] ?? '')) ?>"
                                               class="btn btn-sm btn-outline-secondary">Restock</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card card-clean h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Transaksi Terakhir</span>
                <a href="reports.php" class="btn btn-sm btn-outline-primary">Semua Riwayat</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentTransactions)): ?>
                    <div class="p-4 text-center text-secondary">
                        Belum ada transaksi penjualan yang tercatat.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">ID Transaksi</th>
                                    <th>Waktu</th>
                                    <th class="text-end">Total Bayar</th>
                                    <th class="text-end pe-3">Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $trx): ?>
                                    <tr>
                                        <td class="ps-3"><span class="code-badge"><?= e($trx['id'] ?? '') ?></span></td>
                                        <td class="small text-secondary"><?= e($trx['timestamp'] ?? '') ?></td>
                                        <td class="text-end fw-semibold"><?= e(format_rupiah((int) ($trx['total'] ?? 0))) ?></td>
                                        <td class="text-end pe-3">
                                            <a href="reports.php?trx=<?= urlencode((string) ($trx['id'] ?? '')) ?>"
                                               class="btn btn-sm btn-outline-secondary">Struk</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>

