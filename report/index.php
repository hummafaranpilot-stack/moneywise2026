<?php
declare(strict_types=1);
/**
 * ============================================================================
 * Money Wise 2026 — Visitor Tracker Dashboard
 * Filterable table of all visitors with stats, sortable columns, CSV export.
 * ============================================================================
 */

// TEMP: enable error display for debugging. Remove once stable.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';

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

if (empty($_SESSION['mw_auth'])) {
    header('Location: login.php');
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

// ---------------- Filters ----------------
$dateFrom    = $_GET['from']    ?? date('Y-m-d', strtotime('-7 days'));
$dateTo      = $_GET['to']      ?? date('Y-m-d');
$risk        = $_GET['risk']    ?? '';        // low | medium | high
$country     = $_GET['country'] ?? '';
$source      = $_GET['source']  ?? '';
$search      = trim($_GET['q']  ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 50;
$offset      = ($page - 1) * $perPage;

$where = ['DATE(visit_time) BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];
if ($risk    !== '') { $where[] = 'risk_level = ?';     $params[] = $risk; }
if ($country !== '') { $where[] = 'country_code = ?';   $params[] = $country; }
if ($source  !== '') { $where[] = 'traffic_source = ?'; $params[] = $source; }
if ($search  !== '') {
    $where[] = '(ip_address LIKE ? OR country LIKE ? OR city LIKE ? OR isp LIKE ? OR visitor_id LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

// ---------------- CSV Export ----------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = db()->prepare("
        SELECT visit_time, ip_address, country, city, isp, browser_name, os_name, device_type,
               traffic_source, session_duration, scroll_depth_max, mouse_movements, clicks_count,
               risk_score, risk_level, page_url, referrer
        FROM visitors WHERE $whereSql ORDER BY visit_time DESC LIMIT 5000
    ");
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=visitors-' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time','IP','Country','City','ISP','Browser','OS','Device',
                   'Traffic','Duration (s)','Scroll %','Mouse','Clicks',
                   'Risk Score','Risk Level','Page','Referrer']);
    while ($r = $stmt->fetch()) fputcsv($out, array_values($r));
    fclose($out);
    exit;
}

// ---------------- Stats (today) ----------------
function dbScalar(string $sql, array $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

$today        = date('Y-m-d');
$totalToday   = (int) dbScalar("SELECT COUNT(*) FROM visitors WHERE DATE(visit_time) = ?", [$today]);
$highRisk     = (int) dbScalar("SELECT COUNT(*) FROM visitors WHERE DATE(visit_time) = ? AND risk_level = 'high'", [$today]);
$avgRisk      = (float) dbScalar("SELECT AVG(risk_score) FROM visitors WHERE DATE(visit_time) = ?", [$today]);
$topSource    = (string) dbScalar(
    "SELECT traffic_source FROM visitors WHERE DATE(visit_time) = ?
     GROUP BY traffic_source ORDER BY COUNT(*) DESC LIMIT 1",
    [$today]
) ?: '—';
$totalAllTime = (int) dbScalar("SELECT COUNT(*) FROM visitors");

// Ad click stats (gracefully handle missing ad_clicks table)
try {
    $clicksToday = (int) dbScalar("SELECT COUNT(*) FROM ad_clicks WHERE DATE(clicked_at) = ?", [$today]);
    $clicksTotal = (int) dbScalar("SELECT COUNT(*) FROM ad_clicks");
} catch (Exception $e) {
    $clicksToday = 0;
    $clicksTotal = 0;
}

// ---------------- Total filtered count ----------------
$countStmt = db()->prepare("SELECT COUNT(*) FROM visitors WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

// ---------------- Visitor list ----------------
$listStmt = db()->prepare("
    SELECT id, visit_time, ip_address, country, country_code, city, isp,
           browser_name, browser_version, os_name, device_type,
           traffic_source, session_duration, scroll_depth_max,
           mouse_movements, clicks_count, risk_score, risk_level, risk_flags,
           is_proxy, is_vpn, is_tor, is_datacenter, page_url, page_title
    FROM visitors
    WHERE $whereSql
    ORDER BY visit_time DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

// ---------------- Filter dropdown values ----------------
$countries = db()->query("
    SELECT country_code, MAX(country) AS country, COUNT(*) c FROM visitors
    WHERE country_code IS NOT NULL AND country_code != ''
    GROUP BY country_code ORDER BY c DESC LIMIT 50
")->fetchAll();
$sources = db()->query("
    SELECT traffic_source, COUNT(*) c FROM visitors
    WHERE traffic_source IS NOT NULL
    GROUP BY traffic_source ORDER BY c DESC
")->fetchAll();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flagEmoji(?string $cc): string {
    if (!$cc || strlen($cc) !== 2) return '🌐';
    $cc = strtoupper($cc);
    return mb_chr(0x1F1E6 + (ord($cc[0]) - 65)) . mb_chr(0x1F1E6 + (ord($cc[1]) - 65));
}
function duration(int $s): string {
    if ($s < 60) return $s . 's';
    if ($s < 3600) return floor($s / 60) . 'm ' . ($s % 60) . 's';
    return floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm';
}
// Convert MySQL DATETIME (assumed UTC) -> PKT info: ['ts' => unix, 'pkt' => formatted PKT]
function pktTime(?string $mysqlDt): array {
    if (!$mysqlDt) return ['ts' => 0, 'pkt' => '—'];
    try {
        $dt = new DateTime($mysqlDt, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Karachi'));
        return ['ts' => $dt->getTimestamp(), 'pkt' => $dt->format('M j, H:i') . ' PKT'];
    } catch (Exception $e) {
        return ['ts' => 0, 'pkt' => $mysqlDt];
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Tracker Dashboard — Money Wise 2026</title>
  <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?: time() ?>">
  <link rel="icon" type="image/svg+xml" href="../favicon.svg">
</head>
<body>

<header class="dash-header">
  <div class="dash-header-inner">
    <div class="dash-logo">Money Wise <span>2026</span> · Tracker</div>
    <nav class="dash-nav">
      <a href="/" target="_blank">View Site ↗</a>
      <a href="?logout=1" class="logout">Logout</a>
    </nav>
  </div>
</header>

<main class="dash-main">

  <!-- ============ Stats ============ -->
  <section class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Visitors Today</div>
      <div class="stat-value"><?= number_format($totalToday) ?></div>
    </div>
    <div class="stat-card stat-warn">
      <div class="stat-label">High-Risk Today</div>
      <div class="stat-value"><?= number_format($highRisk) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Avg Risk Score</div>
      <div class="stat-value"><?= number_format($avgRisk, 1) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Top Source</div>
      <div class="stat-value stat-sm"><?= h(ucfirst($topSource)) ?></div>
    </div>
    <div class="stat-card stat-muted">
      <div class="stat-label">All-Time Visits</div>
      <div class="stat-value"><?= number_format($totalAllTime) ?></div>
    </div>
    <div class="stat-card" style="border-color: #58a6ff;">
      <div class="stat-label">Ad Clicks Today</div>
      <div class="stat-value" style="color: #58a6ff;"><?= number_format($clicksToday) ?></div>
    </div>
    <div class="stat-card stat-muted">
      <div class="stat-label">Total Ad Clicks</div>
      <div class="stat-value"><?= number_format($clicksTotal) ?></div>
    </div>
  </section>

  <!-- ============ Filters ============ -->
  <form class="filters" method="GET">
    <div class="filter-row">
      <label>From <input type="date" name="from" value="<?= h($dateFrom) ?>"></label>
      <label>To <input type="date" name="to" value="<?= h($dateTo) ?>"></label>
      <label>Risk
        <select name="risk">
          <option value="">All</option>
          <option value="low"    <?= $risk === 'low' ? 'selected' : '' ?>>Low</option>
          <option value="medium" <?= $risk === 'medium' ? 'selected' : '' ?>>Medium</option>
          <option value="high"   <?= $risk === 'high' ? 'selected' : '' ?>>High</option>
        </select>
      </label>
      <label>Country
        <select name="country">
          <option value="">All</option>
          <?php foreach ($countries as $c): ?>
            <option value="<?= h($c['country_code']) ?>" <?= $country === $c['country_code'] ? 'selected' : '' ?>>
              <?= flagEmoji($c['country_code']) ?> <?= h($c['country'] ?: $c['country_code']) ?> (<?= $c['c'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Source
        <select name="source">
          <option value="">All</option>
          <?php foreach ($sources as $s): ?>
            <option value="<?= h($s['traffic_source']) ?>" <?= $source === $s['traffic_source'] ? 'selected' : '' ?>>
              <?= h(ucfirst($s['traffic_source'])) ?> (<?= $s['c'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Search
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="IP, ISP, city, visitor ID…">
      </label>
      <button type="submit">Apply</button>
      <a class="btn-secondary" href="?<?= h(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
      <a class="btn-secondary" href="index.php">Reset</a>
    </div>
  </form>

  <!-- ============ Bulk Actions ============ -->
  <div class="bulk-actions-bar">
    <span class="action-label">Bulk Actions</span>
    <button id="btn-copy-selected" class="action-btn primary" disabled>📋 Copy Selected JSON (<span id="selected-count">0</span>)</button>
    <button id="btn-copy-page" class="action-btn">📄 Copy Page JSON (<?= count($rows) ?>)</button>
    <button id="btn-copy-all" class="action-btn">🗂️ Copy ALL JSON (Database)</button>
    <a class="action-btn" href="?<?= h(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">💾 Export CSV (Filtered)</a>
  </div>
  <div id="toast-container"></div>

  <!-- ============ Table ============ -->
  <section class="table-wrap">
    <div class="table-meta">
      Showing <?= number_format(count($rows)) ?> of <?= number_format($totalRows) ?> visitors
      &nbsp;·&nbsp; Page <?= $page ?> / <?= $totalPages ?>
    </div>
    <table class="visitors-table">
      <thead>
        <tr>
          <th class="col-check"><input type="checkbox" id="select-all-checkbox"></th>
          <th>Time</th>
          <th>IP / Location</th>
          <th>ISP / ASN</th>
          <th>Browser / OS</th>
          <th>Device</th>
          <th>Traffic</th>
          <th>Duration</th>
          <th>Scroll</th>
          <th>Risk</th>
          <th>Flags</th>
          <th>Page</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="13" class="empty">No visitors found for these filters.</td></tr>
      <?php else: foreach ($rows as $r):
          $flagsArr = $r['risk_flags'] ? (json_decode($r['risk_flags'], true) ?: []) : [];
          $proxyBadges = [];
          if ($r['is_vpn'])        $proxyBadges[] = 'VPN';
          if ($r['is_proxy'])      $proxyBadges[] = 'PROXY';
          if ($r['is_tor'])        $proxyBadges[] = 'TOR';
          if ($r['is_datacenter']) $proxyBadges[] = 'DC';
          $riskClass = 'risk-' . $r['risk_level'];
      ?>
        <tr class="<?= h($riskClass) ?>">
          <td class="col-check"><input type="checkbox" class="row-check" data-id="<?= (int)$r['id'] ?>"></td>
          <?php $tInfo = pktTime($r['visit_time']); ?>
          <td class="nowrap"><time data-ts="<?= (int)$tInfo['ts'] ?>" title="<?= h($tInfo['pkt']) ?>"><?= h($tInfo['pkt']) ?></time></td>
          <td>
            <div class="mono"><?= h($r['ip_address']) ?></div>
            <div class="muted">
              <?= flagEmoji($r['country_code']) ?> <?= h($r['country'] ?: '—') ?>
              <?php if ($r['city']): ?> · <?= h($r['city']) ?><?php endif; ?>
            </div>
          </td>
          <td>
            <div><?= h(mb_strimwidth((string)$r['isp'], 0, 32, '…')) ?></div>
            <?php if (!empty($proxyBadges)): ?>
              <div class="badges">
                <?php foreach ($proxyBadges as $b): ?><span class="badge badge-red"><?= h($b) ?></span><?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td><?= h($r['browser_name']) ?> <?= h($r['browser_version']) ?><br>
              <span class="muted"><?= h($r['os_name']) ?></span></td>
          <td><span class="device-pill"><?= h($r['device_type']) ?></span></td>
          <td><?= h(ucfirst($r['traffic_source'] ?? 'direct')) ?></td>
          <td><?= duration((int)$r['session_duration']) ?></td>
          <td><?= (int)$r['scroll_depth_max'] ?>%</td>
          <td>
            <span class="risk-score"><?= (int)$r['risk_score'] ?></span>
            <span class="risk-pill risk-pill-<?= h($r['risk_level']) ?>"><?= h(strtoupper($r['risk_level'] ?? '—')) ?></span>
          </td>
          <td>
            <?php if (!empty($flagsArr)): ?>
              <span class="muted small"><?= h(implode(', ', array_slice($flagsArr, 0, 3))) ?>
                <?php if (count($flagsArr) > 3): ?> +<?= count($flagsArr) - 3 ?><?php endif; ?>
              </span>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
          <td><span class="muted small"><?= h(mb_strimwidth(parse_url($r['page_url'] ?? '', PHP_URL_PATH) ?: '/', 0, 28, '…')) ?></span></td>
          <td><a class="btn-mini" href="visitor.php?id=<?= (int)$r['id'] ?>">View</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </section>

  <!-- ============ Pagination ============ -->
  <?php if ($totalPages > 1): ?>
    <nav class="pagination">
      <?php
      $qs = $_GET; unset($qs['page']);
      $base = 'index.php?' . http_build_query($qs);
      $start = max(1, $page - 3); $end = min($totalPages, $page + 3);
      ?>
      <?php if ($page > 1): ?><a href="<?= h($base) ?>&page=1">« First</a><?php endif; ?>
      <?php if ($page > 1): ?><a href="<?= h($base) ?>&page=<?= $page - 1 ?>">‹ Prev</a><?php endif; ?>
      <?php for ($p = $start; $p <= $end; $p++): ?>
        <?php if ($p === $page): ?><span class="current"><?= $p ?></span>
        <?php else: ?><a href="<?= h($base) ?>&page=<?= $p ?>"><?= $p ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?><a href="<?= h($base) ?>&page=<?= $page + 1 ?>">Next ›</a><?php endif; ?>
      <?php if ($page < $totalPages): ?><a href="<?= h($base) ?>&page=<?= $totalPages ?>">Last »</a><?php endif; ?>
    </nav>
  <?php endif; ?>

</main>

<footer class="dash-footer">
  <p>Money Wise 2026 Tracker · Internal use only · Data never shared.</p>
</footer>

<script>
(function() {
  'use strict';
  var selectAll  = document.getElementById('select-all-checkbox');
  var rowChecks  = document.querySelectorAll('.row-check');
  var btnSel     = document.getElementById('btn-copy-selected');
  var btnPage    = document.getElementById('btn-copy-page');
  var btnAll     = document.getElementById('btn-copy-all');
  var countEl    = document.getElementById('selected-count');
  var toastWrap  = document.getElementById('toast-container');

  function updateUI() {
    var n = document.querySelectorAll('.row-check:checked').length;
    countEl.textContent = n;
    btnSel.disabled = n === 0;
    btnSel.style.opacity = n === 0 ? '0.5' : '1';
    var total = rowChecks.length;
    selectAll.checked = n === total && total > 0;
    selectAll.indeterminate = n > 0 && n < total;
  }

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      rowChecks.forEach(function(cb) { cb.checked = selectAll.checked; });
      updateUI();
    });
  }
  rowChecks.forEach(function(cb) { cb.addEventListener('change', updateUI); });

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
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
      }
      var ta = document.createElement('textarea');
      ta.value = text; ta.style.position = 'fixed'; ta.style.left = '-9999px';
      document.body.appendChild(ta); ta.select();
      var ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return ok;
    } catch (e) { return false; }
  }

  async function fetchAndCopy(url, label) {
    showToast('Fetching ' + label + '…', 'info');
    try {
      var res = await fetch(url, { credentials: 'same-origin' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var data = await res.json();
      if (data && data.error) throw new Error(data.error);
      var text = JSON.stringify(data, null, 2);
      var ok = await copyToClipboard(text);
      if (ok) showToast('✓ Copied ' + (Array.isArray(data) ? data.length : 1) + ' visitor(s) JSON to clipboard', 'success');
      else    showToast('✗ Clipboard write blocked. Use HTTPS or grant permission.', 'error');
    } catch (e) {
      showToast('✗ Error: ' + e.message, 'error');
    }
  }

  if (btnSel) btnSel.addEventListener('click', function() {
    var ids = Array.from(document.querySelectorAll('.row-check:checked')).map(function(cb) { return cb.dataset.id; });
    if (!ids.length) return;
    fetchAndCopy('/api/get_json.php?ids=' + ids.join(','), ids.length + ' selected');
  });

  if (btnPage) btnPage.addEventListener('click', function() {
    var ids = Array.from(rowChecks).map(function(cb) { return cb.dataset.id; });
    if (!ids.length) { showToast('No rows on page', 'error'); return; }
    fetchAndCopy('/api/get_json.php?ids=' + ids.join(','), 'this page (' + ids.length + ')');
  });

  if (btnAll) btnAll.addEventListener('click', function() {
    if (!confirm('Copy ALL visitors in database to clipboard? This may be a large amount of data.')) return;
    fetchAndCopy('/api/get_json.php?all=1', 'all visitors');
  });

  updateUI();

  // ---------- Relative time updater ----------
  // Replaces text of <time data-ts="UNIX"> with "5 sec ago" style. Refreshes every 30s.
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
      el.textContent = relTime(ts);
      el.title = el.dataset.absolute;
    });
  }
  refreshTimes();
  setInterval(refreshTimes, 30000);
})();
</script>

</body>
</html>
