<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/book.php
 * Purpose : Parent module - "Book Hospital" / "Request for hospital".
 *           The parent picks a child, the vaccines to be given, a
 *           hospital (searchable by city) and a date and time.
 *           The request is saved as "Pending" for the administrator.
 *
 * BOOKING ALGORITHM
 *   1. The child must belong to the parent.
 *   2. Every chosen vaccine must be open on the child's card (not given,
 *      not already booked) and currently Available.
 *   3. The hospital must be Active.
 *   4. Date: today .. today + MAX_BOOKING_DAYS; time: a listed slot that
 *      has not passed; the slot must have fewer than SLOT_CAPACITY
 *      open bookings; the child has no other open booking that day.
 *   5. appointment + appointment_vaccines rows are saved in ONE
 *      transaction, then the administrators are notified.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

$parentId = current_user_id();
$parent   = $GLOBALS['authUser'];
$children = db_all('SELECT * FROM children WHERE parent_id = ? ORDER BY date_of_birth DESC', [$parentId]);

/* ------------------------------------------------------------------
 * 1. Which child? (form value, link value, or the only child)
 * ------------------------------------------------------------------ */
$childId = is_post() ? int_param('child_id', 'post') : int_param('child');
if (!$childId && count($children) === 1) {
    $childId = (int) $children[0]['id'];
}
$child = null;
foreach ($children as $c) {
    if ((int) $c['id'] === $childId) {
        $child = $c;
    }
}

/* ------------------------------------------------------------------
 * 2. Hospital chosen from the public directory, and the city filter
 * ------------------------------------------------------------------ */
$hospitalId  = is_post() ? int_param('hospital_id', 'post') : int_param('hospital');
$preHospital = $hospitalId ? db_one("SELECT * FROM hospitals WHERE id = ? AND status = 'Active'", [$hospitalId]) : null;
if (!$preHospital) {
    $hospitalId = 0;
}

$city = array_key_exists('city', $_GET) ? get('city') : ($preHospital['city'] ?? $parent['city']);
if ($city !== '' && !in_array($city, city_list(), true)) {
    $city = '';
}

$hospitals = db_all(
    "SELECT * FROM hospitals WHERE status = 'Active'" . ($city !== '' ? ' AND city = ?' : '') . ' ORDER BY name',
    $city !== '' ? [$city] : []
);
// Keep a pre-selected hospital visible even when it is in another city
if ($preHospital && !in_array((int) $preHospital['id'], array_map('intval', array_column($hospitals, 'id')), true)) {
    array_unshift($hospitals, $preHospital);
}

/* ------------------------------------------------------------------
 * 3. The child's open doses, grouped by visit
 * ------------------------------------------------------------------ */
$groups      = [];
$selectable  = [];   // vaccine id => vaccine row (only doses that may be booked)
if ($child) {
    $card = child_schedule($child);
    foreach ($card['visits'] as $visit) {
        if ($visit['state'] === 'given') {
            continue;   // finished visits are not shown
        }
        $items = [];
        foreach ($visit['doses'] as $d) {
            $open      = !in_array($d['state']['key'], ['given', 'booked'], true);
            $inStock   = $d['vaccine']['status'] === 'Available';
            $d['can_pick'] = $open && $inStock;
            if ($d['can_pick']) {
                $selectable[(int) $d['vaccine']['id']] = $d['vaccine'];
            }
            $items[] = $d;
        }
        $groups[] = ['visit' => $visit, 'age' => (int) $visit['doses'][0]['vaccine']['age_days'], 'items' => $items];
    }
}

// Visit asked for in the link (?visit=42); 0 is a valid value (birth)
$visitParam = (isset($_GET['visit']) && ctype_digit((string) $_GET['visit'])) ? (int) $_GET['visit'] : null;

/* ------------------------------------------------------------------
 * 4. Default form values
 * ------------------------------------------------------------------ */
$errors  = [];
$checked = [];
$form    = ['appointment_date' => '', 'appointment_time' => '10:00', 'parent_note' => ''];

