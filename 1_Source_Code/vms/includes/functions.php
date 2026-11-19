<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/functions.php
 * Purpose : General helper functions used on every page:
 *           escaping, links, redirects, flash messages, CSRF protection,
 *           input reading, validation, date formatting, status badges
 *           and pagination.
 * Note    : Written to run on PHP 7.4 and PHP 8.x.
 * ---------------------------------------------------------------------
 */

/* ------------------------------------------------------------------
 * PHP 7.4 compatibility (these functions are built into PHP 8)
 * ------------------------------------------------------------------ */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/* ------------------------------------------------------------------
 * Output escaping
 * ------------------------------------------------------------------ */

/** Escapes any value for safe HTML output (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------
 * Paths, links and redirects
 * ------------------------------------------------------------------ */

/**
 * Works out the web path of the project folder, e.g. "/vms".
 * It compares the file path of the running script with its URL path,
 * so the project works from any folder name inside htdocs.
 */
function detect_base_path(): string
{
    if (BASE_PATH_OVERRIDE !== '') {
        return rtrim(BASE_PATH_OVERRIDE, '/');
    }

    $root       = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $scriptFile = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $scriptUrl  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // Relative path of the script inside the project, e.g. "/admin/index.php"
    if ($root === '' || stripos($scriptFile, $root) !== 0) {
        return '';
    }
    $relative = substr($scriptFile, strlen($root));

    // Remove that relative part from the end of the URL to get the base
    if ($relative !== '' && strcasecmp(substr($scriptUrl, -strlen($relative)), $relative) === 0) {
        return rtrim(substr($scriptUrl, 0, -strlen($relative)), '/');
    }
    return '';
}

/** Builds a link inside the project: url('admin/index.php'). */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Builds a link to a static file with a version string to avoid stale cache. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . APP_VERSION;
}

/** Sends the browser to another page of the project and stops. */
function redirect(string $path): void
{
    // Full URLs and absolute paths are used as-is; others are project-relative
    $target = (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, '/')) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** Redirects back to the current page (keeps the query string). */
function redirect_self(): void
{
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? url()));
    exit;
}

/** Name of the current script without folder, e.g. "children.php". */
function current_script(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '');
}

/* ------------------------------------------------------------------
 * Flash messages (shown once on the next page load)
 * ------------------------------------------------------------------ */

/** Stores a message: type is success | danger | warning | info. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Returns and clears all stored flash messages. */
function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Prints stored flash messages as Bootstrap alerts. */
function render_flashes(): void
{
    $icons = [
        'success' => 'bi-check2-circle',
        'danger'  => 'bi-exclamation-triangle',
        'warning' => 'bi-exclamation-triangle',
        'info'    => 'bi-info-circle',
    ];
    foreach (take_flashes() as $f) {
        $icon = $icons[$f['type']] ?? 'bi-info-circle';
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible d-flex gap-2 align-items-start" role="alert">';
        echo '<i class="bi ' . $icon . ' mt-1" aria-hidden="true"></i><div>' . e($f['message']) . '</div>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

/* ------------------------------------------------------------------
 * CSRF protection for every POST form
 * ------------------------------------------------------------------ */

/** Returns (and creates once) the session's CSRF token. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Stops the request if the submitted token does not match the session. */
function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        flash('danger', 'Your session has expired. Please try that again.');
        redirect_self();
    }
}

/* ------------------------------------------------------------------
 * Reading request data
 * ------------------------------------------------------------------ */

/** True when the page was submitted with POST. */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string from $_POST. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Trimmed string from $_GET. */
function get(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Positive integer from $_GET or $_POST (0 when missing/invalid). */
function int_param(string $key, string $source = 'get'): int
{
    $bag   = $source === 'post' ? $_POST : $_GET;
    $value = filter_var($bag[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? 0 : (int) $value;
}

/** Array of positive integers from $_POST (used for checkbox lists). */
function post_int_list(string $key): array
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        return [];
    }
    $ids = array_filter(array_map('intval', $values), function ($v) {
        return $v > 0;
    });
    return array_values(array_unique($ids));
}

/** Search pattern for LIKE: %text% with the user's own % and _ escaped. */
function like_param(string $text): string
{
    return '%' . addcslashes($text, '%_\\') . '%';
}

/* ------------------------------------------------------------------
 * Validation
 * ------------------------------------------------------------------ */

function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 150;
}

