<?php
/**
 * ============================================================================
 * Money Wise 2026 — Tracker Config
 * ============================================================================
 *
 * IMPORTANT SECURITY NOTE:
 * This file contains live credentials. If this repository is ever made PUBLIC,
 * IMMEDIATELY rotate all of the following:
 *   - ipinfo.io token
 *   - proxycheck.io key
 *   - Dashboard password
 *   - MySQL database password
 *
 * For higher security: add this file to .gitignore and upload manually via FTP.
 * ============================================================================
 */

// -------- MySQL Database Credentials (Hostinger) --------
define('DB_HOST',     'localhost');
define('DB_NAME',     'u373133718_moneywise');
define('DB_USER',     'u373133718_moneywise');
define('DB_PASS',     'Ali547$$$');
define('DB_CHARSET',  'utf8mb4');

// -------- External API Keys --------
define('IPINFO_TOKEN',     'b40518a75f376c');
define('PROXYCHECK_KEY',   '992986-k17om9-7188pj-24l64l');

// -------- Dashboard Authentication --------
// To change: replace value below with new password. No hashing — short numeric password
// is mitigated by rate limiting (5 attempts / 15 min) and HTTPS-only session cookies.
define('DASHBOARD_PASSWORD', '786547');

// -------- Rate Limiting --------
define('LOGIN_MAX_ATTEMPTS',  5);        // failed attempts before lockout
define('LOGIN_LOCKOUT_MINS',  15);       // lockout duration in minutes
define('API_RATE_LIMIT',      60);       // log.php max requests per IP per minute

// -------- Session Configuration --------
define('SESSION_LIFETIME', 86400 * 30);  // 30 days
define('SESSION_NAME',     'mw_admin');

// -------- Tracker Behavior --------
define('EXCLUDE_PATHS',    ['/api/', '/report/']);  // never track these paths
define('MAX_BEHAVIOR_EVENTS_PER_SESSION', 200);
