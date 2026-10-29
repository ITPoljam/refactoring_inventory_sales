<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

function handle_sale_submission(array $postData): array
{
    ensure_data_files_exist();

    $rawProducts = @file_get_contents(PRODUCTS_FILE);
    if ($rawProducts === false) {
        return [
            'ok' => false,
            'message' => 'Gagal membaca file persediaan data/products.json.',
        ];
    }

    $productsData = json_decode($rawProducts, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($productsData)) {
        return [
            'ok' => false,
            'message' => 'Format JSON pada data/products.json tidak valid: ' . json_last_error_msg() . '.',
        ];
    }

    $rawTransactions = @file_get_contents(TRANSACTIONS_FILE);
    if ($rawTransactions === false) {
        return [
            'ok' => false,
            'message' => 'Gagal membaca file transaksi data/transactions.json.',
        ];
    }

    $transactionsData = json_decode($rawTransactions, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($transactionsData)) {
        return [
            'ok' => false,
            'message' => 'Format JSON pada data/transactions.json tidak valid: ' . json_last_error_msg() . '.',
        ];
    }

    $submittedCodes = $postData['codes'] ?? [];
    $submittedQuantities = $postData['quantities'] ?? [];
    if (!is_array($submittedCodes) || !is_array($submittedQuantities)) {
        return [
            'ok' => false,
            'message' => 'Data keranjang belanja tidak valid.',
        ];
    }

    $productIndexByCode = [];
    foreach ($productsData as $idx => $prod) {
        $normCode = strtoupper(trim((string) ($prod['code'] ?? '')));
        if ($normCode !== '') {
            $productIndexByCode[$normCode] = $idx;
        }
    }

    $aggregatedQty = [];
    $rowCount = max(count($submittedCodes), count($submittedQuantities));
    for ($i = 0; $i < $rowCount; $i++) {
        $codeVal = strtoupper(trim((string) ($submittedCodes[$i] ?? '')));
        $qtyRaw = trim((string) ($submittedQuantities[$i] ?? ''));

        if ($codeVal === '' && $qtyRaw === '') {
            continue;
        }

        if ($codeVal === '' && $qtyRaw !== '') {
            return [
                'ok' => false,
                'message' => 'Terdapat baris pembelian dengan jumlah terisi tetapi barang belum dipilih.',
            ];
        }

        if (!isset($productIndexByCode[$codeVal])) {
            return [
                'ok' => false,
                'message' => "Barang dengan kode '{$codeVal}' tidak ditemukan pada data toko.",
            ];
        }

        $validatedQty = filter_var($qtyRaw, FILTER_VALIDATE_INT);
        if ($validatedQty === false || $validatedQty <= 0) {
            $prodName = (string) ($productsData[$productIndexByCode[$codeVal]]['name'] ?? $codeVal);
            return [
                'ok' => false,
                'message' => "Jumlah pembelian untuk '{$prodName}' harus berupa bilangan bulat positif.",
            ];
        }

        if (!isset($aggregatedQty[$codeVal])) {
            $aggregatedQty[$codeVal] = 0;
        }
        $aggregatedQty[$codeVal] += (int) $validatedQty;
    }

    if (empty($aggregatedQty)) {
        return [
            'ok' => false,
            'message' => 'Pilih minimal satu barang beserta jumlah pembeliannya sebelum memproses transaksi.',
        ];
    }

    $lineItems = [];
    $subtotal = 0;
    foreach ($aggregatedQty as $codeVal => $requestedQty) {
        $prodIdx = $productIndexByCode[$codeVal];
        $productRecord = $productsData[$prodIdx];
        $availableStock = (int) ($productRecord['stock'] ?? 0);
        $unitPrice = (int) ($productRecord['price'] ?? 0);
        $productName = (string) ($productRecord['name'] ?? $codeVal);

        if ($requestedQty > $availableStock) {
            return [
                'ok' => false,
                'message' => "Stok '{$productName}' ({$codeVal}) tidak mencukupi. Stok tersedia: {$availableStock} unit, diminta: {$requestedQty} unit.",
            ];
        }

        $lineSubtotal = $unitPrice * $requestedQty;
        $subtotal += $lineSubtotal;

        $lineItems[] = [
            'code' => $codeVal,
            'name' => $productName,
            'price' => $unitPrice,
            'quantity' => $requestedQty,
            'subtotal' => $lineSubtotal,
        ];
    }

    if ($subtotal >= 100000) {
        $discount = intdiv($subtotal * 10, 100);
    } else {
        $discount = 0;
    }

    $total = $subtotal - $discount;

    $rawPayment = trim((string) ($postData['payment'] ?? ''));
    $validatedPayment = filter_var($rawPayment, FILTER_VALIDATE_INT);
    if ($validatedPayment === false || $validatedPayment <= 0) {
        return [
            'ok' => false,
            'message' => 'Nominal uang pembayaran harus berupa bilangan bulat positif.',
        ];
    }

    $payment = (int) $validatedPayment;
    if ($payment < $total) {
        $totalLabel = 'Rp' . number_format($total, 0, ',', '.');
        $paymentLabel = 'Rp' . number_format($payment, 0, ',', '.');
        return [
            'ok' => false,
            'message' => "Pembayaran tidak mencukupi. Total tagihan adalah {$totalLabel}, sedangkan pembayaran yang dimasukkan {$paymentLabel}.",
        ];
    }

    $change = $payment - $total;

    $maxSeq = 0;
    foreach ($transactionsData as $existingTrx) {
        $existingId = (string) ($existingTrx['id'] ?? '');
        if (str_starts_with($existingId, 'TRX-')) {
            $numPart = substr($existingId, 4);
            if (ctype_digit($numPart)) {
                $seqVal = (int) $numPart;
                if ($seqVal > $maxSeq) {
                    $maxSeq = $seqVal;
                }
            }
        }
    }

    $newTransactionId = sprintf('TRX-%04d', $maxSeq + 1);
    $timestamp = date('Y-m-d H:i:s');

    foreach ($aggregatedQty as $codeVal => $requestedQty) {
        $prodIdx = $productIndexByCode[$codeVal];
        $productsData[$prodIdx]['stock'] = (int) $productsData[$prodIdx]['stock'] - $requestedQty;
    }

    $newTransaction = [
        'id' => $newTransactionId,
        'timestamp' => $timestamp,
        'items' => $lineItems,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'payment' => $payment,
        'change' => $change,
    ];
    $transactionsData[] = $newTransaction;

    $encodedProducts = json_encode(array_values($productsData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $encodedTransactions = json_encode(array_values($transactionsData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encodedProducts === false || $encodedTransactions === false) {
        return [
            'ok' => false,
            'message' => 'Gagal menyiapkan data JSON untuk penyimpanan transaksi.',
        ];
    }

    if (file_put_contents(PRODUCTS_FILE, $encodedProducts . PHP_EOL, LOCK_EX) === false) {
        return [
            'ok' => false,
            'message' => 'Gagal memperbarui stok barang pada file penyimpanan.',
        ];
    }

    if (file_put_contents(TRANSACTIONS_FILE, $encodedTransactions . PHP_EOL, LOCK_EX) === false) {
        return [
            'ok' => false,
            'message' => 'Gagal menyimpan riwayat transaksi ke file penyimpanan.',
        ];
    }

    $totalFmt = 'Rp' . number_format($total, 0, ',', '.');
    $changeFmt = 'Rp' . number_format($change, 0, ',', '.');

    return [
        'ok' => true,
        'transaction_id' => $newTransactionId,
        'message' => "Transaksi {$newTransactionId} berhasil disimpan! Total bayar {$totalFmt}, kembalian {$changeFmt}.",
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Token keamanan form tidak valid. Silakan muat ulang halaman dan coba kembali.');
        redirect_to('sales.php');
    }

    $saleResult = handle_sale_submission($_POST);
    if ($saleResult['ok']) {
        unset($_SESSION['old_sale_form']);
        set_flash('success', $saleResult['message']);
        redirect_to('sales.php?receipt=' . urlencode((string) $saleResult['transaction_id']));
    }

    $savedRows = [];
    $postedCodes = is_array($_POST['codes'] ?? null) ? $_POST['codes'] : [];
    $postedQtys = is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : [];
    $countRows = max(count($postedCodes), count($postedQtys));
    for ($i = 0; $i < $countRows; $i++) {
        $c = trim((string) ($postedCodes[$i] ?? ''));
        $q = trim((string) ($postedQtys[$i] ?? ''));
        if ($c !== '' || $q !== '') {
            $savedRows[] = ['code' => $c, 'quantity' => $q];
        }
    }

    $_SESSION['old_sale_form'] = [
        'rows' => $savedRows,
        'payment' => trim((string) ($_POST['payment'] ?? '')),
    ];

    set_flash('danger', $saleResult['message']);
    redirect_to('sales.php');
}

$oldSaleForm = $_SESSION['old_sale_form'] ?? null;
unset($_SESSION['old_sale_form']);

$formRows = $oldSaleForm['rows'] ?? [
    ['code' => '', 'quantity' => '1'],
    ['code' => '', 'quantity' => ''],
    ['code' => '', 'quantity' => ''],
];
if (empty($formRows)) {
    $formRows = [['code' => '', 'quantity' => '1']];
}
$oldPayment = (string) ($oldSaleForm['payment'] ?? '');

$productError = null;
$transactionError = null;
$products = load_products($productError);
$transactions = load_transactions($transactionError);

$receiptId = strtoupper(trim((string) ($_GET['receipt'] ?? '')));
$receiptTransaction = null;
if ($receiptId !== '') {
    foreach ($transactions as $trx) {
        if (strtoupper(trim((string) ($trx['id'] ?? ''))) === $receiptId) {
            $receiptTransaction = $trx;
            break;
        }
    }
}

$pageTitle = 'Kasir Penjualan';
$activePage = 'sales';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($productError !== null): ?>
    <div class="alert alert-danger no-print" role="alert">
        <strong>Kesalahan Data Barang:</strong> <?= e($productError) ?>
    </div>
<?php endif; ?>

<?php if ($transactionError !== null): ?>
    <div class="alert alert-danger no-print" role="alert">
        <strong>Kesalahan Data Transaksi:</strong> <?= e($transactionError) ?>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center page-header gap-2 no-print">
    <div>
        <h1>Kasir Transaksi Penjualan</h1>
        <p class="text-secondary mb-0">
            Pilih satu atau beberapa barang. Diskon otomatis <strong><?= e(DISCOUNT_PERCENTAGE) ?>%</strong> diberikan untuk subtotal minimal <strong><?= e(format_rupiah(DISCOUNT_MIN_SUBTOTAL)) ?></strong>.
        </p>
    </div>
    <a href="reports.php" class="btn btn-outline-secondary">Lihat Riwayat Transaksi</a>
</div>

<?php if ($receiptTransaction !== null): ?>
    <div class="receipt-box mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center border-bottom pb-3 mb-3 gap-2">
            <div>
                <h2 class="h5 receipt-title mb-1">STRUK TRANSAKSI &mdash; TOKO RITEL MAJU BERSAMA</h2>
                <div class="small text-secondary">
                    ID Transaksi: <strong><?= e($receiptTransaction['id'] ?? '') ?></strong>
                    &bull; Waktu: <?= e($receiptTransaction['timestamp'] ?? '') ?>
                </div>
            </div>
            <div class="d-flex gap-2 no-print">
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()">Cetak Struk</button>
                <a href="sales.php" class="btn btn-sm btn-primary">+ Transaksi Baru</a>
            </div>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th class="text-end">Harga Satuan</th>
                        <th class="text-end">Jumlah</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receiptTransaction['items'] ?? [] as $item): ?>
                        <tr>
                            <td><span class="code-badge"><?= e($item['code'] ?? '') ?></span></td>
                            <td><?= e($item['name'] ?? '') ?></td>
                            <td class="text-end"><?= e('Rp' . number_format((int) ($item['price'] ?? 0), 0, ',', '.')) ?></td>
                            <td class="text-end"><?= e($item['quantity'] ?? 0) ?></td>
                            <td class="text-end fw-medium"><?= e('Rp' . number_format((int) ($item['subtotal'] ?? 0), 0, ',', '.')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row justify-content-end">
            <div class="col-md-5">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-secondary">Subtotal Belanja</span>
                    <span><?= e('Rp' . number_format((int) ($receiptTransaction['subtotal'] ?? 0), 0, ',', '.')) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-secondary">Diskon (10%)</span>
                    <span class="text-success">&minus; <?= e('Rp' . number_format((int) ($receiptTransaction['discount'] ?? 0), 0, ',', '.')) ?></span>
                </div>
                <div class="d-flex justify-content-between py-2 border-top border-bottom fw-bold fs-5 my-1">
                    <span>Total Bayar</span>
                    <span><?= e('Rp' . number_format((int) ($receiptTransaction['total'] ?? 0), 0, ',', '.')) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-secondary">Uang Tunai</span>
                    <span><?= e('Rp' . number_format((int) ($receiptTransaction['payment'] ?? 0), 0, ',', '.')) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 fw-semibold">
                    <span>Kembalian</span>
                    <span class="text-primary"><?= e('Rp' . number_format((int) ($receiptTransaction['change'] ?? 0), 0, ',', '.')) ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="sales.php" id="saleForm" class="no-print">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card card-clean">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Daftar Barang yang Dibeli</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn">+ Tambah Baris Barang</button>
                </div>
                <div class="card-body">
                    <div id="cartRows">
                        <?php foreach ($formRows as $idx => $row): ?>
                            <div class="row g-2 align-items-end mb-3 cart-row">
                                <div class="col-md-7">
                                    <label class="form-label small text-secondary mb-1">Pilih Barang</label>
                                    <select name="codes[]" class="form-select product-select">
                                        <option value="" data-price="0" data-stock="0">-- Pilih Barang --</option>
                                        <?php foreach ($products as $prod): ?>
                                            <?php
                                            $pCode = (string) ($prod['code'] ?? '');
                                            $pName = (string) ($prod['name'] ?? '');
                                            $pPrice = (int) ($prod['price'] ?? 0);
                                            $pStock = (int) ($prod['stock'] ?? 0);
                                            $isSelected = strcasecmp((string) ($row['code'] ?? ''), $pCode) === 0;
                                            $priceLabel = 'Rp' . number_format($pPrice, 0, ',', '.');
                                            ?>
                                            <option value="<?= e($pCode) ?>"
                                                    data-price="<?= e($pPrice) ?>"
                                                    data-stock="<?= e($pStock) ?>"
                                                    <?= $pStock <= 0 && !$isSelected ? 'disabled' : '' ?>
                                                    <?= $isSelected ? 'selected' : '' ?>>
                                                <?= e($pCode) ?> - <?= e($pName) ?> (<?= e($priceLabel) ?> | Stok: <?= e($pStock) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-8 col-md-3">
                                    <label class="form-label small text-secondary mb-1">Jumlah Beli</label>
                                    <input type="number" name="quantities[]" class="form-control qty-input"
                                           min="1" step="1" placeholder="0"
                                           value="<?= e($row['quantity'] ?? '') ?>">
                                </div>
                                <div class="col-4 col-md-2">
                                    <button type="button" class="btn btn-outline-danger w-100 remove-row-btn">Hapus</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="small text-secondary mt-2">
                        Baris yang dikosongkan akan diabaikan secara otomatis. Harga dan stok dihitung ulang secara resmi di sisi server.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-clean">
                <div class="card-header">Ringkasan &amp; Pembayaran</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Estimasi Subtotal</span>
                        <span id="previewSubtotal" class="fw-medium">Rp0</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Diskon (10% &ge; Rp100.000)</span>
                        <span id="previewDiscount" class="text-success">Rp0</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-bold">Total Pembayaran</span>
                        <span id="previewTotal" class="fs-4 fw-bold text-primary">Rp0</span>
                    </div>

                    <div class="mb-3">
                        <label for="payment" class="form-label fw-semibold">Nominal Uang Pembayaran (Rp)</label>
                        <input type="number" class="form-control form-control-lg" id="payment" name="payment"
                               min="1" step="1" placeholder="Contoh: 150000" required
                               value="<?= e($oldPayment) ?>">
                    </div>

                    <div class="d-flex justify-content-between small mb-3 p-2 bg-light rounded border">
                        <span class="text-secondary">Estimasi Kembalian:</span>
                        <span id="previewChange" class="fw-semibold">Rp0</span>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100">Proses &amp; Simpan Transaksi</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const cartRows = document.getElementById('cartRows');
    const addRowBtn = document.getElementById('addRowBtn');
    const paymentInput = document.getElementById('payment');
    const previewSubtotal = document.getElementById('previewSubtotal');
    const previewDiscount = document.getElementById('previewDiscount');
    const previewTotal = document.getElementById('previewTotal');
    const previewChange = document.getElementById('previewChange');

    if (!cartRows || !addRowBtn) {
        return;
    }

    function formatRupiahJs(value) {
        const rounded = Math.round(value);
        return 'Rp' + Math.abs(rounded).toLocaleString('id-ID');
    }

    function recalculatePreview() {
        let subtotal = 0;
        const rows = cartRows.querySelectorAll('.cart-row');
        rows.forEach(function (row) {
            const selectEl = row.querySelector('.product-select');
            const qtyEl = row.querySelector('.qty-input');
            if (!selectEl || !qtyEl) {
                return;
            }
            const selectedOption = selectEl.options[selectEl.selectedIndex];
            const price = selectedOption ? parseInt(selectedOption.getAttribute('data-price') || '0', 10) : 0;
            const qty = parseInt(qtyEl.value || '0', 10);
            if (price > 0 && qty > 0) {
                subtotal += price * qty;
            }
        });

        const discount = subtotal >= 100000 ? Math.floor((subtotal * 10) / 100) : 0;
        const total = subtotal - discount;
        const paid = parseInt((paymentInput && paymentInput.value) || '0', 10);

        previewSubtotal.textContent = formatRupiahJs(subtotal);
        previewDiscount.textContent = discount > 0 ? '- ' + formatRupiahJs(discount) : 'Rp0';
        previewTotal.textContent = formatRupiahJs(total);

        if (paid > 0 && total > 0) {
            if (paid >= total) {
                previewChange.textContent = formatRupiahJs(paid - total);
                previewChange.className = 'fw-semibold text-success';
            } else {
                previewChange.textContent = 'Kurang ' + formatRupiahJs(total - paid);
                previewChange.className = 'fw-semibold text-danger';
            }
        } else {
            previewChange.textContent = 'Rp0';
            previewChange.className = 'fw-semibold';
        }
    }

    addRowBtn.addEventListener('click', function () {
        const firstRow = cartRows.querySelector('.cart-row');
        if (!firstRow) {
            return;
        }
        const clone = firstRow.cloneNode(true);
        const selectEl = clone.querySelector('.product-select');
        const qtyEl = clone.querySelector('.qty-input');
        if (selectEl) {
            selectEl.selectedIndex = 0;
        }
        if (qtyEl) {
            qtyEl.value = '1';
        }
        cartRows.appendChild(clone);
        recalculatePreview();
    });

    cartRows.addEventListener('click', function (event) {
        const btn = event.target.closest('.remove-row-btn');
        if (!btn) {
            return;
        }
        const allRows = cartRows.querySelectorAll('.cart-row');
        const currentRow = btn.closest('.cart-row');
        if (allRows.length > 1 && currentRow) {
            currentRow.remove();
        } else if (currentRow) {
            const selectEl = currentRow.querySelector('.product-select');
            const qtyEl = currentRow.querySelector('.qty-input');
            if (selectEl) {
                selectEl.selectedIndex = 0;
            }
            if (qtyEl) {
                qtyEl.value = '';
            }
        }
        recalculatePreview();
    });

    cartRows.addEventListener('change', function (event) {
        if (event.target.classList.contains('product-select')) {
            const row = event.target.closest('.cart-row');
            const qtyEl = row ? row.querySelector('.qty-input') : null;
            if (qtyEl && event.target.value !== '' && qtyEl.value === '') {
                qtyEl.value = '1';
            }
        }
        recalculatePreview();
    });

    cartRows.addEventListener('input', recalculatePreview);
    if (paymentInput) {
        paymentInput.addEventListener('input', recalculatePreview);
    }

    recalculatePreview();
})();
</script>

<?php render_footer(); ?>
