<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/bookings.php
 * Purpose : Admin module - "Booking details".
 *           Every appointment with filters (search, hospital, decision,
 *           result, date range). ?id=<id> shows one booking in full,
 *           including each vaccine's result, and lets the admin cancel
 *           a confirmed visit that has not happened yet.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$viewId = int_param('id');

/* ------------------------------------------------------------------
 * POST: cancel a confirmed booking (with a reason)
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $a = find_appointment(int_param('appointment_id', 'post'));

    if (post('action') === 'cancel' && $a) {
        $reason = post('admin_remark');
        if (!booking_can_cancel($a)) {
            flash('warning', 'This booking can no longer be cancelled.');
        } elseif (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
            flash('danger', 'Please give a short reason (5 to 255 characters) so the parent knows what happened.');
        } else {
            db_exec(
                "UPDATE appointments SET request_status = 'Cancelled', admin_remark = ?, decided_at = NOW() WHERE id = ?",
                [$reason, $a['id']]
            );
            $text = booking_ref((int) $a['id']) . ' for ' . $a['child_name'] . ' on ' . visit_when($a) . ' was cancelled by the administrator. Reason: ' . $reason;
            notify('parent', (int) $a['parent_id'], 'Booking cancelled', $text, 'parent/bookings.php?tab=closed');
            if ($a['request_status'] === 'Approved') {
                notify('hospital', (int) $a['hospital_id'], 'Appointment cancelled', $text, 'hospital/appointments.php');
            }
            flash('success', booking_ref((int) $a['id']) . ' has been cancelled and everyone involved was notified.');
        }
    }
    redirect_self();
}

/* ==================================================================
 * DETAIL VIEW
 * ================================================================== */
