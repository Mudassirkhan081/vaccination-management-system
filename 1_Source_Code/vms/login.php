<?php
/**
 * ---------------------------------------------------------------------
 * File    : login.php
 * Purpose : One login page for all three roles (parent, hospital, admin).
 *
 * LOGIN ALGORITHM
 *   1. Check the CSRF token and the temporary lock (5 failed tries = 60 s).
 *   2. Find the account by email in the table of the chosen role.
 *   3. password_verify() the typed password against the bcrypt hash.
 *   4. Refuse blocked parents and pending / inactive hospitals.
 *   5. Start a fresh session (session id regenerated) and open the
 *      dashboard, or the protected page the user asked for earlier.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

redirect_if_logged_in();

// Role from the form (POST) or from the link (?role=hospital)
$role = is_post() ? post('role') : get('role', 'parent');
if (!role_config($role)) {
    $role = 'parent';
}

$email  = '';
$errors = [];

if (is_post()) {
    verify_csrf();
    $email    = strtolower(post('email'));
    $password = (string) ($_POST['password'] ?? '');
    $locked   = login_lock_remaining();

    if ($locked > 0) {
        $errors['form'] = 'Too many failed attempts. Please wait ' . $locked . ' seconds and try again.';
    } elseif (!valid_email($email) || $password === '') {
        $errors['form'] = 'Please enter your email and password.';
    } else {
        $cfg  = role_config($role);
        $user = db_one('SELECT * FROM `' . $cfg['table'] . '` WHERE email = ?', [$email]);

        if (!$user || !password_verify($password, $user['password'])) {
            // Same message for "no such email" and "wrong password"
            register_failed_login();
            $errors['form'] = 'The email or password is not correct. Please check that "' . $cfg['label'] . '" is the right account type.';
        } elseif ($role === 'parent' && $user['status'] === 'Blocked') {
            $errors['form'] = 'This parent account has been blocked. Please contact the administrator.';
        } elseif ($role === 'hospital' && $user['status'] === 'Pending') {
            $errors['form'] = 'Your hospital registration is still waiting for approval. You will be able to log in once it is approved.';
        } elseif ($role === 'hospital' && $user['status'] === 'Inactive') {
            $errors['form'] = 'This hospital account is inactive. Please contact the administrator.';
        } else {
            // Upgrade old hashes automatically if PHP's default algorithm changes
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                db_exec('UPDATE `' . $cfg['table'] . '` SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            login_user($role, $user);
            flash('success', 'Welcome back, ' . $user['name'] . '.');
            redirect(after_login_path($role));
        }
    }
}

$pageTitle = 'Log in';
$activeNav = '';
$renderFlashesInPage = true;
require __DIR__ . '/includes/layout/public-header.php';

// Role switch options: value => [label, icon]
$roleOptions = [
    'parent'   => ['Parent', 'bi-people'],
    'hospital' => ['Hospital', 'bi-hospital'],
    'admin'    => ['Admin', 'bi-shield-check'],
];
?>

<div class="auth-wrap">
    <!-- Left: short message (hidden on small screens) -->
    <aside class="auth-aside">
        <div>
            <h2>Your child's card, always with you.</h2>
            <p>Due dates, bookings and every stamped dose in one place, on any phone or computer.</p>
        </div>
        <div class="epi-card" aria-hidden="true">
            <div class="epi-card__head">
                <span class="epi-card__title">Vaccination card</span>
                <span class="epi-card__child">Sample</span>
            </div>
            <ol class="epi-card__rows">
                <li class="epi-card__row">
                    <div class="epi-card__age">At birth</div>
                    <div class="dose-cells"><span class="dose-cell">BCG</span><span class="dose-cell">OPV-0</span><span class="dose-cell">HepB-0</span></div>
                    <div class="epi-card__mark"><span class="stamp stamp-sm"><b>GIVEN</b></span></div>
                </li>
                <li class="epi-card__row">
                    <div class="epi-card__age">6 weeks</div>
                    <div class="dose-cells"><span class="dose-cell">OPV-1</span><span class="dose-cell">Penta-1</span><span class="dose-cell">PCV-1</span><span class="dose-cell">Rota-1</span></div>
                    <div class="epi-card__mark"><span class="stamp stamp-sm"><b>GIVEN</b></span></div>
                </li>
            </ol>
        </div>
    </aside>

    <!-- Right: the form -->
    <div class="auth-main">
        <div class="auth-box">
            <h1>Log in</h1>
            <p class="text-soft">Choose your account type, then enter your email and password.</p>

            <?php render_flashes(); ?>
            <?php if (!empty($errors['form'])): ?>
                <div class="alert alert-danger d-flex gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle mt-1" aria-hidden="true"></i><div><?= e($errors['form']) ?></div>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>

                <!-- Account type -->
                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Account type</legend>
                    <div class="role-switch">
                        <?php foreach ($roleOptions as $value => [$label, $icon]): ?>
                            <input type="radio" name="role" id="role-<?= $value ?>" value="<?= $value ?>"<?= checked($role === $value) ?>>
                            <label for="role-<?= $value ?>"><i class="bi <?= $icon ?>" aria-hidden="true"></i><?= e($label) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="mb-3">
                    <label class="form-label required" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
                </div>

                <div class="mb-4">
                    <?= password_input('password', 'Password', [], 'current-password') ?>
                </div>

                <button class="btn btn-primary btn-lg w-100" type="submit">Log in</button>
            </form>

            <p class="mt-4 mb-1">New parent? <a href="<?= url('register.php') ?>">Create a free account</a></p>
            <p class="mb-4">Hospital or clinic? <a href="<?= url('hospital-register.php') ?>">Register your hospital</a></p>

            <!-- Demo accounts for evaluation -->
            <div class="demo-box">
                <strong class="d-block mb-1"><i class="bi bi-key me-1" aria-hidden="true"></i>Demo accounts</strong>
                <table>
                    <tr><td>Parent</td><td>parent@teekacard.pk</td><td>Parent@123</td></tr>
                    <tr><td>Hospital</td><td>hospital@teekacard.pk</td><td>Hospital@123</td></tr>
                    <tr><td>Admin</td><td>admin@teekacard.pk</td><td>Admin@123</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout/public-footer.php'; ?>
