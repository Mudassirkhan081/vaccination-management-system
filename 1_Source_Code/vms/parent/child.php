<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/child.php
 * Purpose : One child's digital vaccination card (all visits and doses
 *           with due dates), plus "Record an earlier dose" so doses from
 *           the old paper card are not booked again.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

$parentId = current_user_id();
$childId  = int_param('id');
$child    = db_one('SELECT * FROM children WHERE id = ? AND parent_id = ?', [$childId, $parentId]);
if (!$child) {
    flash('danger', 'That child was not found.');
    redirect('parent/children.php');
}

$card         = child_schedule($child);
$historyForm  = ['vaccine_id' => '', 'given_on' => '', 'place' => ''];
$errors       = [];

/* ------------------------------------------------------------------
 * POST: add or remove a dose from the paper card
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();

    if (post('action') === 'add_history') {
        $historyForm = ['vaccine_id' => post('vaccine_id'), 'given_on' => post('given_on'), 'place' => post('place')];
        $vaccineId   = (int) $historyForm['vaccine_id'];

        // Find this vaccine's dose on the child's card
        $dose = null;
        foreach ($card['visits'] as $visit) {
            foreach ($visit['doses'] as $d) {
                if ((int) $d['vaccine']['id'] === $vaccineId) {
                    $dose = $d;
                }
            }
        }

        if (!$dose) {
            $errors['vaccine_id'] = 'Choose a vaccine from the list.';
        } elseif ($dose['state']['key'] === 'given') {
            $errors['vaccine_id'] = 'This dose is already recorded.';
        } elseif ($dose['state']['key'] === 'booked') {
            $errors['vaccine_id'] = 'This dose is part of an open booking. Cancel that booking first if it was already given.';
        }

        if (!valid_date($historyForm['given_on'])) {
            $errors['given_on'] = 'Enter the date shown on the paper card.';
        } elseif ($historyForm['given_on'] < $child['date_of_birth']) {
            $errors['given_on'] = 'The date cannot be before the date of birth (' . fmt_date($child['date_of_birth']) . ').';
        } elseif ($historyForm['given_on'] > today()) {
            $errors['given_on'] = 'The date cannot be in the future.';
        }

        if (mb_strlen($historyForm['place']) > 150) {
            $errors['place'] = 'Place can be up to 150 characters.';
        }

        if (!$errors) {
            db_exec(
                'INSERT INTO vaccination_history (child_id, vaccine_id, given_on, place) VALUES (?, ?, ?, ?)',
                [$childId, $vaccineId, $historyForm['given_on'], $historyForm['place'] !== '' ? $historyForm['place'] : null]
            );
            flash('success', $dose['vaccine']['short_name'] . ' has been added to ' . $child['name'] . "'s card.");
            redirect('parent/child.php?id=' . $childId . '#history');
        }
    }

    if (post('action') === 'delete_history') {
        $removed = db_exec(
            'DELETE vh FROM vaccination_history vh JOIN children c ON c.id = vh.child_id
              WHERE vh.id = ? AND c.id = ? AND c.parent_id = ?',
            [int_param('history_id', 'post'), $childId, $parentId]
        );
        flash($removed ? 'success' : 'danger', $removed ? 'The earlier dose has been removed.' : 'That record was not found.');
        redirect('parent/child.php?id=' . $childId . '#history');
    }
}

/* ------------------------------------------------------------------
 * Data for the page
 * ------------------------------------------------------------------ */
// Doses that can still be recorded from the paper card (not given, not booked)
$recordable = [];
foreach ($card['visits'] as $visit) {
    foreach ($visit['doses'] as $d) {
        if (!in_array($d['state']['key'], ['given', 'booked'], true)) {
            $recordable[$visit['label']][] = $d['vaccine'];
        }
    }
}

$history = db_all(
    'SELECT vh.*, v.short_name, v.name AS vaccine_name FROM vaccination_history vh
       JOIN vaccines v ON v.id = vh.vaccine_id
      WHERE vh.child_id = ? ORDER BY vh.given_on, v.age_days',
    [$childId]
);

$bookings = db_all(
    'SELECT a.*, h.name AS hospital_name FROM appointments a JOIN hospitals h ON h.id = a.hospital_id
      WHERE a.child_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 5',
    [$childId]
);
$bookingVaccines = appointment_vaccine_map(array_column($bookings, 'id'));

$pageTitle = $child['name'];
$activeNav = 'children';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<!-- Page actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <a class="btn btn-light btn-sm btn-icon" href="<?= url('parent/children.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> All children</a>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($card['summary']['next']): ?>
            <a class="btn btn-primary btn-icon" href="<?= url('parent/book.php?child=' . $childId) ?>"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Book a visit</a>
        <?php endif; ?>
        <a class="btn btn-light btn-icon" href="<?= url('parent/report.php?child=' . $childId) ?>"><i class="bi bi-file-earmark-medical" aria-hidden="true"></i> Report</a>
        <a class="btn btn-light btn-icon" href="<?= url('parent/children.php?action=edit&id=' . $childId) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> Edit details</a>
    </div>