if ($viewId) {
    $a = find_appointment($viewId);
    if (!$a) {
        flash('danger', 'That booking was not found.');
        redirect('admin/bookings.php');
    }
    $list = appointment_vaccine_map([$viewId])[$viewId];

    $pageTitle = 'Booking ' . booking_ref($viewId);
    $activeNav = 'bookings';
    require APP_ROOT . '/includes/layout/panel-header.php';
    ?>
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3 no-print">
        <a class="btn btn-light btn-sm btn-icon" href="<?= url('admin/bookings.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> All bookings</a>
        <div class="d-flex gap-2">
            <?php if ($a['request_status'] === 'Pending'): ?>
                <a class="btn btn-primary btn-sm" href="<?= url('admin/requests.php') ?>">Decide in Requests</a>
            <?php endif; ?>
            <button class="btn btn-light btn-sm btn-icon" type="button" data-print><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="sheet">
                <div class="sheet-head">
                    <h2><?= e(booking_ref($viewId)) ?> &middot; <?= e($a['child_name']) ?></h2>
                    <?= booking_stage_badge($a) ?>
                </div>
                <div class="sheet-body">
                    <dl class="detail-list">
                        <dt>Visit</dt>
                        <dd class="fw-600"><?= e(fmt_date($a['appointment_date'], 'l, d M Y')) ?> at <?= e(fmt_time($a['appointment_time'])) ?> <span class="small-note fw-normal">(<?= e(relative_days($a['appointment_date'])) ?>)</span></dd>
                        <dt>Child</dt>
                        <dd><a href="<?= url('admin/children.php?id=' . (int) $a['child_id']) ?>"><?= e($a['child_name']) ?></a>, <?= e($a['gender']) ?>, born <?= e(fmt_date($a['date_of_birth'])) ?> (<?= e(child_age($a['date_of_birth'])) ?>)</dd>
                        <dt>Parent</dt>
                        <dd><a href="<?= url('admin/parents.php?id=' . (int) $a['parent_id']) ?>"><?= e($a['parent_name']) ?></a> &middot; <?= e($a['parent_phone']) ?> &middot; <?= e($a['parent_email']) ?></dd>
                        <dt>Hospital</dt>
                        <dd><?= e($a['hospital_name']) ?><br><span class="small-note"><?= e($a['hospital_address']) ?>, <?= e($a['hospital_city']) ?> &middot; <?= e($a['hospital_phone']) ?></span></dd>
                        <dt>Parent's note</dt>
                        <dd><?= e($a['parent_note'] ?: '-') ?></dd>
                    </dl>
                </div>
                <div class="table-responsive border-top">
                    <table class="table">
                        <thead><tr><th scope="col">Vaccine</th><th scope="col">Result</th><th scope="col">Given at</th><th scope="col">Stock now</th></tr></thead>
                        <tbody>
                            <?php foreach ($list as $v): ?>
                                <tr>
                                    <td><span class="cell-title"><?= e($v['short_name']) ?></span> <span class="cell-sub"><?= e($v['name']) ?></span></td>
                                    <td><?= $v['status'] === 'Pending' ? '<span class="pill pill-grey">Waiting</span>' : status_badge($v['status']) ?></td>
                                    <td class="text-nowrap"><?= e(fmt_datetime($v['given_at'])) ?></td>
                                    <td><?= status_badge($v['stock_status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <!-- Timeline of the booking -->
            <section class="sheet">
                <div class="sheet-head"><h2>Timeline</h2></div>
                <ol class="timeline sheet-body mb-0">
                    <li class="done">
                        <strong>Requested by the parent</strong>
                        <span><?= e(fmt_datetime($a['created_at'])) ?></span>
                    </li>
                    <li class="<?= $a['request_status'] === 'Pending' ? 'wait' : ($a['request_status'] === 'Approved' ? 'done' : 'stop') ?>">
                        <strong>
                            <?php if ($a['request_status'] === 'Pending'): ?>Waiting for the administrator
                            <?php elseif ($a['request_status'] === 'Cancelled'): ?>Cancelled
                            <?php else: ?><?= e($a['request_status']) ?> by the administrator<?php endif; ?>
                        </strong>
                        <?php if ($a['decided_at']): ?><span><?= e(fmt_datetime($a['decided_at'])) ?></span><?php endif; ?>
                        <?php if ($a['admin_remark']): ?><span>&ldquo;<?= e($a['admin_remark']) ?>&rdquo;</span><?php endif; ?>
                    </li>
                    <?php if ($a['request_status'] === 'Approved'): ?>
                        <li class="<?= $a['vaccination_status'] === 'Pending' ? 'wait' : ($a['vaccination_status'] === 'Not Vaccinated' ? 'stop' : 'done') ?>">
                            <strong><?= $a['vaccination_status'] === 'Pending' ? 'Waiting for the hospital result' : e($a['vaccination_status']) ?></strong>
                            <?php if ($a['completed_at']): ?><span><?= e(fmt_datetime($a['completed_at'])) ?></span><?php endif; ?>
                            <?php if ($a['hospital_remark']): ?><span>&ldquo;<?= e($a['hospital_remark']) ?>&rdquo;</span><?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ol>
            </section>

            <?php if (booking_can_cancel($a) && $a['request_status'] === 'Approved'): ?>
                <form class="sheet no-print" method="post" data-confirm="Cancel this confirmed visit? The parent and hospital will be notified.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="appointment_id" value="<?= $viewId ?>">
                    <div class="sheet-head"><h2>Cancel this visit</h2></div>
                    <div class="sheet-body">
                        <label class="form-label required" for="c-remark">Reason</label>
                        <textarea class="form-control" id="c-remark" name="admin_remark" rows="2" minlength="5" maxlength="255" required placeholder="e.g. The hospital is closed on this day."></textarea>
                    </div>
                    <div class="sheet-foot text-end"><button class="btn btn-outline-danger btn-sm" type="submit">Cancel visit</button></div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
    require APP_ROOT . '/includes/layout/panel-footer.php';
    exit;
}

/* ==================================================================
 * LIST VIEW
 * ================================================================== */
$q          = mb_substr(get('q'), 0, 80);
$hospitalId = int_param('hospital');
$request    = get('request');
$result     = get('result');
$from       = get('from');
$to         = get('to');

$where  = ['1 = 1'];
$params = [];

if ($q !== '') {
    // "TC-00012" or "12" finds the booking number directly
    if (preg_match('/^(?:TC-?)?0*(\d+)$/i', $q, $m)) {
        $where[]  = '(a.id = ? OR c.name LIKE ? OR p.name LIKE ?)';
        $params   = array_merge($params, [(int) $m[1], like_param($q), like_param($q)]);
    } else {
        $where[]  = '(c.name LIKE ? OR p.name LIKE ? OR p.phone LIKE ?)';
        $params   = array_merge($params, [like_param($q), like_param($q), like_param($q)]);
    }
}
if ($hospitalId) {
    $where[]  = 'a.hospital_id = ?';
    $params[] = $hospitalId;
}
if (in_array($request, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) {
    $where[]  = 'a.request_status = ?';
    $params[] = $request;
} else {
    $request = '';
}
if (in_array($result, ['Pending', 'Vaccinated', 'Partially Vaccinated', 'Not Vaccinated'], true)) {
    $where[]  = 'a.vaccination_status = ?';
    $params[] = $result;
} else {
    $result = '';
}
if (valid_date($from)) {
    $where[]  = 'a.appointment_date >= ?';
    $params[] = $from;
} else {
    $from = '';
}
if (valid_date($to)) {
    $where[]  = 'a.appointment_date <= ?';
    $params[] = $to;
} else {
    $to = '';
}
$whereSql = implode(' AND ', $where);
$joins    = 'FROM appointments a JOIN children c ON c.id = a.child_id JOIN parents p ON p.id = a.parent_id JOIN hospitals h ON h.id = a.hospital_id';

$pager = paginate((int) db_value("SELECT COUNT(*) $joins WHERE $whereSql", $params));
$rows  = db_all(
    "SELECT a.*, c.name AS child_name, p.name AS parent_name, p.phone AS parent_phone, h.name AS hospital_name
       $joins WHERE $whereSql
      ORDER BY a.appointment_date DESC, a.appointment_time DESC
      LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per'], $pager['offset']])
);
$vaccines     = appointment_vaccine_map(array_column($rows, 'id'));
$hospitalList = db_all('SELECT id, name FROM hospitals ORDER BY name');
$filtered     = $q !== '' || $hospitalId || $request !== '' || $result !== '' || $from !== '' || $to !== '';

$pageTitle = 'All bookings';
$activeNav = 'bookings';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<form class="sheet sheet-body mb-3" method="get" role="search">
    <div class="filters">
        <div class="grow">
            <label class="form-label" for="q">Search</label>
            <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Booking no., child, parent or phone">
        </div>
        <div>
            <label class="form-label" for="hospital">Hospital</label>
            <select class="form-select" id="hospital" name="hospital">
                <option value="">All</option>
                <?php foreach ($hospitalList as $h): ?>
                    <option value="<?= (int) $h['id'] ?>"<?= selected($h['id'], $hospitalId) ?>><?= e($h['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="request">Decision</label>
            <select class="form-select" id="request" name="request">
                <option value="">Any</option>
                <?php foreach (['Pending', 'Approved', 'Rejected', 'Cancelled'] as $s): ?>
                    <option<?= selected($s, $request) ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="result">Hospital result</label>
            <select class="form-select" id="result" name="result">
                <option value="">Any</option>
                <?php foreach (['Pending', 'Vaccinated', 'Partially Vaccinated', 'Not Vaccinated'] as $s): ?>
                    <option<?= selected($s, $result) ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" for="from">Visit from</label>
            <input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>">
        </div>
        <div>
            <label class="form-label" for="to">Visit to</label>
            <input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <?php if ($filtered): ?><a class="btn btn-light" href="<?= url('admin/bookings.php') ?>">Clear</a><?php endif; ?>
    </div>
</form>

<div class="sheet">
    <div class="sheet-head"><h2><?= (int) $pager['total'] ?> booking<?= $pager['total'] === 1 ? '' : 's' ?></h2></div>
    <?php if (!$rows): ?>
        <?= empty_state('No bookings found', $filtered ? 'Try different filters.' : 'Bookings made by parents will appear here.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col">Booking</th>
                        <th scope="col">Visit</th>
                        <th scope="col">Child and parent</th>
                        <th scope="col">Hospital</th>
                        <th scope="col">Vaccines</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $a): ?>
                        <tr>
                            <td><a class="cell-title ref" href="<?= url('admin/bookings.php?id=' . (int) $a['id']) ?>"><?= e(booking_ref((int) $a['id'])) ?></a><div class="cell-sub">made <?= e(fmt_date($a['created_at'], 'd M')) ?></div></td>
                            <td class="text-nowrap"><?= e(fmt_date($a['appointment_date'])) ?><div class="cell-sub"><?= e(fmt_time($a['appointment_time'])) ?></div></td>
                            <td><?= e($a['child_name']) ?><div class="cell-sub"><?= e($a['parent_name']) ?> &middot; <span class="text-nowrap"><?= e($a['parent_phone']) ?></span></div></td>
                            <td><?= e($a['hospital_name']) ?></td>
                            <td style="min-width: 150px"><?= vaccine_chips($vaccines[$a['id']] ?? [], true) ?></td>
                            <td><?= booking_stage_badge($a) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php render_pagination($pager); ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
