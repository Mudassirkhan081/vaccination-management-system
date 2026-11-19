<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/pages/profile.php
 * Purpose : "My profile" page shared by all three panels.
 *           - update account details (fields depend on the role)
 *           - change password (current password is required)
 *           The role file calls require_role() and includes this file,
 *           so $authUser (fresh database row) is already available.
 * ---------------------------------------------------------------------
 */
$role   = current_role();
$cfg    = role_config($role);
$userId = current_user_id();
$user   = $GLOBALS['authUser'];

$form         = [];   // values shown in the details form
$errors       = [];   // details form errors
$pwErrors     = [];   // password form errors
foreach (account_fields($role) as $field) {
    $form[$field] = (string) ($user[$field] ?? '');
}

/* ------------------------------------------------------------------
 * Form handling
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $action = post('action');

    // 1) Account details
    if ($action === 'details') {
        $form   = read_account_input($role);
        $errors = validate_account($role, $form, $userId);

        if (!$errors) {
            // Build "field = ?" pairs only from the role's own field list
            $fields = account_fields($role);
            $sets   = implode(', ', array_map(function ($f) { return "`$f` = ?"; }, $fields));
            $values = array_map(function ($f) use ($form) {
                // Optional fields are stored as NULL when left empty
                return ($form[$f] === '' && in_array($f, ['cnic', 'address', 'location', 'timings'], true)) ? null : $form[$f];
            }, $fields);

            db_exec('UPDATE `' . $cfg['table'] . "` SET $sets WHERE id = ?", array_merge($values, [$userId]));
            refresh_session_user($form['name'], $form['email']);
            flash('success', 'Your profile has been updated.');
            redirect_self();
        }
    }

    // 2) Password change
    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!password_verify($current, $user['password'])) {
            $pwErrors['current_password'] = 'Your current password is not correct.';
        }
        validate_new_password($new, $confirm, $pwErrors);
        if (!$pwErrors && password_verify($new, $user['password'])) {
            $pwErrors['password'] = 'Please choose a password that is different from the current one.';
        }

        if (!$pwErrors) {
            db_exec('UPDATE `' . $cfg['table'] . '` SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $userId]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect_self();
        }
    }
}

/* ------------------------------------------------------------------
 * Small account summary for the side column
 * ------------------------------------------------------------------ */
$summary = [];
if ($role === 'parent') {
    $summary['Children']          = (int) db_value('SELECT COUNT(*) FROM children WHERE parent_id = ?', [$userId]);
    $summary['Bookings made']     = (int) db_value('SELECT COUNT(*) FROM appointments WHERE parent_id = ?', [$userId]);
} elseif ($role === 'hospital') {
    $summary['Appointments']      = (int) db_value("SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND request_status = 'Approved'", [$userId]);
    $summary['Doses given']       = (int) db_value(
        "SELECT COUNT(*) FROM appointment_vaccines av JOIN appointments a ON a.id = av.appointment_id
          WHERE a.hospital_id = ? AND av.status = 'Vaccinated'",
        [$userId]
    );
} else {
    $summary['Administrators']    = (int) db_value('SELECT COUNT(*) FROM admins');
}

