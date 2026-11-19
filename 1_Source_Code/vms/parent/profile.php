<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/profile.php
 * Purpose : "My profile" of the logged-in parent (details + password).
 *           The page itself is shared: includes/pages/profile.php
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

require APP_ROOT . '/includes/pages/profile.php';
