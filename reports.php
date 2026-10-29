<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';


function render_reports_content(string $trxParam, int $lim): void
{
    ensure_data_files_exist();

    $rawTrx = @file_get_contents(TRANSACTIONS_FILE);
    $trxList = $rawTrx !== false ? json_decode($rawTrx, true) : null;
    $trxJsonErr = ($rawTrx === false || json_last_error() !== JSON_ERROR_NONE || !is_array($trxList))
        ? 'File data/transactions.json tidak dapat dibaca atau memiliki format JSON tidak valid.'
        : null;
    if ($trxJsonErr !== null) {
        $trxList = [];
    }

    $rawProd = @file_get_contents(PRODUCTS_FILE);
    $prodList = $rawProd !== false ? json_decode($rawProd, true) : null;
    $prodJsonErr = ($rawProd === false || json_last_error() !== JSON_ERROR_NONE || !is_array($prodList))
        ? 'File data/products.json tidak dapat dibaca atau memiliki format JSON tidak valid.'
        : null;
    if ($prodJsonErr !== null) {
        $prodList = [];
    }

    if ($trxJsonErr !== null) {
        echo '<div class="alert alert-danger" role="alert">' . e($trxJsonErr) . '</div>';
    }
    if ($prodJsonErr !== null) {
        echo '<div class="alert alert-danger" role="alert">' . e($prodJsonErr) . '</div>';
    }

    $g = 0;
    $d = 0;
    $n = 0;
    $q = 0;
    $picked = null;

    foreach ($trxList as $x) {
        $g += (int) ($x['subtotal'] ?? 0);
        $d += (int) ($x['discount'] ?? 0);
        $n += (int) ($x['total'] ?? 0);
        foreach ($x['items'] ?? [] as $it) {
            $q += (int) ($it['quantity'] ?? 0);
        }
        if ($trxParam !== '' && strcasecmp((string) ($x['id'] ?? ''), $trxParam) === 0) {
            $picked = $x;
        }
    }

    $tmp = [];
    foreach ($prodList as $r) {
        if ((int) ($r['stock'] ?? 0) < $lim) {
            $tmp[] = $r;
        }
    }

    echo '<div class="row g-3 mb-4 no-print">';
    echo '  <div class="col-sm-6 col-xl-3"><div class="card card-clean stat-card h-100"><div class="card-body">';
    echo '    <div class="stat-label">Total Transaksi</div>';
    echo '    <div class="stat-value">' . e(count($trxList)) . '</div>';
    echo '    <div class="small text-secondary mt-1">' . e($q) . ' unit barang terjual</div>';
    echo '  </div></div></div>';

    echo '  <div class="col-sm-6 col-xl-3"><div class="card card-clean stat-card stat-info h-100"><div class="card-body">';
    echo '    <div class="stat-label">Penjualan Kotor</div>';
    echo '    <div class="stat-value">Rp' . e(number_format($g, 0, ',', '.')) . '</div>';
    echo '    <div class="small text-secondary mt-1">Sebelum potongan diskon</div>';
    echo '  </div></div></div>';

    echo '  <div class="col-sm-6 col-xl-3"><div class="card card-clean stat-card stat-warning h-100"><div class="card-body">';
    echo '    <div class="stat-label">Total Diskon (10%)</div>';
    echo '    <div class="stat-value">Rp' . e(number_format($d, 0, ',', '.')) . '</div>';
    echo '    <div class="small text-secondary mt-1">Transaksi minimal Rp100.000</div>';
    echo '  </div></div></div>';

    echo '  <div class="col-sm-6 col-xl-3"><div class="card card-clean stat-card stat-success h-100"><div class="card-body">';
    echo '    <div class="stat-label">Penjualan Bersih</div>';
    echo '    <div class="stat-value">Rp' . e(number_format($n, 0, ',', '.')) . '</div>';
    echo '    <div class="small text-secondary mt-1">Pendapatan bersih diterima</div>';
    echo '  </div></div></div>';
    echo '</div>';

    if ($trxParam !== '') {
        if ($picked === null) {
            echo '<div class="alert alert-warning no-print">Transaksi dengan ID <strong>' . e($trxParam) . '</strong> tidak ditemukan.</div>';
        } else {
            echo '<div class="receipt-box mb-4">';
            echo '  <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-3 gap-2">';
            echo '    <div>';
            echo '      <h2 class="h5 receipt-title mb-1">DETAIL TRANSAKSI ' . e($picked['id'] ?? '') . '</h2>';
            echo '      <div class="small text-secondary">Tanggal &amp; Waktu: ' . e($picked['timestamp'] ?? '') . '</div>';
            echo '    </div>';
            echo '    <div class="d-flex gap-2 no-print">';
            echo '      <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()">Cetak Struk</button>';
            echo '      <a href="reports.php" class="btn btn-sm btn-outline-secondary">Tutup Detail</a>';
            echo '    </div>';
            echo '  </div>';

            echo '  <div class="table-responsive mb-3"><table class="table table-sm align-middle mb-0">';
            echo '    <thead><tr><th>Kode</th><th>Nama Barang</th><th class="text-end">Harga</th><th class="text-end">Jumlah</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
            foreach ($picked['items'] ?? [] as $it) {
                echo '<tr>';
                echo '<td><span class="code-badge">' . e($it['code'] ?? '') . '</span></td>';
                echo '<td>' . e($it['name'] ?? '') . '</td>';
                echo '<td class="text-end">Rp' . e(number_format((int) ($it['price'] ?? 0), 0, ',', '.')) . '</td>';
                echo '<td class="text-end">' . e($it['quantity'] ?? 0) . '</td>';
                echo '<td class="text-end fw-medium">Rp' . e(number_format((int) ($it['subtotal'] ?? 0), 0, ',', '.')) . '</td>';
                echo '</tr>';
            }
            echo '    </tbody></table></div>';

            echo '  <div class="row justify-content-end"><div class="col-md-5">';
            echo '    <div class="d-flex justify-content-between py-1"><span class="text-secondary">Subtotal</span><span>Rp' . e(number_format((int) ($picked['subtotal'] ?? 0), 0, ',', '.')) . '</span></div>';
            echo '    <div class="d-flex justify-content-between py-1"><span class="text-secondary">Diskon</span><span class="text-success">&minus; Rp' . e(number_format((int) ($picked['discount'] ?? 0), 0, ',', '.')) . '</span></div>';
            echo '    <div class="d-flex justify-content-between py-2 border-top border-bottom fw-bold my-1"><span>Total Bayar</span><span>Rp' . e(number_format((int) ($picked['total'] ?? 0), 0, ',', '.')) . '</span></div>';
            echo '    <div class="d-flex justify-content-between py-1"><span class="text-secondary">Pembayaran Tunai</span><span>Rp' . e(number_format((int) ($picked['payment'] ?? 0), 0, ',', '.')) . '</span></div>';
            echo '    <div class="d-flex justify-content-between py-1 fw-semibold"><span>Kembalian</span><span class="text-primary">Rp' . e(number_format((int) ($picked['change'] ?? 0), 0, ',', '.')) . '</span></div>';
            echo '  </div></div>';
            echo '</div>';
        }
    }

    echo '<div class="card card-clean mb-4 no-print">';
    echo '  <div class="card-header d-flex justify-content-between align-items-center">';
    echo '    <span>Riwayat Seluruh Transaksi Penjualan</span>';
    echo '    <a href="sales.php" class="btn btn-sm btn-primary">+ Catat Penjualan</a>';
    echo '  </div>';
    echo '  <div class="card-body p-0">';

    if (empty($trxList)) {
        echo '<div class="p-4 text-center text-secondary">Belum ada riwayat transaksi yang tersimpan.</div>';
    } else {
        $reversed = array_reverse($trxList);
        echo '<div class="table-responsive"><table class="table table-hover align-middle mb-0">';
        echo '<thead><tr><th class="ps-3">ID Transaksi</th><th>Tanggal &amp; Waktu</th><th>Ringkasan Barang</th><th class="text-end">Subtotal</th><th class="text-end">Diskon</th><th class="text-end">Total Bayar</th><th class="text-end pe-3">Aksi</th></tr></thead><tbody>';
        foreach ($reversed as $x) {
            $itemLabels = [];
            foreach ($x['items'] ?? [] as $it) {
                $itemLabels[] = ($it['name'] ?? '') . ' (' . ($it['quantity'] ?? 0) . ')';
            }
            $summaryText = implode(', ', $itemLabels);
            $idVal = (string) ($x['id'] ?? '');

            echo '<tr>';
            echo '<td class="ps-3"><span class="code-badge">' . e($idVal) . '</span></td>';
            echo '<td class="small text-secondary">' . e($x['timestamp'] ?? '') . '</td>';
            echo '<td class="small">' . e($summaryText) . '</td>';
            echo '<td class="text-end">Rp' . e(number_format((int) ($x['subtotal'] ?? 0), 0, ',', '.')) . '</td>';
            echo '<td class="text-end text-success">Rp' . e(number_format((int) ($x['discount'] ?? 0), 0, ',', '.')) . '</td>';
            echo '<td class="text-end fw-semibold">Rp' . e(number_format((int) ($x['total'] ?? 0), 0, ',', '.')) . '</td>';
            echo '<td class="text-end pe-3"><a href="reports.php?trx=' . urlencode($idVal) . '&amp;threshold=' . urlencode((string) $lim) . '" class="btn btn-sm btn-outline-primary">Detail</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '  </div>';
    echo '</div>';

    echo '<div class="card card-clean no-print" id="low-stock">';
    echo '  <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">';
    echo '    <span>Laporan Barang dengan Stok Menipis (Stok &lt; ' . e($lim) . ' Unit)</span>';
    echo '    <form method="get" action="reports.php#low-stock" class="d-flex align-items-center gap-2">';
    if ($trxParam !== '') {
        echo '      <input type="hidden" name="trx" value="' . e($trxParam) . '">';
    }
    echo '      <label for="threshold" class="small text-secondary text-nowrap mb-0">Batas Minimum:</label>';
    echo '      <input type="number" class="form-control form-control-sm" style="width: 90px;" id="threshold" name="threshold" min="1" max="1000" value="' . e($lim) . '">';
    echo '      <button type="submit" class="btn btn-sm btn-outline-secondary">Terapkan</button>';
    echo '    </form>';
    echo '  </div>';
    echo '  <div class="card-body p-0">';

    if (empty($tmp)) {
        echo '<div class="p-4 text-center text-secondary">Tidak ada barang dengan stok di bawah ' . e($lim) . ' unit.</div>';
    } else {
        echo '<div class="table-responsive"><table class="table table-hover align-middle mb-0">';
        echo '<thead><tr><th class="ps-3">Kode</th><th>Nama Barang</th><th>Kategori</th><th class="text-end">Harga Satuan</th><th class="text-end">Sisa Stok</th><th class="text-end">Kekurangan</th><th class="text-end pe-3">Aksi</th></tr></thead><tbody>';
        foreach ($tmp as $r) {
            $stNow = (int) ($r['stock'] ?? 0);
            $shortage = max(0, $lim - $stNow);
            $codeStr = (string) ($r['code'] ?? '');
            echo '<tr>';
            echo '<td class="ps-3"><span class="code-badge">' . e($codeStr) . '</span></td>';
            echo '<td class="fw-medium">' . e($r['name'] ?? '') . '</td>';
            echo '<td>' . e($r['category'] ?? '') . '</td>';
            echo '<td class="text-end">Rp' . e(number_format((int) ($r['price'] ?? 0), 0, ',', '.')) . '</td>';
            echo '<td class="text-end">' . ($stNow === 0
                ? '<span class="badge text-bg-danger">Habis (0)</span>'
                : '<span class="badge text-bg-warning">' . e($stNow) . ' unit</span>') . '</td>';
            echo '<td class="text-end text-danger fw-medium">-' . e($shortage) . ' unit</td>';
            echo '<td class="text-end pe-3"><a href="products.php?edit=' . urlencode($codeStr) . '" class="btn btn-sm btn-outline-secondary">Tambah Stok</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '  </div>';
    echo '</div>';
}

$selectedTransactionId = strtoupper(trim((string) ($_GET['trx'] ?? '')));
$rawThreshold = trim((string) ($_GET['threshold'] ?? (string) LOW_STOCK_LIMIT));
$validatedThreshold = filter_var($rawThreshold, FILTER_VALIDATE_INT);
$stockThreshold = ($validatedThreshold !== false && $validatedThreshold >= 1)
    ? (int) $validatedThreshold
    : LOW_STOCK_LIMIT;

$pageTitle = 'Riwayat & Laporan';
$activePage = 'reports';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header no-print">
    <h1>Riwayat Transaksi &amp; Laporan Persediaan</h1>
    <p class="text-secondary mb-0">Pantau rincian setiap transaksi penjualan serta daftar barang yang perlu segera di-restock.</p>
</div>

<?php render_reports_content($selectedTransactionId, $stockThreshold); ?>

<?php render_footer(); ?>

