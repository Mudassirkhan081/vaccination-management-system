<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/layout/panel-header.php
 * Purpose : Shared dashboard layout for the admin, parent and hospital
 *           panels: <head>, sidebar menu and top bar.
 * Expects : $pageTitle (string), $activeNav (string) set by the page.
 *           All variables created here start with $panel/$nav so they
 *           never overwrite variables of the page that includes this file.
 *           Optional $pageScripts (array of asset paths) for the footer.
 * ---------------------------------------------------------------------
 */
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$panelRole      = current_role();
$panelRoleCfg   = role_config($panelRole);
$panelUid       = current_user_id();

/*
 * Sidebar menu for each role.
 * Each entry: [key, file, label, icon, badge-count]
 */
$panelMenu = [];
if ($panelRole === 'admin') {
    $panelPendingRequests  = (int) db_value("SELECT COUNT(*) FROM appointments WHERE request_status = 'Pending'");
    $panelPendingHospitals = (int) db_value("SELECT COUNT(*) FROM hospitals WHERE status = 'Pending'");
    $panelUnreadMessages   = (int) db_value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
    $panelMenu = [
        'Overview' => [
            ['dashboard', 'admin/index.php', 'Dashboard', 'bi-grid-1x2', 0],
            ['children', 'admin/children.php', 'Children', 'bi-people', 0],
            ['due', 'admin/due.php', 'Vaccination dates', 'bi-calendar-week', 0],
            ['reports', 'admin/reports.php', 'Vaccination report', 'bi-bar-chart-line', 0],
        ],
        'Bookings' => [
            ['requests', 'admin/requests.php', 'Requests', 'bi-inbox', $panelPendingRequests],
            ['bookings', 'admin/bookings.php', 'All bookings', 'bi-journal-text', 0],
        ],
        'Manage' => [
            ['vaccines', 'admin/vaccines.php', 'Vaccines', 'bi-capsule', 0],
            ['hospitals', 'admin/hospitals.php', 'Hospitals', 'bi-hospital', $panelPendingHospitals],
            ['parents', 'admin/parents.php', 'Parents', 'bi-person-lines-fill', 0],
            ['messages', 'admin/messages.php', 'Messages', 'bi-envelope', $panelUnreadMessages],
        ],
    ];
} elseif ($panelRole === 'parent') {
    $panelMenu = [
        'My family' => [
            ['dashboard', 'parent/index.php', 'Dashboard', 'bi-grid-1x2', 0],
            ['children', 'parent/children.php', 'My children', 'bi-people', 0],
            ['report', 'parent/report.php', 'Vaccination report', 'bi-file-earmark-medical', 0],
        ],
        'Hospital visits' => [
            ['book', 'parent/book.php', 'Book a hospital', 'bi-calendar-plus', 0],
            ['bookings', 'parent/bookings.php', 'My bookings', 'bi-journal-text', 0],
        ],
    ];
} elseif ($panelRole === 'hospital') {
    $panelTodayCount = (int) db_value(
        "SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND request_status = 'Approved'
           AND vaccination_status = 'Pending' AND appointment_date = ?",
        [$panelUid, today()]
    );
    $panelMenu = [
        'Clinic' => [
            ['dashboard', 'hospital/index.php', 'Dashboard', 'bi-grid-1x2', 0],
            ['appointments', 'hospital/appointments.php', 'Appointments', 'bi-clipboard2-pulse', $panelTodayCount],
        ],
    ];
}

// Shared account links for every role
$panelMenu['Account'] = [
    ['notifications', $panelRole . '/notifications.php', 'Notifications', 'bi-bell', 0],
    ['profile', $panelRole . '/profile.php', 'My profile', 'bi-person-gear', 0],
];

$panelUnread        = unread_notification_count();
$panelNotes = latest_notifications(5);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?> <?= e($panelRoleCfg['label']) ?></title>
    <meta name="theme-color" content="#16503F">
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#content">Skip to content</a>
<a class="visually-hidden-focusable btn btn-primary position-absolute m-2" style="z-index:2000" href="#content">Skip to content</a>

