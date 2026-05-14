<?php
/**
 * ============================================================================
 * Money Wise 2026 — Ad Click Logger
 * Receives ad click events from tracker, validates, stores in ad_clicks.
 * ============================================================================
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$raw = file_get_contents('php://input');
if (!$raw) { http_response_code(400); echo json_encode(['error' => 'empty body']); exit; }
$input = json_decode($raw, true);
if (!is_array($input) || empty($input['ad_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid data — ad_id required']);
    exit;
}

$ip = getRealIP();

try {
    $stmt = db()->prepare(
        "INSERT INTO ad_clicks
            (visitor_id, session_id, ad_id, ad_format, ad_position,
             click_x, click_y, time_to_click, target_url, page_url, user_agent, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        substr((string)($input['visitor_id'] ?? ''), 0, 64) ?: null,
        substr((string)($input['session_id'] ?? ''), 0, 64) ?: null,
        substr((string)$input['ad_id'], 0, 20),
        substr((string)($input['ad_format']   ?? ''), 0, 50) ?: null,
        substr((string)($input['ad_position'] ?? ''), 0, 50) ?: null,
        isset($input['click_x']) ? (int)$input['click_x'] : null,
        isset($input['click_y']) ? (int)$input['click_y'] : null,
        isset($input['time_to_click']) ? (int)$input['time_to_click'] : null,
        substr((string)($input['target_url'] ?? ''), 0, 500) ?: null,
        substr((string)($input['page_url']   ?? ''), 0, 500) ?: null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $ip,
    ]);
    echo json_encode(['status' => 'ok', 'id' => (int)db()->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('click_log failed: ' . $e->getMessage());
    echo json_encode(['error' => 'storage_failed', 'message' => $e->getMessage()]);
}