</div>

<!-- Summary strip -->
<?php $s = $card['summary']; ?>
<div class="ledger mb-4">
    <div class="ledger-cell is-ink"><div class="ledger-num"><?= (int) $s['given'] ?></div><div class="ledger-label">doses given</div></div>
    <div class="ledger-cell"><div class="ledger-num"><?= (int) $s['booked'] ?></div><div class="ledger-label">doses booked</div></div>
    <div class="ledger-cell<?= $s['due'] ? ' is-amber' : '' ?>"><div class="ledger-num"><?= (int) $s['due'] ?></div><div class="ledger-label">due in <?= (int) DUE_SOON_DAYS ?> days</div></div>
    <div class="ledger-cell<?= $s['overdue'] ? ' is-alert' : '' ?>"><div class="ledger-num"><?= (int) $s['overdue'] ?></div><div class="ledger-label">overdue</div></div>
</div>

<!-- The vaccination card -->
<?php
$cardBookUrl = url('parent/book.php?child=' . $childId);
require APP_ROOT . '/includes/partials/vaccination-card.php';
?>

<div class="row g-4 mt-1">
    <!-- Record an earlier dose -->
    <div class="col-xl-6" id="history">
        <div class="sheet h-100">
            <div class="sheet-head"><h2>Record an earlier dose</h2></div>
            <div class="sheet-body">
                <p class="text-soft">Copy doses from the paper card that were given before you joined <?= e(APP_NAME) ?>.</p>

                <?php if (!$recordable): ?>
                    <p class="mb-0">Every dose is already recorded or booked.</p>
                <?php else: ?>
                    <form method="post" novalidate class="row g-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_history">
                        <div class="col-md-6">
                            <label class="form-label required" for="h-vaccine">Vaccine</label>
                            <select class="form-select<?= invalid($errors, 'vaccine_id') ?>" id="h-vaccine" name="vaccine_id" required>
                                <option value="">Choose a dose</option>
                                <?php foreach ($recordable as $label => $vaccines): ?>
                                    <optgroup label="<?= e($label) ?>">
                                        <?php foreach ($vaccines as $v): ?>
                                            <option value="<?= (int) $v['id'] ?>"<?= selected($v['id'], $historyForm['vaccine_id']) ?>><?= e($v['short_name'] . ' - ' . $v['name']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error($errors, 'vaccine_id') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="h-date">Date given</label>
                            <input class="form-control<?= invalid($errors, 'given_on') ?>" type="date" id="h-date" name="given_on"
                                   value="<?= e($historyForm['given_on']) ?>" min="<?= e($child['date_of_birth']) ?>" max="<?= today() ?>" required>
                            <?= field_error($errors, 'given_on') ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="h-place">Where was it given?</label>
                            <input class="form-control<?= invalid($errors, 'place') ?>" id="h-place" name="place" value="<?= e($historyForm['place']) ?>" maxlength="150" placeholder="e.g. Civil Hospital, Karachi">
                            <?= field_error($errors, 'place') ?>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-ink" type="submit">Add to card</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($history): ?>
                <div class="table-responsive border-top">
                    <table class="table">
                        <thead><tr><th scope="col">Dose</th><th scope="col">Given</th><th scope="col">Place</th><th scope="col"><span class="visually-hidden">Remove</span></th></tr></thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><span class="cell-title"><?= e($h['short_name']) ?></span><div class="cell-sub"><?= e($h['vaccine_name']) ?></div></td>
                                    <td class="text-nowrap"><?= e(fmt_date($h['given_on'])) ?></td>
                                    <td><?= e($h['place'] ?: '-') ?></td>
                                    <td class="text-end">
                                        <form method="post" data-confirm="Remove this earlier dose from the card?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_history">
                                            <input type="hidden" name="history_id" value="<?= (int) $h['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Remove <?= e($h['short_name']) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent bookings for this child -->
    <div class="col-xl-6">
        <div class="sheet h-100">
            <div class="sheet-head">
                <h2>Recent bookings</h2>
                <a class="small" href="<?= url('parent/bookings.php?child=' . $childId . '&tab=all') ?>">See all</a>
            </div>
            <?php if (!$bookings): ?>
                <?= empty_state('No bookings yet', 'Book a hospital visit when a vaccine is due.') ?>
            <?php else: ?>
                <?php foreach ($bookings as $b): ?>
                    <div class="reminder">
                        <div class="reminder-date">
                            <span><?= e(strtoupper(fmt_date($b['appointment_date'], 'M'))) ?></span>
                            <strong><?= e(fmt_date($b['appointment_date'], 'j')) ?></strong>
                        </div>
                        <div>
                            <div class="fw-600"><?= e($b['hospital_name']) ?></div>
                            <div class="small-note mb-1"><?= e(booking_ref((int) $b['id'])) ?> &middot; <?= e(fmt_time($b['appointment_time'])) ?></div>
                            <?= vaccine_chips($bookingVaccines[$b['id']] ?? [], true) ?>
                        </div>
                        <div><?= booking_stage_badge($b) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
