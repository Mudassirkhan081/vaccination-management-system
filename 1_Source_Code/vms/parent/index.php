<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/index.php
 * Purpose : Parent dashboard.
 *           "Vaccination dates" requirement: every visit that is overdue
 *           or due within the next 30 days is listed as a reminder, with
 *           a Book button or the current booking status.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

$parentId = current_user_id();
$children = db_all('SELECT * FROM children WHERE parent_id = ? ORDER BY date_of_birth DESC', [$parentId]);

/* ------------------------------------------------------------------
 * Schedule reminders and counters
 * ------------------------------------------------------------------ */
$schedules = build_schedules($children);
$reminders = schedule_alerts($children, 30);

$overdueVisits = count(array_filter($reminders, function ($r) { return $r['state'] === 'overdue'; }));
$dueVisits     = count(array_filter($reminders, function ($r) { return in_array($r['state'], ['due', 'upcoming'], true); }));

// Open bookings (waiting or confirmed, visit not done yet)
$upcoming = db_all(
    "SELECT a.*, c.name AS child_name, h.name AS hospital_name, h.address AS hospital_address
       FROM appointments a
       JOIN children c  ON c.id = a.child_id
       JOIN hospitals h ON h.id = a.hospital_id
      WHERE a.parent_id = ? AND a.request_status IN ('Pending', 'Approved')
        AND a.vaccination_status = 'Pending' AND a.appointment_date >= ?
      ORDER BY a.appointment_date, a.appointment_time",
    [$parentId, today()]
);
$upcomingVaccines = appointment_vaccine_map(array_column($upcoming, 'id'));

$totalGiven = array_sum(array_map(function ($s) { return $s['summary']['given']; }, $schedules));

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<p class="mb-3">Assalam-o-Alaikum, <strong><?= e(current_user_name()) ?></strong>. Here is what needs your attention.</p>

<!-- ================= Summary strip ================= -->
<div class="ledger mb-4">
    <a class="ledger-cell" href="<?= url('parent/children.php') ?>">
        <div class="ledger-num"><?= count($children) ?></div>
        <div class="ledger-label">children on your account</div>
    </a>
    <div class="ledger-cell<?= $overdueVisits ? ' is-alert' : '' ?>">
        <div class="ledger-num"><?= $overdueVisits ?></div>
        <div class="ledger-label">overdue visit<?= $overdueVisits === 1 ? '' : 's' ?></div>
    </div>
    <div class="ledger-cell<?= $dueVisits ? ' is-amber' : '' ?>">
        <div class="ledger-num"><?= $dueVisits ?></div>
        <div class="ledger-label">visits due in 30 days</div>
    </div>
    <a class="ledger-cell" href="<?= url('parent/bookings.php') ?>">
        <div class="ledger-num"><?= count($upcoming) ?></div>
        <div class="ledger-label">open booking<?= count($upcoming) === 1 ? '' : 's' ?></div>
    </a>
    <div class="ledger-cell is-ink">
        <div class="ledger-num"><?= $totalGiven ?></div>
        <div class="ledger-label">doses given in total</div>
    </div>
</div>

<?php if (!$children): ?>
    <div class="sheet">
        <?= empty_state('Start by adding your child', 'Enter the date of birth and every vaccination date will appear here.', url('parent/children.php?action=add'), 'Add a child') ?>
    </div>
<?php else: ?>

