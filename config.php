<?php
function envValue($key, $default = null)
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

define('APP_ENV', envValue('APP_ENV', 'development'));
define('APP_DEBUG', envValue('APP_DEBUG', '1') === '1');
define('APP_VERSION', envValue('APP_VERSION', '1.0.0'));
define('HLS_BASE_URL', rtrim((string) envValue('HLS_BASE_URL', 'http://localhost:8080/hls'), '/'));

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('DB_HOST', envValue('DB_HOST', ''));
define('DB_PORT', envValue('DB_PORT', '5432'));
define('DB_USER', envValue('DB_USER', 'postgres'));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_NAME', envValue('DB_NAME', 'postgres'));

// Prefer configured URL in production; fallback to runtime detection.
$configuredBaseUrl = rtrim((string) envValue('APP_URL', ''), '/');
if ($configuredBaseUrl !== '') {
    define('BASE_URL', $configuredBaseUrl);
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if ($dir === '\\' || $dir === '/') {
        $dir = '';
    }
    $dir = preg_replace('/(\/admin|\/user)$/', '', $dir);

    define('BASE_URL', rtrim($protocol . $host . $dir, '/'));
}

function sendSecurityHeaders()
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

sendSecurityHeaders();

try {
    $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=require';
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit(APP_DEBUG ? 'Database connection failed: ' . e($e->getMessage()) : 'Service is temporarily unavailable.');
}

function e($string)
{
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

function table_exists($tableName)
{
    global $pdo;
    static $cache = [];

    $key = (string) $tableName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("SELECT to_regclass(?) IS NOT NULL");
        $stmt->execute(['public.' . $key]);
        $cache[$key] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

function column_exists($tableName, $columnName)
{
    global $pdo;
    static $cache = [];

    $key = (string) $tableName . '.' . (string) $columnName;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1'
        );
        $stmt->execute(['public', (string) $tableName, (string) $columnName]);
        $cache[$key] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

// Single-device enforcement: validate session token on every request
function validate_user_session()
{
    global $pdo;
    if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
        return;
    }

    if (!column_exists('users', 'session_token')) {
        return;
    }

    $stmt = $pdo->prepare('SELECT session_token FROM users WHERE id = ?');
    try {
        $stmt->execute([(int) $_SESSION['user_id']]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return;
    }

    if (!$row || !hash_equals((string) $row['session_token'], (string) $_SESSION['session_token'])) {
        $_SESSION = [];
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?kicked=1');
        exit;
    }
}

function redirect($url)
{
    $target = BASE_URL . $url;
    header('Location: ' . $target);
    exit;
}

function asset_url($relativePath)
{
    $relativePath = '/' . ltrim((string) $relativePath, '/');
    $absolutePath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $version = file_exists($absolutePath) ? (string) filemtime($absolutePath) : APP_VERSION;
    return BASE_URL . $relativePath . '?v=' . rawurlencode($version);
}

function currentClientIp()
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logAction($action, $details = '')
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO logs (action, details, ip_address) VALUES (?, ?, ?)');
    $stmt->execute([$action, $details, currentClientIp()]);
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_fail()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Request expired or invalid token.');
    }
}

function require_post()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
}

function throttle_attempts($key, $maxAttempts = 5, $windowSeconds = 300)
{
    if (!isset($_SESSION['throttle']) || !is_array($_SESSION['throttle'])) {
        $_SESSION['throttle'] = [];
    }

    $bucket = $_SESSION['throttle'][$key] ?? ['count' => 0, 'first' => time()];
    $now = time();

    if (($now - (int) $bucket['first']) > $windowSeconds) {
        $bucket = ['count' => 0, 'first' => $now];
    }

    if ((int) $bucket['count'] >= $maxAttempts) {
        return false;
    }

    $bucket['count']++;
    $_SESSION['throttle'][$key] = $bucket;
    return true;
}

function clear_throttle($key)
{
    if (isset($_SESSION['throttle'][$key])) {
        unset($_SESSION['throttle'][$key]);
    }
}
