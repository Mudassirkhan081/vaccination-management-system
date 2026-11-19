<?php
/**
 * ---------------------------------------------------------------------
 * File    : logout.php
 * Purpose : Ends the session. Only a POST with a valid CSRF token logs
 *           the user out, so another website cannot log users out with
 *           a hidden link or image.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

// A plain visit (GET) just goes back to the right place
if (!is_post()) {
    redirect(is_logged_in() ? dashboard_path(current_role()) : 'index.php');
}

// Check the token by hand: verify_csrf() would redirect back to this page
$sent = $_POST['csrf_token'] ?? '';
if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
    flash('warning', 'Your session had expired. Please try logging out again.');
    redirect(is_logged_in() ? dashboard_path(current_role()) : 'index.php');
}

$role = current_role();
logout_user();

// Start a new, empty session only to show the goodbye message
restart_session();
flash('success', 'You have logged out safely.');
redirect('login.php' . ($role ? '?role=' . urlencode($role) : ''));
