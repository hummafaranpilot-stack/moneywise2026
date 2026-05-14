<?php
declare(strict_types=1);
/**
 * ============================================================================
 * Money Wise 2026 — Visitor Detail Page v2
 * 10-stage analysis with all 150+ tracker fields + copy-JSON button.
 * ============================================================================
 */

// TEMP: enable error display for debugging. Remove once stable.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';

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
if (empty($_SESSION['mw_auth'])) { header('Location: login.php'); exit; }
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = db()->prepare("SELECT * FROM visitors WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) { http_response_code(404); echo 'Visitor not found.'; exit; }

// ---------------- Decode JSON columns ----------------
function jget($v, $k, $default = []) {
    if (empty($v[$k])) return $default;
    if (is_string($v[$k])) {
        $d = json_decode($v[$k], true);
        return $d !== null ? $d : $default;
    }
    return is_array($v[$k]) ? $v[$k] : $default;
}
$flags          = jget($v, 'risk_flags', []);
$languages      = jget($v, 'languages', []);
$fontsList      = jget($v, 'fonts_list', jget($v, 'fonts_detected', []));
$pluginsList    = jget($v, 'plugins_list', []);
$mimeList       = jget($v, 'mime_types_list', []);
$webglExt       = jget($v, 'webgl_extensions', []);
$lsKeys         = jget($v, 'localstorage_keys', []);
$ssKeys         = jget($v, 'sessionstorage_keys', []);
$permissions    = jget($v, 'permissions_state', []);
$speechVoices   = jget($v, 'speech_voices_list', []);
$mediaDevices   = jget($v, 'media_devices_list', []);
$webrtcIPs      = jget($v, 'webrtc_ips', []);
$codecSupport   = jget($v, 'codec_support', []);
$featureSupport = jget($v, 'feature_support', []);
$behaviorFull   = jget($v, 'behavior_full_data', []);
$fullData       = jget($v, 'full_data', []);

// ---------------- Helpers ----------------
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Wrapper class — signals to row() that this value is already-safe HTML
// and must NOT be escaped again. Use this for ynBadge(), flagEmoji+text concat, etc.
class RawHtml {
    public string $html;
    public function __construct(string $html) { $this->html = $html; }
    public function __toString(): string { return $this->html; }
}
function raw(string $html): RawHtml { return new RawHtml($html); }

function flagEmoji(?string $cc): string {
    if (!$cc || strlen($cc) !== 2) return '🌐';
    $cc = strtoupper($cc);
    return mb_chr(0x1F1E6 + (ord($cc[0]) - 65)) . mb_chr(0x1F1E6 + (ord($cc[1]) - 65));
}
// Convert MySQL DATETIME (assumed UTC) -> PKT info
function pktTime(?string $mysqlDt): array {
    if (!$mysqlDt) return ['ts' => 0, 'pkt' => '—'];
    try {
        $dt = new DateTime($mysqlDt, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Karachi'));
        return ['ts' => $dt->getTimestamp(), 'pkt' => $dt->format('M j, Y H:i:s') . ' PKT'];
    } catch (Exception $e) {
        return ['ts' => 0, 'pkt' => $mysqlDt];
    }
}
function ynBadge($v): RawHtml {
    return raw($v ? '<span class="yn yes">YES</span>' : '<span class="yn no">no</span>');
}
function row(string $label, $value, bool $mono = false) {
    if ($value === null || $value === '') $value = '—';
    if ($value instanceof RawHtml) {
        $rendered = $value->html;
    } elseif (is_string($value)) {
        $rendered = h($value);
    } else {
        $rendered = h((string)$value);
    }
    echo '<tr><th>' . h($label) . '</th><td' . ($mono ? ' class="mono"' : '') . '>' . $rendered . '</td></tr>';
}
function flagExplanation(string $f): string {
    $map = [
        'proxy_detected'      => 'Proxy detected via proxycheck.io',
        'vpn_detected'        => 'IP identified as VPN',
        'tor_detected'        => 'Tor exit node',
        'public_proxy'        => 'Public/compromised proxy',
        'datacenter_ip'       => 'Datacenter / hosting IP',
        'datacenter_org'      => 'ISP organization is hosting/cloud',
        'high_ip_risk'        => 'proxycheck.io risk > 50',
        'no_cookies'          => 'Cookies disabled',
        'no_languages'        => 'No browser languages reported',
        'incognito_mode'      => 'Private/incognito browsing',
        'webdriver_detected'  => 'navigator.webdriver === true (automation)',
        'bot_user_agent'      => 'UA matches bot patterns',
        'low_cpu_cores'       => 'Hardware concurrency < 2',
        'no_device_memory'    => 'navigator.deviceMemory missing',
        'no_webgl'            => 'WebGL not available',
        'no_canvas'           => 'Canvas blocked',
        'no_audio_api'        => 'AudioContext not available',
        'low_font_count'      => 'Few fonts detected',
        'no_mouse_movement'   => 'Long session, no mouse activity',
        'too_short_session'   => 'Session under 5 seconds',
        'no_scroll'           => 'Long session, no scrolling',
        'click_flood'         => 'Many clicks in short window',
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
  <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: time() ?>">
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

  <!-- ============ Header card + Copy JSON ============ -->
  <section class="visitor-header risk-<?= h($v['risk_level']) ?>">
    <div>
      <h1>Visitor #<?= (int)$v['id'] ?></h1>
      <?php $first = pktTime($v['visit_time']); $last = pktTime($v['last_seen']); ?>
      <p class="muted">
        First seen: <time data-ts="<?= (int)$first['ts'] ?>" title="<?= h($first['pkt']) ?>"><?= h($first['pkt']) ?></time>
        &nbsp;·&nbsp;
        Last seen: <time data-ts="<?= (int)$last['ts'] ?>" title="<?= h($last['pkt']) ?>"><?= h($last['pkt']) ?></time>
      </p>
      <p class="mono small"><?= h($v['visitor_id']) ?></p>
    </div>
    <div class="risk-display">
      <div class="risk-score-big"><?= (int)$v['risk_score'] ?></div>
      <div class="risk-pill risk-pill-<?= h($v['risk_level']) ?>"><?= h(strtoupper((string)$v['risk_level'])) ?></div>
      <button id="btn-copy-full" class="action-btn primary" style="margin-top:10px;">📋 Copy Full JSON</button>
    </div>
  </section>

  <div id="toast-container"></div>

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

  <!-- ============ Stage 1: IP + Network ============ -->
  <section class="card">
    <h2>🌐 Stage 1 — IP Address & Network</h2>
    <table class="kv">
      <?php
      row('IP Address', $v['ip_address'], true);
      row('Country', raw(flagEmoji($v['country_code']) . ' ' . h($v['country'] ?: '—')));
      row('Region / City', trim(($v['region'] ?? '') . ' / ' . ($v['city'] ?? '')) ?: '—');
      row('Continent', $v['continent']);
      row('ISP', $v['isp']);
      row('ASN', $v['asn']);
      row('AS Name', $v['as_name']);
      row('AS Domain', $v['as_domain']);
      row('Org', $v['org']);
      row('Is Proxy', ynBadge($v['is_proxy']));
      row('Is VPN', ynBadge($v['is_vpn']));
      row('Is Tor', ynBadge($v['is_tor']));
      row('Is Datacenter', ynBadge($v['is_datacenter']));
      row('Proxy Type', $v['proxy_type']);
      row('Proxy Risk Score', (int)$v['proxy_risk_score']);
      ?>
    </table>
    <?php if (!empty($v['city']) || !empty($v['country'])): ?>
      <p class="muted">📍 <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= urlencode(($v['city'] ?? '') . ', ' . ($v['country'] ?? '')) ?>">View approximate location ↗</a></p>
    <?php endif; ?>
  </section>

  <!-- ============ Stage 2: Browser ============ -->
  <section class="card">
    <h2>🧬 Stage 2 — Browser Fingerprint</h2>
    <table class="kv">
      <?php
      row('User-Agent', $v['user_agent'], true);
      row('Browser', trim(($v['browser_name'] ?? '') . ' ' . ($v['browser_version'] ?? '')));
      row('OS', trim(($v['os_name'] ?? '') . ' ' . ($v['os_version'] ?? '')));
      row('Device Type', $v['device_type']);
      row('Vendor', $v['vendor']);
      row('Product', $v['product']);
      row('Platform', $v['platform']);
      row('App Name', $v['app_name']);
      row('Language (primary)', $v['language'] ?? $v['language_primary']);
      row('All Languages', is_array($languages) ? implode(', ', $languages) : '');
      row('Online', ynBadge($v['online']));
      row('Cookies Enabled (navigator)', ynBadge($v['cookie_enabled']));
      row('Do Not Track', $v['do_not_track']);
      row('WebDriver', ynBadge($v['webdriver']));
      row('PDF Viewer', ynBadge($v['pdf_viewer_enabled']));
      row('Java Enabled', ynBadge($v['java_enabled']));
      row('Is Brave', ynBadge($v['is_brave']));
      row('Max Touch Points', (int)$v['max_touch_points']);
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
      row('Window Inner', $v['window_inner_width'] . ' × ' . $v['window_inner_height']);
      row('Window Outer', $v['window_outer_width'] . ' × ' . $v['window_outer_height']);
      row('Color Depth', ($v['screen_color_depth'] ?: '—') . ' bit');
      row('Pixel Depth', $v['screen_pixel_depth']);
      row('Device Pixel Ratio', $v['device_pixel_ratio'] ?? $v['pixel_ratio']);
      row('Orientation', trim(($v['screen_orientation_type'] ?? '') . ' (' . ($v['screen_orientation_angle'] ?? '') . '°)'));
      row('Color Scheme', $v['prefers_color_scheme']);
      row('Reduced Motion', ynBadge($v['prefers_reduced_motion']));
      row('Color Gamut P3', ynBadge($v['color_gamut_p3']));
      row('Color Gamut sRGB', ynBadge($v['color_gamut_srgb']));
      row('CPU Cores', $v['hardware_concurrency'] ?? $v['cpu_cores']);
      row('Device Memory (GB)', $v['device_memory']);
      row('Touch Support', ynBadge($v['touch_support']));
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
      row('WebGL Supported', ynBadge($v['webgl_supported']));
      row('WebGL Vendor', $v['webgl_vendor']);
      row('WebGL Renderer', $v['webgl_renderer']);
      row('WebGL Unmasked Vendor', $v['webgl_unmasked_vendor']);
      row('WebGL Unmasked Renderer', $v['webgl_unmasked_renderer']);
      row('WebGL Version', $v['webgl_version']);
      row('Shading Language', $v['webgl_shading_language']);
      row('Max Texture Size', $v['webgl_max_texture_size']);
      row('Extensions Count', is_array($webglExt) ? count($webglExt) : 0);
      row('Canvas Supported', ynBadge($v['canvas_supported']));
      row('Canvas Hash', $v['canvas_hash'] ?? $v['canvas_fingerprint'], true);
      row('Audio Supported', ynBadge($v['audio_supported']));
      row('Audio Fingerprint', $v['audio_fingerprint'], true);
      row('Audio Sample Rate', $v['audio_sample_rate']);
      row('Fonts Detected', (int)$v['fonts_count']);
      row('Fonts List', is_array($fontsList) ? implode(', ', array_slice($fontsList, 0, 30)) : '');
      row('Plugins Count', (int)$v['plugins_count']);
      row('MIME Types Count', (int)$v['mime_types_count']);
      ?>
    </table>
  </section>

  <!-- ============ Stage 5: Storage / State ============ -->
  <section class="card">
    <h2>🔒 Stage 5 — Browser Storage State</h2>
    <table class="kv">
      <?php
      row('Cookies Enabled', ynBadge($v['cookies_enabled']));
      row('Cookies Count', (int)$v['cookies_count']);
      row('localStorage', ynBadge($v['localstorage_supported'] ?? $v['local_storage_enabled']));
      row('localStorage Size (bytes)', (int)$v['localstorage_size']);
      row('localStorage Keys', is_array($lsKeys) ? implode(', ', $lsKeys) : '');
      row('sessionStorage', ynBadge($v['sessionstorage_supported'] ?? $v['session_storage_enabled']));
      row('sessionStorage Keys', is_array($ssKeys) ? implode(', ', $ssKeys) : '');
      row('IndexedDB', ynBadge($v['indexeddb_supported'] ?? $v['indexed_db_enabled']));
      row('Service Worker', ynBadge($v['service_worker_supported']));
      row('Cache API', ynBadge($v['cache_supported']));
      row('Storage Quota (bytes)', $v['storage_quota']);
      row('Storage Usage (bytes)', $v['storage_usage']);
      row('Is Incognito', ynBadge($v['is_incognito']));
      ?>
    </table>
  </section>

  <!-- ============ Stage 6: Network Detail ============ -->
  <section class="card">
    <h2>📡 Stage 6 — Network & Connection</h2>
    <table class="kv">
      <?php
      row('Connection API Supported', ynBadge($v['connection_supported']));
      row('Connection Type', $v['connection_type']);
      row('Effective Type', $v['connection_effective_type'] ?? $v['effective_type']);
      row('Downlink (Mbps)', $v['connection_downlink'] ?? $v['downlink']);
      row('RTT (ms)', $v['connection_rtt'] ?? $v['rtt']);
      row('Save Data Mode', ynBadge($v['connection_save_data'] ?? $v['save_data']));
      row('WebRTC Supported', ynBadge($v['webrtc_supported']));
      row('WebRTC IP Count', (int)$v['webrtc_ip_count']);
      row('WebRTC IPs', is_array($webrtcIPs) ? implode(', ', $webrtcIPs) : '', true);
      ?>
    </table>
  </section>

  <!-- ============ Stage 7: Behavior & Engagement ============ -->
  <section class="card">
    <h2>🖱️ Stage 7 — Behavior & Engagement</h2>
    <table class="kv">
      <?php
      row('Session Duration', $v['session_duration'] . ' seconds');
      row('Page Visible Time', ($v['page_visible_time'] ?? 0) . ' seconds');
      row('Page Hidden Time',  ($v['page_hidden_time']  ?? 0) . ' seconds');
      row('Mouse Movements', $v['mouse_movements_count'] ?? $v['mouse_movements']);
      row('Mouse Clicks', $v['mouse_clicks_count'] ?? $v['clicks_count']);
      row('Scroll Events', $v['scroll_events_count']);
      row('Key Events', $v['key_events_count'] ?? $v['keystrokes_count']);
      row('Tab Switches', (int)$v['tab_switches']);
      row('Max Scroll Depth', $v['scroll_depth_max'] . '%');
      row('Total Scroll Distance (px)', $v['total_scroll_distance']);
      row('Pages Viewed', (int)$v['pages_viewed']);
      ?>
    </table>
  </section>

  <!-- ============ Stage 8: Permissions / Media / Speech ============ -->
  <section class="card">
    <h2>🔐 Stage 8 — Permissions, Media & Speech</h2>
    <table class="kv">
      <?php
      row('Permissions API', ynBadge($v['permissions_supported']));
      if (!empty($permissions)) {
          foreach ($permissions as $p => $state) {
              row('  ' . ucfirst($p), $state);
          }
      }
      row('Media Devices API', ynBadge($v['media_devices_supported']));
      row('Audio Inputs', (int)$v['audio_inputs']);
      row('Audio Outputs', (int)$v['audio_outputs']);
      row('Video Inputs', (int)$v['video_inputs']);
      row('Speech Synthesis', ynBadge($v['speech_supported']));
      row('Voices Count', (int)$v['speech_voices_count']);
      ?>
    </table>
  </section>

  <!-- ============ Stage 9: Traffic Source ============ -->
  <section class="card">
    <h2>🚦 Stage 9 — Traffic Source & Page</h2>
    <table class="kv">
      <?php
      row('Traffic Source', ucfirst((string)$v['traffic_source']));
      row('Referrer', $v['referrer'], true);
      row('Referrer Domain', $v['referrer_domain']);
      row('Landing Page', $v['landing_page']);
      row('Current Page URL', $v['page_url']);
      row('Page Path', $v['page_pathname'] ?? $v['page_path']);
      row('Page Title', $v['page_title']);
      row('Page Hostname', $v['page_hostname']);
      row('UTM Source', $v['utm_source']);
      row('UTM Medium', $v['utm_medium']);
      row('UTM Campaign', $v['utm_campaign']);
      row('UTM Content', $v['utm_content']);
      row('UTM Term', $v['utm_term']);
      row('FBCLID', $v['fbclid']);
      row('GCLID', $v['gclid']);
      row('MSCLKID', $v['msclkid']);
      row('TTCLID', $v['ttclid']);
      ?>
    </table>
  </section>

  <!-- ============ Stage 10: Codecs & Features ============ -->
  <section class="card">
    <h2>🎬 Stage 10 — Codecs & Feature Support</h2>
    <table class="kv">
      <?php
      if (!empty($codecSupport) && is_array($codecSupport)) {
          foreach ($codecSupport as $codec => $support) row(str_replace('_', ' ', $codec), $support ?: 'no');
      } else {
          row('Codec Data', '—');
      }
      ?>
    </table>
    <h3 style="margin-top:18px; color: var(--accent-2); font-size:14px;">Browser API Features</h3>
    <table class="kv">
      <?php
      if (!empty($featureSupport) && is_array($featureSupport)) {
          foreach ($featureSupport as $feat => $available) row(str_replace('_', ' ', $feat), ynBadge($available));
      } else {
          row('Feature Data', '—');
      }
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

<script>
// ---------- Relative time (auto-refreshing every 30s) ----------
(function() {
  function relTime(ts) {
    var diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 5)        return 'just now';
    if (diff < 60)       return diff + 's ago';
    var m = Math.floor(diff / 60);
    if (m < 60)          return m + (m === 1 ? ' min ago' : ' mins ago');
    var h = Math.floor(m / 60);
    if (h < 24)          return h + (h === 1 ? ' hr ago' : ' hrs ago');
    var d = Math.floor(h / 24);
    if (d < 7)           return d + (d === 1 ? ' day ago' : ' days ago');
    if (d < 30)          return Math.floor(d/7) + 'w ago';
    return null;
  }
  function refreshTimes() {
    document.querySelectorAll('time[data-ts]').forEach(function(el) {
      var ts = parseInt(el.dataset.ts, 10);
      if (!ts) return;
      var rel = relTime(ts);
      if (rel) el.textContent = rel;
    });
  }
  refreshTimes();
  setInterval(refreshTimes, 30000);
})();

(function() {
  var btn = document.getElementById('btn-copy-full');
  var toastWrap = document.getElementById('toast-container');

  function showToast(msg, type) {
    var t = document.createElement('div');
    t.className = 'toast toast-' + (type || 'info');
    t.textContent = msg;
    toastWrap.appendChild(t);
    setTimeout(function() { t.classList.add('show'); }, 10);
    setTimeout(function() { t.classList.remove('show'); setTimeout(function() { t.remove(); }, 300); }, 3500);
  }

  async function copyToClipboard(text) {
    try {
      if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); return true; }
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.left = '-9999px';
      document.body.appendChild(ta); ta.select();
      var ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return ok;
    } catch (e) { return false; }
  }

  if (btn) btn.addEventListener('click', async function() {
    showToast('Fetching JSON…', 'info');
    try {
      var res = await fetch('/api/get_json.php?ids=<?= (int)$v['id'] ?>', { credentials: 'same-origin' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var data = await res.json();
      if (data && data.error) throw new Error(data.error);
      var ok = await copyToClipboard(JSON.stringify(data, null, 2));
      showToast(ok ? '✓ Full visitor JSON copied to clipboard' : '✗ Clipboard write blocked', ok ? 'success' : 'error');
    } catch (e) {
      showToast('✗ Error: ' + e.message, 'error');
    }
  });

  // ---------- Relative time updater ----------
  function relTime(unix) {
    if (!unix) return '—';
    var diff = Math.floor(Date.now() / 1000) - unix;
    if (diff < 0) diff = 0;
    if (diff < 5)        return 'a few seconds ago';
    if (diff < 60)       return diff + ' seconds ago';
    if (diff < 120)      return '1 minute ago';
    if (diff < 3600)     return Math.floor(diff / 60) + ' minutes ago';
    if (diff < 7200)     return '1 hour ago';
    if (diff < 86400)    return Math.floor(diff / 3600) + ' hours ago';
    if (diff < 172800)   return 'yesterday';
    if (diff < 2592000)  return Math.floor(diff / 86400) + ' days ago';
    if (diff < 5184000)  return '1 month ago';
    if (diff < 31536000) return Math.floor(diff / 2592000) + ' months ago';
    return Math.floor(diff / 31536000) + ' years ago';
  }
  function refreshTimes() {
    document.querySelectorAll('time[data-ts]').forEach(function(el) {
      var ts = parseInt(el.getAttribute('data-ts'), 10);
      if (!ts) return;
      if (!el.dataset.absolute) el.dataset.absolute = el.textContent;
      el.textContent = relTime(ts) + ' (' + el.dataset.absolute + ')';
      el.title = el.dataset.absolute;
    });
  }
  refreshTimes();
  setInterval(refreshTimes, 30000);
})();
</script>

</body>
</html>
