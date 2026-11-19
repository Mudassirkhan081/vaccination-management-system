<?php
/**
 * ---------------------------------------------------------------------
 * File    : register.php
 * Purpose : Parent registration. A new parent account is active at once;
 *           the parent is logged in and taken to "Add a child".
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

redirect_if_logged_in();

$form   = ['name' => '', 'email' => '', 'phone' => '', 'cnic' => '', 'city' => 'Karachi', 'address' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();

    // 1) Read and validate the shared account fields
    $form   = read_account_input('parent');
    $errors = validate_account('parent', $form);

    // 2) Password rules and the terms checkbox
    $password = (string) ($_POST['password'] ?? '');
    validate_new_password($password, (string) ($_POST['password_confirm'] ?? ''), $errors);
    if (post('agree') !== '1') {
        $errors['agree'] = 'Please confirm that the details are correct.';
    }

    // 3) Save the account and log the parent in
    if (!$errors) {
        $id = db_insert(
            'INSERT INTO parents (name, email, phone, cnic, city, address, password) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $form['name'], $form['email'], $form['phone'],
                $form['cnic'] !== '' ? $form['cnic'] : null,
                $form['city'],
                $form['address'] !== '' ? $form['address'] : null,
                password_hash($password, PASSWORD_DEFAULT),
            ]
        );

        notify('parent', $id, 'Welcome to ' . APP_NAME, 'Add your child to see every vaccination due date.', 'parent/children.php?action=add');
        login_user('parent', ['id' => $id, 'name' => $form['name'], 'email' => $form['email']]);

        flash('success', 'Your account is ready. Now add your first child.');
        redirect('parent/children.php?action=add');
    }
}

$pageTitle = 'Register as parent';
$renderFlashesInPage = true;
require __DIR__ . '/includes/layout/public-header.php';
?>

<section class="section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <h1>Create a parent account</h1>
                <p class="text-soft">It takes a minute. After this you can add your children and book hospital visits.</p>

                <?php render_flashes(); ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div>
                <?php endif; ?>

                <form class="sheet" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="sheet-body">
                        <h2 class="fieldset-title">Your details</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="f-name">Full name</label>
                                <input class="form-control<?= invalid($errors, 'name') ?>" id="f-name" name="name" value="<?= e($form['name']) ?>" required autocomplete="name">
                                <?= field_error($errors, 'name') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="f-email">Email</label>
                                <input class="form-control<?= invalid($errors, 'email') ?>" type="email" id="f-email" name="email" value="<?= e($form['email']) ?>" required autocomplete="email">
                                <?= field_error($errors, 'email') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="f-phone">Mobile number</label>
                                <input class="form-control<?= invalid($errors, 'phone') ?>" type="tel" id="f-phone" name="phone" value="<?= e($form['phone']) ?>" placeholder="0300-1234567" required autocomplete="tel">
                                <?= field_error($errors, 'phone') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="f-cnic">CNIC <span class="text-soft fw-normal">(optional)</span></label>
                                <input class="form-control<?= invalid($errors, 'cnic') ?>" id="f-cnic" name="cnic" value="<?= e($form['cnic']) ?>" placeholder="42101-1234567-1" inputmode="numeric">
                                <?= field_error($errors, 'cnic') ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required" for="f-city">City</label>
                                <select class="form-select<?= invalid($errors, 'city') ?>" id="f-city" name="city" required>
                                    <?= city_options($form['city']) ?>
                                </select>
                                <?= field_error($errors, 'city') ?>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="f-address">Home address <span class="text-soft fw-normal">(optional)</span></label>
                                <input class="form-control<?= invalid($errors, 'address') ?>" id="f-address" name="address" value="<?= e($form['address']) ?>" autocomplete="street-address">
                                <?= field_error($errors, 'address') ?>
                            </div>
                        </div>

                        <h2 class="fieldset-title mt-4">Password</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><?= password_input('password', 'Password', $errors, 'new-password', 'At least 8 characters with a letter and a number.') ?></div>
                            <div class="col-md-6"><?= password_input('password_confirm', 'Repeat password', $errors) ?></div>
                        </div>

                        <div class="form-check mt-4">
                            <input class="form-check-input<?= invalid($errors, 'agree') ?>" type="checkbox" id="f-agree" name="agree" value="1"<?= checked(post('agree') === '1') ?>>
                            <label class="form-check-label" for="f-agree">I am the parent or legal guardian, and these details are correct.</label>
                            <?= field_error($errors, 'agree') ?>
                        </div>
                    </div>
                    <div class="sheet-foot d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Already registered? <a href="<?= url('login.php') ?>">Log in</a></span>
                        <button class="btn btn-primary" type="submit">Create account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/public-footer.php'; ?>
