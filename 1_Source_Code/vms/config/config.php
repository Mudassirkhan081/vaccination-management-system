<?php
/**
 * ---------------------------------------------------------------------
 * TeekaCard - Vaccination Management System
 * File    : config/config.php
 * Purpose : Central settings for the whole application.
 *           Change the DB_* values if your MySQL user/password differ
 *           from the XAMPP defaults (user "root", empty password).
 * ---------------------------------------------------------------------
 */

// ----- Database connection (XAMPP / WAMP defaults) -----
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'vaccination_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ----- Application identity -----
define('APP_NAME', 'TeekaCard');
define('APP_TAGLINE', 'Vaccination Management System');
define('APP_VERSION', '1.0.0');

// ----- Regional settings -----
define('APP_TIMEZONE', 'Asia/Karachi');

// ----- Web path of the project folder -----
// Leave empty to detect it automatically (recommended).
// If links or CSS ever break, set it by hand, e.g. '/vaccination-management-system'
define('BASE_PATH_OVERRIDE', '');

// ----- Behaviour -----
define('PER_PAGE', 15);             // rows per page in long tables
define('DUE_SOON_DAYS', 14);        // a dose is "due soon" inside this many days
define('MAX_BOOKING_DAYS', 180);    // parents can book up to this many days ahead
define('SLOT_CAPACITY', 3);         // children one hospital can see in the same half-hour slot
define('MAX_CHILD_AGE_YEARS', 12);  // the system is for infants and children under teenage
define('LOGIN_MAX_ATTEMPTS', 5);    // failed logins before a short lock
define('LOGIN_LOCK_SECONDS', 60);   // length of that lock

// Show PHP errors on screen while developing (set to false when deployed)
define('APP_DEBUG', true);
