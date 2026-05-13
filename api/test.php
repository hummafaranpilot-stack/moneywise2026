<?php
/**
 * ============================================================================
 * Money Wise 2026 — Diagnostic Test Page
 * Visit this to identify what's failing. DELETE after debugging.
 * URL: https://moneywise2026.com/api/test.php
 * ============================================================================
 */

// Show ALL errors for diagnosis
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Money Wise 2026 — Diagnostic Test ===\n\n";

// 1. PHP Version
echo "[1] PHP Version: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '7.3.0', '<')) {
    echo "    ❌ PHP 7.3+ required. Upgrade in Hostinger hPanel → PHP Configuration.\n";
} else {
    echo "    ✓ OK\n";
}

// 2. Required extensions
echo "\n[2] Required PHP Extensions:\n";
$required = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
foreach ($required as $ext) {
    $ok = extension_loaded($ext);
    echo "    " . ($ok ? "✓" : "❌") . " $ext: " . ($ok ? "loaded" : "MISSING") . "\n";
}

// 3. Required functions
echo "\n[3] Required Functions:\n";
$funcs = ['random_bytes', 'mb_chr', 'mb_strimwidth', 'session_set_cookie_params', 'file_get_contents', 'json_encode'];
foreach ($funcs as $f) {
    echo "    " . (function_exists($f) ? "✓" : "❌") . " $f()\n";
}

// 4. allow_url_fopen (for ipinfo + proxycheck calls)
echo "\n[4] allow_url_fopen: " . (ini_get('allow_url_fopen') ? "✓ enabled" : "❌ DISABLED — external API calls won't work") . "\n";

// 5. Config file
echo "\n[5] Config file check:\n";
$configPath = __DIR__ . '/config.php';
echo "    Path: $configPath\n";
echo "    Exists: " . (file_exists($configPath) ? "✓" : "❌") . "\n";
echo "    Readable: " . (is_readable($configPath) ? "✓" : "❌") . "\n";

if (file_exists($configPath)) {
    try {
        require_once $configPath;
        echo "    Loaded: ✓\n";
        echo "    DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'UNDEFINED') . "\n";
        echo "    DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'UNDEFINED') . "\n";
        echo "    DB_USER: " . (defined('DB_USER') ? DB_USER : 'UNDEFINED') . "\n";
        echo "    DB_PASS: " . (defined('DB_PASS') ? '[set, ' . strlen(DB_PASS) . ' chars]' : 'UNDEFINED') . "\n";
        echo "    IPINFO_TOKEN: " . (defined('IPINFO_TOKEN') ? '[set]' : 'UNDEFINED') . "\n";
        echo "    DASHBOARD_PASSWORD: " . (defined('DASHBOARD_PASSWORD') ? '[set]' : 'UNDEFINED') . "\n";
    } catch (Throwable $e) {
        echo "    ❌ Failed to load: " . $e->getMessage() . "\n";
    }
}

// 6. Database connection
echo "\n[6] Database connection:\n";
if (defined('DB_HOST') && defined('DB_NAME')) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "    ✓ Connected to MySQL\n";

        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "    Tables found: " . count($tables) . "\n";
        $required_tables = ['visitors', 'behavior_events', 'login_attempts'];
        foreach ($required_tables as $t) {
            echo "      " . (in_array($t, $tables) ? "✓" : "❌") . " $t\n";
        }

        // Count rows
        if (in_array('visitors', $tables)) {
            $count = $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
            echo "    Visitor rows: $count\n";
        }
    } catch (PDOException $e) {
        echo "    ❌ Connection FAILED: " . $e->getMessage() . "\n";
        echo "    Check Hostinger MySQL credentials in api/config.php\n";
    }
} else {
    echo "    ❌ Cannot test — config not loaded\n";
}

// 7. helpers.php load test
echo "\n[7] helpers.php check:\n";
$helpersPath = __DIR__ . '/helpers.php';
echo "    Exists: " . (file_exists($helpersPath) ? "✓" : "❌") . "\n";
if (file_exists($helpersPath)) {
    try {
        require_once $helpersPath;
        echo "    Loaded: ✓\n";
        echo "    getRealIP(): " . getRealIP() . "\n";
    } catch (Throwable $e) {
        echo "    ❌ Failed: " . $e->getMessage() . "\n";
    }
}

// 8. db.php load test
echo "\n[8] db.php check:\n";
$dbPath = __DIR__ . '/db.php';
echo "    Exists: " . (file_exists($dbPath) ? "✓" : "❌") . "\n";
if (file_exists($dbPath)) {
    try {
        require_once $dbPath;
        $pdo2 = db();
        echo "    db() function: ✓\n";
    } catch (Throwable $e) {
        echo "    ❌ Failed: " . $e->getMessage() . "\n";
    }
}

// 9. ipinfo.io API test
echo "\n[9] ipinfo.io Lite API test:\n";
if (defined('IPINFO_TOKEN') && function_exists('curl_init')) {
    $ch = curl_init('https://api.ipinfo.io/lite/8.8.8.8');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . IPINFO_TOKEN]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "    HTTP $code\n";
    echo "    Response: " . substr($resp, 0, 200) . "\n";
} else {
    echo "    Skipped (token undefined or curl missing)\n";
}

// 10. proxycheck.io API test
echo "\n[10] proxycheck.io API test:\n";
if (defined('PROXYCHECK_KEY') && function_exists('curl_init')) {
    $ch = curl_init('https://proxycheck.io/v2/8.8.8.8?key=' . PROXYCHECK_KEY . '&vpn=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "    HTTP $code\n";
    echo "    Response: " . substr($resp, 0, 200) . "\n";
} else {
    echo "    Skipped (key undefined or curl missing)\n";
}

echo "\n=== End of Diagnostic ===\n";
echo "\n⚠️  DELETE this file (api/test.php) after debugging — it exposes diagnostic info.\n";
