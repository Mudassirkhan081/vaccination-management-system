<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/notifications.php
 * Purpose : Notifications of the logged-in parent.
 *           The page itself is shared: includes/pages/notifications.php
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

require APP_ROOT . '/includes/pages/notifications.php';
