<?php
/**
 * ============================================================================
 * Money Wise 2026 — Tracker Log Endpoint v2
 * Receives 150+ field JSON from tracker.js, enriches with IP intelligence,
 * scores risk, and stores dynamically (handles missing columns gracefully).
 * ============================================================================
 */

declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// ---------------- Parse body ----------------
$raw = file_get_contents('php://input');
if (!$raw) { http_response_code(400); echo json_encode(['error'=>'empty body']); exit; }
$input = json_decode($raw, true);
if (!is_array($input)) { http_response_code(400); echo json_encode(['error'=>'invalid json']); exit; }

// ---------------- Self-track exclusion ----------------
$pagePath = parse_url($input['page_url'] ?? ($input['page_pathname'] ?? ''), PHP_URL_PATH) ?: ($input['page_pathname'] ?? '');
if (shouldExclude($pagePath)) { echo json_encode(['status'=>'excluded']); exit; }

// ---------------- Required IDs ----------------
$visitorId = isset($input['visitor_id']) ? substr((string)$input['visitor_id'], 0, 64) : null;
$sessionId = isset($input['session_id']) ? substr((string)$input['session_id'], 0, 64) : null;
if (!$visitorId || !$sessionId) { http_response_code(400); echo json_encode(['error'=>'missing visitor_id/session_id']); exit; }

// ---------------- Get IP + intelligence ----------------
$ip = getRealIP();
if (!checkApiRateLimit($ip)) { http_response_code(429); echo json_encode(['error'=>'rate limit exceeded']); exit; }

$ipInfo    = getIPInfo($ip);
$proxyInfo = getProxyInfo($ip);

