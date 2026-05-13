<?php
declare(strict_types=1);
/**
 * ============================================================================
 * Money Wise 2026 — Visitor Detail Page
 * 10-stage analysis + raw JSON for a single visitor.
 * ============================================================================
 */

// TEMP: enable error display for debugging. Remove once stable.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';

session_name(SESSION_NAME);
session_start();
if (empty($_SESSION['mw_auth'])) {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = db()->prepare("SELECT * FROM visitors WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) { http_response_code(404); echo 'Visitor not found.'; exit; }

$flags = $v['risk_flags'] ? (json_decode($v['risk_flags'], true) ?: []) : [];
$languages = $v['languages'] ? (json_decode($v['languages'], true) ?: []) : [];
$fonts = $v['fonts_list'] ? (json_decode($v['fonts_list'], true) ?: []) : [];
$fullData = $v['full_data'] ? json_decode($v['full_data'], true) : [];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flagEmoji(?string $cc): string {
    if (!$cc || strlen($cc) !== 2) return '🌐';
    $cc = strtoupper($cc);
    return mb_chr(0x1F1E6 + (ord($cc[0]) - 65)) . mb_chr(0x1F1E6 + (ord($cc[1]) - 65));
}
function ynBadge($v): string {
    return $v ? '<span class="yn yes">YES</span>' : '<span class="yn no">no</span>';
}
function row(string $label, $value, bool $mono = false) {
    if ($value === null || $value === '') $value = '—';
    $cls = $mono ? 'mono' : '';
    echo '<tr><th>' . h($label) . '</th><td class="' . $cls . '">' . (is_string($value) ? h($value) : $value) . '</td></tr>';
}
function flagExplanation(string $f): string {
    $map = [
        'proxy_detected'      => 'IP identified as proxy via proxycheck.io',
        'vpn_detected'        => 'IP identified as VPN service',
        'tor_detected'        => 'IP is a Tor exit node',
        'public_proxy'        => 'Public/compromised proxy',
        'datacenter_ip'       => 'IP is from a datacenter (AWS/GCP/Azure/etc.)',
        'datacenter_org'      => 'ISP organization name suggests datacenter',
        'high_ip_risk'        => 'proxycheck.io risk score > 50',
        'no_cookies'          => 'Browser cookies are disabled',
        'no_local_storage'    => 'localStorage disabled (rare in real users)',
        'incognito_mode'      => 'Private/incognito browsing detected',
        'webdriver_detected'  => 'navigator.webdriver === true (automation)',
        'bot_signal'          => 'Bot indicators present in environment',
        'bot_user_agent'      => 'User-Agent matches known bot patterns',
        'missing_webgl'       => 'No WebGL renderer (headless or stripped)',
        'missing_canvas'      => 'Canvas fingerprint blocked or empty',
        'low_font_count'      => 'Very few fonts detected (uncommon for real users)',
        'missing_cpu_info'    => 'hardwareConcurrency missing',
        'no_mouse_movement'   => 'Long session with no mouse activity',
        'too_short_session'   => 'Session under 3 seconds with no interaction',
        'no_scroll'           => 'Long session with no scrolling',
        'click_flood'         => 'Many clicks in a very short window',
    ];
    return $map[$f] ?? 'Heuristic risk indicator';
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Visitor #<?= (int)$v['id'] ?> — Tracker</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/svg+xml" href="../favicon.svg">
</head>
<body>

<header class="dash-header">
  <div class="dash-header-inner">
    <div class="dash-logo">Money Wise <span>2026</span> · Tracker</div>
    <nav class="dash-nav">
      <a href="index.php">← Back to Dashboard</a>
      <a href="?logout=1" class="logout">Logout</a>
    </nav>
  </div>
</header>

<main class="dash-main">

  <!-- ============ Visitor Header Card ============ -->
  <section class="visitor-header risk-<?= h($v['risk_level']) ?>">
    <div>
      <h1>Visitor #<?= (int)$v['id'] ?></h1>
      <p class="muted">First seen: <?= h($v['visit_time']) ?> · Last seen: <?= h($v['last_seen']) ?></p>
      <p class="mono small"><?= h($v['visitor_id']) ?></p>
    </div>
    <div class="risk-display">
      <div class="risk-score-big"><?= (int)$v['risk_score'] ?></div>
      <div class="risk-pill risk-pill-<?= h($v['risk_level']) ?>"><?= h(strtoupper((string)$v['risk_level'])) ?></div>
    </div>
  </section>

  <!-- ============ Risk Flags ============ -->
  <?php if (!empty($flags)): ?>
  <section class="card">
    <h2>🚩 Risk Flags Raised</h2>
    <ul class="flag-list">
      <?php foreach ($flags as $f): ?>
        <li><code><?= h($f) ?></code> — <?= h(flagExplanation($f)) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <!-- ============ Stage 1: IP Address ============ -->
  <section class="card">
    <h2>🌐 Stage 1 — IP Address & Network</h2>
    <table class="kv">
      <?php
      row('IP Address', $v['ip_address'], true);
      row('Country', flagEmoji($v['country_code']) . ' ' . h($v['country'] ?: '—'));
      row('Region / City', trim(($v['region'] ?? '') . ' / ' . ($v['city'] ?? '')) ?: '—');
      row('Continent', $v['continent']);
      row('ISP / Org', $v['isp']);
      row('ASN', $v['asn']);
      row('AS Name', $v['as_name']);
      row('AS Domain', $v['as_domain']);
      row('Is Proxy', ynBadge($v['is_proxy']));
      row('Is VPN', ynBadge($v['is_vpn']));
      row('Is Tor', ynBadge($v['is_tor']));
      row('Is Datacenter', ynBadge($v['is_datacenter']));
      row('Proxy Type', $v['proxy_type']);
      row('Proxy Risk (0-100)', (int)$v['proxy_risk_score']);
      ?>
    </table>
    <?php if (!empty($v['city']) || !empty($v['country'])): ?>
      <p class="muted">📍 Approximate location:
        <a target="_blank" rel="noopener"
           href="https://www.google.com/maps?q=<?= urlencode(($v['city'] ?? '') . ', ' . ($v['country'] ?? '')) ?>">View on Google Maps ↗</a>
      </p>
    <?php endif; ?>
  </section>

  <!-- ============ Stage 2: Browser Fingerprint ============ -->
  <section class="card">
    <h2>🧬 Stage 2 — Browser Fingerprint</h2>
    <table class="kv">
      <?php
      row('User-Agent', $v['user_agent'], true);
      row('Browser', $v['browser_name'] . ' ' . $v['browser_version']);
      row('OS', $v['os_name'] . ' ' . $v['os_version']);
      row('Device Type', $v['device_type']);
      row('Primary Language', $v['language_primary']);
      row('All Languages', is_array($languages) ? implode(', ', $languages) : '');
      row('Timezone', $v['timezone']);
      row('Timezone Offset (min)', $v['timezone_offset']);
      ?>
    </table>
  </section>

  <!-- ============ Stage 3: Device & Hardware ============ -->
  <section class="card">
    <h2>💻 Stage 3 — Device & Hardware</h2>
    <table class="kv">
      <?php
      row('Screen', $v['screen_width'] . ' × ' . $v['screen_height']);
      row('Available Screen', $v['screen_avail_width'] . ' × ' . $v['screen_avail_height']);
      row('Color Depth', ($v['screen_color_depth'] ?: '—') . ' bit');
      row('Pixel Ratio', $v['pixel_ratio']);
      row('Viewport', $v['viewport_width'] . ' × ' . $v['viewport_height']);
      row('CPU Cores', $v['cpu_cores']);
      row('Device Memory (GB)', $v['device_memory']);
      row('Touch Support', ynBadge($v['touch_support']));
      row('Max Touch Points', $v['max_touch_points']);
      row('Battery Level', $v['battery_level'] !== null ? round(((float)$v['battery_level']) * 100) . '%' : '—');
      row('Battery Charging', $v['battery_charging'] === null ? '—' : ynBadge($v['battery_charging']));
      ?>
    </table>
  </section>

  <!-- ============ Stage 4: Advanced Fingerprint ============ -->
  <section class="card">
    <h2>🎨 Stage 4 — Advanced Fingerprint</h2>
    <table class="kv">
      <?php
      row('WebGL Renderer', $v['webgl_renderer']);
      row('WebGL Vendor', $v['webgl_vendor']);
      row('WebGL Version', $v['webgl_version']);
      row('Canvas Fingerprint', $v['canvas_fingerprint'], true);
      row('Audio Fingerprint', $v['audio_fingerprint'], true);
      row('Fonts Detected', $v['fonts_count']);
      row('Fonts List', is_array($fonts) ? implode(', ', $fonts) : '');
      row('Plugins Count', $v['plugins_count']);
      ?>
    </table>
  </section>

  <!-- ============ Stage 5: Browser State ============ -->
  <section class="card">
    <h2>🔒 Stage 5 — Browser State</h2>
    <table class="kv">
      <?php
      row('Cookies Enabled', ynBadge($v['cookies_enabled']));
      row('localStorage', ynBadge($v['local_storage_enabled']));
      row('sessionStorage', ynBadge($v['session_storage_enabled']));
      row('indexedDB', ynBadge($v['indexed_db_enabled']));
      row('Do Not Track', $v['do_not_track']);
      row('Incognito Mode', ynBadge($v['is_incognito']));
      row('Bot Signal', ynBadge($v['is_bot']));
      row('WebDriver', ynBadge($v['is_webdriver']));
      ?>
    </table>
  </section>

  <!-- ============ Stage 6: Network ============ -->
  <section class="card">
    <h2>📡 Stage 6 — Network Info</h2>
    <table class="kv">
      <?php
      row('Connection Type', $v['connection_type']);
      row('Effective Type', $v['effective_type']);
      row('Downlink (Mbps)', $v['downlink']);
      row('RTT (ms)', $v['rtt']);
      row('Save Data Mode', ynBadge($v['save_data']));
      ?>
    </table>
  </section>

  <!-- ============ Stage 7: Behavior ============ -->
  <section class="card">
    <h2>🖱️ Stage 7 — Behavior & Engagement</h2>
    <table class="kv">
      <?php
      row('Session Duration', $v['session_duration'] . ' seconds');
      row('Scroll Depth Max', $v['scroll_depth_max'] . '%');
      row('Mouse Movements', $v['mouse_movements']);
      row('Clicks Count', $v['clicks_count']);
      row('Keystrokes', $v['keystrokes_count']);
      row('Tab Switches', $v['tab_switches']);
      row('Pages Viewed', $v['pages_viewed']);
      ?>
    </table>
  </section>

  <!-- ============ Stage 8: Traffic Source ============ -->
  <section class="card">
    <h2>🚦 Stage 8 — Traffic Source</h2>
    <table class="kv">
      <?php
      row('Traffic Source', ucfirst((string)$v['traffic_source']));
      row('Referrer', $v['referrer'], true);
      row('Referrer Domain', $v['referrer_domain']);
      row('Landing Page', $v['landing_page']);
      row('Current Page', $v['page_url']);
      row('Page Title', $v['page_title']);
      row('UTM Source', $v['utm_source']);
      row('UTM Medium', $v['utm_medium']);
      row('UTM Campaign', $v['utm_campaign']);
      row('UTM Content', $v['utm_content']);
      row('UTM Term', $v['utm_term']);
      ?>
    </table>
  </section>

  <!-- ============ Raw JSON ============ -->
  <section class="card">
    <h2>📦 Raw Data (Full JSON)</h2>
    <details>
      <summary>Click to expand</summary>
      <pre class="json-viewer"><?= h(json_encode($fullData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
    </details>
  </section>

</main>

<footer class="dash-footer">
  <p>Money Wise 2026 Tracker · Internal use only.</p>
</footer>

</body>
</html>
