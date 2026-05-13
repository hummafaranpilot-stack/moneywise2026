<?php
/**
 * ============================================================================
 * Money Wise 2026 — Tracker Log Endpoint
 * Receives JSON from tracker.js, enriches with IP intelligence, scores risk,
 * and stores in MySQL.
 * ============================================================================
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// ---------------- Parse incoming JSON ----------------
$raw = file_get_contents('php://input');
if (!$raw) {
    http_response_code(400);
    echo json_encode(['error' => 'empty body']);
    exit;
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid json']);
    exit;
}

// ---------------- Exclude self-tracking ----------------
$pagePath = parse_url($data['page_url'] ?? '', PHP_URL_PATH);
if (shouldExclude($pagePath)) {
    echo json_encode(['status' => 'excluded']);
    exit;
}

// ---------------- Required fields ----------------
$visitorId = isset($data['visitor_id']) ? substr((string)$data['visitor_id'], 0, 64) : null;
$sessionId = isset($data['session_id']) ? substr((string)$data['session_id'], 0, 64) : null;
if (!$visitorId || !$sessionId) {
    http_response_code(400);
    echo json_encode(['error' => 'missing visitor_id or session_id']);
    exit;
}

// ---------------- Get real IP ----------------
$ip = getRealIP();

// ---------------- Rate limit ----------------
if (!checkApiRateLimit($ip)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate limit exceeded']);
    exit;
}

// ---------------- Heartbeat update? (existing session) ----------------
// If a row already exists for this session_id, UPDATE it instead of inserting
$existing = db()->prepare("SELECT id FROM visitors WHERE session_id = ? LIMIT 1");
$existing->execute([$sessionId]);
$existingRow = $existing->fetch();

// ---------------- IP intelligence ----------------
$ipInfo = getIPInfo($ip);
$proxyInfo = getProxyInfo($ip);

// ---------------- Parse UA server-side as a backup ----------------
$ua = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
$uaParts = parseUserAgent($ua);

// ---------------- UTM + referrer ----------------
$referrer = $data['referrer'] ?? '';
$utm = extractUTM($data['page_url'] ?? '');
$trafficSource = classifyTrafficSource($referrer, $utm['utm_source'] ?? null);
$referrerDomain = $referrer ? (parse_url($referrer, PHP_URL_HOST) ?: null) : null;

// ---------------- Risk score ----------------
$risk = calculateRiskScore($data, $ipInfo, $proxyInfo, $ip);

// ---------------- Extract proxy data for current IP ----------------
$proxyData = $proxyInfo[$ip] ?? [];
$isProxy      = !empty($proxyData['proxy']) && $proxyData['proxy'] === 'yes' ? 1 : 0;
$proxyType    = $proxyData['type'] ?? null;
$isVpn        = ($proxyType && strtoupper($proxyType) === 'VPN') ? 1 : 0;
$isTor        = ($proxyType && strtoupper($proxyType) === 'TOR') ? 1 : 0;
$isDatacenter = ($proxyType && in_array(strtoupper($proxyType), ['BUSINESS','HOSTING','DATACENTER'])) ? 1 : 0;
$proxyRisk    = isset($proxyData['risk']) ? (int)$proxyData['risk'] : 0;

// ---------------- Build full data JSON for archival ----------------
$fullData = json_encode([
    'input'     => $data,
    'ipInfo'    => $ipInfo,
    'proxyInfo' => $proxyInfo,
    'server'    => [
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'accept_lang' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

try {
    if ($existingRow) {
        // ---------------- UPDATE existing session (heartbeat) ----------------
        $stmt = db()->prepare("
            UPDATE visitors SET
                last_seen          = NOW(),
                session_duration   = GREATEST(session_duration, ?),
                scroll_depth_max   = GREATEST(scroll_depth_max, ?),
                mouse_movements    = GREATEST(mouse_movements, ?),
                clicks_count       = GREATEST(clicks_count, ?),
                keystrokes_count   = GREATEST(keystrokes_count, ?),
                tab_switches       = GREATEST(tab_switches, ?),
                pages_viewed       = GREATEST(pages_viewed, ?),
                risk_score         = ?,
                risk_level         = ?,
                risk_flags         = ?,
                full_data          = ?
            WHERE session_id = ?
        ");
        $stmt->execute([
            (int)($data['session_duration'] ?? 0),
            (int)($data['scroll_depth_max'] ?? 0),
            (int)($data['mouse_movements'] ?? 0),
            (int)($data['clicks_count'] ?? 0),
            (int)($data['keystrokes_count'] ?? 0),
            (int)($data['tab_switches'] ?? 0),
            (int)($data['pages_viewed'] ?? 1),
            $risk['score'],
            $risk['level'],
            json_encode($risk['flags']),
            $fullData,
            $sessionId,
        ]);
        echo json_encode([
            'status' => 'updated',
            'risk_score' => $risk['score'],
            'risk_level' => $risk['level'],
        ]);
        exit;
    }

    // ---------------- INSERT new visitor ----------------
    $stmt = db()->prepare("
        INSERT INTO visitors (
            visitor_id, session_id, ip_address, ip_type,
            country, country_code, continent, region, city,
            isp, asn, as_name, as_domain, org,
            is_proxy, is_vpn, is_tor, is_datacenter, proxy_type, proxy_risk_score,
            user_agent, browser_name, browser_version, os_name, os_version, device_type,
            languages, language_primary, timezone, timezone_offset,
            screen_width, screen_height, screen_avail_width, screen_avail_height,
            screen_color_depth, pixel_ratio, viewport_width, viewport_height,
            cpu_cores, device_memory, touch_support, max_touch_points,
            battery_level, battery_charging,
            webgl_renderer, webgl_vendor, webgl_version,
            canvas_fingerprint, audio_fingerprint, fonts_count, fonts_list, plugins_count,
            cookies_enabled, local_storage_enabled, session_storage_enabled, indexed_db_enabled,
            do_not_track, is_incognito, is_bot, is_webdriver,
            connection_type, effective_type, downlink, rtt, save_data,
            referrer, referrer_domain, traffic_source, landing_page,
            utm_source, utm_medium, utm_campaign, utm_content, utm_term,
            page_url, page_title, page_path,
            session_duration, scroll_depth_max, mouse_movements, clicks_count,
            keystrokes_count, tab_switches, pages_viewed,
            risk_score, risk_level, risk_flags, full_data
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?
        )
    ");
    $stmt->execute([
        $visitorId, $sessionId, $ip, $ipInfo['type'] ?? null,
        $ipInfo['country'] ?? null,
        $ipInfo['country_code'] ?? null,
        $ipInfo['continent'] ?? null,
        $ipInfo['region'] ?? null,
        $ipInfo['city'] ?? null,
        $ipInfo['as_name'] ?? ($ipInfo['org'] ?? null),
        $ipInfo['asn'] ?? null,
        $ipInfo['as_name'] ?? null,
        $ipInfo['as_domain'] ?? null,
        $ipInfo['org'] ?? null,
        $isProxy, $isVpn, $isTor, $isDatacenter, $proxyType, $proxyRisk,
        $ua,
        $data['browser_name'] ?? $uaParts['browser_name'],
        $data['browser_version'] ?? $uaParts['browser_version'],
        $data['os_name'] ?? $uaParts['os_name'],
        $data['os_version'] ?? $uaParts['os_version'],
        $data['device_type'] ?? $uaParts['device_type'],
        json_encode($data['languages'] ?? []),
        $data['language_primary'] ?? null,
        $data['timezone'] ?? null,
        isset($data['timezone_offset']) ? (int)$data['timezone_offset'] : null,
        (int)($data['screen_width'] ?? 0),
        (int)($data['screen_height'] ?? 0),
        (int)($data['screen_avail_width'] ?? 0),
        (int)($data['screen_avail_height'] ?? 0),
        (int)($data['screen_color_depth'] ?? 0),
        (float)($data['pixel_ratio'] ?? 1),
        (int)($data['viewport_width'] ?? 0),
        (int)($data['viewport_height'] ?? 0),
        (int)($data['cpu_cores'] ?? 0),
        (float)($data['device_memory'] ?? 0),
        !empty($data['touch_support']) ? 1 : 0,
        (int)($data['max_touch_points'] ?? 0),
        isset($data['battery_level']) ? (float)$data['battery_level'] : null,
        isset($data['battery_charging']) ? (int)(bool)$data['battery_charging'] : null,
        $data['webgl_renderer'] ?? null,
        $data['webgl_vendor'] ?? null,
        $data['webgl_version'] ?? null,
        $data['canvas_fingerprint'] ?? null,
        $data['audio_fingerprint'] ?? null,
        (int)($data['fonts_count'] ?? 0),
        json_encode($data['fonts_list'] ?? []),
        (int)($data['plugins_count'] ?? 0),
        !empty($data['cookies_enabled']) ? 1 : 0,
        !empty($data['local_storage_enabled']) ? 1 : 0,
        !empty($data['session_storage_enabled']) ? 1 : 0,
        !empty($data['indexed_db_enabled']) ? 1 : 0,
        $data['do_not_track'] ?? null,
        !empty($data['is_incognito']) ? 1 : 0,
        !empty($data['is_bot']) ? 1 : 0,
        !empty($data['is_webdriver']) ? 1 : 0,
        $data['connection_type'] ?? null,
        $data['effective_type'] ?? null,
        isset($data['downlink']) ? (float)$data['downlink'] : null,
        isset($data['rtt']) ? (int)$data['rtt'] : null,
        !empty($data['save_data']) ? 1 : 0,
        $referrer,
        $referrerDomain,
        $trafficSource,
        $data['landing_page'] ?? ($data['page_url'] ?? null),
        $utm['utm_source'] ?? null,
        $utm['utm_medium'] ?? null,
        $utm['utm_campaign'] ?? null,
        $utm['utm_content'] ?? null,
        $utm['utm_term'] ?? null,
        $data['page_url'] ?? null,
        $data['page_title'] ?? null,
        $pagePath,
        (int)($data['session_duration'] ?? 0),
        (int)($data['scroll_depth_max'] ?? 0),
        (int)($data['mouse_movements'] ?? 0),
        (int)($data['clicks_count'] ?? 0),
        (int)($data['keystrokes_count'] ?? 0),
        (int)($data['tab_switches'] ?? 0),
        (int)($data['pages_viewed'] ?? 1),
        $risk['score'],
        $risk['level'],
        json_encode($risk['flags']),
        $fullData,
    ]);

    echo json_encode([
        'status'     => 'logged',
        'risk_score' => $risk['score'],
        'risk_level' => $risk['level'],
        'flags'      => $risk['flags'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log('Tracker insert failed: ' . $e->getMessage());
    echo json_encode(['error' => 'storage_failed']);
}
