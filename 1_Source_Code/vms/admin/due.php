<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/due.php
 * Purpose : Admin module - "Date & Time of vaccination: upcoming date
 *           of vaccination of all children".
 *           Part 1: every child's visits that are overdue or due inside
 *                   the chosen window (worked out from date of birth).
 *           Part 2: confirmed appointments (date and time) inside the
 *                   same window, grouped by day.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

/* ------------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------------ */
$windows = [
    'overdue' => 'Overdue only',
    '7'       => 'Next 7 days',
    '30'      => 'Next 30 days',
    '90'      => 'Next 90 days',
];
$window = get('window', '30');
if (!isset($windows[$window])) {
    $window = '30';
}
$showBooked = get('booked') !== 'hide';
$days       = $window === 'overdue' ? 0 : (int) $window;
$limitDate  = add_days(today(), $days);

/* ------------------------------------------------------------------
 * Part 1: due visits from the schedule engine
 * ------------------------------------------------------------------ */
$children = db_all(
    'SELECT c.*, p.name AS parent_name, p.phone AS parent_phone FROM children c JOIN parents p ON p.id = c.parent_id'
);
$alerts = schedule_alerts($children, $days);
$alerts = array_values(array_filter($alerts, function ($a) use ($window, $showBooked) {
    if ($window === 'overdue' && $a['state'] !== 'overdue') {
        return false;
    }
    return $showBooked || $a['state'] !== 'booked';
}));

$counts = ['overdue' => 0, 'due' => 0, 'booked' => 0, 'upcoming' => 0];
foreach ($alerts as $a) {
    $counts[$a['state']]++;
}

/* ------------------------------------------------------------------
 * Part 2: confirmed appointments by day
 * ------------------------------------------------------------------ */
$appointments = [];
if ($window !== 'overdue') {
    $rows = db_all(
        "SELECT a.*, c.name AS child_name, c.date_of_birth, p.phone AS parent_phone, h.name AS hospital_name
           FROM appointments a
           JOIN children c  ON c.id = a.child_id
           JOIN parents p   ON p.id = a.parent_id
           JOIN hospitals h ON h.id = a.hospital_id
          WHERE a.request_status = 'Approved' AND a.vaccination_status = 'Pending'
            AND a.appointment_date BETWEEN ? AND ?
          ORDER BY a.appointment_date, a.appointment_time",
        [today(), $limitDate]
    );
    $apptVaccines = appointment_vaccine_map(array_column($rows, 'id'));
    foreach ($rows as $r) {
        $appointments[$r['appointment_date']][] = $r;
    }
}

$pageTitle = 'Vaccination dates';
$activeNav = 'due';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<form class="filters mb-3" method="get">
    <div>
        <label class="form-label" for="window">Show</label>
        <select class="form-select" id="window" name="window" data-autosubmit>
            <?php foreach ($windows as $key => $label): ?>
                <option value="<?= $key ?>"<?= selected($key, $window) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="booked">Booked visits</label>
        <select class="form-select" id="booked" name="booked" data-autosubmit>
            <option value="">Include</option>
            <option value="hide"<?= selected('hide', $showBooked ? '' : 'hide') ?>>Hide (not booked only)</option>
        </select>
    </div>
    <noscript><button class="btn btn-light" type="submit">Apply</button></noscript>
    <button class="btn btn-light btn-icon ms-auto" type="button" data-print><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
</form>

<div class="ledger mb-4">
    <div class="ledger-cell<?= $counts['overdue'] ? ' is-alert' : '' ?>"><div class="ledger-num"><?= $counts['overdue'] ?></div><div class="ledger-label">overdue visits</div></div>
    <div class="ledger-cell<?= $counts['due'] ? ' is-amber' : '' ?>"><div class="ledger-num"><?= $counts['due'] ?></div><div class="ledger-label">due within <?= (int) DUE_SOON_DAYS ?> days</div></div>
    <div class="ledger-cell"><div class="ledger-num"><?= $counts['upcoming'] ?></div><div class="ledger-label">later in this window</div></div>
    <div class="ledger-cell"><div class="ledger-num"><?= $counts['booked'] ?></div><div class="ledger-label">already booked</div></div>