<div class="panel-shell">

    <!-- ============ Sidebar (becomes a slide-in menu on small screens) ============ -->
    <aside class="panel-side">
        <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="sideNav" aria-labelledby="sideNavLabel">
            <div class="offcanvas-body d-flex flex-column p-0">
                <div class="side-brand d-flex justify-content-between align-items-start">
                    <div>
                        <a class="brand" href="<?= url('index.php') ?>" id="sideNavLabel">
                            <img src="<?= asset('img/logo.svg') ?>" alt="">
                            <span>
                                <span class="brand-name"><?= e(APP_NAME) ?></span>
                                <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
                            </span>
                        </a>
                        <span class="side-role"><?= e($panelRoleCfg['label']) ?> panel</span>
                    </div>
                    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sideNav" aria-label="Close menu"></button>
                </div>

                <nav class="side-nav" aria-label="Panel menu">
                    <?php foreach ($panelMenu as $navGroup => $navItems): ?>
                        <div class="side-group"><?= e($navGroup) ?></div>
                        <?php foreach ($navItems as [$navKey, $navFile, $navLabel, $navIcon, $navCount]): ?>
                            <a class="nav-link<?= $activeNav === $navKey ? ' active' : '' ?>" href="<?= url($navFile) ?>"
                               <?= $activeNav === $navKey ? 'aria-current="page"' : '' ?>>
                                <i class="bi <?= e($navIcon) ?>" aria-hidden="true"></i>
                                <span><?= e($navLabel) ?></span>
                                <?php if ($navCount > 0): ?>
                                    <span class="count" title="<?= (int) $navCount ?> waiting"><?= (int) $navCount ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <!-- Logout is a POST form so it cannot be triggered by a link on another site -->
                    <form method="post" action="<?= url('logout.php') ?>" class="mt-1">
                        <?= csrf_field() ?>
                        <button type="submit" class="nav-link logout-btn">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Log out</span>
                        </button>
                    </form>
                </nav>
            </div>
        </div>
    </aside>

    <!-- ============ Main column ============ -->
    <div class="panel-main">
        <header class="panel-top no-print">
            <button class="top-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sideNav" aria-controls="sideNav" aria-label="Open menu">
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>

            <div class="me-auto">
                <span class="panel-crumb"><?= e($panelRoleCfg['label']) ?> panel</span>
                <h1 class="panel-title"><?= e($pageTitle) ?></h1>
            </div>

            <!-- Notifications dropdown -->
            <div class="dropdown">
                <button class="top-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                        aria-label="Notifications<?= $panelUnread ? ', ' . $panelUnread . ' unread' : '' ?>">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    <?php if ($panelUnread > 0): ?><span class="dot"><?= $panelUnread > 9 ? '9+' : $panelUnread ?></span><?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notify-menu p-0">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2">
                        <strong>Notifications</strong>
                        <a class="small" href="<?= url($panelRole . '/notifications.php') ?>">See all</a>
                    </div>
                    <?php if (!$panelNotes): ?>
                        <p class="px-3 pb-3 mb-0 small-note">Nothing new yet.</p>
                    <?php endif; ?>
                    <?php foreach ($panelNotes as $navNote): ?>
                        <a class="notify-item<?= $navNote['is_read'] ? '' : ' unread' ?>"
                           href="<?= url($panelRole . '/notifications.php?open=' . (int) $navNote['id']) ?>">
                            <strong><?= e($navNote['title']) ?></strong>
                            <span><?= e($navNote['message']) ?></span>
                            <span><?= e(time_ago($navNote['created_at'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- User menu -->
            <div class="dropdown">
                <button class="user-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar"><?= e(initials(current_user_name())) ?></span>
                    <span class="name fw-600"><?= e(current_user_name()) ?></span>
                    <i class="bi bi-chevron-down small" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= url($panelRole . '/profile.php') ?>"><i class="bi bi-person-gear me-2"></i>My profile</a></li>
                    <li><a class="dropdown-item" href="<?= url('index.php') ?>"><i class="bi bi-house me-2"></i>Public website</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= url('logout.php') ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="panel-content" id="content" tabindex="-1">
            <?php render_flashes(); ?>
