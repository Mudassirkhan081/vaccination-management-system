<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/reports.php
 * Purpose : Admin module - "Report of vaccination (child, vaccination,
 *           date-wise report)".
 *           Filters: date range, hospital, vaccine, source, search.
 *           Views  : date-wise (default) or child-wise.
 *           Extras : two charts, print, and CSV download (?export=csv).
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

/* ------------------------------------------------------------------
 * 1. Read and check the filters
 * ------------------------------------------------------------------ */
$defaultFrom = date('Y-m-01', strtotime(date('Y-m-01') . ' -5 months'));
$from        = get('from', $defaultFrom);
$to          = get('to', today());
$hospitalId  = int_param('hospital');
$vaccineId   = int_param('vaccine');
$source      = get('source');
$q           = mb_substr(get('q'), 0, 80);
$view        = get('view') === 'child' ? 'child' : 'date';
$notice      = '';

if (!valid_date($from)) {
    $from = $defaultFrom;
}
if (!valid_date($to)) {
    $to = today();
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
    $notice = 'The dates were swapped so that "From" comes before "To".';
}
if (days_between($from, $to) > 1096) {
    $from   = add_days($to, -1096);
    $notice = 'Reports can cover up to three years, so the start date was adjusted.';
}
if (!in_array($source, ['hospital', 'parent'], true)) {
    $source = '';
}
// A hospital filter only makes sense for hospital records
if ($hospitalId) {
    $source = 'hospital';
}

/* ------------------------------------------------------------------
 * 2. Load the doses (hospital records UNION parent records)
 * ------------------------------------------------------------------ */
$parts  = [];
$params = [];

if ($source !== 'parent') {
    $sql = "SELECT DATE(av.given_at) AS given_on, TIME(av.given_at) AS given_time, 'Hospital' AS source,
                   c.id AS child_id, c.name AS child_name, c.date_of_birth, c.gender, p.name AS parent_name, p.phone AS parent_phone,
                   v.id AS vaccine_id, v.short_name, v.name AS vaccine_name, v.age_days,
                   h.name AS place, a.id AS appointment_id
              FROM appointment_vaccines av
              JOIN appointments a ON a.id = av.appointment_id
              JOIN children c     ON c.id = a.child_id
              JOIN parents p      ON p.id = a.parent_id
              JOIN vaccines v     ON v.id = av.vaccine_id
              JOIN hospitals h    ON h.id = a.hospital_id
             WHERE av.status = 'Vaccinated' AND DATE(av.given_at) BETWEEN ? AND ?";
    $params = array_merge($params, [$from, $to]);
    if ($hospitalId) {
        $sql     .= ' AND h.id = ?';
        $params[] = $hospitalId;
    }
    if ($vaccineId) {
        $sql     .= ' AND v.id = ?';
        $params[] = $vaccineId;
    }
    if ($q !== '') {
        $sql   .= ' AND (c.name LIKE ? OR p.name LIKE ?)';
        $params = array_merge($params, [like_param($q), like_param($q)]);
    }
    $parts[] = $sql;
}

if ($source !== 'hospital') {
    // Same column names as the first SELECT, so ORDER BY works for either part alone
    $sql = "SELECT vh.given_on AS given_on, NULL AS given_time, 'Parent record' AS source,
                   c.id AS child_id, c.name AS child_name, c.date_of_birth, c.gender, p.name AS parent_name, p.phone AS parent_phone,
                   v.id AS vaccine_id, v.short_name, v.name AS vaccine_name, v.age_days,
                   vh.place AS place, NULL AS appointment_id
              FROM vaccination_history vh
              JOIN children c ON c.id = vh.child_id
              JOIN parents p  ON p.id = c.parent_id
              JOIN vaccines v ON v.id = vh.vaccine_id
             WHERE vh.given_on BETWEEN ? AND ?";
    $params = array_merge($params, [$from, $to]);
    if ($vaccineId) {
        $sql     .= ' AND v.id = ?';
        $params[] = $vaccineId;
    }
    if ($q !== '') {
        $sql   .= ' AND (c.name LIKE ? OR p.name LIKE ?)';
        $params = array_merge($params, [like_param($q), like_param($q)]);
    }
    $parts[] = $sql;
}

$doses = db_all(
    '(' . implode(') UNION ALL (', $parts) . ') ORDER BY given_on DESC, child_name, age_days, short_name',
    $params
);

/* ------------------------------------------------------------------
 * 3. CSV download (same filters)
 * ------------------------------------------------------------------ */