/** Pakistani phone: mobile 03XXXXXXXXX / +923XXXXXXXXX or landline 0XX-XXXXXXX. */
function valid_phone(string $phone): bool
{
    $digits = preg_replace('/[\s\-()]/', '', $phone);
    return (bool) preg_match('/^(\+92|0)\d{9,11}$/', $digits);
}

/**
 * Stores phone numbers in one style: mobiles as 0300-1234567,
 * +92 numbers converted to 0..., other numbers only trimmed.
 */
function format_phone(string $phone): string
{
    $digits = preg_replace('/[\s\-()]/', '', $phone);
    if (str_starts_with($digits, '+92')) {
        $digits = '0' . substr($digits, 3);
    }
    if (preg_match('/^03\d{9}$/', $digits)) {
        return substr($digits, 0, 4) . '-' . substr($digits, 4);
    }
    return trim($phone);
}

/** CNIC in the form 12345-1234567-1 (dashes optional). */
function valid_cnic(string $cnic): bool
{
    return (bool) preg_match('/^\d{5}-?\d{7}-?\d$/', $cnic);
}

/** Formats 13 CNIC digits as 12345-1234567-1. */
function format_cnic(string $cnic): string
{
    $d = preg_replace('/\D/', '', $cnic);
    return strlen($d) === 13 ? substr($d, 0, 5) . '-' . substr($d, 5, 7) . '-' . substr($d, 12) : $cnic;
}

/** Date string in Y-m-d format that really exists on the calendar. */
function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/** At least 8 characters with at least one letter and one number. */
function valid_password(string $password): bool
{
    return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
}

/** Checks if an email is already used in a table (optionally ignoring one id). */
function email_taken(string $table, string $email, int $ignoreId = 0): bool
{
    $allowed = ['admins', 'parents', 'hospitals'];
    if (!in_array($table, $allowed, true)) {
        return true;
    }
    return (bool) db_value("SELECT COUNT(*) FROM `$table` WHERE email = ? AND id <> ?", [$email, $ignoreId]);
}

/** Prints Bootstrap's invalid class when a field has an error. */
function invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

/** Prints the error text under a field. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
}

/* ------------------------------------------------------------------
 * Dates and ages
 * ------------------------------------------------------------------ */

function today(): string
{
    return date('Y-m-d');
}

/** 16 Sep 2026 */
function fmt_date(?string $date, string $format = 'd M Y'): string
{
    if (empty($date) || str_starts_with($date, '0000')) {
        return '-';
    }
    return date($format, strtotime($date));
}

/** 11:30 AM */
function fmt_time(?string $time): string
{
    return empty($time) ? '-' : date('g:i A', strtotime($time));
}

/** 16 Sep 2026, 11:30 AM */
function fmt_datetime(?string $datetime): string
{
    return empty($datetime) ? '-' : date('d M Y, g:i A', strtotime($datetime));
}

/** Adds days to a Y-m-d date. */
function add_days(string $date, int $days): string
{
    return date('Y-m-d', strtotime($date . ' ' . ($days >= 0 ? '+' : '') . $days . ' days'));
}

/** Whole days from $from to $to (negative when $to is earlier). */
function days_between(string $from, string $to): int
{
    $a = new DateTime($from);
    $b = new DateTime($to);
    return (int) $a->diff($b)->format('%r%a');
}

/** Friendly age: "5 days", "6 weeks", "4 months", "1 year 3 months". */
function child_age(string $dob): string
{
    $days = days_between($dob, today());
    if ($days < 0) {
        return 'Not born yet';
    }
    if ($days < 14) {
        return $days . ' day' . ($days === 1 ? '' : 's');
    }
    if ($days < 91) {
        $w = intdiv($days, 7);
        return $w . ' weeks';
    }
    $diff   = (new DateTime($dob))->diff(new DateTime(today()));
    $months = $diff->y * 12 + $diff->m;
    if ($months < 24) {
        return $months . ' months';
    }
    return $diff->y . ' years' . ($diff->m ? ' ' . $diff->m . ' months' : '');
}

