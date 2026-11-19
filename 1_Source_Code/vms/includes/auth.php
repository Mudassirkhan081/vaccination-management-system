<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/auth.php
 * Purpose : Login sessions and role-based access control for the three
 *           user types: admin, parent and hospital.
 * ---------------------------------------------------------------------
 */

/** Maps each role to its database table and dashboard. */
function role_config(string $role): ?array
{
    $roles = [
        'admin'    => ['table' => 'admins',    'home' => 'admin/index.php',    'label' => 'Administrator'],
        'parent'   => ['table' => 'parents',   'home' => 'parent/index.php',   'label' => 'Parent'],
        'hospital' => ['table' => 'hospitals', 'home' => 'hospital/index.php', 'label' => 'Hospital'],
    ];
    return $roles[$role] ?? null;
}

/**
 * Starts a logged-in session for a user row.
 * session_regenerate_id() stops session-fixation attacks.
 */
function login_user(string $role, array $user): void
{
    session_regenerate_id(true);
    $_SESSION['auth'] = [
        'role'  => $role,
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
    ];
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
}

/** Ends the session completely. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Starts a new, empty session right after logout_user(), only so that a
 * message can be shown on the next page. The new session id also
 * replaces the cookie that logout_user() asked the browser to delete.
 */
function restart_session(): void
{
    session_start();
    session_regenerate_id(true);
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth']['id']);
}

function current_role(): string
{
    return $_SESSION['auth']['role'] ?? '';
}

function current_user_id(): int
{
    return (int) ($_SESSION['auth']['id'] ?? 0);
}

function current_user_name(): string
{
    return $_SESSION['auth']['name'] ?? '';
}

/** Updates the cached name/email after a profile change. */
function refresh_session_user(string $name, string $email): void
{
    $_SESSION['auth']['name']  = $name;
    $_SESSION['auth']['email'] = $email;
}

/** Dashboard URL for a role. */
function dashboard_path(string $role): string
{
    $cfg = role_config($role);
    return $cfg ? $cfg['home'] : 'index.php';
}

/**
 * Protects a page: only a logged-in user of the given role may continue.
 * The account is re-checked in the database on every request so that a
 * blocked parent or deactivated hospital is logged out immediately.
 */
function require_role(string $role): void
{
    if (!is_logged_in() || current_role() !== $role) {
        // Remember the page so the user comes back to it after logging in
        if (!is_post()) {
            $_SESSION['intended'] = ['role' => $role, 'url' => $_SERVER['REQUEST_URI'] ?? ''];
        }
        flash('warning', 'Please log in to continue.');
        redirect('login.php?role=' . urlencode($role));
    }

    $cfg  = role_config($role);
    $user = db_one('SELECT * FROM `' . $cfg['table'] . '` WHERE id = ?', [current_user_id()]);

    $blocked = !$user
        || ($role === 'parent' && $user['status'] !== 'Active')
        || ($role === 'hospital' && $user['status'] !== 'Active');

    if ($blocked) {
        logout_user();
        restart_session();
        flash('danger', 'Your account is not active. Please contact the administrator.');
        redirect('login.php?role=' . urlencode($role));
    }

    // Make the fresh account row available to the page
    $GLOBALS['authUser'] = $user;
}

/**
 * Page to open right after login: the protected page the user tried to
 * open (only if it belongs to the same role), otherwise the dashboard.
 */
function after_login_path(string $role): string
{
    $intended = $_SESSION['intended'] ?? null;
    unset($_SESSION['intended']);

    if (is_array($intended) && ($intended['role'] ?? '') === $role) {
        $target = (string) ($intended['url'] ?? '');
        // Only local paths inside this role's folder are accepted (no open redirects)
        if (str_starts_with($target, BASE_PATH . '/' . $role . '/') && !str_starts_with($target, '//')) {
            return $target;
        }
    }
    return dashboard_path($role);
}

/** Sends logged-in users straight to their dashboard (used on login/register). */
function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        redirect(dashboard_path(current_role()));
    }
}

/* ------------------------------------------------------------------
 * Simple brute-force protection for the login form
 * ------------------------------------------------------------------ */

/** Seconds left on the login lock (0 when not locked). */
function login_lock_remaining(): int
{
    $until = (int) ($_SESSION['login_locked_until'] ?? 0);
    return max(0, $until - time());
}

/** Counts a failed attempt and locks the form after too many. */
function register_failed_login(): void
{
    $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
        $_SESSION['login_attempts']     = 0;
    }
}
