<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/children.php
 * Purpose : Parent module - "Child details".
 *           Lists the parent's children with their vaccination progress,
 *           and adds / edits / deletes a child.
 *           ?action=add            -> add form
 *           ?action=edit&id=<id>   -> edit form
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

$parentId = current_user_id();
$action   = get('action');
$editId   = int_param('id');

/** Loads one child only if it belongs to the logged-in parent. */
function own_child(int $childId, int $parentId): ?array
{
    return db_one('SELECT * FROM children WHERE id = ? AND parent_id = ?', [$childId, $parentId]);
}

/* ------------------------------------------------------------------
 * Form defaults (add) or the child's current values (edit)
 * ------------------------------------------------------------------ */
$form   = ['name' => '', 'date_of_birth' => '', 'gender' => '', 'blood_group' => '', 'birth_weight' => '', 'notes' => ''];
$errors = [];
$child  = null;

if ($action === 'edit') {
    $child = own_child($editId, $parentId);
    if (!$child) {
        flash('danger', 'That child was not found.');
        redirect('parent/children.php');
    }
    foreach ($form as $key => $unused) {
        $form[$key] = (string) ($child[$key] ?? '');
    }
    $form['birth_weight'] = $child['birth_weight'] !== null ? (string) (float) $child['birth_weight'] : '';
}

/* ------------------------------------------------------------------
 * POST: save (add or edit) and delete
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $postAction = post('action');

    /* ---------- Delete a child ---------- */
    if ($postAction === 'delete') {
        $target = own_child(int_param('child_id', 'post'), $parentId);
        if (!$target) {
            flash('danger', 'That child was not found.');
            redirect('parent/children.php');
        }

        // Keep medical records safe: no delete when a hospital has recorded doses or a booking is open
        $hospitalDoses = (int) db_value(
            "SELECT COUNT(*) FROM appointment_vaccines av JOIN appointments a ON a.id = av.appointment_id
              WHERE a.child_id = ? AND av.status = 'Vaccinated'",
            [$target['id']]
        );
        $openBookings = (int) db_value(
            "SELECT COUNT(*) FROM appointments WHERE child_id = ? AND request_status IN ('Pending', 'Approved') AND vaccination_status = 'Pending'",
            [$target['id']]
        );

        if ($hospitalDoses > 0) {
            flash('danger', $target['name'] . ' has doses recorded by a hospital, so the record cannot be deleted. Contact the administrator if it was added by mistake.');
        } elseif ($openBookings > 0) {
            flash('warning', 'Please cancel ' . $target['name'] . "'s open bookings before deleting the record.");
        } else {
            // Paper-card doses and closed bookings are removed with the child (ON DELETE CASCADE)
            db_exec('DELETE FROM children WHERE id = ? AND parent_id = ?', [$target['id'], $parentId]);
            flash('success', $target['name'] . ' has been removed.');
        }
        redirect('parent/children.php');
    }

    /* ---------- Add or edit ---------- */
    if ($postAction === 'save') {
        foreach ($form as $key => $unused) {
            $form[$key] = post($key);
        }
        $form['name'] = preg_replace('/\s+/', ' ', $form['name']);
        $oldestDob    = (new DateTime(today()))->modify('-' . MAX_CHILD_AGE_YEARS . ' years')->format('Y-m-d');

        // Name
        if (!preg_match("/^[\\p{L} .'\\-]{2,100}$/u", $form['name'])) {
            $errors['name'] = 'Enter the child\'s name (2 to 100 letters).';
        }

        // Date of birth: real date, not in the future, child under the age limit
        if (!valid_date($form['date_of_birth'])) {
            $errors['date_of_birth'] = 'Enter a valid date of birth.';
        } elseif ($form['date_of_birth'] > today()) {
            $errors['date_of_birth'] = 'The date of birth cannot be in the future.';
        } elseif ($form['date_of_birth'] < $oldestDob) {
            $errors['date_of_birth'] = APP_NAME . ' is for children up to ' . MAX_CHILD_AGE_YEARS . ' years old.';
        }

        // Gender and optional fields
        if (!in_array($form['gender'], ['Male', 'Female'], true)) {
            $errors['gender'] = 'Please choose one.';
        }
        if ($form['blood_group'] !== '' && !in_array($form['blood_group'], blood_groups(), true)) {
            $errors['blood_group'] = 'Choose a blood group from the list.';
        }
        if ($form['birth_weight'] !== '' && (!is_numeric($form['birth_weight']) || $form['birth_weight'] < 0.5 || $form['birth_weight'] > 6.5)) {
            $errors['birth_weight'] = 'Birth weight should be between 0.5 and 6.5 kg.';
        }
        if (mb_strlen($form['notes']) > 255) {
            $errors['notes'] = 'Notes can be up to 255 characters.';
        }

        $childId = $action === 'edit' ? (int) $child['id'] : 0;

        // Same name and birthday twice for one parent is almost always a mistake
        if (!$errors) {
            $duplicate = db_value(
                'SELECT id FROM children WHERE parent_id = ? AND name = ? AND date_of_birth = ? AND id <> ?',
                [$parentId, $form['name'], $form['date_of_birth'], $childId]
            );
            if ($duplicate) {
                $errors['name'] = 'This child is already on your list.';
            }
        }

        // When editing: the birthday cannot be after a dose that was already given
        if (!$errors && $childId) {
            $firstDose = db_value(
                "SELECT MIN(d) FROM (
                    SELECT DATE(av.given_at) AS d FROM appointment_vaccines av
                      JOIN appointments a ON a.id = av.appointment_id
                     WHERE a.child_id = ? AND av.status = 'Vaccinated'
                    UNION ALL
                    SELECT given_on FROM vaccination_history WHERE child_id = ?
                 ) doses",
                [$childId, $childId]
            );
            if ($firstDose && $form['date_of_birth'] > $firstDose) {
                $errors['date_of_birth'] = 'A dose was recorded on ' . fmt_date($firstDose) . ', so the birth date must be on or before that day.';
            }
        }

        if (!$errors) {
            $values = [
                $form['name'],
                $form['date_of_birth'],
                $form['gender'],
                $form['blood_group'] !== '' ? $form['blood_group'] : null,
                $form['birth_weight'] !== '' ? round((float) $form['birth_weight'], 2) : null,
                $form['notes'] !== '' ? $form['notes'] : null,
            ];

            if ($childId) {
                db_exec(
                    'UPDATE children SET name = ?, date_of_birth = ?, gender = ?, blood_group = ?, birth_weight = ?, notes = ?
                      WHERE id = ? AND parent_id = ?',
                    array_merge($values, [$childId, $parentId])
                );
                flash('success', $form['name'] . "'s details have been updated.");
            } else {
                $childId = db_insert(
                    'INSERT INTO children (name, date_of_birth, gender, blood_group, birth_weight, notes, parent_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    array_merge($values, [$parentId])
                );
                notify_admins('New child added', current_user_name() . ' added ' . $form['name'] . ' (born ' . fmt_date($form['date_of_birth']) . ').', 'admin/children.php?id=' . $childId);
                flash('success', $form['name'] . ' has been added. Here is the vaccination card with every due date.');
            }
            redirect('parent/child.php?id=' . $childId);
        }
    }
}