</div>

<!-- ================= Part 1: due visits ================= -->
<section class="sheet" aria-labelledby="due-title">
    <div class="sheet-head">
        <h2 id="due-title">Upcoming vaccination dates</h2>
        <span class="small-note"><?= $window === 'overdue' ? 'Visits whose date has passed' : 'Up to ' . e(fmt_date($limitDate)) . ', overdue included' ?></span>
    </div>
    <?php if (!$alerts): ?>
        <?= empty_state('Nothing in this window', 'No child has a visit due in the selected period.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Due date</th>
                        <th scope="col">Child</th>
                        <th scope="col">Parent</th>
                        <th scope="col">Visit and vaccines</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $a): ?>
                        <?php [, $cls] = state_meta($a['state']); ?>
                        <tr>
                            <td class="text-nowrap">
                                <div class="fw-600"><?= e(fmt_date($a['due_date'])) ?></div>
                                <div class="cell-sub"><?= e(due_phrase($a['due_date'])) ?></div>
                            </td>
                            <td>
                                <a class="fw-600" href="<?= url('admin/children.php?id=' . (int) $a['child']['id']) ?>"><?= e($a['child']['name']) ?></a>
                                <div class="cell-sub"><?= e(child_age($a['child']['date_of_birth'])) ?></div>
                            </td>
                            <td><?= e($a['child']['parent_name']) ?><div class="cell-sub"><?= e($a['child']['parent_phone']) ?></div></td>
                            <td>
                                <div class="fw-600 mb-1"><?= e($a['label']) ?></div>
                                <div class="dose-cells"><?php foreach ($a['doses'] as $short): ?><span class="dose-cell"><?= e($short) ?></span><?php endforeach; ?></div>
                            </td>
                            <td>
                                <?php if ($a['state'] === 'booked'): ?>
                                    <span class="state-chip state-booked"><?= ($a['booking']['status'] ?? '') === 'Approved' ? 'Booked' : 'Requested' ?></span>
                                    <div class="cell-sub"><?= e(fmt_date($a['booking']['date'])) ?>, <?= e(fmt_time($a['booking']['time'])) ?></div>
                                    <div class="cell-sub"><?= e($a['booking']['place']) ?></div>
                                <?php else: ?>
                                    <span class="state-chip <?= $cls ?>"><?= e(state_meta($a['state'])[0]) ?></span>
                                    <div class="cell-sub">Not booked yet</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- ================= Part 2: confirmed appointments ================= -->
<?php if ($window !== 'overdue'): ?>
    <section class="sheet" aria-labelledby="appt-title">
        <div class="sheet-head">
            <h2 id="appt-title">Confirmed appointments</h2>
            <span class="small-note">Date and time of vaccination, today to <?= e(fmt_date($limitDate)) ?></span>
        </div>
        <?php if (!$appointments): ?>
            <?= empty_state('No confirmed appointments', 'Approved bookings in this period will be listed here.') ?>
        <?php else: ?>
            <?php foreach ($appointments as $date => $list): ?>
                <h3 class="day-heading"><?= e(fmt_date($date, 'l, d M Y')) ?> <span class="small-note fw-normal"><?= e(relative_days($date)) ?> &middot; <?= count($list) ?> visit<?= count($list) === 1 ? '' : 's' ?></span></h3>
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <?php foreach ($list as $r): ?>
                                <tr>
                                    <td class="text-nowrap fw-600" style="width: 110px"><?= e(fmt_time($r['appointment_time'])) ?></td>
                                    <td style="width: 26%"><a href="<?= url('admin/bookings.php?id=' . (int) $r['id']) ?>"><?= e($r['child_name']) ?></a><div class="cell-sub"><?= e(booking_ref((int) $r['id'])) ?> &middot; <?= e($r['parent_phone']) ?></div></td>
                                    <td style="width: 30%"><?= e($r['hospital_name']) ?></td>
                                    <td><?= vaccine_chips($apptVaccines[$r['id']] ?? [], false, true) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
