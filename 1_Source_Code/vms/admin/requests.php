<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/requests.php
 * Purpose : Admin module - "Get the list of requests from parents and
 *           approve or reject".
 *           Approve: the admin may move the date/time and add a remark;
 *                    the parent and the hospital are notified.
 *           Reject : a reason is required; the parent is notified.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

/* ------------------------------------------------------------------
 * POST: approve or reject one request
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $action = post('action');
    $a      = find_appointment(int_param('appointment_id', 'post'));

    if (!$a) {
        flash('danger', 'That request was not found.');
        redirect_self();
    }
    if ($a['request_status'] !== 'Pending') {
        flash('warning', booking_ref((int) $a['id']) . ' was already ' . strtolower($a['request_status']) . '.');
        redirect_self();
    }

    $ref    = booking_ref((int) $a['id']);
    $remark = post('admin_remark');
    $count  = (int) db_value('SELECT COUNT(*) FROM appointment_vaccines WHERE appointment_id = ?', [$a['id']]);

    /* ---------- Approve ---------- */
    if ($action === 'approve') {
        $date   = post('appointment_date');
        $time   = post('appointment_time');
        $errors = [];

        validate_visit_slot($date, $time, (int) $a['hospital_id'], (int) $a['id'], $errors);
        if (mb_strlen($remark) > 255) {
            $errors['admin_remark'] = 'The remark can be up to 255 characters.';
        }
        $hospitalStatus = db_value('SELECT status FROM hospitals WHERE id = ?', [$a['hospital_id']]);
        if ($hospitalStatus !== 'Active') {
            $errors['hospital'] = $a['hospital_name'] . ' is not active any more. Please reject the request with a note.';
        }

        if ($errors) {
            flash('danger', $ref . ' was not approved: ' . implode(' ', $errors));
            redirect_self();
        }

        $moved = $date !== $a['appointment_date'] || $time !== substr($a['appointment_time'], 0, 5);
        db_exec(
            "UPDATE appointments
                SET request_status = 'Approved', appointment_date = ?, appointment_time = ?, admin_remark = ?, decided_at = NOW()
              WHERE id = ? AND request_status = 'Pending'",
            [$date, $time . ':00', $remark !== '' ? $remark : null, $a['id']]
        );

        $when = fmt_date($date) . ' at ' . fmt_time($time);
        notify(
            'parent', (int) $a['parent_id'], 'Booking approved',
            $a['child_name'] . "'s visit at " . $a['hospital_name'] . ' is confirmed for ' . $when . '.'
                . ($moved ? ' The date or time was changed by the administrator.' : '')
                . ($remark !== '' ? ' Note: ' . $remark : ''),
            'parent/bookings.php'
        );
        notify(
            'hospital', (int) $a['hospital_id'], 'New appointment',
            $a['child_name'] . ' is booked for ' . $when . ' (' . $count . ' vaccine' . ($count === 1 ? '' : 's') . ').',
            'hospital/appointments.php?view=' . ($date === today() ? 'today' : 'upcoming')
        );

        flash('success', $ref . ' approved for ' . $when . '. The parent and the hospital have been notified.');
        redirect_self();
    }

    /* ---------- Reject ---------- */
    if ($action === 'reject') {
        if (mb_strlen($remark) < 5 || mb_strlen($remark) > 255) {
            flash('danger', $ref . ' was not rejected: please give the parent a short reason (5 to 255 characters).');
            redirect_self();
        }

        db_exec(
            "UPDATE appointments SET request_status = 'Rejected', admin_remark = ?, decided_at = NOW()
              WHERE id = ? AND request_status = 'Pending'",
            [$remark, $a['id']]
        );
        notify(
            'parent', (int) $a['parent_id'], 'Booking rejected',
            $a['child_name'] . "'s request for " . visit_when($a) . ' at ' . $a['hospital_name'] . ' was not approved. Reason: ' . $remark,
            'parent/bookings.php?tab=closed'
        );

        flash('success', $ref . ' has been rejected and the parent has been told why.');
        redirect_self();
    }

    redirect_self();
}

/* ------------------------------------------------------------------
 * Pending list (oldest request first) with hospital filter
 * ------------------------------------------------------------------ */
