<?php
/**
 * ---------------------------------------------------------------------
 * File    : hospital/index.php
 * Purpose : Hospital dashboard: today's confirmed visits, results that
 *           still need to be recorded, the next seven days, and the
 *           current vaccine availability.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('hospital');

$hospitalId = current_user_id();
$hospital   = $GLOBALS['authUser'];
$weekEnd    = add_days(today(), 7);

/* ------------------------------------------------------------------
 * Key numbers
 * ------------------------------------------------------------------ */
$stats = db_one(
    "SELECT SUM(appointment_date = CURDATE()) AS today_total,
            SUM(appointment_date = CURDATE() AND vaccination_status = 'Pending') AS today_open,
            SUM(appointment_date < CURDATE() AND vaccination_status = 'Pending') AS awaiting,
            SUM(appointment_date > CURDATE() AND appointment_date <= ? AND vaccination_status = 'Pending') AS week
       FROM appointments
      WHERE hospital_id = ? AND request_status = 'Approved'",
    [$weekEnd, $hospitalId]
);
$dosesMonth = (int) db_value(
    "SELECT COUNT(*) FROM appointment_vaccines av JOIN appointments a ON a.id = av.appointment_id
      WHERE a.hospital_id = ? AND av.status = 'Vaccinated' AND av.given_at >= ?",
    [$hospitalId, date('Y-m-01')]
);
$childrenServed = (int) db_value(
    "SELECT COUNT(DISTINCT child_id) FROM appointments WHERE hospital_id = ? AND vaccination_status IN ('Vaccinated', 'Partially Vaccinated')",
    [$hospitalId]
);

/* ------------------------------------------------------------------
 * Today's visits and the coming week
 * ------------------------------------------------------------------ */
$select = "SELECT a.*, c.name AS child_name, c.date_of_birth, c.gender, c.notes AS child_notes, p.phone AS parent_phone
             FROM appointments a
             JOIN children c ON c.id = a.child_id
             JOIN parents p  ON p.id = a.parent_id
            WHERE a.hospital_id = ? AND a.request_status = 'Approved'";

$todayList = db_all($select . ' AND a.appointment_date = CURDATE() ORDER BY a.appointment_time', [$hospitalId]);
$weekList  = db_all(
    $select . " AND a.appointment_date > CURDATE() AND a.appointment_date <= ? AND a.vaccination_status = 'Pending'
               ORDER BY a.appointment_date, a.appointment_time",
    [$hospitalId, $weekEnd]
);
$vaccines = appointment_vaccine_map(array_merge(array_column($todayList, 'id'), array_column($weekList, 'id')));

// Coming week grouped by day
$weekByDay = [];
foreach ($weekList as $a) {
    $weekByDay[$a['appointment_date']][] = $a;
}

$stock = db_all('SELECT short_name, name, status FROM vaccines ORDER BY age_days, id');
$outOfStock = array_filter($stock, function ($v) { return $v['status'] === 'Unavailable'; });

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<p class="mb-3"><strong><?= e($hospital['name']) ?></strong> &middot; <?= e(fmt_date(today(), 'l, d M Y')) ?></p>

