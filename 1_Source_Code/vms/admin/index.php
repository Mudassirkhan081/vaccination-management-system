<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/index.php
 * Purpose : Administrator dashboard: key numbers, items that need a
 *           decision, today's appointments in every hospital and a
 *           six-month chart of doses given.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$today = today();

/* ------------------------------------------------------------------
 * Key numbers
 * ------------------------------------------------------------------ */
$stats = [
    'children'         => (int) db_value('SELECT COUNT(*) FROM children'),
    'parents'          => (int) db_value("SELECT COUNT(*) FROM parents WHERE status = 'Active'"),
    'hospitals'        => (int) db_value("SELECT COUNT(*) FROM hospitals WHERE status = 'Active'"),
    'pendingHospitals' => (int) db_value("SELECT COUNT(*) FROM hospitals WHERE status = 'Pending'"),
    'requests'         => (int) db_value("SELECT COUNT(*) FROM appointments WHERE request_status = 'Pending'"),
    'today'            => (int) db_value("SELECT COUNT(*) FROM appointments WHERE request_status = 'Approved' AND appointment_date = ?", [$today]),
    'dosesMonth'       => (int) db_value(
        "SELECT COUNT(*) FROM appointment_vaccines WHERE status = 'Vaccinated' AND given_at >= ?",
        [date('Y-m-01')]
    ),
    'unavailable'      => (int) db_value("SELECT COUNT(*) FROM vaccines WHERE status = 'Unavailable'"),
    'awaiting'         => (int) db_value(
        "SELECT COUNT(*) FROM appointments WHERE request_status = 'Approved' AND vaccination_status = 'Pending' AND appointment_date < ?",
        [$today]
    ),
];

// Children with at least one overdue visit that is not booked yet
$allChildren  = db_all('SELECT * FROM children');
$overdueKids  = 0;
foreach (build_schedules($allChildren) as $card) {
    if ($card['summary']['overdue'] > 0) {
        $overdueKids++;
    }
}

/* ------------------------------------------------------------------
 * Today's appointments and newest requests
 * ------------------------------------------------------------------ */
$todayList = db_all(
    "SELECT a.*, c.name AS child_name, h.name AS hospital_name
       FROM appointments a
       JOIN children c  ON c.id = a.child_id
       JOIN hospitals h ON h.id = a.hospital_id
      WHERE a.request_status = 'Approved' AND a.appointment_date = ?
      ORDER BY a.appointment_time",
    [$today]
);
$latestRequests = db_all(
    "SELECT a.*, c.name AS child_name, p.name AS parent_name, h.name AS hospital_name
       FROM appointments a
       JOIN children c  ON c.id = a.child_id
       JOIN parents p   ON p.id = a.parent_id
       JOIN hospitals h ON h.id = a.hospital_id
      WHERE a.request_status = 'Pending'
      ORDER BY a.created_at DESC
      LIMIT 5"
);
$listVaccines = appointment_vaccine_map(array_merge(array_column($todayList, 'id'), array_column($latestRequests, 'id')));

/* ------------------------------------------------------------------
 * Chart: doses given by hospitals in the last six months
 * ------------------------------------------------------------------ */
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $key          = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
    $months[$key] = 0;
}
$rows = db_all(
    "SELECT DATE_FORMAT(given_at, '%Y-%m') AS ym, COUNT(*) AS total
       FROM appointment_vaccines
      WHERE status = 'Vaccinated' AND given_at >= ?
      GROUP BY ym",
    [array_key_first($months) . '-01']
);
foreach ($rows as $r) {
    if (isset($months[$r['ym']])) {
        $months[$r['ym']] = (int) $r['total'];
    }
}
$chartLabels = array_map(function ($ym) { return date('M Y', strtotime($ym . '-01')); }, array_keys($months));

$stock = db_all('SELECT id, short_name, name, status FROM vaccines ORDER BY status DESC, age_days, id');

