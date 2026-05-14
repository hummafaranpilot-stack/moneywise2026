<?php
/**
 * ============================================================================
 * Money Wise 2026 — Schema Migration Runner v2
 * Runs database/schema_v3.sql (ad_clicks table).
 * Password-protected. Idempotent.
 * Open in browser → enter password → click Run.
 * DELETE THIS FILE after migration completes.
 * ============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

$schemaFile = __DIR__ . '/../database/schema_v3.sql';
$authed = false;
$ranResults = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if (!hash_equals(DASHBOARD_PASSWORD, $pw)) {
        $error = 'Wrong password';
    } else {
        $authed = true;
        if (!file_exists($schemaFile)) {
            $error = 'schema_v3.sql not found at: ' . $schemaFile;
        } else {
            $sql = file_get_contents($schemaFile);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            // Split on semicolons but keep multi-line CREATE statements together
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            $ranResults = [];
            $pdo = db();
            foreach ($statements as $stmt) {
                $label = '';
                if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $stmt, $m))     $label = 'CREATE TABLE ' . $m[1];
                elseif (preg_match('/ALTER TABLE\s+`?(\w+)`?/i', $stmt, $m))                $label = 'ALTER TABLE ' . $m[1];
                else $label = substr(preg_replace('/\s+/', ' ', $stmt), 0, 60) . '…';
                try {
                    $pdo->exec($stmt);
                    $ranResults[] = ['stmt' => $label, 'status' => 'OK', 'error' => null];
                } catch (Exception $e) {
                    $msg = $e->getMessage();
                    $isDup = (stripos($msg, 'duplicate') !== false) || (stripos($msg, 'already exists') !== false);
                    $ranResults[] = ['stmt' => $label, 'status' => $isDup ? 'EXISTS' : 'ERROR', 'error' => $isDup ? null : $msg];
                }
            }
        }
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Schema v3 Migration — Money Wise 2026</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #0f1419; color: #e6edf3; padding: 20px; margin: 0; line-height: 1.5; }
    .wrap { max-width: 900px; margin: 0 auto; }
    h1 { font-family: Georgia, serif; color: #00d68f; margin-top: 0; }
    .card { background: #1a2028; border: 1px solid #2d3540; border-radius: 8px;
      padding: 24px; margin-bottom: 20px; }
    label { display: block; font-size: 12px; text-transform: uppercase;
      letter-spacing: 0.1em; color: #8b949e; margin-bottom: 6px; }
    input[type="password"] { width: 100%; padding: 12px; background: #232a35;
      border: 1px solid #2d3540; color: #e6edf3; border-radius: 6px;
      font-size: 16px; box-sizing: border-box; }
    input[type="password"]:focus { outline: none; border-color: #00d68f; }
    button { margin-top: 14px; padding: 12px 24px; background: #00875a;
      color: white; border: none; border-radius: 6px; font-size: 14px;
      font-weight: 600; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; }
    button:hover { background: #00d68f; }
    .err { background: rgba(248,81,73,0.1); border: 1px solid #f85149;
      color: #f85149; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
    .summary { background: rgba(0,135,90,0.1); border: 1px solid #00875a;
      color: #00d68f; padding: 14px 18px; border-radius: 6px; margin-bottom: 16px;
      font-weight: 600; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
    th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #2d3540; }
    th { background: #232a35; color: #8b949e; font-size: 11px;
      text-transform: uppercase; letter-spacing: 0.08em; }
    .ok      { color: #3fb950; font-weight: 700; }
    .exists  { color: #d29922; font-weight: 700; }
    .err-cell{ color: #f85149; font-weight: 700; }
    .warn { background: rgba(210,153,34,0.1); border: 1px solid #d29922;
      color: #d29922; padding: 16px; border-radius: 6px; margin-top: 24px; font-size: 14px; }
    code { background: #232a35; padding: 2px 8px; border-radius: 4px; color: #00d68f; }
  </style>
</head>
<body>
<div class="wrap">
  <h1>🗄️ Schema v3 Migration</h1>
  <p style="color: #8b949e;">Adds <code>ad_clicks</code> table for click tracking.</p>

  <?php if (!$authed): ?>
    <div class="card">
      <p>Enter dashboard password to run <code>schema_v3.sql</code>. Idempotent — safe to re-run.</p>
      <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
      <form method="POST" autocomplete="off">
        <label for="pw">Dashboard Password</label>
        <input type="password" id="pw" name="password" required autofocus inputmode="numeric">
        <button type="submit">▶ Run Migration</button>
      </form>
    </div>
  <?php else: ?>
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <?php if ($ranResults !== null):
      $ok = count(array_filter($ranResults, fn($r) => $r['status'] === 'OK'));
      $ex = count(array_filter($ranResults, fn($r) => $r['status'] === 'EXISTS'));
      $er = count(array_filter($ranResults, fn($r) => $r['status'] === 'ERROR'));
    ?>
      <div class="summary">
        ✅ Migration finished. Total: <?= count($ranResults) ?>
        &nbsp;·&nbsp; OK: <?= $ok ?>
        &nbsp;·&nbsp; Already existed: <?= $ex ?>
        &nbsp;·&nbsp; Errors: <?= $er ?>
      </div>
      <div class="card">
        <h3 style="margin-top:0;">Results</h3>
        <table>
          <thead><tr><th>#</th><th>Statement</th><th>Status</th><th>Error</th></tr></thead>
          <tbody>
            <?php foreach ($ranResults as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= h($r['stmt']) ?></code></td>
                <td>
                  <?php if ($r['status'] === 'OK'): ?><span class="ok">✓ OK</span>
                  <?php elseif ($r['status'] === 'EXISTS'): ?><span class="exists">⊙ EXISTS</span>
                  <?php else: ?><span class="err-cell">✗ ERROR</span><?php endif; ?>
                </td>
                <td><?= $r['error'] ? '<small style="color:#f85149;">' . h($r['error']) . '</small>' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
      try {
          $tableExists = (int)db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ad_clicks'")->fetchColumn();
      } catch (Exception $e) { $tableExists = 0; }
      ?>
      <div class="card">
        <h3 style="margin-top:0;">Verification</h3>
        <p>✅ <code>ad_clicks</code> table exists: <strong><?= $tableExists ? 'YES' : 'NO' ?></strong></p>
      </div>
    <?php endif; ?>

    <div class="warn">
      ⚠️ <strong>SECURITY: Delete this file now.</strong><br><br>
      Tell Claude <strong>"delete migrate.php"</strong> after confirming migration worked.
    </div>
    <p style="margin-top: 24px; text-align: center;">
      <a href="/report/login.php" style="color: #00d68f; text-decoration: none; font-weight: 600;">→ Open Dashboard</a>
    </p>
  <?php endif; ?>
</div>
</body>
</html>
