<?php
/**
 * ---------------------------------------------------------------------
 * File    : contact.php
 * Purpose : Public contact form. Messages are saved in the
 *           contact_messages table and shown to the administrator.
 *           Spam protection: a hidden "website" field (bots fill it)
 *           and a 30-second wait between two messages per session.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

$form = [
    'name'    => '',
    'email'   => '',
    'subject' => mb_substr(get('subject'), 0, 150),
    'message' => '',
];

// Logged-in users do not need to type their name and email again
if (is_logged_in()) {
    $form['name']  = current_user_name();
    $form['email'] = $_SESSION['auth']['email'] ?? '';
}

$errors = [];

if (is_post()) {
    verify_csrf();

    foreach (array_keys($form) as $field) {
        $form[$field] = post($field);
    }
    $form['email'] = strtolower($form['email']);

    // Honeypot: real visitors never see or fill this field
    if (post('website') !== '') {
        flash('success', 'Thank you. Your message has been sent.');
        redirect('contact.php');
    }

    // Validation
    if (mb_strlen($form['name']) < 3 || mb_strlen($form['name']) > 100) {
        $errors['name'] = 'Please enter your name (3 to 100 characters).';
    }
    if (!valid_email($form['email'])) {
        $errors['email'] = 'Please enter a valid email address so we can reply.';
    }
    if (mb_strlen($form['subject']) < 3 || mb_strlen($form['subject']) > 150) {
        $errors['subject'] = 'Please add a short subject (3 to 150 characters).';
    }
    if (mb_strlen($form['message']) < 10 || mb_strlen($form['message']) > 3000) {
        $errors['message'] = 'Please write at least 10 characters (3000 at most).';
    }

    // Simple rate limit per browser session
    $wait = (int) ($_SESSION['last_contact_at'] ?? 0) + 30 - time();
    if (!$errors && $wait > 0) {
        $errors['message'] = 'Please wait ' . $wait . ' seconds before sending another message.';
    }

    if (!$errors) {
        db_exec(
            'INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)',
            [$form['name'], $form['email'], $form['subject'], $form['message']]
        );
        notify_admins('New message', $form['name'] . ' wrote: ' . $form['subject'], 'admin/messages.php');
        $_SESSION['last_contact_at'] = time();

        flash('success', 'Thank you, ' . $form['name'] . '. Your message has been sent. We usually reply within one working day.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contact';
$activeNav = 'contact';
$renderFlashesInPage = true;
require __DIR__ . '/includes/layout/public-header.php';
?>

<section class="section">
    <div class="container">
        <div class="row g-4 g-lg-5">
            <!-- Help information -->
            <div class="col-lg-5">
                <h1>Contact us</h1>
                <p class="text-soft">Questions about bookings, a hospital you would like to see listed, or trouble with your account? Send us a message.</p>

                <dl class="detail-list mt-4">
                    <dt>Helpline</dt>
                    <dd><a href="tel:021111832532">021-111-832-532</a></dd>
                    <dt>Email</dt>
                    <dd><a href="mailto:help@teekacard.pk">help@teekacard.pk</a></dd>
                    <dt>Hours</dt>
                    <dd>Monday to Saturday, 9 AM to 6 PM</dd>
                    <dt>Office</dt>
                    <dd>Aptech Learning, Shahrah-e-Faisal, Karachi</dd>
                </dl>

                <h2 class="h4 mt-5">Quick answers</h2>
                <details class="faq">
                    <summary>How do I add twins or more than one child?</summary>
                    <p>Add each child separately under <em>My children</em>. Each child gets their own card and can be booked on their own.</p>
                </details>
                <details class="faq">
                    <summary>Why is my booking still waiting?</summary>
                    <p>The administrator checks every request against the hospital's timings. You get a notification as soon as it is approved or rejected.</p>
                </details>
                <details class="faq">
                    <summary>My child already had some vaccines. What now?</summary>
                    <p>Open the child's card and use <em>Record an earlier dose</em> to copy dates from the paper card.</p>
                </details>
            </div>

            <!-- Message form -->
            <div class="col-lg-7">
                <?php render_flashes(); ?>
                <form class="sheet" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="sheet-head"><h2>Send a message</h2></div>
                    <div class="sheet-body">
                        <!-- Hidden from people; bots tend to fill it -->
                        <div class="visually-hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="name">Your name</label>
                                <input class="form-control<?= invalid($errors, 'name') ?>" id="name" name="name" value="<?= e($form['name']) ?>" required autocomplete="name">
                                <?= field_error($errors, 'name') ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required" for="email">Email</label>
                                <input class="form-control<?= invalid($errors, 'email') ?>" type="email" id="email" name="email" value="<?= e($form['email']) ?>" required autocomplete="email">
                                <?= field_error($errors, 'email') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label required" for="subject">Subject</label>
                                <input class="form-control<?= invalid($errors, 'subject') ?>" id="subject" name="subject" value="<?= e($form['subject']) ?>" required maxlength="150">
                                <?= field_error($errors, 'subject') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label required" for="message">Message</label>
                                <textarea class="form-control<?= invalid($errors, 'message') ?>" id="message" name="message" rows="6" required maxlength="3000"><?= e($form['message']) ?></textarea>
                                <?= field_error($errors, 'message') ?>
                            </div>
                        </div>
                    </div>
                    <div class="sheet-foot d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small-note">We never share your details.</span>
                        <button class="btn btn-primary btn-icon" type="submit"><i class="bi bi-send" aria-hidden="true"></i> Send message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/public-footer.php'; ?>
