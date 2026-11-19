<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/layout/public-header.php
 * Purpose : <head> section and top navigation for the public website
 *           (home, schedule, hospitals, contact, login, register).
 * Expects : $pageTitle (string) and $activeNav (string) from the page.
 * ---------------------------------------------------------------------
 */
$pageTitle = $pageTitle ?? APP_TAGLINE;
$activeNav = $activeNav ?? '';

// Links shown in the top menu: key => [file, label]
$siteNav = [
    'home'      => ['index.php', 'Home'],
    'schedule'  => ['schedule.php', 'Vaccine schedule'],
    'hospitals' => ['hospitals.php', 'Hospitals'],
    'contact'   => ['contact.php', 'Contact'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e(APP_NAME) ?> helps parents track every child vaccination date, book a hospital visit and keep a digital vaccination record.">
    <meta name="theme-color" content="#16503F">
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<a class="visually-hidden-focusable btn btn-primary position-absolute m-2" href="#main">Skip to content</a>

<header class="site-nav">
    <nav class="navbar navbar-expand-lg" aria-label="Main menu">
        <div class="container">
            <!-- Brand -->
            <a class="brand" href="<?= url('index.php') ?>">
                <img src="<?= asset('img/logo.svg') ?>" alt="">
                <span>
                    <span class="brand-name"><?= e(APP_NAME) ?></span>
                    <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
                </span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteMenu"
                    aria-controls="siteMenu" aria-expanded="false" aria-label="Open menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="siteMenu">
                <!-- Page links -->
                <ul class="navbar-nav mx-auto my-3 my-lg-0">
                    <?php foreach ($siteNav as $navKey => [$navFile, $navLabel]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activeNav === $navKey ? ' active' : '' ?>"
                               <?= $activeNav === $navKey ? 'aria-current="page"' : '' ?>
                               href="<?= url($navFile) ?>"><?= e($navLabel) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Account buttons change when the visitor is logged in -->
                <div class="d-flex flex-wrap gap-2 pb-3 pb-lg-0">
                    <?php if (is_logged_in()): ?>
                        <a class="btn btn-primary btn-icon" href="<?= url(dashboard_path(current_role())) ?>">
                            <i class="bi bi-grid-1x2" aria-hidden="true"></i> My dashboard
                        </a>
                    <?php else: ?>
                        <a class="btn btn-light" href="<?= url('login.php') ?>">Log in</a>
                        <a class="btn btn-primary" href="<?= url('register.php') ?>">Register as parent</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="main" class="flex-grow-1 d-flex flex-column" tabindex="-1">
<?php
// Pages with their own message area set $renderFlashesInPage = true
if (empty($renderFlashesInPage) && !empty($_SESSION['flash'])): ?>
    <div class="container pt-3"><?php render_flashes(); ?></div>
<?php endif; ?>