if (!is_post() && $child) {
    // Tick the doses of the requested visit, or of overdue / due visits
    foreach ($groups as $g) {
        $wanted = $visitParam !== null ? $g['age'] === $visitParam : in_array($g['visit']['state'], ['overdue', 'due'], true);
        if ($wanted) {
            foreach ($g['items'] as $d) {
                if ($d['can_pick']) {
                    $checked[] = (int) $d['vaccine']['id'];
                }
            }
        }
    }
    // Nothing urgent: tick the first visit that still has open doses
    if (!$checked) {
        foreach ($groups as $g) {
            $ids = [];
            foreach ($g['items'] as $d) {
                if ($d['can_pick']) {
                    $ids[] = (int) $d['vaccine']['id'];
                }
            }
            if ($ids) {
                $checked = $ids;
                break;
            }
        }
    }

    // Suggested date: the due date of the first ticked visit, never earlier than tomorrow
    $tomorrow = add_days(today(), 1);
    $suggest  = $tomorrow;
    foreach ($groups as $g) {
        foreach ($g['items'] as $d) {
            if (in_array((int) $d['vaccine']['id'], $checked, true)) {
                $suggest = max($tomorrow, $d['due_date']);
                break 2;
            }
        }
    }
    $form['appointment_date'] = $suggest <= add_days(today(), MAX_BOOKING_DAYS) ? $suggest : $tomorrow;
}

