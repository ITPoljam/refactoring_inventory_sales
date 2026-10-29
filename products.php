<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

function process_product_form(array $postData): array
{
    $action = trim((string) ($postData['action'] ?? ''));

    if ($action === 'add') {
        $code = strtoupper(trim((string) ($postData['code'] ?? '')));
        $name = trim((string) ($postData['name'] ?? ''));
        $category = trim((string) ($postData['category'] ?? ''));
        $rawPrice = trim((string) ($postData['price'] ?? ''));
        $rawStock = trim((string) ($postData['stock'] ?? ''));

        if ($code !== '') {
            if (preg_match('/^[A-Z0-9\-]+$/', $code) === 1) {
                if ($name !== '') {
                    if ($category !== '') {
                        $validatedPrice = filter_var($rawPrice, FILTER_VALIDATE_INT);
                        if ($validatedPrice !== false && $validatedPrice > 0) {
                            $validatedStock = filter_var($rawStock, FILTER_VALIDATE_INT);
                            if ($validatedStock !== false && $validatedStock >= 0) {
                                $jsonError = null;
                                $products = load_products($jsonError);
                                if ($jsonError === null) {
                                    $isDuplicate = false;
                                    foreach ($products as $existing) {
                                        if (strtoupper(trim((string) ($existing['code'] ?? ''))) === $code) {
                                            $isDuplicate = true;
                                            break;
                                        }
                                    }

                                    if (!$isDuplicate) {
                                        $products[] = [
                                            'code' => $code,
                                            'name' => $name,
                                            'category' => $category,
                                            'price' => (int) $validatedPrice,
                                            'stock' => (int) $validatedStock,
                                        ];

                                        if (save_products($products)) {
                                            $priceLabel = 'Rp' . number_format((int) $validatedPrice, 0, ',', '.');
                                            return [
                                                'ok' => true,
                                                'message' => "Barang '{$name}' ({$code}) berhasil ditambahkan dengan harga {$priceLabel} dan stok {$validatedStock} unit.",
                                                'redirect' => 'products.php',
                                            ];
                                        } else {
                                            return [
                                                'ok' => false,
                                                'message' => 'Gagal menyimpan perubahan ke file data/products.json.',
                                                'redirect' => 'products.php',
                                            ];
                                        }
                                    } else {
                                        return [
                                            'ok' => false,
                                            'message' => "Kode barang '{$code}' sudah terdaftar. Gunakan kode unik yang lain.",
                                            'redirect' => 'products.php',
                                        ];
                                    }
                                } else {
                                    return [
                                        'ok' => false,
                                        'message' => $jsonError,
                                        'redirect' => 'products.php',
                                    ];
                                }
                            } else {
                                return [
                                    'ok' => false,
                                    'message' => 'Jumlah stok awal harus berupa bilangan bulat nol atau positif.',
                                    'redirect' => 'products.php',
                                ];
                            }
                        } else {
                            return [
                                'ok' => false,
                                'message' => 'Harga satuan barang harus berupa bilangan bulat positif lebih dari Rp0.',
                                'redirect' => 'products.php',
                            ];
                        }
                    } else {
                        return [
                            'ok' => false,
                            'message' => 'Kategori barang wajib diisi.',
                            'redirect' => 'products.php',
                        ];
                    }
                } else {
                    return [
                        'ok' => false,
                        'message' => 'Nama barang wajib diisi.',
                        'redirect' => 'products.php',
                    ];
                }
            } else {
                return [
                    'ok' => false,
                    'message' => 'Kode barang hanya boleh berisi huruf, angka, atau tanda hubung (contoh: BRG013).',
                    'redirect' => 'products.php',
                ];
            }
        } else {
            return [
                'ok' => false,
                'message' => 'Kode barang wajib diisi.',
                'redirect' => 'products.php',
            ];
        }
    }

    if ($action === 'update') {
        $code = strtoupper(trim((string) ($postData['code'] ?? '')));
        $rawPrice = trim((string) ($postData['price'] ?? ''));
        $rawStock = trim((string) ($postData['stock'] ?? ''));
        $editRedirect = $code !== '' ? 'products.php?edit=' . urlencode($code) : 'products.php';

        if ($code === '') {
            return [
                'ok' => false,
                'message' => 'Kode barang yang akan diperbarui tidak boleh kosong.',
                'redirect' => 'products.php',
            ];
        }

        $validatedPrice = filter_var($rawPrice, FILTER_VALIDATE_INT);
        if ($validatedPrice === false || $validatedPrice <= 0) {
            return [
                'ok' => false,
                'message' => 'Harga barang baru harus berupa bilangan bulat positif lebih dari Rp0.',
                'redirect' => $editRedirect,
            ];
        }

        $validatedStock = filter_var($rawStock, FILTER_VALIDATE_INT);
        if ($validatedStock === false || $validatedStock < 0) {
            return [
                'ok' => false,
                'message' => 'Jumlah stok baru harus berupa bilangan bulat nol atau positif.',
                'redirect' => $editRedirect,
            ];
        }

        ensure_data_files_exist();
        $rawJson = @file_get_contents(PRODUCTS_FILE);
        if ($rawJson === false) {
            return [
                'ok' => false,
                'message' => 'Gagal membaca file penyimpanan data/products.json.',
                'redirect' => $editRedirect,
            ];
        }

        $products = json_decode($rawJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($products)) {
            return [
                'ok' => false,
                'message' => 'Format JSON pada data/products.json tidak valid: ' . json_last_error_msg() . '.',
                'redirect' => $editRedirect,
            ];
        }

        $foundIndex = null;
        foreach ($products as $idx => $item) {
            if (strtoupper(trim((string) ($item['code'] ?? ''))) === $code) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex === null) {
            return [
                'ok' => false,
                'message' => "Barang dengan kode '{$code}' tidak ditemukan.",
                'redirect' => 'products.php',
            ];
        }

        $products[$foundIndex]['price'] = (int) $validatedPrice;
        $products[$foundIndex]['stock'] = (int) $validatedStock;
        $updatedName = (string) ($products[$foundIndex]['name'] ?? $code);

        $encoded = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false || file_put_contents(PRODUCTS_FILE, $encoded . PHP_EOL, LOCK_EX) === false) {
            return [
                'ok' => false,
                'message' => 'Gagal menyimpan pembaruan barang ke data/products.json.',
                'redirect' => $editRedirect,
            ];
        }

        $priceFormatted = 'Rp' . number_format((int) $validatedPrice, 0, ',', '.');
        return [
            'ok' => true,
            'message' => "Data '{$updatedName}' ({$code}) berhasil diperbarui: harga {$priceFormatted}, stok {$validatedStock} unit.",
            'redirect' => 'products.php',
        ];
    }

    return [
        'ok' => false,
        'message' => 'Aksi pengelolaan barang tidak dikenali.',
        'redirect' => 'products.php',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('danger', 'Sesi keamanan form tidak valid. Silakan ulangi pengisian form.');
        redirect_to('products.php');
    }

    $result = process_product_form($_POST);
    if (!$result['ok'] && ($_POST['action'] ?? '') === 'add') {
        $_SESSION['old_add_product'] = [
            'code' => trim((string) ($_POST['code'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'stock' => trim((string) ($_POST['stock'] ?? '')),
        ];
    }

    set_flash($result['ok'] ? 'success' : 'danger', $result['message']);
    redirect_to($result['redirect']);
}

$oldAdd = $_SESSION['old_add_product'] ?? [
    'code' => '',
    'name' => '',
    'category' => '',
    'price' => '',
    'stock' => '',
];
unset($_SESSION['old_add_product']);

$productError = null;
$allProducts = load_products($productError);

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$selectedCategory = trim((string) ($_GET['category'] ?? ''));
$editCode = strtoupper(trim((string) ($_GET['edit'] ?? '')));

$categories = [];
foreach ($allProducts as $item) {
    $cat = trim((string) ($item['category'] ?? ''));
    if ($cat !== '' && !in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }
}
sort($categories);

$filteredProducts = [];
foreach ($allProducts as $item) {
    $matchesSearch = true;
    if ($searchQuery !== '') {
        $haystackCode = strtolower((string) ($item['code'] ?? ''));
        $haystackName = strtolower((string) ($item['name'] ?? ''));
        $needle = strtolower($searchQuery);
        $matchesSearch = str_contains($haystackCode, $needle) || str_contains($haystackName, $needle);
    }

    $matchesCategory = true;
    if ($selectedCategory !== '') {
        $matchesCategory = strcasecmp((string) ($item['category'] ?? ''), $selectedCategory) === 0;
    }

    if ($matchesSearch && $matchesCategory) {
        $filteredProducts[] = $item;
    }
}

$editingProduct = $editCode !== '' ? find_product_by_code($allProducts, $editCode) : null;

$pageTitle = 'Data Barang';
$activePage = 'products';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($productError !== null): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Kesalahan Penyimpanan:</strong> <?= e($productError) ?>
    </div>
<?php endif; ?>

<div class="page-header">
    <h1>Manajemen Persediaan Barang</h1>
    <p class="text-secondary mb-0">Kelola katalog barang, perbarui harga satuan, serta sesuaikan ketersediaan stok.</p>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <?php if ($editingProduct !== null): ?>
            <div class="card card-clean border-primary mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span>Ubah Harga &amp; Stok Barang</span>
                    <a href="products.php" class="btn btn-sm btn-light">Batal</a>
                </div>
                <div class="card-body">
                    <form method="post" action="products.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="code" value="<?= e($editingProduct['code'] ?? '') ?>">

                        <div class="mb-3">
                            <label class="form-label text-secondary small mb-1">Barang Dipilih</label>
                            <div class="fw-semibold">
                                <span class="code-badge me-1"><?= e($editingProduct['code'] ?? '') ?></span>
                                <?= e($editingProduct['name'] ?? '') ?>
                            </div>
                            <div class="small text-secondary mt-1">Kategori: <?= e($editingProduct['category'] ?? '') ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_price" class="form-label">Harga Satuan Baru (Rp)</label>
                            <input type="number" class="form-control" id="edit_price" name="price"
                                   min="1" step="1" required
                                   value="<?= e($editingProduct['price'] ?? 0) ?>">
                        </div>

                        <div class="mb-3">
                            <label for="edit_stock" class="form-label">Jumlah Stok Terbaru</label>
                            <input type="number" class="form-control" id="edit_stock" name="stock"
                                   min="0" step="1" required
                                   value="<?= e($editingProduct['stock'] ?? 0) ?>">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card card-clean">
            <div class="card-header">Tambah Barang Baru</div>
            <div class="card-body">
                <form method="post" action="products.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label for="code" class="form-label">Kode Barang</label>
                        <input type="text" class="form-control" id="code" name="code"
                               placeholder="Contoh: BRG013" maxlength="20" required
                               value="<?= e($oldAdd['code']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Barang</label>
                        <input type="text" class="form-control" id="name" name="name"
                               placeholder="Contoh: Garam Dapur Beryodium 250 g" maxlength="100" required
                               value="<?= e($oldAdd['name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="category" class="form-label">Kategori</label>
                        <input type="text" class="form-control" id="category" name="category"
                               list="categoryOptions" placeholder="Contoh: Sembako" maxlength="60" required
                               value="<?= e($oldAdd['category']) ?>">
                        <datalist id="categoryOptions">
                            <?php foreach ($categories as $catOption): ?>
                                <option value="<?= e($catOption) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label for="price" class="form-label">Harga Satuan (Rp)</label>
                        <input type="number" class="form-control" id="price" name="price"
                               min="1" step="1" placeholder="Contoh: 5000" required
                               value="<?= e($oldAdd['price']) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="stock" class="form-label">Stok Awal</label>
                        <input type="number" class="form-control" id="stock" name="stock"
                               min="0" step="1" placeholder="Contoh: 25" required
                               value="<?= e($oldAdd['stock']) ?>">
                    </div>

                    <button type="submit" class="btn btn-success w-100">+ Simpan Barang Baru</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-clean">
            <div class="card-header">Daftar &amp; Pencarian Barang</div>
            <div class="card-body border-bottom">
                <form method="get" action="products.php" class="row g-2 align-items-end">
                    <div class="col-sm-6">
                        <label for="q" class="form-label small text-secondary mb-1">Cari Kode atau Nama Barang</label>
                        <input type="text" class="form-control" id="q" name="q"
                               placeholder="Ketik kata kunci..." value="<?= e($searchQuery) ?>">
                    </div>
                    <div class="col-sm-4">
                        <label for="filter_category" class="form-label small text-secondary mb-1">Filter Kategori</label>
                        <select class="form-select" id="filter_category" name="category">
                            <option value="">Semua Kategori</option>
                            <?php foreach ($categories as $catOption): ?>
                                <option value="<?= e($catOption) ?>" <?= strcasecmp($selectedCategory, $catOption) === 0 ? 'selected' : '' ?>>
                                    <?= e($catOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary w-100">Cari</button>
                        <?php if ($searchQuery !== '' || $selectedCategory !== ''): ?>
                            <a href="products.php" class="btn btn-outline-secondary" title="Reset pencarian">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <?php if (empty($filteredProducts)): ?>
                    <div class="p-4 text-center text-secondary">
                        Tidak ditemukan data barang yang sesuai dengan kriteria pencarian.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Kode</th>
                                    <th>Nama Barang</th>
                                    <th>Kategori</th>
                                    <th class="text-end">Harga Satuan</th>
                                    <th class="text-end">Stok</th>
                                    <th class="text-end pe-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredProducts as $product): ?>
                                    <?php
                                    $stockVal = (int) ($product['stock'] ?? 0);
                                    $priceDisplay = 'Rp' . number_format((int) ($product['price'] ?? 0), 0, ',', '.');
                                    ?>
                                    <tr>
                                        <td class="ps-3"><span class="code-badge"><?= e($product['code'] ?? '') ?></span></td>
                                        <td class="fw-medium"><?= e($product['name'] ?? '') ?></td>
                                        <td><span class="badge text-bg-light border text-dark"><?= e($product['category'] ?? '') ?></span></td>
                                        <td class="text-end"><?= e($priceDisplay) ?></td>
                                        <td class="text-end">
                                            <?php if ($stockVal === 0): ?>
                                                <span class="badge text-bg-danger">Habis (0)</span>
                                            <?php elseif ($stockVal < LOW_STOCK_LIMIT): ?>
                                                <span class="badge text-bg-warning"><?= e($stockVal) ?> (Menipis)</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-success"><?= e($stockVal) ?> unit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="products.php?edit=<?= urlencode((string) ($product['code'] ?? '')) ?>"
                                               class="btn btn-sm btn-outline-primary">Ubah</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white text-secondary small">
                Menampilkan <?= e(count($filteredProducts)) ?> dari total <?= e(count($allProducts)) ?> barang terdaftar.
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>

