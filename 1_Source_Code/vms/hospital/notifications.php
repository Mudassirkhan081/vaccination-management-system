<?php
/**
 * ---------------------------------------------------------------------
 * File    : hospital/notifications.php
 * Purpose : Notifications of the logged-in hospital.
 *           The page itself is shared: includes/pages/notifications.php
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('hospital');

require APP_ROOT . '/includes/pages/notifications.php';