$pageTitle = 'My profile';
$activeNav = 'profile';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<div class="row g-4">
    <!-- ============ Details form ============ -->
    <div class="col-xl-8">
        <form class="sheet" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="details">
            <div class="sheet-head"><h2>Account details</h2></div>
            <div class="sheet-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="f-name"><?= $role === 'hospital' ? 'Hospital name' : 'Full name' ?></label>
                        <input class="form-control<?= invalid($errors, 'name') ?>" id="f-name" name="name" value="<?= e($form['name']) ?>" required autocomplete="name">
                        <?= field_error($errors, 'name') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="f-email">Email</label>
                        <input class="form-control<?= invalid($errors, 'email') ?>" type="email" id="f-email" name="email" value="<?= e($form['email']) ?>" required autocomplete="email">
                        <?= field_error($errors, 'email') ?>
                    </div>

                    <?php if (isset($form['phone'])): ?>
                        <div class="col-md-6">
                            <label class="form-label required" for="f-phone">Phone</label>
                            <input class="form-control<?= invalid($errors, 'phone') ?>" type="tel" id="f-phone" name="phone" value="<?= e($form['phone']) ?>" required autocomplete="tel">
                            <?= field_error($errors, 'phone') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($form['cnic'])): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="f-cnic">CNIC <span class="text-soft fw-normal">(optional)</span></label>
                            <input class="form-control<?= invalid($errors, 'cnic') ?>" id="f-cnic" name="cnic" value="<?= e($form['cnic']) ?>" placeholder="42101-1234567-1" inputmode="numeric">
                            <?= field_error($errors, 'cnic') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($form['city'])): ?>
                        <div class="col-md-6">
                            <label class="form-label required" for="f-city">City</label>
                            <select class="form-select<?= invalid($errors, 'city') ?>" id="f-city" name="city" required>
                                <?= city_options($form['city']) ?>
                            </select>
                            <?= field_error($errors, 'city') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($form['timings'])): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="f-timings">Vaccination timings</label>
                            <input class="form-control<?= invalid($errors, 'timings') ?>" id="f-timings" name="timings" value="<?= e($form['timings']) ?>" placeholder="Mon to Sat, 9:00 AM to 5:00 PM">
                            <?= field_error($errors, 'timings') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($form['address'])): ?>
                        <div class="col-12">
                            <label class="form-label<?= $role === 'hospital' ? ' required' : '' ?>" for="f-address">Address</label>
                            <input class="form-control<?= invalid($errors, 'address') ?>" id="f-address" name="address" value="<?= e($form['address']) ?>" autocomplete="street-address">
                            <?= field_error($errors, 'address') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($form['location'])): ?>
                        <div class="col-12">
                            <label class="form-label" for="f-location">Location / landmark</label>
                            <input class="form-control<?= invalid($errors, 'location') ?>" id="f-location" name="location" value="<?= e($form['location']) ?>" placeholder="Near Maskan Chowrangi, or a Google Maps link">
                            <?= field_error($errors, 'location') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sheet-foot text-end">
                <button class="btn btn-primary" type="submit">Save changes</button>
            </div>
        </form>

        <!-- ============ Password form ============ -->
        <form class="sheet" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="sheet-head"><h2>Change password</h2></div>
            <div class="sheet-body">
                <div class="row g-3">
                    <div class="col-md-4"><?= password_input('current_password', 'Current password', $pwErrors, 'current-password') ?></div>
                    <div class="col-md-4"><?= password_input('password', 'New password', $pwErrors, 'new-password', '8+ characters, a letter and a number.') ?></div>
                    <div class="col-md-4"><?= password_input('password_confirm', 'Repeat new password', $pwErrors) ?></div>
                </div>
            </div>
            <div class="sheet-foot text-end">
                <button class="btn btn-ink" type="submit">Update password</button>
            </div>
        </form>
    </div>

    <!-- ============ Account summary ============ -->
    <div class="col-xl-4">
        <div class="sheet">
            <div class="sheet-body d-flex gap-3 align-items-center">
                <span class="avatar avatar-lg"><?= e(initials($user['name'])) ?></span>
                <div>
                    <div class="fw-600"><?= e($user['name']) ?></div>
                    <div class="small-note"><?= e($cfg['label']) ?> account</div>
                </div>
            </div>
            <dl class="detail-list sheet-body border-top">
                <dt>Status</dt>
                <dd><?= status_badge($user['status'] ?? 'Active') ?></dd>
                <dt>Member since</dt>
                <dd><?= e(fmt_date($user['created_at'])) ?></dd>
                <?php if (!empty($user['updated_at'])): ?>
                    <dt>Last updated</dt>
                    <dd><?= e(fmt_datetime($user['updated_at'])) ?></dd>
                <?php endif; ?>
                <?php foreach ($summary as $label => $value): ?>
                    <dt><?= e($label) ?></dt>
                    <dd><?= (int) $value ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>
        <p class="small-note mt-3 mb-0">
            <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
            Passwords are stored as secure one-way hashes. Nobody, including the administrator, can read them.
        </p>
    </div>
</div>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
