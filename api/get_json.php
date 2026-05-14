<?php
/**
 * ============================================================================
 * Money Wise 2026 — Visitor JSON Export Endpoint
 * Returns visitor records as JSON for the dashboard "Copy JSON" feature.
 * Authentication: requires active dashboard session.
 * Modes:
 *   ?ids=1,2,3      → fetch specific visitor IDs
 *   ?all=1          → fetch ALL visitors in DB (use with caution)
 *   (default)       → 400 Missing params
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'domain'   => '',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if (empty($_SESSION['mw_auth'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    if (isset($_GET['all']) && $_GET['all'] === '1') {
        // Hard cap to prevent runaway memory; adjust if needed.
        $stmt = db()->query("SELECT * FROM visitors ORDER BY visit_time DESC LIMIT 5000");
        $visitors = $stmt->fetchAll();
    } elseif (isset($_GET['ids'])) {
        $ids = array_filter(array_map('intval', explode(',', (string)$_GET['ids'])));
        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid IDs supplied']);
            exit;
        }
        // Cap the number of IDs to prevent abuse
        $ids = array_slice($ids, 0, 1000);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT * FROM visitors WHERE id IN ($placeholders) ORDER BY visit_time DESC");
        $stmt->execute($ids);
        $visitors = $stmt->fetchAll();
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing params (use ?ids=1,2,3 or ?all=1)']);
        exit;
    }

    // Decode JSON-encoded fields into nested objects for easier reading
    $jsonFields = [
        'webgl_extensions','fonts_detected','fonts_list','plugins_list','mime_types_list',
        'permissions_state','speech_voices_list','media_devices_list','webrtc_ips',
        'codec_support','feature_support','behavior_full_data','risk_flags','languages',
        'localstorage_keys','sessionstorage_keys','full_data',
    ];

    foreach ($visitors as &$v) {
        foreach ($jsonFields as $f) {
            if (!empty($v[$f]) && is_string($v[$f])) {
                $decoded = json_decode($v[$f], true);
                if ($decoded !== null) $v[$f] = $decoded;
            }
        }
    }
    unset($v);

    echo json_encode($visitors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log('get_json failed: ' . $e->getMessage());
    echo json_encode(['error' => 'query_failed', 'message' => $e->getMessage()]);
}
