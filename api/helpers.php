<?php
/**
 * ============================================================================
 * Money Wise 2026 — Tracker Helpers
 * ============================================================================
 *  - IP intelligence (ipinfo.io Lite + proxycheck.io)
 *  - Risk scoring (0-100)
 *  - Traffic source classification
 *  - Rate limiting
 *  - User-Agent parsing
 * ============================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ----------------------------------------------------------------------------
// Get visitor's real IP (handles Cloudflare, proxies)
// ----------------------------------------------------------------------------
function getRealIP(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ----------------------------------------------------------------------------
// ipinfo.io Lite API — returns country, ASN, ISP
// ----------------------------------------------------------------------------
function getIPInfo(string $ip): array {
    if (!filter_var($ip, FILTER_VALIDATE_IP) || isPrivateIP($ip)) {
        return ['error' => 'private_or_invalid_ip'];
    }
    $url = "https://api.ipinfo.io/lite/" . urlencode($ip);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer " . IPINFO_TOKEN . "\r\nAccept: application/json\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return ['error' => 'ipinfo_unreachable'];
    $data = json_decode($resp, true);
    return is_array($data) ? $data : ['error' => 'ipinfo_invalid_response'];
}

// ----------------------------------------------------------------------------
// proxycheck.io — VPN/Proxy/Datacenter detection + risk score
// ----------------------------------------------------------------------------
function getProxyInfo(string $ip): array {
    if (!filter_var($ip, FILTER_VALIDATE_IP) || isPrivateIP($ip)) {
        return ['error' => 'private_or_invalid_ip'];
    }
    $url = "https://proxycheck.io/v2/" . urlencode($ip) . "?key=" . PROXYCHECK_KEY . "&vpn=1&asn=1&risk=1&node=1";
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return ['error' => 'proxycheck_unreachable'];
    $data = json_decode($resp, true);
    return is_array($data) ? $data : ['error' => 'proxycheck_invalid_response'];
}

// ----------------------------------------------------------------------------
// Detect private/reserved IPs (localhost, LAN)
// ----------------------------------------------------------------------------
function isPrivateIP(string $ip): bool {
    return !filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
    );
}

// ----------------------------------------------------------------------------
// Classify traffic source from referrer
// ----------------------------------------------------------------------------
function classifyTrafficSource(?string $referrer, ?string $utmSource = null): string {
    if (!empty($utmSource)) {
        return 'campaign';
    }
    if (empty($referrer)) {
        return 'direct';
    }
    $host = parse_url($referrer, PHP_URL_HOST) ?? '';
    $host = strtolower($host);

    $searchEngines = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.'];
    foreach ($searchEngines as $se) {
        if (strpos($host, $se) !== false) return 'organic';
    }
    $social = ['facebook.', 'fb.com', 'twitter.', 'x.com', 'instagram.', 'linkedin.', 'pinterest.', 'tiktok.', 'reddit.', 'youtube.', 'whatsapp.'];
    foreach ($social as $s) {
        if (strpos($host, $s) !== false) return 'social';
    }
    $email = ['mail.google.com', 'outlook.', 'yahoo.com/mail'];
    foreach ($email as $e) {
        if (strpos($host, $e) !== false) return 'email';
    }
    return 'referral';
}

// ----------------------------------------------------------------------------
// Risk Score (0-100) — based on IP, browser state, behavior, fingerprint
// ----------------------------------------------------------------------------
function calculateRiskScore(array $data, array $ipInfo, array $proxyInfo, string $ip): array {
    $score = 0;
    $flags = [];

    // ---- IP Intelligence ----
    $proxyData = $proxyInfo[$ip] ?? [];
    if (!empty($proxyData['proxy']) && $proxyData['proxy'] === 'yes') {
        $score += 30;
        $flags[] = 'proxy_detected';
    }
    if (!empty($proxyData['type'])) {
        $t = strtoupper($proxyData['type']);
        if ($t === 'VPN')                           { $score += 25; $flags[] = 'vpn_detected'; }
        if ($t === 'TOR')                           { $score += 40; $flags[] = 'tor_detected'; }
        if (in_array($t, ['COMPROMISED SERVER', 'PUBLIC PROXY', 'WEB PROXY'])) {
            $score += 35; $flags[] = 'public_proxy';
        }
        if (in_array($t, ['BUSINESS', 'HOSTING', 'DATACENTER'])) {
            $score += 25; $flags[] = 'datacenter_ip';
        }
    }
    $org = strtolower(($ipInfo['as_name'] ?? '') . ' ' . ($ipInfo['as_domain'] ?? ''));
    foreach (['amazon','google cloud','microsoft','digitalocean','linode','vultr','hetzner','ovh','contabo','choopa'] as $dc) {
        if (strpos($org, $dc) !== false) {
            if (!in_array('datacenter_ip', $flags)) {
                $score += 20;
                $flags[] = 'datacenter_org';
            }
            break;
        }
    }
    if (!empty($proxyData['risk']) && (int)$proxyData['risk'] > 50) {
        $score += 15;
        $flags[] = 'high_ip_risk';
    }

    // ---- Browser State ----
    if (empty($data['cookies_enabled']))          { $score += 5;  $flags[] = 'no_cookies'; }
    if (empty($data['local_storage_enabled']))    { $score += 5;  $flags[] = 'no_local_storage'; }
    if (!empty($data['is_incognito']))            { $score += 15; $flags[] = 'incognito_mode'; }
    if (!empty($data['is_webdriver']))            { $score += 40; $flags[] = 'webdriver_detected'; }
    if (!empty($data['is_bot']))                  { $score += 50; $flags[] = 'bot_signal'; }

    // ---- Fingerprint ----
    if (empty($data['webgl_renderer']))           { $score += 10; $flags[] = 'missing_webgl'; }
    if (empty($data['canvas_fingerprint']))       { $score += 10; $flags[] = 'missing_canvas'; }
    if (($data['fonts_count'] ?? 0) < 5)          { $score += 10; $flags[] = 'low_font_count'; }
    if (($data['cpu_cores'] ?? 0) === 0)          { $score += 5;  $flags[] = 'missing_cpu_info'; }

    // ---- Behavior ----
    $duration = (int)($data['session_duration'] ?? 0);
    $mouse    = (int)($data['mouse_movements'] ?? 0);
    $clicks   = (int)($data['clicks_count'] ?? 0);
    $scroll   = (int)($data['scroll_depth_max'] ?? 0);

    if ($duration > 30 && $mouse < 5)             { $score += 20; $flags[] = 'no_mouse_movement'; }
    if ($duration < 3 && $mouse === 0)            { $score += 15; $flags[] = 'too_short_session'; }
    if ($duration > 60 && $scroll === 0)          { $score += 10; $flags[] = 'no_scroll'; }
    if ($clicks > 50 && $duration < 30)           { $score += 15; $flags[] = 'click_flood'; }

    // ---- UA / DNT signals ----
    $ua = strtolower($data['user_agent'] ?? '');
    foreach (['headlesschrome','phantomjs','selenium','puppeteer','playwright','bot','crawler','spider','curl','wget','python-requests'] as $bot) {
        if (strpos($ua, $bot) !== false) {
            $score += 40;
            $flags[] = 'bot_user_agent';
            break;
        }
    }

    $score = max(0, min(100, $score));
    $level = $score < 30 ? 'low' : ($score < 60 ? 'medium' : 'high');

    return ['score' => $score, 'flags' => array_values(array_unique($flags)), 'level' => $level];
}

// ----------------------------------------------------------------------------
// Lightweight User-Agent parser (no dependencies)
// ----------------------------------------------------------------------------
function parseUserAgent(string $ua): array {
    $browser = 'Unknown'; $browserVer = ''; $os = 'Unknown'; $osVer = ''; $device = 'desktop';

    // Browser detection (order matters: more specific first)
    if (preg_match('/Edg\/([\d\.]+)/', $ua, $m))            { $browser = 'Edge';    $browserVer = $m[1]; }
    elseif (preg_match('/OPR\/([\d\.]+)/', $ua, $m))        { $browser = 'Opera';   $browserVer = $m[1]; }
    elseif (preg_match('/Firefox\/([\d\.]+)/', $ua, $m))    { $browser = 'Firefox'; $browserVer = $m[1]; }
    elseif (preg_match('/Chrome\/([\d\.]+)/', $ua, $m))     { $browser = 'Chrome';  $browserVer = $m[1]; }
    elseif (preg_match('/Version\/([\d\.]+).*Safari/', $ua, $m)) { $browser = 'Safari'; $browserVer = $m[1]; }
    elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false) { $browser = 'Internet Explorer'; }

    // OS
    if     (preg_match('/Windows NT ([\d\.]+)/', $ua, $m))  { $os = 'Windows'; $osVer = $m[1]; }
    elseif (preg_match('/Mac OS X ([\d_]+)/', $ua, $m))     { $os = 'macOS';   $osVer = str_replace('_', '.', $m[1]); }
    elseif (preg_match('/Android ([\d\.]+)/', $ua, $m))     { $os = 'Android'; $osVer = $m[1]; $device = 'mobile'; }
    elseif (preg_match('/iPhone OS ([\d_]+)/', $ua, $m))    { $os = 'iOS';     $osVer = str_replace('_', '.', $m[1]); $device = 'mobile'; }
    elseif (preg_match('/iPad.*OS ([\d_]+)/', $ua, $m))     { $os = 'iPadOS';  $osVer = str_replace('_', '.', $m[1]); $device = 'tablet'; }
    elseif (stripos($ua, 'Linux') !== false)                { $os = 'Linux'; }

    if ($device === 'desktop') {
        if (stripos($ua, 'Mobile') !== false) $device = 'mobile';
        elseif (stripos($ua, 'Tablet') !== false || stripos($ua, 'iPad') !== false) $device = 'tablet';
    }

    return [
        'browser_name'    => $browser,
        'browser_version' => $browserVer,
        'os_name'         => $os,
        'os_version'      => $osVer,
        'device_type'     => $device,
    ];
}

// ----------------------------------------------------------------------------
// Simple in-DB rate limiting for /api/log.php
// ----------------------------------------------------------------------------
function checkApiRateLimit(string $ip): bool {
    static $checked = false;
    if ($checked) return true;
    $checked = true;
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM visitors WHERE ip_address = ? AND visit_time > (NOW() - INTERVAL 1 MINUTE)"
        );
        $stmt->execute([$ip]);
        $count = (int)$stmt->fetchColumn();
        return $count < API_RATE_LIMIT;
    } catch (Exception $e) {
        return true;
    }
}

// ----------------------------------------------------------------------------
// Check if current request should be excluded from tracking
// ----------------------------------------------------------------------------
function shouldExclude(?string $path): bool {
    if (empty($path)) return false;
    foreach (EXCLUDE_PATHS as $exc) {
        if (strpos($path, $exc) === 0) return true;
    }
    return false;
}

// ----------------------------------------------------------------------------
// Extract UTM parameters from URL
// ----------------------------------------------------------------------------
function extractUTM(?string $url): array {
    if (empty($url)) return [];
    $query = parse_url($url, PHP_URL_QUERY);
    if (!$query) return [];
    parse_str($query, $params);
    return [
        'utm_source'   => $params['utm_source']   ?? null,
        'utm_medium'   => $params['utm_medium']   ?? null,
        'utm_campaign' => $params['utm_campaign'] ?? null,
        'utm_content'  => $params['utm_content']  ?? null,
        'utm_term'     => $params['utm_term']     ?? null,
    ];
}