if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vaccination-report-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel reads Urdu names correctly
    fputcsv($out, ['Date given', 'Time', 'Child', 'Gender', 'Date of birth', 'Parent', 'Parent phone', 'Vaccine', 'Vaccine name', 'Given at', 'Source', 'Booking']);
    foreach ($doses as $d) {
        fputcsv($out, [
            $d['given_on'], $d['given_time'] ? substr($d['given_time'], 0, 5) : '', $d['child_name'], $d['gender'],
            $d['date_of_birth'], $d['parent_name'], $d['parent_phone'], $d['short_name'], $d['vaccine_name'],
            $d['place'], $d['source'], $d['appointment_id'] ? booking_ref((int) $d['appointment_id']) : '',
        ]);
    }
    fclose($out);
    exit;
}

/* ------------------------------------------------------------------
 * 4. Group and count
 * ------------------------------------------------------------------ */
$byDate  = [];   // date => child id => row with vaccines
$byChild = [];   // child id => summary
$perVaccine = [];
foreach ($doses as $d) {
    $cid = (int) $d['child_id'];
    if (!isset($byDate[$d['given_on']][$cid])) {
        $byDate[$d['given_on']][$cid] = ['dose' => $d, 'vaccines' => [], 'places' => [], 'sources' => []];
    }
    $byDate[$d['given_on']][$cid]['vaccines'][] = $d['short_name'];
    $byDate[$d['given_on']][$cid]['places'][$d['place'] ?: '-'] = true;
    $byDate[$d['given_on']][$cid]['sources'][$d['source']] = true;

    if (!isset($byChild[$cid])) {
        $byChild[$cid] = ['dose' => $d, 'count' => 0, 'vaccines' => [], 'first' => $d['given_on'], 'last' => $d['given_on']];
    }
    $byChild[$cid]['count']++;
    $byChild[$cid]['vaccines'][] = $d['short_name'];
    $byChild[$cid]['first'] = min($byChild[$cid]['first'], $d['given_on']);
    $byChild[$cid]['last']  = max($byChild[$cid]['last'], $d['given_on']);

    $perVaccine[$d['short_name']] = ($perVaccine[$d['short_name']] ?? 0) + 1;
}
uasort($byChild, function ($a, $b) {
    return strcmp($a['dose']['child_name'], $b['dose']['child_name']);
});

$visitCount = 0;
foreach ($byDate as $rows) {
    $visitCount += count($rows);
}

// Doses a hospital could not give in this period
$missedParams = [$from, $to];
$missedSql    = "SELECT COUNT(*) FROM appointment_vaccines av JOIN appointments a ON a.id = av.appointment_id
                  WHERE av.status = 'Not Vaccinated' AND a.appointment_date BETWEEN ? AND ?";
if ($hospitalId) {
    $missedSql     .= ' AND a.hospital_id = ?';
    $missedParams[] = $hospitalId;
}
if ($vaccineId) {
    $missedSql     .= ' AND av.vaccine_id = ?';
    $missedParams[] = $vaccineId;
}
$missedCount = (int) db_value($missedSql, $missedParams);

/* ------------------------------------------------------------------
 * 5. Chart data: per day (short ranges) or per month (long ranges)
 * ------------------------------------------------------------------ */
$daily   = days_between($from, $to) <= 62;
$buckets = [];
$cursor  = $daily ? $from : date('Y-m-01', strtotime($from));
while ($cursor <= $to) {
    $buckets[$daily ? $cursor : substr($cursor, 0, 7)] = 0;
    $cursor = $daily ? add_days($cursor, 1) : date('Y-m-01', strtotime($cursor . ' +1 month'));
}
foreach ($doses as $d) {
    $key = $daily ? $d['given_on'] : substr($d['given_on'], 0, 7);
    if (isset($buckets[$key])) {
        $buckets[$key]++;
    }
}
$timeLabels = array_map(function ($k) use ($daily) {
    return $daily ? date('d M', strtotime($k)) : date('M Y', strtotime($k . '-01'));
}, array_keys($buckets));
arsort($perVaccine);

$hospitalList = db_all('SELECT id, name FROM hospitals ORDER BY name');
$vaccineList  = db_all('SELECT id, short_name, name FROM vaccines ORDER BY age_days, id');