$hospitalFilter = int_param('hospital');
$where          = "a.request_status = 'Pending'";
$params         = [];
if ($hospitalFilter) {
    $where   .= ' AND a.hospital_id = ?';
    $params[] = $hospitalFilter;
}

$pager   = paginate((int) db_value("SELECT COUNT(*) FROM appointments a WHERE $where", $params));
$pending = db_all(
    "SELECT a.*, c.name AS child_name, c.date_of_birth, c.gender, c.notes AS child_notes,
            p.name AS parent_name, p.phone AS parent_phone,
            h.name AS hospital_name, h.city AS hospital_city, h.status AS hospital_status
       FROM appointments a
       JOIN children c  ON c.id = a.child_id
       JOIN parents p   ON p.id = a.parent_id
       JOIN hospitals h ON h.id = a.hospital_id
      WHERE $where
      ORDER BY a.created_at ASC
      LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per'], $pager['offset']])
);
$vaccines = appointment_vaccine_map(array_column($pending, 'id'));

// Recently decided requests for reference
$recent = db_all(
    "SELECT a.*, c.name AS child_name, h.name AS hospital_name
       FROM appointments a
       JOIN children c  ON c.id = a.child_id
       JOIN hospitals h ON h.id = a.hospital_id
      WHERE a.request_status IN ('Approved', 'Rejected') AND a.decided_at IS NOT NULL
      ORDER BY a.decided_at DESC
      LIMIT 8"
);

$hospitalOptions = db_all("SELECT id, name FROM hospitals WHERE id IN (SELECT hospital_id FROM appointments WHERE request_status = 'Pending') ORDER BY name");

$pageTitle = 'Booking requests';
$activeNav = 'requests';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <p class="mb-0 text-soft">
        <?= (int) $pager['total'] ?> request<?= $pager['total'] === 1 ? '' : 's' ?> waiting. Oldest requests are shown first.
    </p>
    <?php if ($hospitalOptions): ?>
        <form class="filters" method="get">
            <div>
                <label class="form-label" for="hospital">Hospital</label>
                <select class="form-select" id="hospital" name="hospital" data-autosubmit>
                    <option value="">All hospitals</option>
                    <?php foreach ($hospitalOptions as $h): ?>
                        <option value="<?= (int) $h['id'] ?>"<?= selected($h['id'], $hospitalFilter) ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript><button class="btn btn-light" type="submit">Filter</button></noscript>
        </form>
    <?php endif; ?>
</div>