// ---------------- Server-side UA fallback ----------------
$ua = $input['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
$uaParts = parseUserAgent($ua);

// ---------------- Referrer + traffic source + UTM ----------------
$referrer = $input['referrer'] ?? ($input['page_referrer'] ?? '');
$utm = extractUTM($input['page_url'] ?? '');
$trafficSource = $input['traffic_source'] ?? classifyTrafficSource($referrer, $input['utm_source'] ?? ($utm['utm_source'] ?? null));
$referrerDomain = $referrer ? (parse_url($referrer, PHP_URL_HOST) ?: null) : null;

// ---------------- Risk score (v2) ----------------
$risk = calculateRiskScoreV2($input, $ipInfo, $proxyInfo, $ip);

// ---------------- Proxy data extraction ----------------
$proxyData    = $proxyInfo[$ip] ?? [];
$isProxy      = !empty($proxyData['proxy']) && $proxyData['proxy'] === 'yes' ? 1 : 0;
$proxyType    = $proxyData['type'] ?? null;
$isVpn        = ($proxyType && strtoupper($proxyType) === 'VPN') ? 1 : 0;
$isTor        = ($proxyType && strtoupper($proxyType) === 'TOR') ? 1 : 0;
$isDatacenter = ($proxyType && in_array(strtoupper($proxyType), ['BUSINESS','HOSTING','DATACENTER'])) ? 1 : 0;
if (!$isDatacenter && isDatacenter($ipInfo['as_name'] ?? ($ipInfo['org'] ?? ''))) $isDatacenter = 1;
$proxyRisk    = isset($proxyData['risk']) ? (int)$proxyData['risk'] : 0;

// ---------------- Helper to JSON-encode arrays / leave scalars alone ----------------
$j = function ($v) { return is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $v; };
$bool = function ($v) { return $v ? 1 : 0; };

// ---------------- Build full $data array (will be filtered by DB columns) ----------------
$data = [
    // IDs + IP
    'visitor_id'                 => $visitorId,
    'session_id'                 => $sessionId,
    'ip_address'                 => $ip,
    'ip_type'                    => $ipInfo['type'] ?? null,
    'country'                    => $ipInfo['country'] ?? null,
    'country_code'               => $ipInfo['country_code'] ?? null,
    'continent'                  => $ipInfo['continent'] ?? null,
    'region'                     => $ipInfo['region'] ?? null,
    'city'                       => $ipInfo['city'] ?? null,
    'isp'                        => $ipInfo['as_name'] ?? ($ipInfo['org'] ?? null),
    'asn'                        => $ipInfo['asn'] ?? null,
    'as_name'                    => $ipInfo['as_name'] ?? null,
    'as_domain'                  => $ipInfo['as_domain'] ?? null,
    'org'                        => $ipInfo['org'] ?? null,
    'is_proxy'                   => $isProxy,
    'is_vpn'                     => $isVpn,
    'is_tor'                     => $isTor,
    'is_datacenter'              => $isDatacenter,
    'proxy_type'                 => $proxyType,
    'proxy_risk_score'           => $proxyRisk,

    // Browser
    'user_agent'                 => $ua,
    'app_name'                   => $input['app_name'] ?? null,
    'app_version'                => $input['app_version'] ?? null,
    'product'                    => $input['product'] ?? null,
    'product_sub'                => $input['product_sub'] ?? null,
    'vendor'                     => $input['vendor'] ?? null,
    'vendor_sub'                 => $input['vendor_sub'] ?? null,
    'platform'                   => $input['platform'] ?? null,
    'browser_name'               => $input['browser_name'] ?? $uaParts['browser_name'],
    'browser_version'            => $input['browser_version'] ?? $uaParts['browser_version'],
    'os_name'                    => $input['os_name'] ?? $uaParts['os_name'],
    'os_version'                 => $input['os_version'] ?? $uaParts['os_version'],
    'device_type'                => $input['device_type'] ?? $uaParts['device_type'],
    'language'                   => $input['language'] ?? null,
    'language_primary'           => $input['language'] ?? null,
    'languages'                  => $j($input['languages'] ?? []),
    'online'                     => $bool($input['online'] ?? 1),
    'cookie_enabled'             => $bool($input['cookie_enabled'] ?? 0),
    'do_not_track'               => $input['do_not_track'] ?? null,
    'webdriver'                  => $bool($input['webdriver'] ?? 0),
    'is_webdriver'               => $bool($input['webdriver'] ?? 0),
    'pdf_viewer_enabled'         => $bool($input['pdf_viewer_enabled'] ?? 0),
    'java_enabled'               => $bool($input['java_enabled'] ?? 0),
    'max_touch_points'           => (int)($input['max_touch_points'] ?? 0),
    'touch_support'              => $bool($input['touch_support'] ?? ($input['max_touch_points'] ?? 0) > 0),
    'is_brave'                   => $bool($input['is_brave'] ?? 0),

    // Screen
    'screen_width'               => (int)($input['screen_width'] ?? 0),
    'screen_height'              => (int)($input['screen_height'] ?? 0),
    'screen_avail_width'         => (int)($input['screen_avail_width'] ?? 0),
    'screen_avail_height'        => (int)($input['screen_avail_height'] ?? 0),
    'screen_color_depth'         => (int)($input['screen_color_depth'] ?? 0),
    'screen_pixel_depth'         => (int)($input['screen_pixel_depth'] ?? 0),
    'window_inner_width'         => (int)($input['window_inner_width'] ?? 0),
    'window_inner_height'        => (int)($input['window_inner_height'] ?? 0),
    'window_outer_width'         => (int)($input['window_outer_width'] ?? 0),
    'window_outer_height'        => (int)($input['window_outer_height'] ?? 0),
    'pixel_ratio'                => (float)($input['pixel_ratio'] ?? $input['device_pixel_ratio'] ?? 1),
    'device_pixel_ratio'         => (float)($input['device_pixel_ratio'] ?? $input['pixel_ratio'] ?? 1),
    'viewport_width'             => (int)($input['viewport_width'] ?? $input['window_inner_width'] ?? 0),
    'viewport_height'            => (int)($input['viewport_height'] ?? $input['window_inner_height'] ?? 0),
    'screen_orientation_type'    => $input['screen_orientation_type'] ?? null,
    'screen_orientation_angle'   => isset($input['screen_orientation_angle']) ? (int)$input['screen_orientation_angle'] : null,
    'prefers_color_scheme'       => $input['prefers_color_scheme'] ?? null,
    'prefers_reduced_motion'     => $bool($input['prefers_reduced_motion'] ?? 0),
    'color_gamut_p3'             => $bool($input['color_gamut_p3'] ?? 0),
    'color_gamut_srgb'           => $bool($input['color_gamut_srgb'] ?? 0),

    // Hardware
    'hardware_concurrency'       => (int)($input['hardware_concurrency'] ?? $input['cpu_cores'] ?? 0),
    'cpu_cores'                  => (int)($input['cpu_cores'] ?? $input['hardware_concurrency'] ?? 0),
    'device_memory'              => isset($input['device_memory']) ? (float)$input['device_memory'] : null,
    'battery_level'              => isset($input['battery_level']) ? (float)$input['battery_level'] : null,
    'battery_charging'           => isset($input['battery_charging']) ? $bool($input['battery_charging']) : null,
    'battery_charging_time'      => isset($input['battery_charging_time']) ? (int)$input['battery_charging_time'] : null,
    'battery_discharging_time'   => isset($input['battery_discharging_time']) ? (int)$input['battery_discharging_time'] : null,

    // Timezone
    'timezone'                   => $input['timezone'] ?? null,
    'timezone_offset'            => isset($input['timezone_offset']) ? (int)$input['timezone_offset'] : null,
    'timezone_offset_hours'      => isset($input['timezone_offset_hours']) ? (float)$input['timezone_offset_hours'] : null,
    'locale'                     => $input['locale'] ?? null,
    'calendar'                   => $input['calendar'] ?? null,
    'numbering_system'           => $input['numbering_system'] ?? null,

    // WebGL
    'webgl_supported'            => $bool($input['webgl_supported'] ?? !empty($input['webgl_renderer'])),
    'webgl_vendor'               => $input['webgl_vendor'] ?? null,
    'webgl_renderer'             => $input['webgl_renderer'] ?? null,
    'webgl_unmasked_vendor'      => $input['webgl_unmasked_vendor'] ?? null,
    'webgl_unmasked_renderer'    => $input['webgl_unmasked_renderer'] ?? null,
    'webgl_version'              => $input['webgl_version'] ?? null,
    'webgl_shading_language'     => $input['webgl_shading_language'] ?? null,
    'webgl_extensions'           => $j($input['webgl_extensions'] ?? null),
    'webgl_max_texture_size'     => isset($input['webgl_max_texture_size']) ? (int)$input['webgl_max_texture_size'] : null,
    'webgl_max_renderbuffer_size'=> isset($input['webgl_max_renderbuffer_size']) ? (int)$input['webgl_max_renderbuffer_size'] : null,

    // Canvas + Audio
    'canvas_supported'           => $bool($input['canvas_supported'] ?? !empty($input['canvas_hash'] ?? $input['canvas_fingerprint'] ?? null)),
    'canvas_hash'                => $input['canvas_hash'] ?? ($input['canvas_fingerprint'] ?? null),
    'canvas_fingerprint'         => $input['canvas_fingerprint'] ?? ($input['canvas_hash'] ?? null),
    'audio_supported'            => $bool($input['audio_supported'] ?? !empty($input['audio_fingerprint'])),
    'audio_fingerprint'          => $input['audio_fingerprint'] ?? null,
    'audio_sample_rate'          => isset($input['audio_sample_rate']) ? (int)$input['audio_sample_rate'] : null,

    // Fonts
    'fonts_count'                => (int)($input['fonts_count'] ?? 0),
    'fonts_list'                 => $j($input['fonts_list'] ?? $input['fonts_detected'] ?? []),
    'fonts_detected'             => $j($input['fonts_detected'] ?? $input['fonts_list'] ?? []),

    // Plugins
    'plugins_list'               => $j($input['plugins_list'] ?? []),
    'plugins_count'              => (int)($input['plugins_count'] ?? 0),
    'mime_types_list'            => $j($input['mime_types_list'] ?? []),
    'mime_types_count'           => (int)($input['mime_types_count'] ?? 0),

    // Storage
    'cookies_enabled'            => $bool($input['cookies_enabled'] ?? $input['cookie_enabled'] ?? 0),
    'cookies_string'             => $input['cookies_string'] ?? null,
    'cookies_count'              => (int)($input['cookies_count'] ?? 0),
    'localstorage_supported'     => $bool($input['localstorage_supported'] ?? $input['local_storage_enabled'] ?? 0),
    'local_storage_enabled'      => $bool($input['local_storage_enabled'] ?? $input['localstorage_supported'] ?? 0),
    'localstorage_keys'          => $j($input['localstorage_keys'] ?? []),
    'localstorage_size'          => (int)($input['localstorage_size'] ?? 0),
    'sessionstorage_supported'   => $bool($input['sessionstorage_supported'] ?? $input['session_storage_enabled'] ?? 0),
    'session_storage_enabled'    => $bool($input['session_storage_enabled'] ?? $input['sessionstorage_supported'] ?? 0),
    'sessionstorage_keys'        => $j($input['sessionstorage_keys'] ?? []),
    'indexeddb_supported'        => $bool($input['indexeddb_supported'] ?? $input['indexed_db_enabled'] ?? 0),
    'indexed_db_enabled'         => $bool($input['indexed_db_enabled'] ?? $input['indexeddb_supported'] ?? 0),
    'service_worker_supported'   => $bool($input['service_worker_supported'] ?? 0),
    'cache_supported'            => $bool($input['cache_supported'] ?? 0),
    'storage_quota'              => isset($input['storage_quota']) ? (int)$input['storage_quota'] : null,
    'storage_usage'              => isset($input['storage_usage']) ? (int)$input['storage_usage'] : null,
    'is_incognito'               => $bool($input['is_incognito'] ?? 0),
    'is_bot'                     => $bool($input['is_bot'] ?? 0),

    // Permissions
    'permissions_supported'      => $bool($input['permissions_supported'] ?? 0),
    'permissions_state'          => $j($input['permissions_state'] ?? null),

    // Speech + Media
    'speech_supported'           => $bool($input['speech_supported'] ?? 0),
    'speech_voices_count'        => (int)($input['speech_voices_count'] ?? 0),
    'speech_voices_list'         => $j($input['speech_voices_list'] ?? null),
    'media_devices_supported'    => $bool($input['media_devices_supported'] ?? 0),
    'media_devices_list'         => $j($input['media_devices_list'] ?? null),
    'audio_inputs'               => (int)($input['audio_inputs'] ?? 0),
    'audio_outputs'              => (int)($input['audio_outputs'] ?? 0),
    'video_inputs'               => (int)($input['video_inputs'] ?? 0),

    // WebRTC
    'webrtc_supported'           => $bool($input['webrtc_supported'] ?? 0),
    'webrtc_ips'                 => $j($input['webrtc_ips'] ?? []),
    'webrtc_ip_count'            => (int)($input['webrtc_ip_count'] ?? 0),

    // Network
    'connection_supported'       => $bool($input['connection_supported'] ?? 0),
    'connection_type'            => $input['connection_type'] ?? null,
    'connection_effective_type'  => $input['connection_effective_type'] ?? ($input['effective_type'] ?? null),
    'effective_type'             => $input['effective_type'] ?? ($input['connection_effective_type'] ?? null),
    'connection_downlink'        => isset($input['connection_downlink']) ? (float)$input['connection_downlink'] : (isset($input['downlink']) ? (float)$input['downlink'] : null),
    'downlink'                   => isset($input['downlink']) ? (float)$input['downlink'] : (isset($input['connection_downlink']) ? (float)$input['connection_downlink'] : null),
    'connection_rtt'             => isset($input['connection_rtt']) ? (int)$input['connection_rtt'] : (isset($input['rtt']) ? (int)$input['rtt'] : null),
    'rtt'                        => isset($input['rtt']) ? (int)$input['rtt'] : (isset($input['connection_rtt']) ? (int)$input['connection_rtt'] : null),
    'connection_save_data'       => $bool($input['connection_save_data'] ?? $input['save_data'] ?? 0),
    'save_data'                  => $bool($input['save_data'] ?? $input['connection_save_data'] ?? 0),

    // Codecs + Features
    'codec_support'              => $j($input['codec_support'] ?? null),
    'feature_support'            => $j($input['feature_support'] ?? null),

    // Behavior
    'session_duration'           => (int)($input['session_duration'] ?? 0),
    'page_visible_time'          => (int)($input['page_visible_time'] ?? 0),
    'page_hidden_time'           => (int)($input['page_hidden_time'] ?? 0),
    'mouse_movements_count'      => (int)($input['mouse_movements_count'] ?? $input['mouse_movements'] ?? 0),
    'mouse_movements'            => (int)($input['mouse_movements'] ?? $input['mouse_movements_count'] ?? 0),
    'mouse_clicks_count'         => (int)($input['mouse_clicks_count'] ?? $input['clicks_count'] ?? 0),
    'clicks_count'               => (int)($input['clicks_count'] ?? $input['mouse_clicks_count'] ?? 0),
    'scroll_events_count'        => (int)($input['scroll_events_count'] ?? 0),
    'key_events_count'           => (int)($input['key_events_count'] ?? $input['keystrokes_count'] ?? 0),
    'keystrokes_count'           => (int)($input['keystrokes_count'] ?? $input['key_events_count'] ?? 0),
    'tab_switches'               => (int)($input['tab_switches'] ?? 0),
    'scroll_depth_max'           => (int)($input['scroll_depth_max'] ?? $input['max_scroll_depth'] ?? 0),
    'total_scroll_distance'      => (int)($input['total_scroll_distance'] ?? 0),
    'pages_viewed'               => (int)($input['pages_viewed'] ?? 1),
    'behavior_full_data'         => $j($input['behavior_full_data'] ?? null),

    // Page
    'page_url'                   => $input['page_url'] ?? null,
    'page_title'                 => $input['page_title'] ?? null,
    'page_protocol'              => $input['page_protocol'] ?? null,
    'page_host'                  => $input['page_host'] ?? null,
    'page_hostname'              => $input['page_hostname'] ?? null,
    'page_port'                  => $input['page_port'] ?? null,
    'page_pathname'              => $input['page_pathname'] ?? $pagePath,
    'page_path'                  => $input['page_pathname'] ?? $pagePath,
    'page_search'                => $input['page_search'] ?? null,
    'page_hash'                  => $input['page_hash'] ?? null,
    'page_origin'                => $input['page_origin'] ?? null,
    'page_charset'               => $input['page_charset'] ?? null,
    'page_visibility_state'      => $input['page_visibility_state'] ?? null,
    'page_has_focus'             => $bool($input['page_has_focus'] ?? 1),

    // Referrer / Source / UTM
    'referrer'                   => $referrer,
    'referrer_domain'            => $referrerDomain,
    'traffic_source'             => $trafficSource,
    'landing_page'               => $input['landing_page'] ?? ($input['page_url'] ?? null),
    'utm_source'                 => $input['utm_source']   ?? ($utm['utm_source']   ?? null),
    'utm_medium'                 => $input['utm_medium']   ?? ($utm['utm_medium']   ?? null),
    'utm_campaign'               => $input['utm_campaign'] ?? ($utm['utm_campaign'] ?? null),
    'utm_content'                => $input['utm_content']  ?? ($utm['utm_content']  ?? null),
    'utm_term'                   => $input['utm_term']     ?? ($utm['utm_term']     ?? null),
    'fbclid'                     => $input['fbclid']  ?? null,
    'gclid'                      => $input['gclid']   ?? null,
    'msclkid'                    => $input['msclkid'] ?? null,
    'ttclid'                     => $input['ttclid']  ?? null,

    // Misc
    'is_final_beacon'            => $bool($input['is_final_beacon'] ?? 0),

    // Risk
    'risk_score'                 => $risk['score'],
    'risk_level'                 => $risk['level'],
    'risk_flags'                 => $j($risk['flags']),

    // Full backup
    'full_data'                  => json_encode([
        'input'     => $input,
        'ipInfo'    => $ipInfo,
        'proxyInfo' => $proxyInfo,
        'server'    => [
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'          => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'accept_lang' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
];

// ---------------- Filter to actual DB columns ----------------
$columns = getVisitorColumns();
if (empty($columns)) { http_response_code(500); echo json_encode(['error'=>'cannot read columns']); exit; }
$columnsSet = array_flip($columns);
$data = array_intersect_key($data, $columnsSet);

// ---------------- INSERT or UPDATE based on session existence ----------------
try {
    $existing = db()->prepare("SELECT id FROM visitors WHERE session_id = ? LIMIT 1");
    $existing->execute([$sessionId]);
    $existingRow = $existing->fetch();

    if ($existingRow) {
        // UPDATE — preserve highest behavior counters via GREATEST() AND preserve any
        // existing fingerprint data from being overwritten with null/empty from a later beacon.
        //
        // BUG FIX (2026-05-17): The unload beacon (`pagehide`/`beforeunload`) fires while the
        // page is closing — `collectAll()` is async and Promise.all may not have resolved, so
        // the beacon sends partial data with NULL fingerprint fields. Without this protection,
        // the UPDATE wiped the good data captured by the initial +3s beacon. Now we:
        //   1. GREATEST for behavior counters (already done)
        //   2. SKIP empty/null/'[]'/'{}' values for non-counter columns (preserves real data)
        //   3. Always update certain meta columns regardless (risk, full_data, ip, last_seen)
        $greatestCols = ['session_duration','scroll_depth_max','mouse_movements','mouse_movements_count',
                         'clicks_count','mouse_clicks_count','keystrokes_count','key_events_count',
                         'scroll_events_count','tab_switches','pages_viewed','total_scroll_distance',
                         'page_visible_time','page_hidden_time'];
        // Columns that should ALWAYS update with the latest value (even if empty), because they
        // are re-derived server-side or represent the most recent state.
        $alwaysUpdateCols = ['risk_score','risk_level','risk_flags','full_data','ip_address',
                             'ip_type','country','country_code','continent','region','city','isp',
                             'asn','as_name','as_domain','org','is_proxy','is_vpn','is_tor',
                             'is_datacenter','proxy_type','proxy_risk_score','traffic_source',
                             'referrer','referrer_domain','is_final_beacon'];
        $isEmpty = function ($v) {
            if ($v === null || $v === '' || $v === 'null') return true;
            if ($v === '[]' || $v === '{}') return true;
            if (is_array($v) && count($v) === 0) return true;
            return false;
        };
        $set = [];
        $bindData = [];
        foreach ($data as $col => $val) {
            if (in_array($col, $greatestCols, true) && is_numeric($val)) {
                $set[] = "`$col` = GREATEST(COALESCE(`$col`, 0), :$col)";
                $bindData[$col] = $val;
            } else if (in_array($col, $alwaysUpdateCols, true)) {
                // Always overwrite — these are server-derived or meta fields
                $set[] = "`$col` = :$col";
                $bindData[$col] = $val;
            } else if (!$isEmpty($val)) {
                // Non-empty client data — overwrite existing
                $set[] = "`$col` = :$col";
                $bindData[$col] = $val;
            }
            // else: empty value, skip — preserve whatever's already in DB
        }
        $set[] = '`last_seen` = NOW()';
        $sql = "UPDATE visitors SET " . implode(', ', $set) . " WHERE id = :__id";
        $stmt = db()->prepare($sql);
        foreach ($bindData as $col => $val) $stmt->bindValue(':' . $col, $val);
        $stmt->bindValue(':__id', (int)$existingRow['id'], PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['status'=>'updated','id'=>(int)$existingRow['id'],'risk_score'=>$risk['score'],'risk_level'=>$risk['level'],'cols_updated'=>count($bindData)]);
    } else {
        // INSERT
        $cols   = array_keys($data);
        $placeh = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO visitors (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $placeh) . ")";
        $stmt = db()->prepare($sql);
        foreach ($data as $col => $val) $stmt->bindValue(':' . $col, $val);
        $stmt->execute();
        echo json_encode(['status'=>'logged','id'=>(int)db()->lastInsertId(),'risk_score'=>$risk['score'],'risk_level'=>$risk['level'],'flags'=>$risk['flags']]);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Tracker insert failed: ' . $e->getMessage());
    echo json_encode(['error'=>'storage_failed','message'=>$e->getMessage()]);
}