/* ------------------------------------------------------------------
 * List view data
 * ------------------------------------------------------------------ */
$children  = db_all('SELECT * FROM children WHERE parent_id = ? ORDER BY date_of_birth DESC, id DESC', [$parentId]);
$schedules = build_schedules($children);

$showForm  = in_array($action, ['add', 'edit'], true);
$pageTitle = $showForm ? ($action === 'edit' ? 'Edit child details' : 'Add a child') : 'My children';
$activeNav = 'children';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<?php if ($showForm): ?>
    <!-- ================= Add / edit form ================= -->
    <div class="row g-4">
        <div class="col-xl-8">
            <form class="sheet" method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <div class="sheet-head">
                    <h2><?= $action === 'edit' ? 'Update ' . e($child['name']) : 'Child details' ?></h2>
                </div>
                <div class="sheet-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label required" for="f-name">Child's full name</label>
                            <input class="form-control<?= invalid($errors, 'name') ?>" id="f-name" name="name" value="<?= e($form['name']) ?>" required autofocus>
                            <?= field_error($errors, 'name') ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label required" for="f-dob">Date of birth</label>
                            <input class="form-control<?= invalid($errors, 'date_of_birth') ?>" type="date" id="f-dob" name="date_of_birth"
                                   value="<?= e($form['date_of_birth']) ?>" max="<?= today() ?>" required>
                            <?= field_error($errors, 'date_of_birth') ?>
                        </div>

                        <div class="col-md-5">
                            <fieldset>
                                <legend class="form-label required fs-6">Gender</legend>
                                <div class="d-flex gap-4 pt-1">
                                    <?php foreach (['Female', 'Male'] as $g): ?>
                                        <div class="form-check">
                                            <input class="form-check-input<?= invalid($errors, 'gender') ?>" type="radio" name="gender" id="g-<?= $g ?>" value="<?= $g ?>"<?= checked($form['gender'] === $g) ?>>
                                            <label class="form-check-label" for="g-<?= $g ?>"><?= $g === 'Female' ? 'Girl' : 'Boy' ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (isset($errors['gender'])): ?><div class="text-danger small"><?= e($errors['gender']) ?></div><?php endif; ?>
                            </fieldset>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="f-blood">Blood group</label>
                            <select class="form-select<?= invalid($errors, 'blood_group') ?>" id="f-blood" name="blood_group">
                                <option value="">Not known</option>
                                <?php foreach (blood_groups() as $bg): ?>
                                    <option<?= selected($bg, $form['blood_group']) ?>><?= e($bg) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'blood_group') ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="f-weight">Birth weight</label>
                            <div class="input-group has-validation">
                                <input class="form-control<?= invalid($errors, 'birth_weight') ?>" type="number" step="0.01" min="0.5" max="6.5"
                                       id="f-weight" name="birth_weight" value="<?= e($form['birth_weight']) ?>">
                                <span class="input-group-text">kg</span>
                                <?= field_error($errors, 'birth_weight') ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="f-notes">Allergies or medical notes</label>
                            <input class="form-control<?= invalid($errors, 'notes') ?>" id="f-notes" name="notes" value="<?= e($form['notes']) ?>"
                                   maxlength="255" placeholder="e.g. allergic to eggs" aria-describedby="notes-help">
                            <div class="form-text" id="notes-help">Hospitals see this note with the appointment.</div>
                            <?= field_error($errors, 'notes') ?>
                        </div>
                    </div>
                </div>
                <div class="sheet-foot d-flex justify-content-end gap-2">
                    <a class="btn btn-light" href="<?= url($action === 'edit' ? 'parent/child.php?id=' . (int) $child['id'] : 'parent/children.php') ?>">Cancel</a>
                    <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Save changes' : 'Add child' ?></button>
                </div>
            </form>
        </div>

        <div class="col-xl-4">
            <div class="sheet sheet-body">
                <h2 class="h4">What happens next?</h2>
                <p class="text-soft mb-2">As soon as you save, <?= e(APP_NAME) ?> works out the date of every vaccine from the date of birth.</p>
                <p class="text-soft mb-0">If your child already had some vaccines, open the card and record them from the paper card, so they are not booked again.</p>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ================= Children list ================= -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="mb-0 text-soft">
            <?= count($children) ?> child<?= count($children) === 1 ? '' : 'ren' ?> on your account.
        </p>
        <a class="btn btn-primary btn-icon" href="<?= url('parent/children.php?action=add') ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Add a child
        </a>
    </div>

    <div class="sheet">
        <?php if (!$children): ?>
            <?= empty_state('No children added yet', 'Add your child to see the full vaccination schedule with dates.', url('parent/children.php?action=add'), 'Add a child') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Child</th>
                            <th scope="col">Born</th>
                            <th scope="col">Progress</th>
                            <th scope="col">Next vaccine</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $c): ?>
                            <?php
                            $s       = $schedules[$c['id']]['summary'];
                            $percent = $s['total'] ? (int) round($s['given'] / $s['total'] * 100) : 0;
                            $next    = $s['next'];
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar <?= $c['gender'] === 'Female' ? 'avatar-girl' : 'avatar-boy' ?>" aria-hidden="true"><?= e(initials($c['name'])) ?></span>
                                        <div>
                                            <a class="cell-title" href="<?= url('parent/child.php?id=' . (int) $c['id']) ?>"><?= e($c['name']) ?></a>
                                            <div class="cell-sub"><?= $c['gender'] === 'Female' ? 'Girl' : 'Boy' ?>, <?= e(child_age($c['date_of_birth'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-nowrap"><?= e(fmt_date($c['date_of_birth'])) ?></td>
                                <td style="min-width: 160px">
                                    <div class="small mb-1"><?= (int) $s['given'] ?> of <?= (int) $s['total'] ?> doses</div>
                                    <div class="progress-rail"><span style="width: <?= $percent ?>%"></span></div>
                                    <?php if ($s['overdue']): ?><div class="small text-danger mt-1"><?= (int) $s['overdue'] ?> overdue</div><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$next): ?>
                                        <span class="stamp-line">ALL DONE</span>
                                    <?php else: ?>
                                        <?php [, $cls] = state_meta($next['state']['key']); ?>
                                        <div class="fw-600"><?= e($next['vaccine']['age_label']) ?> visit</div>
                                        <span class="state-chip <?= $cls ?>"><?= e($next['state']['key'] === 'booked' ? $next['state']['label'] : due_phrase($next['due_date'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-sm btn-primary" href="<?= url('parent/child.php?id=' . (int) $c['id']) ?>">Card</a>
                                        <?php if ($next): ?>
                                            <a class="btn btn-sm btn-light" href="<?= url('parent/book.php?child=' . (int) $c['id']) ?>">Book</a>
                                        <?php endif; ?>
                                        <a class="btn btn-sm btn-light" href="<?= url('parent/children.php?action=edit&id=' . (int) $c['id']) ?>" aria-label="Edit <?= e($c['name']) ?>">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </a>
                                        <form method="post" data-confirm="Remove <?= e($c['name']) ?> from your account? Their paper-card records will also be removed.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="child_id" value="<?= (int) $c['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete <?= e($c['name']) ?>">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
