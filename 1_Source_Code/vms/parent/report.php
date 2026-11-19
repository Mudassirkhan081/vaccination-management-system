<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/report.php
 * Purpose : Parent module - "Report of vaccination taken".
 *           A printable record for one child: doses given (with date,
 *           place and who recorded them), doses missed at a visit, and
 *           the doses still to come.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

$parentId = current_user_id();
$parent   = $GLOBALS['authUser'];
$children = db_all('SELECT * FROM children WHERE parent_id = ? ORDER BY date_of_birth DESC', [$parentId]);

// Selected child (the first child when none is chosen)
$childId = int_param('child');
$child   = null;
foreach ($children as $c) {
    if ((int) $c['id'] === $childId) {
        $child = $c;
    }
}
if (!$child && $children) {
    $child   = $children[0];
    $childId = (int) $child['id'];
}

if ($child) {
    $card    = child_schedule($child);
    $summary = $card['summary'];

    // Split the card into "given" and "still to come"
    $given = [];
    $open  = [];
    foreach ($card['visits'] as $visit) {
        foreach ($visit['doses'] as $d) {
            if ($d['state']['key'] === 'given') {
                $given[] = $d;
            } else {
                $open[] = $d;
            }
        }
    }
    // Given doses in date order
    usort($given, function ($a, $b) {
        return strcmp($a['given']['date'], $b['given']['date']);
    });

    // Doses a hospital could not give at a visit
    $missed = db_all(
        "SELECT a.id AS appointment_id, a.appointment_date, a.hospital_remark, v.short_name, v.name, h.name AS hospital_name
           FROM appointment_vaccines av
           JOIN appointments a ON a.id = av.appointment_id
           JOIN vaccines v     ON v.id = av.vaccine_id
           JOIN hospitals h    ON h.id = a.hospital_id
          WHERE a.child_id = ? AND av.status = 'Not Vaccinated'
          ORDER BY a.appointment_date",
        [$childId]
    );
}

$pageTitle = 'Vaccination report';
$activeNav = 'report';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<?php if (!$children): ?>
    <div class="sheet">
        <?= empty_state('No report yet', 'Add a child to start the vaccination record.', url('parent/children.php?action=add'), 'Add a child') ?>
    </div>
<?php else: ?>

<!-- Child picker and print button -->
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3 no-print">
    <form class="filters" method="get">
        <div>
            <label class="form-label" for="child">Report for</label>
            <select class="form-select" id="child" name="child" data-autosubmit>
                <?php foreach ($children as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $childId) ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button class="btn btn-light" type="submit">Show</button></noscript>
    </form>
    <button class="btn btn-ink btn-icon" type="button" data-print><i class="bi bi-printer" aria-hidden="true"></i> Print or save as PDF</button>
</div>

