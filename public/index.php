<?php

declare(strict_types=1);

// Front-controller tunggal. Seluruh request (pretty URL maupun built-in server) masuk ke sini.

$root = dirname(__DIR__);
require $root . '/app/Config/Config.php';
require $root . '/app/Helpers/helpers.php';

use App\Config\Config;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\FormController;
use App\Controllers\PengirimanController;
use App\Database\Connection;
use App\Models\User;
use App\Services\TandaTangan;

// Autoload PSR-4 untuk App\ + library vendor yang dipasang manual tanpa Composer
// (lihat README: composer install menghasilkan vendor/autoload.php bila tersedia).
$vendorPsr4 = [
    'Dompdf\\' => $root . '/vendor/dompdf/dompdf/src/',
    'FontLib\\' => $root . '/vendor/dompdf/php-font-lib/src/FontLib/',
    'Svg\\' => $root . '/vendor/dompdf/php-svg-lib/src/Svg/',
    'Masterminds\\' => $root . '/vendor/masterminds/html5/src/',
];
$vendorClassmap = [
    'Dompdf\\Cpdf' => $root . '/vendor/dompdf/dompdf/lib/Cpdf.php',
];
spl_autoload_register(function (string $class) use ($root, $vendorPsr4, $vendorClassmap): void {
    if (isset($vendorClassmap[$class]) && is_file($vendorClassmap[$class])) {
        require $vendorClassmap[$class];
        return;
    }
    foreach ($vendorPsr4 as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
    if (str_starts_with($class, 'App\\')) {
        $rel = substr($class, 4); // buang prefix "App\", sisa path relatif terhadap app/
        $file = $root . '/app/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// Error handling: production sembunyikan detail, tulis ke storage/logs/.
if (!Config::isDev()) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = $root . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php.log');
}

// Header keamanan HTTP (defense-in-depth; php -S tidak pakai .htaccess).
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

ensure_session();

function render(string $view, array $data, string $title, string $active): void
{
    $appUser = $GLOBALS['APP_USER'] ?? null;
    $appAdmin = $GLOBALS['APP_ADMIN'] ?? false;
    extract($data, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/app/Views/' . $view . '.php';
    $content = (string) ob_get_clean();
    require dirname(__DIR__) . '/app/Views/layout.php';
}

try {
    $pdo = Connection::get();
} catch (Throwable $e) {
    app_log('DB connect gagal: ' . $e->getMessage());
    http_response_code(500);
    echo 'Database belum siap. Jalankan: php setup.php';
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// --- Auth: halaman publik ---
if ($path === '/login' && $method === 'GET') {
    if (AuthController::current($pdo) !== null) {
        redirect('/');
    }
    render('login', ['error' => null, 'oldUser' => ''], 'Masuk', 'login');
    exit;
}

if ($path === '/login' && $method === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        render('login', ['error' => 'Sesi kedaluwarsa. Coba lagi.', 'oldUser' => (string) ($_POST['username'] ?? '')], 'Masuk', 'login');
        exit;
    }
    $res = AuthController::login($pdo, (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($res['ok']) {
        redirect('/');
    }
    render('login', ['error' => $res['error'], 'oldUser' => (string) ($_POST['username'] ?? '')], 'Masuk', 'login');
    exit;
}

// --- Guard: selain /login wajib login ---
$appUser = AuthController::current($pdo);
if ($appUser === null) {
    redirect('/login');
}
$GLOBALS['APP_USER'] = $appUser;
$GLOBALS['APP_ADMIN'] = AuthController::isAdmin($appUser);

if ($path === '/logout' && $method === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        redirect('/');
    }
    AuthController::logout();
    redirect('/login');
}

function require_admin(): void
{
    if (empty($GLOBALS['APP_ADMIN'])) {
        http_response_code(403);
        echo 'Akses ditolak (admin saja). <a href="/dashboard">Kembali</a>.';
        exit;
    }
}

function validate_new_user(PDO $pdo, array $in): array
{
    $errors = [];
    $username = strtolower(trim((string) ($in['username'] ?? '')));
    $nama = mb_substr(trim((string) ($in['nama'] ?? '')), 0, 100);
    $password = (string) ($in['password'] ?? '');
    $role = (string) ($in['role'] ?? 'operator');
    if (!preg_match('/^[a-z0-9._]{3,30}$/', $username)) {
        $errors[] = 'Username 3–30 karakter (huruf kecil, angka, titik, underscore).';
    } elseif (App\Models\User::findByUsername($pdo, $username) !== null) {
        $errors[] = 'Username sudah dipakai.';
    }
    if ($nama === '') {
        $errors[] = 'Nama wajib diisi.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if (!in_array($role, ['admin', 'operator'], true)) {
        $errors[] = 'Role tidak valid.';
    }
    return [$errors, $username, $nama, $password, $role];
}

// --- Manajemen pengguna (admin) ---
if ($path === '/users' && $method === 'GET') {
    require_admin();
    render('users', ['users' => User::all($pdo), 'me' => (int) $appUser['id'], 'errors' => [], 'old' => []], 'Pengguna', 'users');
    exit;
}

if ($path === '/users' && $method === 'POST') {
    require_admin();
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash('error', 'Sesi kedaluwarsa.');
        redirect('/users');
    }
    [$errors, $username, $nama, $password, $role] = validate_new_user($pdo, $_POST);
    if ($errors !== []) {
        render('users', ['users' => User::all($pdo), 'me' => (int) $appUser['id'], 'errors' => $errors, 'old' => $_POST], 'Pengguna', 'users');
        exit;
    }
    User::create($pdo, $username, $password, $nama, $role);
    flash('success', 'Pengguna ' . $username . ' ditambahkan.');
    redirect('/users');
}

if (preg_match('#^/users/(\d+)/(toggle|password|delete)$#', $path, $m) && $method === 'POST') {
    require_admin();
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash('error', 'Sesi kedaluwarsa.');
        redirect('/users');
    }
    $uid = (int) $m[1];
    if ($uid === (int) $appUser['id']) {
        flash('error', 'Tidak dapat mengubah akun sendiri.');
        redirect('/users');
    }
    $target = User::find($pdo, $uid);
    if ($target === null) {
        http_response_code(404);
        echo 'Pengguna tidak ditemukan.';
        exit;
    }
    if ($m[2] === 'toggle') {
        User::setAktif($pdo, $uid, (int) $target['aktif'] !== 1);
        flash('success', 'Status pengguna diperbarui.');
    } elseif ($m[2] === 'password') {
        $pw = (string) ($_POST['password'] ?? '');
        if (strlen($pw) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
            redirect('/users');
        }
        User::setPassword($pdo, $uid, $pw);
        flash('success', 'Password pengguna direset.');
    } else {
        User::delete($pdo, $uid);
        flash('success', 'Pengguna dihapus.');
    }
    redirect('/users');
}

// --- Routing ---
if ($path === '/' && $method === 'GET') {
    render('form', FormController::show($pdo), 'Form Tanda Terima', 'form');
    exit;
}

if ($path === '/pengiriman' && $method === 'POST') {
    $res = FormController::store($pdo, $_POST);
    if (isset($res['id'])) {
        flash('success', 'Data tersimpan.');
        redirect('/pengiriman/' . $res['id']);
    }
    render('form', FormController::show($pdo, $res['old'], $res['errors']), 'Form Tanda Terima', 'form');
    exit;
}

if ($path === '/dashboard' && $method === 'GET') {
    render('dashboard', DashboardController::index($pdo, $_GET), 'Rekapitulasi', 'dashboard');
    exit;
}

if (preg_match('#^/pengiriman/(\d+)$#', $path, $m) && $method === 'GET') {
    $row = PengirimanController::show($pdo, (int) $m[1]);
    if ($row === null) {
        http_response_code(404);
        echo 'Data tidak ditemukan.';
        exit;
    }
    render('show', ['row' => $row], 'Detail #' . $m[1], 'dashboard');
    exit;
}

if (preg_match('#^/pengiriman/(\d+)/edit$#', $path, $m) && $method === 'GET') {
    $d = PengirimanController::edit($pdo, (int) $m[1]);
    if ($d === null) {
        http_response_code(404);
        echo 'Data tidak ditemukan.';
        exit;
    }
    render('form', [
        'preset' => $d['preset'],
        'old' => [
            'preset_penerima_id' => $d['row']['preset_penerima_id'],
            'nomor_referensi' => $d['row']['nomor_referensi'],
            'nama_penerima' => $d['row']['nama_penerima'],
            'pangkat_golongan' => $d['row']['pangkat_golongan'],
            'jabatan' => $d['row']['jabatan'],
            'tanggal' => $d['row']['tanggal'],
            'pukul' => substr((string) $d['row']['pukul'], 0, 5),
            'telp_hp' => $d['row']['telp_hp'],
        ],
        'errors' => [],
        'isEdit' => true,
        'editId' => (int) $m[1],
        'existingTtd' => $d['row']['tanda_tangan_path'] ?? null,
    ], 'Edit #' . $m[1], 'dashboard');
    exit;
}

if (preg_match('#^/pengiriman/(\d+)/update$#', $path, $m) && $method === 'POST') {
    $id = (int) $m[1];
    if (PengirimanController::show($pdo, $id) === null) {
        http_response_code(404);
        echo 'Data tidak ditemukan.';
        exit;
    }
    $res = FormController::update($pdo, $id, $_POST);
    if (isset($res['id'])) {
        flash('success', 'Perubahan tersimpan.');
        redirect('/pengiriman/' . $id);
    }
    $d = PengirimanController::edit($pdo, $id);
    render('form', [
        'preset' => $d['preset'],
        'old' => $res['old'],
        'errors' => $res['errors'],
        'isEdit' => true,
        'editId' => $id,
        'existingTtd' => $d['row']['tanda_tangan_path'] ?? null,
    ], 'Edit #' . $id, 'dashboard');
    exit;
}

if (preg_match('#^/tandatangan/(ttd_[A-Za-z0-9_\-]+\.png)$#', $path, $m) && $method === 'GET') {
    $full = $root . '/storage/tandatangan/' . $m[1];
    if (!is_file($full)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit;
}

if (preg_match('#^/pengiriman/(\d+)/delete$#', $path, $m) && $method === 'POST') {
    require_admin();
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash('error', 'Sesi kedaluwarsa.');
        redirect('/dashboard');
    }
    try {
        $oldTtd = \App\Models\Pengiriman::destroy($pdo, (int) $m[1]);
        TandaTangan::delete($oldTtd); // file dihapus hanya bila DB sukses
        flash('success', 'Data #' . $m[1] . ' dihapus.');
    } catch (\Throwable $e) {
        app_log('Delete gagal id=' . $m[1] . ': ' . $e->getMessage());
        flash('error', 'Data gagal dihapus. Silakan coba kembali.');
    }
    redirect('/dashboard');
}

if (preg_match('#^/pengiriman/(\d+)/pdf$#', $path, $m) && $method === 'GET') {
    try {
        PengirimanController::pdf($pdo, (int) $m[1]); // exit di dalam
    } catch (Throwable $e) {
        // Data DB tidak hilang bila PDF gagal; user bisa generate ulang dari dashboard.
        app_log('PDF gagal id=' . $m[1] . ': ' . $e->getMessage());
        flash('error', 'PDF gagal dibuat: ' . $e->getMessage() . ' Data tetap tersimpan, silakan coba lagi dari dashboard.');
        redirect('/dashboard');
    }
    exit;
}

http_response_code(404);
echo 'Halaman tidak ditemukan. <a href="/">Kembali ke form</a>.';
