<?php
/**
 * ---------------------------------------------------------------------
 * File    : hospital-register.php
 * Purpose : Hospital self-registration (name, address, location ...).
 *           New hospitals are saved as "Pending" and can log in only
 *           after the administrator approves them.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

redirect_if_logged_in();

$form = ['name' => '', 'email' => '', 'phone' => '', 'city' => 'Karachi', 'address' => '', 'location' => '', 'timings' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();

    $form   = read_account_input('hospital');
    $errors = validate_account('hospital', $form);

    $password = (string) ($_POST['password'] ?? '');
    validate_new_password($password, (string) ($_POST['password_confirm'] ?? ''), $errors);
    if (post('agree') !== '1') {
        $errors['agree'] = 'Please confirm that you are allowed to register this hospital.';
    }

    if (!$errors) {
        db_insert(
            "INSERT INTO hospitals (name, email, phone, city, address, location, timings, password, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')",
            [
                $form['name'], $form['email'], $form['phone'], $form['city'], $form['address'],
                $form['location'] !== '' ? $form['location'] : null,
                $form['timings'] !== '' ? $form['timings'] : null,
                password_hash($password, PASSWORD_DEFAULT),
            ]
        );
        notify_admins('Hospital registration', $form['name'] . ' (' . $form['city'] . ') registered and is waiting for approval.', 'admin/hospitals.php?status=Pending');

        flash('success', 'Thank you. ' . $form['name'] . ' has been registered. You can log in as soon as the administrator approves it.');
        redirect('login.php?role=hospital');
    }
}

$pageTitle = 'Register a hospital';
$renderFlashesInPage = true;
require __DIR__ . '/includes/layout/public-header.php';
?>

<section class="section">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <div class="col-lg-4">
                <h1>Register your hospital</h1>
                <p class="text-soft">Receive confirmed vaccination appointments and record doses online.</p>
                <ol class="plain-steps">
                    <li><strong>Fill in this form.</strong> Use the address parents will visit.</li>
                    <li><strong>Wait for approval.</strong> The administrator checks the details, usually within one working day.</li>
                    <li><strong>Log in.</strong> Approved appointments appear on your dashboard every morning.</li>
                </ol>
            </div>

            <div class="col-lg-8">
                <?php render_flashes(); ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div>
                <?php endif; ?>

                <form class="sheet" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="sheet-body">
                        <h2 class="fieldset-title">Hospital details</h2>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label required" for="f-name">Hospital name</label>
                                <input class="form-control<?= invalid($errors, 'name') ?>" id="f-name" name="name" value="<?= e($form['name']) ?>" required autocomplete="organization">
                                <?= field_error($errors, 'name') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="f-email">Official email</label>
                                <input class="form-control<?= invalid($errors, 'email') ?>" type="email" id="f-email" name="email" value="<?= e($form['email']) ?>" required autocomplete="email">
                                <?= field_error($errors, 'email') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="f-phone">Phone</label>
                                <input class="form-control<?= invalid($errors, 'phone') ?>" type="tel" id="f-phone" name="phone" value="<?= e($form['phone']) ?>" placeholder="021-34567890" required autocomplete="tel">
                                <?= field_error($errors, 'phone') ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required" for="f-city">City</label>
                                <select class="form-select<?= invalid($errors, 'city') ?>" id="f-city" name="city" required>
                                    <?= city_options($form['city']) ?>
                                </select>
                                <?= field_error($errors, 'city') ?>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label required" for="f-address">Address</label>
                                <input class="form-control<?= invalid($errors, 'address') ?>" id="f-address" name="address" value="<?= e($form['address']) ?>" placeholder="Plot, block and area" required autocomplete="street-address">
                                <?= field_error($errors, 'address') ?>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label" for="f-location">Location / landmark</label>
                                <input class="form-control<?= invalid($errors, 'location') ?>" id="f-location" name="location" value="<?= e($form['location']) ?>" placeholder="Near Maskan Chowrangi, or a Google Maps link">
                                <?= field_error($errors, 'location') ?>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="f-timings">Vaccination timings</label>
                                <input class="form-control<?= invalid($errors, 'timings') ?>" id="f-timings" name="timings" value="<?= e($form['timings']) ?>" placeholder="Mon to Sat, 9 AM to 5 PM">
                                <?= field_error($errors, 'timings') ?>
                            </div>
                        </div>

                        <h2 class="fieldset-title mt-4">Login password</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><?= password_input('password', 'Password', $errors, 'new-password', 'At least 8 characters with a letter and a number.') ?></div>
                            <div class="col-md-6"><?= password_input('password_confirm', 'Repeat password', $errors) ?></div>
                        </div>

                        <div class="form-check mt-4">
                            <input class="form-check-input<?= invalid($errors, 'agree') ?>" type="checkbox" id="f-agree" name="agree" value="1"<?= checked(post('agree') === '1') ?>>
                            <label class="form-check-label" for="f-agree">I work at this hospital and I am allowed to register it.</label>
                            <?= field_error($errors, 'agree') ?>
                        </div>
                    </div>
                    <div class="sheet-foot d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Already approved? <a href="<?= url('login.php?role=hospital') ?>">Hospital login</a></span>
                        <button class="btn btn-primary" type="submit">Submit for approval</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/public-footer.php'; ?>