/** "in 3 days", "today", "5 days ago" */
function relative_days(string $date): string
{
    $d = days_between(today(), $date);
    if ($d === 0) {
        return 'today';
    }
    if ($d === 1) {
        return 'tomorrow';
    }
    if ($d === -1) {
        return 'yesterday';
    }
    return $d > 0 ? 'in ' . $d . ' days' : abs($d) . ' days ago';
}

/** "2 hours ago" style text for notifications. */
function time_ago(string $datetime): string
{
    $seconds = time() - strtotime($datetime);
    if ($seconds < 60) {
        return 'just now';
    }
    $units = [86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $size => $name) {
        if ($seconds >= $size) {
            $n = intdiv($seconds, $size);
            return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/** Half-hour booking slots from 9:00 AM to 4:30 PM. */
function time_slots(): array
{
    $slots = [];
    for ($minutes = 9 * 60; $minutes <= 16 * 60 + 30; $minutes += 30) {
        $slots[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
    return $slots;
}

/* ------------------------------------------------------------------
 * Small UI helpers
 * ------------------------------------------------------------------ */

/** Coloured status pill used in all tables. */
function status_badge(?string $status): string
{
    $map = [
        'Pending'              => 'pill-amber',
        'Approved'             => 'pill-green',
        'Active'               => 'pill-green',
        'Available'            => 'pill-green',
        'Rejected'             => 'pill-red',
        'Blocked'              => 'pill-red',
        'Not Vaccinated'       => 'pill-red',
        'Unavailable'          => 'pill-red',
        'Cancelled'            => 'pill-grey',
        'Inactive'             => 'pill-grey',
        'Vaccinated'           => 'pill-ink',
        'Partially Vaccinated' => 'pill-ink-soft',
    ];
    $class = $map[$status] ?? 'pill-grey';
    return '<span class="pill ' . $class . '">' . e($status) . '</span>';
}

/** Booking reference shown to users, e.g. TC-00042. */
function booking_ref(int $id): string
{
    return 'TC-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/** Two-letter initials for avatars. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last  = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

/** "selected" attribute helper for <option>. */
function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

/** "checked" attribute helper for radios/checkboxes. */
function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/** Cities offered in registration forms. */
function city_list(): array
{
    return ['Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Hyderabad',
            'Peshawar', 'Quetta', 'Sialkot', 'Gujranwala', 'Sukkur', 'Larkana', 'Other'];
}

function blood_groups(): array
{
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
}

/* ------------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------------ */

/**
 * Returns [page, perPage, offset, totalPages] for a result size.
 */
function paginate(int $total, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, int_param('page')), $pages);
    return ['page' => $page, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'pages' => $pages, 'total' => $total];
}

/** Builds a URL to the current page with changed query values. */
function query_url(array $changes): string
{
    $query = array_merge($_GET, $changes);
    $query = array_filter($query, function ($v) {
        return $v !== '' && $v !== null;
    });
    return strtok($_SERVER['REQUEST_URI'] ?? '', '?') . ($query ? '?' . http_build_query($query) : '');
}

/** Prints Bootstrap pagination links (only when there is more than one page). */
function render_pagination(array $p): void
{
    if ($p['pages'] <= 1) {
        return;
    }
    echo '<nav aria-label="Pages" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">';
    $from = $p['offset'] + 1;
    $to   = min($p['total'], $p['offset'] + $p['per']);
    echo '<small class="text-soft">Showing ' . $from . ' to ' . $to . ' of ' . $p['total'] . '</small>';
    echo '<ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $active = $i === $p['page'] ? ' active" aria-current="page' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="' . e(query_url(['page' => $i])) . '">' . $i . '</a></li>';
    }
    echo '</ul></nav>';
}

/** Standard empty-state block for tables and lists. */
function empty_state(string $title, string $text, string $actionUrl = '', string $actionLabel = ''): string
{
    $html = '<div class="empty-state"><p class="empty-title">' . e($title) . '</p><p>' . e($text) . '</p>';
    if ($actionUrl !== '') {
        $html .= '<a class="btn btn-primary btn-sm" href="' . e($actionUrl) . '">' . e($actionLabel) . '</a>';
    }
    return $html . '</div>';
}
