<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/init.php
 * Purpose : Loaded first by every page. It loads the configuration,
 *           prepares error reporting, timezone and session, and then
 *           includes all helper libraries.
 * ---------------------------------------------------------------------
 */

// Load the settings file
require_once __DIR__ . '/../config/config.php';

// Error reporting: show everything while developing, hide in production
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// All dates and times follow Pakistan Standard Time
date_default_timezone_set(APP_TIMEZONE);

// Absolute folder path of the project (e.g. C:/xampp/htdocs/vms)
define('APP_ROOT', dirname(__DIR__));

// Basic security headers: no framing by other sites, no MIME sniffing
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

// Start a session with safer cookie flags (HttpOnly, SameSite)
if (session_status() === PHP_SESSION_NONE) {
    session_name('teekacard_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Helper libraries (order matters: functions first)
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/schedule.php';
require_once __DIR__ . '/bookings.php';
require_once __DIR__ . '/accounts.php';

// Web path of the project folder, worked out once per request
define('BASE_PATH', detect_base_path());
