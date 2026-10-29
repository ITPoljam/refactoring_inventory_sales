<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

define('BASE_PATH', dirname(__DIR__));
define('DATA_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'data');
define('PRODUCTS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'products.json');
define('TRANSACTIONS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'transactions.json');

define('LOW_STOCK_LIMIT', 10);
define('DISCOUNT_MIN_SUBTOTAL', 100000);
define('DISCOUNT_PERCENTAGE', 10);

/**
 * Mengamankan teks output HTML dari risiko XSS.
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Memformat angka ke dalam mata uang Rupiah (contoh: Rp72.000).
 */
function format_rupiah(int|float $amount): string
{
    return 'Rp' . number_format((int) round($amount), 0, ',', '.');
}

/**
 * Menghasilkan atau mengambil token CSRF pada sesi aktif.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Memeriksa kecocokan token CSRF dari request POST.
 */
function verify_csrf_token(?string $submittedToken): bool
{
    if (!is_string($submittedToken) || $submittedToken === '') {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }
    return hash_equals($sessionToken, $submittedToken);
}

/**
 * Menyimpan pesan notifikasi sementara (flash message) untuk pola Post/Redirect/Get.
 */
function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Mengambil sekaligus mengosongkan seluruh flash message pada sesi aktif.
 */
function consume_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return is_array($messages) ? $messages : [];
}

/**
 * Melakukan pengalihan halaman HTTP (Post/Redirect/Get).
 */
function redirect_to(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Menyediakan data simulasi awal apabila file products.json belum tersedia.
 */
function get_default_products(): array
{
    return [
        [
            'code' => 'BRG001',
            'name' => 'Beras Premium 5 kg',
            'category' => 'Sembako',
            'price' => 72000,
            'stock' => 25,
        ],
        [
            'code' => 'BRG002',
            'name' => 'Minyak Goreng Pouch 2 L',
            'category' => 'Sembako',
            'price' => 36000,
            'stock' => 30,
        ],
        [
            'code' => 'BRG003',
            'name' => 'Gula Pasir Putih 1 kg',
            'category' => 'Sembako',
            'price' => 17500,
            'stock' => 40,
        ],
        [
            'code' => 'BRG004',
            'name' => 'Kopi Bubuk Hitam 165 g',
            'category' => 'Minuman',
            'price' => 15000,
            'stock' => 8,
        ],
        [
            'code' => 'BRG005',
            'name' => 'Teh Celup Isi 25',
            'category' => 'Minuman',
            'price' => 8500,
            'stock' => 22,
        ],
        [
            'code' => 'BRG006',
            'name' => 'Telur Ayam Negeri 1 kg',
            'category' => 'Sembako',
            'price' => 28000,
            'stock' => 6,
        ],
        [
            'code' => 'BRG007',
            'name' => 'Mie Instan Goreng',
            'category' => 'Makanan Instan',
            'price' => 3500,
            'stock' => 80,
        ],
        [
            'code' => 'BRG008',
            'name' => 'Susu Kental Manis 370 g',
            'category' => 'Minuman',
            'price' => 12500,
            'stock' => 18,
        ],
        [
            'code' => 'BRG009',
            'name' => 'Tepung Terigu Serbaguna 1 kg',
            'category' => 'Sembako',
            'price' => 13000,
            'stock' => 20,
        ],
        [
            'code' => 'BRG010',
            'name' => 'Sabun Cuci Piring 750 ml',
            'category' => 'Kebutuhan Rumah Tangga',
            'price' => 16000,
            'stock' => 4,
        ],
        [
            'code' => 'BRG011',
            'name' => 'Deterjen Bubuk 800 g',
            'category' => 'Kebutuhan Rumah Tangga',
            'price' => 21000,
            'stock' => 15,
        ],
        [
            'code' => 'BRG012',
            'name' => 'Kecap Manis Pouch 520 ml',
            'category' => 'Bumbu Dapur',
            'price' => 22500,
            'stock' => 12,
        ],
    ];
}

/**
 * Menginisialisasi file JSON awal secara aman tanpa pernah menimpa file yang sudah ada.
 */
function ensure_data_files_exist(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }

    if (!file_exists(PRODUCTS_FILE)) {
        file_put_contents(
            PRODUCTS_FILE,
            json_encode(get_default_products(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            LOCK_EX
        );
    }

    if (!file_exists(TRANSACTIONS_FILE)) {
        file_put_contents(
            TRANSACTIONS_FILE,
            json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            LOCK_EX
        );
    }
}

/**
 * Membaca data barang dari data/products.json beserta informasi error bila JSON rusak.
 */
function load_products(?string &$errorMessage = null): array
{
    ensure_data_files_exist();
    $errorMessage = null;

    $rawContent = @file_get_contents(PRODUCTS_FILE);
    if ($rawContent === false) {
        $errorMessage = 'Gagal membaca file penyimpanan data barang (data/products.json).';
        return [];
    }

    $decoded = json_decode($rawContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $errorMessage = 'Format JSON pada data/products.json tidak valid: ' . json_last_error_msg() . '.';
        return [];
    }

    return $decoded;
}

/**
 * Menyimpan daftar barang ke data/products.json.
 */
function save_products(array $products): bool
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    $encoded = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return false;
    }
    return file_put_contents(PRODUCTS_FILE, $encoded . PHP_EOL, LOCK_EX) !== false;
}

/**
 * Membaca daftar transaksi dari data/transactions.json beserta informasi error bila JSON rusak.
 */
function load_transactions(?string &$errorMessage = null): array
{
    ensure_data_files_exist();
    $errorMessage = null;

    $rawContent = @file_get_contents(TRANSACTIONS_FILE);
    if ($rawContent === false) {
        $errorMessage = 'Gagal membaca file penyimpanan transaksi (data/transactions.json).';
        return [];
    }

    $decoded = json_decode($rawContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $errorMessage = 'Format JSON pada data/transactions.json tidak valid: ' . json_last_error_msg() . '.';
        return [];
    }

    return $decoded;
}

/**
 * Mencari satu data barang berdasarkan kode uniknya (case-insensitive).
 */
function find_product_by_code(array $products, string $code): ?array
{
    $normalized = strtoupper(trim($code));
    if ($normalized === '') {
        return null;
    }

    foreach ($products as $product) {
        if (strtoupper(trim((string) ($product['code'] ?? ''))) === $normalized) {
            return $product;
        }
    }
    return null;
}