$pageTitle    = 'Vaccination report';
$activeNav    = 'reports';
$pageScripts  = ['vendor/chartjs/chart.umd.js'];
$inlineScript = "
(function () {
  if (!window.Chart) { return; }
  var grid = { color: '#E6EBE7' };
  var t = document.getElementById('timeChart');
  if (t) {
    new Chart(t, { type: 'bar',
      data: { labels: " . json_encode($timeLabels, JSON_HEX_TAG) . ",
              datasets: [{ label: 'Doses', data: " . json_encode(array_values($buckets)) . ", backgroundColor: '#4A3AA8', borderRadius: 3, maxBarThickness: 36 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
                 scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: grid }, x: { grid: { display: false } } } } });
  }
  var v = document.getElementById('vaccineChart');
  if (v) {
    new Chart(v, { type: 'bar',
      data: { labels: " . json_encode(array_keys($perVaccine), JSON_HEX_TAG) . ",
              datasets: [{ label: 'Doses', data: " . json_encode(array_values($perVaccine)) . ", backgroundColor: '#1E7156', borderRadius: 3, maxBarThickness: 22 }] },
      options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } },
                 scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: grid }, y: { grid: { display: false } } } } });
  }
})();";
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<!-- ================= Filters ================= -->
<form class="sheet sheet-body mb-3 no-print" method="get">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="filters">
        <div>
            <label class="form-label" for="from">From</label>
            <input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>" max="<?= today() ?>">
        </div>
        <div>
            <label class="form-label" for="to">To</label>
            <input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>" max="<?= today() ?>">
        </div>
        <div>
            <label class="form-label" for="hospital">Hospital</label>
            <select class="form-select" id="hospital" name="hospital">
                <option value="">All hospitals</option>
                <?php foreach ($hospitalList as $h): ?>
                    <option value="<?= (int) $h['id'] ?>"<?= selected($h['id'], $hospitalId) ?>><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="vaccine">Vaccine</label>
            <select class="form-select" id="vaccine" name="vaccine">
                <option value="">All vaccines</option>
                <?php foreach ($vaccineList as $v): ?>
                    <option value="<?= (int) $v['id'] ?>"<?= selected($v['id'], $vaccineId) ?>><?= e($v['short_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="source">Recorded by</label>
            <select class="form-select" id="source" name="source">
                <option value="">Hospitals and parents</option>
                <option value="hospital"<?= selected('hospital', $source) ?>>Hospitals only</option>
                <option value="parent"<?= selected('parent', $source) ?>>Parents (paper card) only</option>
            </select>
        </div>
        <div class="grow">
            <label class="form-label" for="q">Child or parent</label>
            <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name">
        </div>
        <button class="btn btn-primary" type="submit">Show report</button>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <span class="small-note me-1 align-self-center">Quick ranges:</span>
        <?php
        $quick = [
            'This month'    => [date('Y-m-01'), today()],
            'Last 30 days'  => [add_days(today(), -29), today()],
            'Last 6 months' => [$defaultFrom, today()],
            'This year'     => [date('Y-01-01'), today()],
        ];
        foreach ($quick as $label => [$qf, $qt]): ?>
            <a class="btn btn-sm btn-light" href="<?= e(query_url(['from' => $qf, 'to' => $qt, 'export' => null])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</form>

<?php if ($notice): ?>
    <div class="alert alert-info"><?= e($notice) ?></div>
<?php endif; ?>

<!-- Printed heading -->
<div class="print-only report-head">
    <div><strong><?= e(APP_NAME) ?></strong> vaccination report</div>
    <div><?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></div>
</div>

<!-- ================= Totals ================= -->
<div class="ledger mb-4">
    <div class="ledger-cell is-ink"><div class="ledger-num"><?= count($doses) ?></div><div class="ledger-label">doses given</div></div>
    <div class="ledger-cell"><div class="ledger-num"><?= count($byChild) ?></div><div class="ledger-label">children vaccinated</div></div>
    <div class="ledger-cell"><div class="ledger-num"><?= $visitCount ?></div><div class="ledger-label">vaccination days (child and date)</div></div>
    <div class="ledger-cell<?= $missedCount ? ' is-alert' : '' ?>"><div class="ledger-num"><?= $missedCount ?></div><div class="ledger-label">doses not given at a visit</div></div>
</div>

<!-- ================= Charts ================= -->
<div class="row g-4 mb-1">
    <div class="col-xl-7">
        <section class="sheet h-100" aria-labelledby="c1">
            <div class="sheet-head"><h2 id="c1">Doses per <?= $daily ? 'day' : 'month' ?></h2></div>
            <div class="sheet-body"><div class="chart-box"><canvas id="timeChart" role="img" aria-label="Doses given per <?= $daily ? 'day' : 'month' ?>"></canvas></div></div>
        </section>
    </div>
    <div class="col-xl-5">
        <section class="sheet h-100" aria-labelledby="c2">
            <div class="sheet-head"><h2 id="c2">Doses per vaccine</h2></div>
            <div class="sheet-body">
                <?php if (!$perVaccine): ?>
                    <p class="text-soft mb-0">No doses in this period.</p>
                <?php else: ?>
                    <div class="chart-box" style="height: <?= max(200, count($perVaccine) * 26) ?>px"><canvas id="vaccineChart" role="img" aria-label="Doses given per vaccine"></canvas></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<!-- ================= Table ================= -->
<section class="sheet mt-4" aria-labelledby="table-title">
    <nav class="tabs-line no-print" aria-label="Report view">
        <a class="<?= $view === 'date' ? 'active' : '' ?>" href="<?= e(query_url(['view' => 'date', 'page' => null])) ?>">Date-wise</a>
        <a class="<?= $view === 'child' ? 'active' : '' ?>" href="<?= e(query_url(['view' => 'child', 'page' => null])) ?>">Child-wise</a>
    </nav>
    <div class="sheet-head">
        <h2 id="table-title"><?= $view === 'date' ? 'Vaccinations by date' : 'Vaccinations by child' ?></h2>
        <div class="d-flex gap-2 no-print">
            <a class="btn btn-sm btn-light btn-icon" href="<?= e(query_url(['export' => 'csv'])) ?>"><i class="bi bi-download" aria-hidden="true"></i> CSV</a>
            <button class="btn btn-sm btn-light btn-icon" type="button" data-print><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
        </div>
    </div>

    <?php if (!$doses): ?>
        <?= empty_state('No vaccinations found', 'No doses match these filters. Try a wider date range.') ?>
    <?php elseif ($view === 'date'): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th scope="col">Date</th><th scope="col">Child</th><th scope="col">Parent</th><th scope="col">Vaccines given</th><th scope="col">Given at</th><th scope="col">Recorded by</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($byDate as $date => $rows): ?>
                        <?php $first = true; foreach ($rows as $cid => $row): $d = $row['dose']; ?>
                            <tr<?= $first ? ' class="row-group-start"' : '' ?>>
                                <?php if ($first): ?>
                                    <th scope="rowgroup" rowspan="<?= count($rows) ?>" class="text-nowrap align-top">
                                        <?= e(fmt_date($date)) ?>
                                        <div class="cell-sub fw-normal"><?= e(fmt_date($date, 'l')) ?></div>
                                    </th>
                                <?php endif; ?>
                                <td><a class="fw-600" href="<?= url('admin/children.php?id=' . $cid) ?>"><?= e($d['child_name']) ?></a><div class="cell-sub"><?= e($d['gender']) ?>, born <?= e(fmt_date($d['date_of_birth'])) ?></div></td>
                                <td><?= e($d['parent_name']) ?></td>
                                <td><div class="dose-cells"><?php foreach ($row['vaccines'] as $s): ?><span class="dose-cell is-given"><?= e($s) ?></span><?php endforeach; ?></div></td>
                                <td><?= e(implode(', ', array_keys($row['places']))) ?></td>
                                <td class="cell-sub"><?= e(implode(', ', array_keys($row['sources']))) ?></td>
                            </tr>
                        <?php $first = false; endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th scope="col">Child</th><th scope="col">Parent</th><th scope="col">Doses</th><th scope="col">Vaccines given in this period</th><th scope="col">First dose</th><th scope="col">Latest dose</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($byChild as $cid => $row): $d = $row['dose']; ?>
                        <tr>
                            <td><a class="fw-600" href="<?= url('admin/children.php?id=' . $cid) ?>"><?= e($d['child_name']) ?></a><div class="cell-sub"><?= e(child_age($d['date_of_birth'])) ?></div></td>
                            <td><?= e($d['parent_name']) ?><div class="cell-sub"><?= e($d['parent_phone']) ?></div></td>
                            <td class="fw-600"><?= (int) $row['count'] ?></td>
                            <td><div class="dose-cells"><?php foreach (array_reverse($row['vaccines']) as $s): ?><span class="dose-cell is-given"><?= e($s) ?></span><?php endforeach; ?></div></td>
                            <td class="text-nowrap"><?= e(fmt_date($row['first'])) ?></td>
                            <td class="text-nowrap"><?= e(fmt_date($row['last'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="sheet-foot small-note">
        Period <?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?>. Generated <?= e(fmt_datetime(date('Y-m-d H:i:s'))) ?>.
    </div>
</section>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