<div class="sheet">
    <?php if (!$pending): ?>
        <?= empty_state('No requests waiting', 'New booking requests from parents will appear here.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-top">
                <thead>
                    <tr>
                        <th scope="col">Request</th>
                        <th scope="col">Child and parent</th>
                        <th scope="col">Hospital and time</th>
                        <th scope="col">Vaccines</th>
                        <th scope="col" class="text-end">Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $a): ?>
                        <?php
                        $list       = $vaccines[$a['id']] ?? [];
                        $outOfStock = array_filter($list, function ($v) { return $v['stock_status'] === 'Unavailable'; });
                        $slotLoad   = slot_bookings((int) $a['hospital_id'], $a['appointment_date'], substr($a['appointment_time'], 0, 5));
                        $fill       = json_encode([
                            'appointment_id'   => (string) $a['id'],
                            'appointment_date' => $a['appointment_date'] >= today() ? $a['appointment_date'] : add_days(today(), 1),
                            'appointment_time' => substr($a['appointment_time'], 0, 5),
                            'ref'              => booking_ref((int) $a['id']),
                            'child'            => $a['child_name'],
                            'hospital'         => $a['hospital_name'],
                        ]);
                        ?>
                        <tr>
                            <td>
                                <div class="cell-title ref"><?= e(booking_ref((int) $a['id'])) ?></div>
                                <div class="cell-sub"><?= e(time_ago($a['created_at'])) ?></div>
                            </td>
                            <td>
                                <a class="fw-600" href="<?= url('admin/children.php?id=' . (int) $a['child_id']) ?>"><?= e($a['child_name']) ?></a>
                                <div class="cell-sub"><?= e($a['gender']) ?>, <?= e(child_age($a['date_of_birth'])) ?></div>
                                <div class="cell-sub"><?= e($a['parent_name']) ?> &middot; <span class="text-nowrap"><?= e($a['parent_phone']) ?></span></div>
                                <?php if ($a['child_notes']): ?><div class="cell-sub text-warning-emphasis"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($a['child_notes']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-600"><?= e($a['hospital_name']) ?></div>
                                <div class="cell-sub"><?= e(visit_when($a)) ?> (<?= e(relative_days($a['appointment_date'])) ?>)</div>
                                <div class="cell-sub">Slot: <?= $slotLoad ?> of <?= (int) SLOT_CAPACITY ?> taken</div>
                                <?php if ($a['appointment_date'] < today()): ?><span class="pill pill-red mt-1">Date has passed</span><?php endif; ?>
                                <?php if ($a['hospital_status'] !== 'Active'): ?><span class="pill pill-red mt-1">Hospital <?= e(strtolower($a['hospital_status'])) ?></span><?php endif; ?>
                            </td>
                            <td style="min-width: 170px">
                                <?= vaccine_chips($list, false, true) ?>
                                <?php if ($outOfStock): ?>
                                    <div class="cell-sub text-warning-emphasis mt-1">Out of stock: <?= e(implode(', ', array_column($outOfStock, 'short_name'))) ?></div>
                                <?php endif; ?>
                                <?php if ($a['parent_note']): ?>
                                    <div class="cell-sub mt-1"><strong>Parent:</strong> <?= e($a['parent_note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#approveModal" data-fill="<?= e($fill) ?>">Approve</button>
                                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal" data-fill="<?= e($fill) ?>">Reject</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php render_pagination($pager); ?>

<!-- ================= Recently decided ================= -->
<section class="sheet mt-4" aria-labelledby="recent-title">
    <div class="sheet-head">
        <h2 id="recent-title">Recently decided</h2>
        <a class="small" href="<?= url('admin/bookings.php') ?>">All bookings</a>
    </div>
    <?php if (!$recent): ?>
        <?= empty_state('Nothing decided yet', 'Approved and rejected requests will be listed here.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th scope="col">Booking</th><th scope="col">Child</th><th scope="col">Hospital and visit</th><th scope="col">Decision</th><th scope="col">Remark</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><a class="fw-600 ref" href="<?= url('admin/bookings.php?id=' . (int) $r['id']) ?>"><?= e(booking_ref((int) $r['id'])) ?></a></td>
                            <td><?= e($r['child_name']) ?></td>
                            <td><?= e($r['hospital_name']) ?><div class="cell-sub"><?= e(visit_when($r)) ?></div></td>
                            <td><?= status_badge($r['request_status']) ?><div class="cell-sub"><?= e(time_ago($r['decided_at'])) ?></div></td>
                            <td class="cell-sub"><?= e($r['admin_remark'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- ================= Approve modal ================= -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveTitle" aria-hidden="true" data-fill-form>
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="appointment_id" value="">
            <div class="modal-header">
                <h2 class="modal-title h4" id="approveTitle">Approve <span data-slot="ref"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong data-slot="child"></strong> at <span data-slot="hospital"></span>. Keep the requested time or move it.</p>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="ap-date">Date</label>
                        <input class="form-control" type="date" id="ap-date" name="appointment_date" min="<?= today() ?>" max="<?= e(add_days(today(), MAX_BOOKING_DAYS)) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="ap-time">Time</label>
                        <select class="form-select" id="ap-time" name="appointment_time" required><?= time_slot_options() ?></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ap-remark">Note for the parent <span class="text-soft fw-normal">(optional)</span></label>
                        <textarea class="form-control" id="ap-remark" name="admin_remark" rows="2" maxlength="255" placeholder="e.g. Please bring the EPI card."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Approve and notify</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= Reject modal ================= -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectTitle" aria-hidden="true" data-fill-form>
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="appointment_id" value="">
            <div class="modal-header">
                <h2 class="modal-title h4" id="rejectTitle">Reject <span data-slot="ref"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong data-slot="child"></strong> at <span data-slot="hospital"></span>. The parent will see your reason.</p>
                <label class="form-label required" for="rj-remark">Reason</label>
                <textarea class="form-control" id="rj-remark" name="admin_remark" rows="3" minlength="5" maxlength="255" required
                          placeholder="e.g. The clinic is closed on the selected date. Please choose another date."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Reject request</button>
            </div>
        </form>
    </div>
</div>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