<div class="row g-4">
    <!-- ================= Vaccination date reminders ================= -->
    <div class="col-xl-7">
        <section class="sheet" aria-labelledby="dates-title">
            <div class="sheet-head">
                <h2 id="dates-title">Vaccination dates</h2>
                <span class="small-note">Overdue and next 30 days</span>
            </div>
            <?php if (!$reminders): ?>
                <?= empty_state('Nothing due in the next 30 days', 'We will remind you here when the next vaccine comes close.') ?>
            <?php endif; ?>
            <?php foreach ($reminders as $r): ?>
                <?php
                [, $cls] = state_meta($r['state']);
                // Booked visits show the booking date, others the due date
                $boxDate = $r['state'] === 'booked' ? $r['booking']['date'] : $r['due_date'];
                ?>
                <div class="reminder <?= $cls ?>">
                    <div class="reminder-date" aria-hidden="true">
                        <span><?= e(strtoupper(fmt_date($boxDate, 'M'))) ?></span>
                        <strong><?= e(fmt_date($boxDate, 'j')) ?></strong>
                    </div>
                    <div>
                        <div class="fw-600"><?= e($r['child']['name']) ?> &middot; <?= e($r['label']) ?> visit</div>
                        <div class="small-note mb-1">
                            <?php if ($r['state'] === 'booked'): ?>
                                Due <?= e(fmt_date($r['due_date'])) ?> &middot;
                                <?= ($r['booking']['status'] ?? '') === 'Approved' ? 'booked' : 'requested' ?> for
                                <?= e(fmt_time($r['booking']['time'])) ?> at <?= e($r['booking']['place']) ?>
                            <?php else: ?>
                                <?= e(due_phrase($r['due_date'])) ?> (<?= e(fmt_date($r['due_date'])) ?>)
                            <?php endif; ?>
                        </div>
                        <div class="dose-cells">
                            <?php foreach ($r['doses'] as $short): ?><span class="dose-cell"><?= e($short) ?></span><?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <?php if ($r['state'] === 'booked'): ?>
                            <span class="state-chip state-booked"><?= ($r['booking']['status'] ?? '') === 'Approved' ? 'Confirmed' : 'Waiting for approval' ?></span>
                        <?php else: ?>
                            <!-- Opens the booking form with exactly this visit's doses ticked -->
                            <a class="btn btn-sm <?= $r['state'] === 'overdue' ? 'btn-danger' : 'btn-primary' ?>"
                               href="<?= e(url('parent/book.php?child=' . (int) $r['child']['id'] . '&visit=' . $r['age_days'])) ?>">
                                Book<span class="visually-hidden"> <?= e($r['label']) ?> visit for <?= e($r['child']['name']) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- ================= Upcoming bookings ================= -->
        <section class="sheet" aria-labelledby="visits-title">
            <div class="sheet-head">
                <h2 id="visits-title">Upcoming hospital visits</h2>
                <a class="small" href="<?= url('parent/bookings.php') ?>">All bookings</a>
            </div>
            <?php if (!$upcoming): ?>
                <?= empty_state('No visits booked', 'Choose a hospital and a time that suits you.', url('parent/book.php'), 'Book a hospital') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th scope="col">When</th><th scope="col">Child and hospital</th><th scope="col">Vaccines</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($upcoming as $a): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <div class="cell-title"><?= e(fmt_date($a['appointment_date'])) ?></div>
                                        <div class="cell-sub"><?= e(fmt_time($a['appointment_time'])) ?>, <?= e(relative_days($a['appointment_date'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-600"><?= e($a['child_name']) ?></div>
                                        <div class="cell-sub"><?= e($a['hospital_name']) ?></div>
                                    </td>
                                    <td style="min-width: 150px"><?= vaccine_chips($upcomingVaccines[$a['id']] ?? [], false, true) ?></td>
                                    <td><?= booking_stage_badge($a) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- ================= Children ================= -->
    <div class="col-xl-5">
        <section class="sheet" aria-labelledby="kids-title">
            <div class="sheet-head">
                <h2 id="kids-title">My children</h2>
                <a class="btn btn-sm btn-light btn-icon" href="<?= url('parent/children.php?action=add') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add</a>
            </div>
            <?php foreach ($children as $c): ?>
                <?php
                $s       = $schedules[$c['id']]['summary'];
                $percent = $s['total'] ? (int) round($s['given'] / $s['total'] * 100) : 0;
                ?>
                <a class="child-tile" href="<?= url('parent/child.php?id=' . (int) $c['id']) ?>">
                    <span class="avatar <?= $c['gender'] === 'Female' ? 'avatar-girl' : 'avatar-boy' ?>" aria-hidden="true"><?= e(initials($c['name'])) ?></span>
                    <span class="flex-grow-1">
                        <span class="d-flex justify-content-between gap-2">
                            <span class="fw-600"><?= e($c['name']) ?></span>
                            <span class="small-note"><?= e(child_age($c['date_of_birth'])) ?></span>
                        </span>
                        <span class="progress-rail d-block my-1"><span style="width: <?= $percent ?>%"></span></span>
                        <span class="small-note">
                            <?= (int) $s['given'] ?>/<?= (int) $s['total'] ?> doses
                            <?php if ($s['overdue']): ?>&middot; <span class="text-danger fw-600"><?= (int) $s['overdue'] ?> overdue</span><?php endif; ?>
                            <?php if (!$s['next']): ?>&middot; schedule complete<?php endif; ?>
                        </span>
                    </span>
                    <i class="bi bi-chevron-right text-soft" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="sheet sheet-body" aria-labelledby="tips-title">
            <h2 class="h4" id="tips-title">Before the visit</h2>
            <ul class="mb-0 ps-3">
                <li>Carry the child's paper card and your CNIC.</li>
                <li>Tell the doctor about fever or allergies.</li>
                <li>Mild fever or soreness after a vaccine is common and passes in a day or two.</li>
            </ul>
        </section>
    </div>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
