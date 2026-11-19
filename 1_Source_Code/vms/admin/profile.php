<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/profile.php
 * Purpose : "My profile" of the logged-in admin (details + password).
 *           The page itself is shared: includes/pages/profile.php
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

require APP_ROOT . '/includes/pages/profile.php';