/* ------------------------------------------------------------------
 * 5. Saving the request
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();

    $form = [
        'appointment_date' => post('appointment_date'),
        'appointment_time' => post('appointment_time'),
        'parent_note'      => post('parent_note'),
    ];
    $checked = post_int_list('vaccine_ids');

    if (!$child) {
        $errors['child_id'] = 'Please choose one of your children.';
    }

    // Vaccines
    if (!$checked) {
        $errors['vaccine_ids'] = 'Tick at least one vaccine.';
    } else {
        foreach ($checked as $vid) {
            if (!isset($selectable[$vid])) {
                $errors['vaccine_ids'] = 'One of the chosen vaccines was already given, is already booked or is out of stock. Please check the list again.';
                break;
            }
        }
    }

    // Hospital
    if (!$preHospital) {
        $errors['hospital_id'] = 'Please choose a hospital.';
    }

    // Date and time
    validate_visit_slot($form['appointment_date'], $form['appointment_time'], $hospitalId, 0, $errors);

    // One open visit per child per day
    if ($child && !isset($errors['appointment_date']) && db_value(
        "SELECT COUNT(*) FROM appointments WHERE child_id = ? AND appointment_date = ?
            AND request_status IN ('Pending', 'Approved') AND vaccination_status = 'Pending'",
        [$childId, $form['appointment_date']]
    )) {
        $errors['appointment_date'] = $child['name'] . ' already has a booking on this day.';
    }

    if (mb_strlen($form['parent_note']) > 255) {
        $errors['parent_note'] = 'The note can be up to 255 characters.';
    }

    if (!$errors) {
        // Both tables are written together: all rows are saved, or none
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $appointmentId = db_insert(
                'INSERT INTO appointments (parent_id, child_id, hospital_id, appointment_date, appointment_time, parent_note)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$parentId, $childId, $hospitalId, $form['appointment_date'], $form['appointment_time'] . ':00',
                 $form['parent_note'] !== '' ? $form['parent_note'] : null]
            );
            foreach ($checked as $vid) {
                db_exec('INSERT INTO appointment_vaccines (appointment_id, vaccine_id) VALUES (?, ?)', [$appointmentId, $vid]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        notify_admins(
            'New booking request',
            current_user_name() . ' requested a visit for ' . $child['name'] . ' at ' . $preHospital['name']
                . ' on ' . fmt_date($form['appointment_date']) . ', ' . fmt_time($form['appointment_time'])
                . ' (' . count($checked) . ' vaccine' . (count($checked) === 1 ? '' : 's') . ').',
            'admin/requests.php'
        );

        flash('success', 'Request ' . booking_ref($appointmentId) . ' has been sent. You will get a notification when the administrator approves it.');
        redirect('parent/bookings.php');
    }
}

$pageTitle = 'Book a hospital';
$activeNav = 'book';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<?php if (!$children): ?>
    <div class="sheet">
        <?= empty_state('Add a child first', 'Bookings are made for a child, so please add your child before booking.', url('parent/children.php?action=add'), 'Add a child') ?>
    </div>
<?php else: ?>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <strong>The request was not sent.</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">

        <!-- ============ Step 1: child and city (GET form, reloads the page) ============ -->
        <form class="sheet" method="get">
            <?php if ($visitParam !== null): ?><input type="hidden" name="visit" value="<?= (int) $visitParam ?>"><?php endif; ?>
            <div class="sheet-body">
                <h2 class="fieldset-title">1. Child and area</h2>
                <div class="filters">
                    <div class="grow">
                        <label class="form-label" for="child">Child</label>
                        <select class="form-select" id="child" name="child" data-autosubmit>
                            <option value="">Choose a child</option>
                            <?php foreach ($children as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $childId) ?>><?= e($c['name']) ?> (<?= e(child_age($c['date_of_birth'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="city">Hospitals in</label>
                        <select class="form-select" id="city" name="city" data-autosubmit>
                            <?= city_options($city, 'All cities') ?>
                        </select>
                    </div>
                    <noscript><button class="btn btn-light" type="submit">Apply</button></noscript>
                </div>
            </div>
        </form>

        <?php if (!$child): ?>
            <div class="sheet">
                <?= empty_state('Choose a child to continue', 'The vaccines on the next step depend on the child\'s age.') ?>
            </div>
        <?php else: ?>

        <!-- ============ Steps 2-4: the booking request (POST form) ============ -->
        <form class="sheet" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="child_id" value="<?= (int) $childId ?>">

            <!-- Step 2: vaccines -->
            <div class="sheet-body">
                <h2 class="fieldset-title">2. Vaccines for <?= e($child['name']) ?></h2>
                <?php if (!$groups): ?>
                    <p class="mb-0"><span class="stamp-line">ALL DONE</span> Every vaccine on the schedule has been given.</p>
                <?php else: ?>
                    <div class="vaccine-pick<?= isset($errors['vaccine_ids']) ? ' border-danger' : '' ?>">
                        <?php foreach ($groups as $g): ?>
                            <?php
                            [, $gClass] = state_meta($g['visit']['state']);
                            $groupId    = 'visit-' . $g['age'];
                            $pickable   = array_filter($g['items'], function ($d) { return $d['can_pick']; });
                            ?>
                            <div class="vaccine-pick-group" id="<?= $groupId ?>" role="group" aria-labelledby="<?= $groupId ?>-title">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <h3 id="<?= $groupId ?>-title">
                                        <?= e($g['visit']['label']) ?>
                                        <span class="small-note fw-normal">due <?= e(fmt_date($g['visit']['due_date'])) ?></span>
                                        <span class="state-chip <?= $gClass ?>"><?= e(state_meta($g['visit']['state'])[0]) ?></span>
                                    </h3>
                                    <?php if (count($pickable) > 1): ?>
                                        <button class="btn btn-sm btn-link p-0" type="button" data-check-group="<?= $groupId ?>">Select all</button>
                                    <?php endif; ?>
                                </div>
                                <div class="row">
                                    <?php foreach ($g['items'] as $d): ?>
                                        <?php $v = $d['vaccine']; $vid = (int) $v['id']; ?>
                                        <div class="col-md-6">
                                            <div class="vaccine-option<?= $d['can_pick'] ? '' : ' is-disabled' ?>">
                                                <input class="form-check-input" type="checkbox" name="vaccine_ids[]" value="<?= $vid ?>" id="v-<?= $vid ?>"
                                                    <?= $d['can_pick'] ? checked(in_array($vid, $checked, true)) : 'disabled' ?>>
                                                <label for="v-<?= $vid ?>">
                                                    <strong><?= e($v['short_name']) ?></strong> <span class="text-soft"><?= e($v['name']) ?></span>
                                                    <span class="d-block small-note">
                                                        <?php if ($d['state']['key'] === 'given'): ?>
                                                            Already given
                                                        <?php elseif ($d['state']['key'] === 'booked'): ?>
                                                            Already in a booking for <?= e(fmt_date($d['booked']['date'])) ?>
                                                        <?php elseif (!$d['can_pick']): ?>
                                                            <span class="text-warning-emphasis">Out of stock at the moment</span>
                                                        <?php else: ?>
                                                            <?= e($v['protects_against']) ?>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Step 3: hospital -->
            <div class="sheet-body border-top">
                <h2 class="fieldset-title">3. Hospital <?= $city !== '' ? '<span class="small-note fw-normal">in ' . e($city) . '</span>' : '' ?></h2>
                <?php if (!$hospitals): ?>
                    <p class="mb-0">No active hospitals in <?= e($city) ?> yet. Choose <strong>All cities</strong> above, or
                        <a href="<?= e(url('contact.php?subject=' . urlencode('Hospital request for ' . $city))) ?>">request a hospital</a>.</p>
                <?php else: ?>
                    <fieldset>
                        <legend class="visually-hidden">Choose a hospital</legend>
                        <div class="pick-list<?= isset($errors['hospital_id']) ? ' border-danger' : '' ?>">
                            <?php foreach ($hospitals as $h): ?>
                                <label class="pick-item">
                                    <input class="form-check-input" type="radio" name="hospital_id" value="<?= (int) $h['id'] ?>"<?= checked((int) $h['id'] === $hospitalId) ?> required>
                                    <span>
                                        <span class="fw-600 d-block"><?= e($h['name']) ?></span>
                                        <span class="small-note d-block"><?= e($h['address']) ?>, <?= e($h['city']) ?><?= $h['location'] && !preg_match('#^https?://#', $h['location']) ? ' (' . e($h['location']) . ')' : '' ?></span>
                                        <span class="small-note d-block"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= e($h['timings'] ?: 'Timings not listed') ?> &middot; <?= e($h['phone']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>
            </div>

            <!-- Step 4: date, time and note -->
            <div class="sheet-body border-top">
                <h2 class="fieldset-title">4. Date and time</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="f-date">Date</label>
                        <input class="form-control<?= invalid($errors, 'appointment_date') ?>" type="date" id="f-date" name="appointment_date"
                               value="<?= e($form['appointment_date']) ?>" min="<?= today() ?>" max="<?= e(add_days(today(), MAX_BOOKING_DAYS)) ?>" required>
                        <?= field_error($errors, 'appointment_date') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="f-time">Time</label>
                        <select class="form-select<?= invalid($errors, 'appointment_time') ?>" id="f-time" name="appointment_time" required>
                            <?= time_slot_options($form['appointment_time']) ?>
                        </select>
                        <?= field_error($errors, 'appointment_time') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="f-note">Note for the hospital <span class="text-soft fw-normal">(optional)</span></label>
                        <textarea class="form-control<?= invalid($errors, 'parent_note') ?>" id="f-note" name="parent_note" rows="2" maxlength="255"
                                  placeholder="e.g. my child had a cold last week"><?= e($form['parent_note']) ?></textarea>
                        <?= field_error($errors, 'parent_note') ?>
                    </div>
                </div>
            </div>

            <div class="sheet-foot d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small-note">The administrator confirms the request, usually the same day.</span>
                <button class="btn btn-primary btn-icon" type="submit" <?= ($groups && $hospitals) ? '' : 'disabled' ?>>
                    <i class="bi bi-send" aria-hidden="true"></i> Send booking request
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- ============ Side notes ============ -->
    <div class="col-xl-4">
        <?php if ($child): ?>
            <?php $sum = $card['summary']; ?>
            <div class="sheet">
                <div class="sheet-body d-flex gap-3 align-items-center">
                    <span class="avatar avatar-lg <?= $child['gender'] === 'Female' ? 'avatar-girl' : 'avatar-boy' ?>" aria-hidden="true"><?= e(initials($child['name'])) ?></span>
                    <div>
                        <div class="fw-600"><?= e($child['name']) ?></div>
                        <div class="small-note"><?= e(child_age($child['date_of_birth'])) ?> old &middot; <?= (int) $sum['given'] ?>/<?= (int) $sum['total'] ?> doses given</div>
                        <a class="small" href="<?= url('parent/child.php?id=' . (int) $childId) ?>">Open vaccination card</a>
                    </div>
                </div>
                <?php if ($child['notes']): ?>
                    <div class="sheet-foot small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e($child['notes']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="sheet sheet-body">
            <h2 class="h4">How booking works</h2>
            <ol class="plain-steps mt-2 mb-0">
                <li>You send the request with the vaccines, hospital and time.</li>
                <li>The administrator approves it, or suggests another date.</li>
                <li>The hospital sees your visit and records each dose.</li>
            </ol>
            <p class="small-note mt-3 mb-0">Each half-hour slot takes up to <?= (int) SLOT_CAPACITY ?> children. Bookings are open for the next <?= (int) MAX_BOOKING_DAYS ?> days.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