$pageTitle   = 'Dashboard';
$activeNav   = 'dashboard';
$pageScripts = ['vendor/chartjs/chart.umd.js'];
$inlineScript = "
(function () {
  var canvas = document.getElementById('dosesChart');
  if (!canvas || !window.Chart) { return; }
  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: " . json_encode($chartLabels, JSON_HEX_TAG) . ",
      datasets: [{ label: 'Doses given', data: " . json_encode(array_values($months)) . ",
                   backgroundColor: '#4A3AA8', borderRadius: 4, maxBarThickness: 42 }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#E6EBE7' } }, x: { grid: { display: false } } }
    }
  });
})();";
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<!-- ================= Key numbers ================= -->
<div class="ledger mb-4">
    <a class="ledger-cell" href="<?= url('admin/children.php') ?>">
        <div class="ledger-num"><?= $stats['children'] ?></div><div class="ledger-label">children registered</div>
    </a>
    <a class="ledger-cell" href="<?= url('admin/parents.php') ?>">
        <div class="ledger-num"><?= $stats['parents'] ?></div><div class="ledger-label">active parents</div>
    </a>
    <a class="ledger-cell" href="<?= url('admin/hospitals.php') ?>">
        <div class="ledger-num"><?= $stats['hospitals'] ?></div><div class="ledger-label">active hospitals</div>
    </a>
    <a class="ledger-cell<?= $stats['requests'] ? ' is-amber' : '' ?>" href="<?= url('admin/requests.php') ?>">
        <div class="ledger-num"><?= $stats['requests'] ?></div><div class="ledger-label">requests waiting</div>
    </a>
    <a class="ledger-cell" href="<?= url('admin/bookings.php?from=' . $today . '&to=' . $today . '&request=Approved') ?>">
        <div class="ledger-num"><?= $stats['today'] ?></div><div class="ledger-label">appointments today</div>
    </a>
    <a class="ledger-cell is-ink" href="<?= url('admin/reports.php') ?>">
        <div class="ledger-num"><?= $stats['dosesMonth'] ?></div><div class="ledger-label">doses given this month</div>
    </a>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <!-- ================= Needs attention ================= -->
        <section class="sheet" aria-labelledby="attention-title">
            <div class="sheet-head"><h2 id="attention-title">Needs attention</h2></div>
            <?php
            $attention = [
                [$stats['requests'], 'booking request(s) waiting for a decision', 'admin/requests.php', 'Review', 'bi-inbox'],
                [$stats['pendingHospitals'], 'hospital registration(s) waiting for approval', 'admin/hospitals.php?status=Pending', 'Review', 'bi-hospital'],
                [$overdueKids, 'child(ren) with overdue vaccines that are not booked', 'admin/due.php?window=overdue', 'See list', 'bi-exclamation-triangle'],
                [$stats['awaiting'], 'past appointment(s) without a result from the hospital', 'admin/bookings.php?request=Approved&result=Pending&to=' . add_days($today, -1), 'See list', 'bi-hourglass-split'],
                [$stats['unavailable'], 'vaccine(s) marked unavailable', 'admin/vaccines.php?status=Unavailable', 'Manage', 'bi-capsule'],
            ];
            $shown = 0;
            ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($attention as [$count, $text, $link, $label, $icon]): ?>
                    <?php if ($count < 1) { continue; } $shown++; ?>
                    <li class="notice-row">
                        <span class="notice-icon"><i class="bi <?= $icon ?>" aria-hidden="true"></i></span>
                        <div class="pt-1"><strong><?= (int) $count ?></strong> <?= e($text) ?></div>
                        <a class="btn btn-sm btn-light" href="<?= e(url($link)) ?>"><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$shown): ?>
                <?= empty_state('All clear', 'There is nothing waiting for you right now.') ?>
            <?php endif; ?>
        </section>

        <!-- ================= Today's appointments ================= -->
        <section class="sheet" aria-labelledby="today-title">
            <div class="sheet-head">
                <h2 id="today-title">Today's appointments</h2>
                <span class="small-note"><?= e(fmt_date($today, 'l, d M Y')) ?></span>
            </div>
            <?php if (!$todayList): ?>
                <?= empty_state('No appointments today', 'Approved visits for today will be listed here.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th scope="col">Time</th><th scope="col">Child</th><th scope="col">Hospital</th><th scope="col">Vaccines</th><th scope="col">Result</th></tr></thead>
                        <tbody>
                            <?php foreach ($todayList as $a): ?>
                                <tr>
                                    <td class="text-nowrap fw-600"><?= e(fmt_time($a['appointment_time'])) ?></td>
                                    <td><a href="<?= url('admin/bookings.php?id=' . (int) $a['id']) ?>"><?= e($a['child_name']) ?></a></td>
                                    <td><?= e($a['hospital_name']) ?></td>
                                    <td><?= vaccine_chips($listVaccines[$a['id']] ?? [], true) ?></td>
                                    <td><?= booking_stage_badge($a) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- ================= Latest requests ================= -->
        <section class="sheet" aria-labelledby="latest-title">
            <div class="sheet-head">
                <h2 id="latest-title">Latest requests</h2>
                <a class="small" href="<?= url('admin/requests.php') ?>">Open requests</a>
            </div>
            <?php if (!$latestRequests): ?>
                <?= empty_state('No requests waiting', 'New requests from parents will appear here.') ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th scope="col">Booking</th><th scope="col">Child and parent</th><th scope="col">Hospital and visit</th><th scope="col">Vaccines</th></tr></thead>
                        <tbody>
                            <?php foreach ($latestRequests as $a): ?>
                                <tr>
                                    <td><span class="cell-title ref"><?= e(booking_ref((int) $a['id'])) ?></span><div class="cell-sub"><?= e(time_ago($a['created_at'])) ?></div></td>
                                    <td><?= e($a['child_name']) ?><div class="cell-sub"><?= e($a['parent_name']) ?></div></td>
                                    <td><?= e($a['hospital_name']) ?><div class="cell-sub"><?= e(visit_when($a)) ?></div></td>
                                    <td><?= vaccine_chips($listVaccines[$a['id']] ?? [], false, true) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-xl-4">
        <!-- ================= Chart ================= -->
        <section class="sheet" aria-labelledby="chart-title">
            <div class="sheet-head">
                <h2 id="chart-title">Doses given</h2>
                <span class="small-note">last 6 months</span>
            </div>
            <div class="sheet-body">
                <div class="chart-box">
                    <canvas id="dosesChart" role="img" aria-label="Bar chart of doses given per month"></canvas>
                </div>
                <!-- Same numbers as text for screen readers and printing -->
                <table class="visually-hidden">
                    <caption>Doses given per month</caption>
                    <?php foreach ($months as $ym => $total): ?>
                        <tr><th scope="row"><?= e(date('M Y', strtotime($ym . '-01'))) ?></th><td><?= (int) $total ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </section>

        <!-- ================= Vaccine stock ================= -->
        <section class="sheet" aria-labelledby="stock-title">
            <div class="sheet-head">
                <h2 id="stock-title">Vaccine availability</h2>
                <a class="small" href="<?= url('admin/vaccines.php') ?>">Manage</a>
            </div>
            <div class="sheet-body">
                <div class="dose-cells">
                    <?php foreach ($stock as $v): ?>
                        <span class="dose-cell<?= $v['status'] === 'Unavailable' ? ' is-out' : '' ?>" title="<?= e($v['name'] . ' - ' . $v['status']) ?>">
                            <?= e($v['short_name']) ?><?= $v['status'] === 'Unavailable' ? ' <span class="visually-hidden">(unavailable)</span>' : '' ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <p class="small-note mt-2 mb-0"><span class="dose-cell is-out">dashed</span> = currently unavailable. Parents cannot book these.</p>
            </div>
        </section>
    </div>
</div>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