<article class="sheet report">
    <div class="sheet-body">
        <!-- Report heading (also used on paper) -->
        <header class="report-head">
            <div class="brand">
                <img src="<?= asset('img/logo.svg') ?>" alt="">
                <span>
                    <span class="brand-name"><?= e(APP_NAME) ?></span>
                    <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
                </span>
            </div>
            <div class="text-end">
                <h2 class="h3 mb-0">Vaccination report</h2>
                <div class="small-note">Generated <?= e(fmt_datetime(date('Y-m-d H:i:s'))) ?></div>
            </div>
        </header>

        <div class="row g-4">
            <div class="col-md-6">
                <dl class="detail-list">
                    <dt>Child</dt><dd class="fw-600"><?= e($child['name']) ?></dd>
                    <dt>Date of birth</dt><dd><?= e(fmt_date($child['date_of_birth'])) ?> (<?= e(child_age($child['date_of_birth'])) ?>)</dd>
                    <dt>Gender</dt><dd><?= e($child['gender']) ?></dd>
                    <dt>Blood group</dt><dd><?= e($child['blood_group'] ?: 'Not recorded') ?></dd>
                    <?php if ($child['notes']): ?><dt>Medical notes</dt><dd><?= e($child['notes']) ?></dd><?php endif; ?>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="detail-list">
                    <dt>Parent</dt><dd><?= e($parent['name']) ?></dd>
                    <dt>Phone</dt><dd><?= e($parent['phone']) ?></dd>
                    <?php if ($parent['cnic']): ?><dt>CNIC</dt><dd><?= e($parent['cnic']) ?></dd><?php endif; ?>
                    <dt>City</dt><dd><?= e($parent['city']) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Totals -->
        <div class="ledger my-4">
            <div class="ledger-cell is-ink"><div class="ledger-num"><?= (int) $summary['given'] ?>/<?= (int) $summary['total'] ?></div><div class="ledger-label">doses given</div></div>
            <div class="ledger-cell"><div class="ledger-num"><?= $summary['total'] ? (int) round($summary['given'] / $summary['total'] * 100) : 0 ?>%</div><div class="ledger-label">schedule complete</div></div>
            <div class="ledger-cell<?= $summary['overdue'] ? ' is-alert' : '' ?>"><div class="ledger-num"><?= (int) $summary['overdue'] ?></div><div class="ledger-label">overdue doses</div></div>
            <div class="ledger-cell"><div class="ledger-num"><?= count($missed) ?></div><div class="ledger-label">not given at a visit</div></div>
        </div>

        <!-- 1. Doses given -->
        <h3 class="h4">Vaccines given</h3>
        <?php if (!$given): ?>
            <p class="text-soft">No doses have been recorded yet.</p>
        <?php else: ?>
            <div class="table-responsive mb-4">
                <table class="table table-sm border">
                    <thead>
                        <tr><th scope="col">#</th><th scope="col">Vaccine</th><th scope="col">Protects against</th><th scope="col">Date given</th><th scope="col">Given at</th><th scope="col">Recorded by</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($given as $i => $d): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= e($d['vaccine']['short_name']) ?></strong><div class="cell-sub"><?= e($d['vaccine']['name']) ?></div></td>
                                <td><?= e($d['vaccine']['protects_against']) ?></td>
                                <td class="text-nowrap"><?= e(fmt_date($d['given']['date'])) ?></td>
                                <td><?= e($d['given']['place'] ?: '-') ?></td>
                                <td class="text-nowrap">
                                    <?php if ($d['given']['source'] === 'hospital'): ?>
                                        Hospital <span class="cell-sub">(<?= e(booking_ref($d['given']['appointment_id'])) ?>)</span>
                                    <?php else: ?>
                                        Parent, from paper card
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- 2. Missed at a visit -->
        <?php if ($missed): ?>
            <h3 class="h4">Not given at a visit</h3>
            <div class="table-responsive mb-4">
                <table class="table table-sm border">
                    <thead><tr><th scope="col">Visit date</th><th scope="col">Vaccine</th><th scope="col">Hospital</th><th scope="col">Hospital remark</th></tr></thead>
                    <tbody>
                        <?php foreach ($missed as $m): ?>
                            <tr>
                                <td class="text-nowrap"><?= e(fmt_date($m['appointment_date'])) ?></td>
                                <td><strong><?= e($m['short_name']) ?></strong> <span class="cell-sub"><?= e($m['name']) ?></span></td>
                                <td><?= e($m['hospital_name']) ?></td>
                                <td><?= e($m['hospital_remark'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- 3. Still to come -->
        <h3 class="h4">Still to be given</h3>
        <?php if (!$open): ?>
            <p><span class="stamp-line">SCHEDULE COMPLETE</span> Every routine vaccine has been given.</p>
        <?php else: ?>
            <div class="table-responsive mb-2">
                <table class="table table-sm border">
                    <thead><tr><th scope="col">Vaccine</th><th scope="col">Recommended age</th><th scope="col">Due date</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($open as $d): ?>
                            <?php [, $cls] = state_meta($d['state']['key']); ?>
                            <tr>
                                <td><strong><?= e($d['vaccine']['short_name']) ?></strong> <span class="cell-sub"><?= e($d['vaccine']['name']) ?></span></td>
                                <td><?= e($d['vaccine']['age_label']) ?></td>
                                <td class="text-nowrap"><?= e(fmt_date($d['due_date'])) ?></td>
                                <td>
                                    <span class="state-chip <?= $cls ?>"><?= e($d['state']['label']) ?></span>
                                    <?php if ($d['booked']): ?>
                                        <span class="cell-sub"><?= e(fmt_date($d['booked']['date'])) ?>, <?= e($d['booked']['place']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Signature area for printed copies -->
        <div class="print-only mt-5">
            <div class="d-flex justify-content-between">
                <span>Checked by: ______________________</span>
                <span>Stamp and date: ______________________</span>
            </div>
        </div>
    </div>
    <div class="sheet-foot small-note">
        This report is generated from <?= e(APP_NAME) ?> records. Doses marked "Parent, from paper card" were entered by the parent.
    </div>
</article>
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
