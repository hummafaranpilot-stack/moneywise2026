<?php
/**
 * ============================================================================
 * Money Wise 2026 — Dashboard Login
 * Rate-limited password authentication.
 * ============================================================================
 */

// TEMP: enable error display for debugging. Remove once stable.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/helpers.php';

// Session config — HTTPS-only, HttpOnly, SameSite Strict
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

// Already authenticated? -> straight to dashboard
if (!empty($_SESSION['mw_auth'])) {
    header('Location: index.php');
    exit;
}

$ip = getRealIP();
$error = '';

// ---------------- Rate-limit check ----------------
function isLockedOut(string $ip): array {
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND success = 0
             AND attempt_time > (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->execute([$ip, LOGIN_LOCKOUT_MINS]);
        $count = (int)$stmt->fetchColumn();
        return ['locked' => $count >= LOGIN_MAX_ATTEMPTS, 'count' => $count];
    } catch (Exception $e) {
        return ['locked' => false, 'count' => 0];
    }
}

function recordAttempt(string $ip, bool $success): void {
    try {
        $stmt = db()->prepare("INSERT INTO login_attempts (ip_address, success) VALUES (?, ?)");
        $stmt->execute([$ip, $success ? 1 : 0]);
    } catch (Exception $e) { /* silent */ }
}

// ---------------- CSRF token ----------------
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$lock = isLockedOut($ip);

// ---------------- Handle login POST ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($lock['locked']) {
        $error = 'Too many failed attempts. Locked out for ' . LOGIN_LOCKOUT_MINS . ' minutes.';
    } elseif (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $error = 'Invalid request token. Please reload and try again.';
    } else {
        $pw = (string)($_POST['password'] ?? '');
        if (hash_equals(DASHBOARD_PASSWORD, $pw)) {
            recordAttempt($ip, true);
            session_regenerate_id(true);
            $_SESSION['mw_auth'] = true;
            $_SESSION['mw_login_time'] = time();
            $_SESSION['mw_login_ip'] = $ip;
            header('Location: index.php');
            exit;
        } else {
            recordAttempt($ip, false);
            $lock = isLockedOut($ip);
            $remaining = max(0, LOGIN_MAX_ATTEMPTS - $lock['count']);
            $error = $lock['locked']
                ? 'Too many failed attempts. Locked out for ' . LOGIN_LOCKOUT_MINS . ' minutes.'
                : 'Wrong password. ' . $remaining . ' attempts remaining.';
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Dashboard Login — Money Wise 2026</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/svg+xml" href="../favicon.svg">
</head>
<body class="login-body">
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-logo">Money Wise <span>2026</span></div>
      <p class="login-subtitle">Tracker Dashboard</p>

      <?php if ($error): ?>
        <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <?php if ($lock['locked']): ?>
        <div class="login-locked">Account locked. Try again later.</div>
      <?php else: ?>
        <form method="POST" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
          <label for="pw">Password</label>
          <input type="password" id="pw" name="password" required autofocus
                 inputmode="numeric" pattern="[0-9]*" autocomplete="off">
          <button type="submit">Sign In</button>
        </form>
      <?php endif; ?>

      <p class="login-footer">moneywise2026.com</p>
    </div>
  </div>
</body>
</html>