<!-- ================= Key numbers ================= -->
<div class="ledger mb-4">
    <a class="ledger-cell<?= $stats['today_open'] ? ' is-amber' : '' ?>" href="<?= url('hospital/appointments.php?view=today') ?>">
        <div class="ledger-num"><?= (int) $stats['today_total'] ?></div>
        <div class="ledger-label">visits today (<?= (int) $stats['today_open'] ?> still open)</div>
    </a>
    <a class="ledger-cell<?= $stats['awaiting'] ? ' is-alert' : '' ?>" href="<?= url('hospital/appointments.php?view=awaiting') ?>">
        <div class="ledger-num"><?= (int) $stats['awaiting'] ?></div>
        <div class="ledger-label">past visits without a result</div>
    </a>
    <a class="ledger-cell" href="<?= url('hospital/appointments.php?view=upcoming') ?>">
        <div class="ledger-num"><?= (int) $stats['week'] ?></div>
        <div class="ledger-label">visits in the next 7 days</div>
    </a>
    <div class="ledger-cell is-ink">
        <div class="ledger-num"><?= $dosesMonth ?></div>
        <div class="ledger-label">doses given this month</div>
    </div>
    <div class="ledger-cell">
        <div class="ledger-num"><?= $childrenServed ?></div>
        <div class="ledger-label">children vaccinated here</div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <!-- ================= Today ================= -->
        <section class="sheet" aria-labelledby="today-title">
            <div class="sheet-head">
                <h2 id="today-title">Today's visits</h2>
                <a class="btn btn-sm btn-ink" href="<?= url('hospital/appointments.php?view=today') ?>">Record results</a>
            </div>
            <?php if (!$todayList): ?>
                <?= empty_state('No visits today', 'Appointments approved by the administrator appear here on the day.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th scope="col">Time</th><th scope="col">Child</th><th scope="col">Vaccines</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($todayList as $a): ?>
                                <tr>
                                    <td class="fw-600 text-nowrap"><?= e(fmt_time($a['appointment_time'])) ?></td>
                                    <td>
                                        <div class="fw-600"><?= e($a['child_name']) ?></div>
                                        <div class="cell-sub"><?= e($a['gender']) ?>, <?= e(child_age($a['date_of_birth'])) ?> &middot; <span class="text-nowrap"><?= e($a['parent_phone']) ?></span></div>
                                        <?php if ($a['child_notes']): ?><div class="cell-sub text-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= e($a['child_notes']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= vaccine_chips($vaccines[$a['id']] ?? [], true, true) ?></td>
                                    <td><?= booking_stage_badge($a) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- ================= Next seven days ================= -->
        <section class="sheet" aria-labelledby="week-title">
            <div class="sheet-head">
                <h2 id="week-title">Next 7 days</h2>
                <a class="small" href="<?= url('hospital/appointments.php?view=upcoming') ?>">All upcoming</a>
            </div>
            <?php if (!$weekByDay): ?>
                <?= empty_state('Nothing booked this week', 'New confirmed appointments will show up here.') ?>
            <?php else: ?>
                <?php foreach ($weekByDay as $date => $list): ?>
                    <h3 class="day-heading"><?= e(fmt_date($date, 'l, d M')) ?> <span class="small-note fw-normal"><?= e(relative_days($date)) ?> &middot; <?= count($list) ?> visit<?= count($list) === 1 ? '' : 's' ?></span></h3>
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                                <?php foreach ($list as $a): ?>
                                    <tr>
                                        <td class="fw-600 text-nowrap" style="width: 110px"><?= e(fmt_time($a['appointment_time'])) ?></td>
                                        <td style="width: 35%"><?= e($a['child_name']) ?><div class="cell-sub"><?= e(child_age($a['date_of_birth'])) ?></div></td>
                                        <td><?= vaccine_chips($vaccines[$a['id']] ?? [], false, true) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-xl-4">
        <!-- ================= Hospital details ================= -->
        <section class="sheet" aria-labelledby="info-title">
            <div class="sheet-head">
                <h2 id="info-title">Listing details</h2>
                <a class="small" href="<?= url('hospital/profile.php') ?>">Edit</a>
            </div>
            <dl class="detail-list sheet-body mb-0 detail-list-stack">
                <dt>Address</dt><dd><?= e($hospital['address']) ?>, <?= e($hospital['city']) ?></dd>
                <dt>Location</dt><dd class="text-break"><?= e($hospital['location'] ?: '-') ?></dd>
                <dt>Timings</dt><dd><?= e($hospital['timings'] ?: 'Not listed') ?></dd>
                <dt>Phone</dt><dd><?= e($hospital['phone']) ?></dd>
            </dl>
            <div class="sheet-foot small-note">Parents see these details when they choose a hospital.</div>
        </section>

        <!-- ================= Vaccine availability ================= -->
        <section class="sheet" aria-labelledby="stock-title">
            <div class="sheet-head"><h2 id="stock-title">Vaccine availability</h2></div>
            <div class="sheet-body">
                <?php if ($outOfStock): ?>
                    <p class="small mb-2"><i class="bi bi-exclamation-circle text-warning-emphasis me-1" aria-hidden="true"></i>
                        Out of stock: <strong><?= e(implode(', ', array_column($outOfStock, 'short_name'))) ?></strong>. Parents cannot book these right now.</p>
                <?php endif; ?>
                <div class="dose-cells">
                    <?php foreach ($stock as $v): ?>
                        <span class="dose-cell<?= $v['status'] === 'Unavailable' ? ' is-out' : '' ?>" title="<?= e($v['name'] . ' - ' . $v['status']) ?>"><?= e($v['short_name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="small-note mt-2 mb-0">Stock is managed by the administrator. <a href="<?= e(url('contact.php?subject=' . urlencode('Vaccine stock update'))) ?>">Report a change</a></p>
            </div>
        </section>

        <!-- ================= How to record ================= -->
        <section class="sheet sheet-body" aria-labelledby="howto-title">
            <h2 class="h4" id="howto-title">Recording a visit</h2>
            <ol class="plain-steps mt-2 mb-0">
                <li>Open <em>Appointments</em> and find the child.</li>
                <li>Press <em>Record result</em> and mark each vaccine.</li>
                <li>Add a remark if a vaccine could not be given.</li>
            </ol>
        </section>
    </div>
</div>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
