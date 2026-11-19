<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/notifications.php
 * Purpose : Notifications of the logged-in admin.
 *           The page itself is shared: includes/pages/notifications.php
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

require APP_ROOT . '/includes/pages/notifications.php';
