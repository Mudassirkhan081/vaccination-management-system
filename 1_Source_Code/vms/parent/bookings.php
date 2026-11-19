<?php
/**
 * ---------------------------------------------------------------------
 * File    : parent/bookings.php
 * Purpose : The parent's booking requests with their status
 *           (waiting / confirmed / vaccinated / rejected ...), remarks
 *           from the administrator and hospital, and cancelling.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('parent');

$parentId = current_user_id();

/* ------------------------------------------------------------------
 * POST: cancel a booking
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();

    if (post('action') === 'cancel') {
        $booking = find_appointment(int_param('appointment_id', 'post'), 'AND a.parent_id = ?', [$parentId]);

        if (!$booking) {
            flash('danger', 'That booking was not found.');
        } elseif (!booking_can_cancel($booking)) {
            flash('warning', 'This booking can no longer be cancelled.');
        } else {
            $wasApproved = $booking['request_status'] === 'Approved';
            db_exec("UPDATE appointments SET request_status = 'Cancelled' WHERE id = ? AND parent_id = ?", [$booking['id'], $parentId]);

            $text = $booking['parent_name'] . ' cancelled ' . booking_ref((int) $booking['id']) . ' for ' . $booking['child_name']
                  . ' (' . visit_when($booking) . ').';
            notify_admins('Booking cancelled', $text, 'admin/bookings.php?id=' . (int) $booking['id']);
            if ($wasApproved) {
                // The hospital was already expecting the child
                notify('hospital', (int) $booking['hospital_id'], 'Appointment cancelled', $text, 'hospital/appointments.php?view=upcoming');
            }
            flash('success', booking_ref((int) $booking['id']) . ' has been cancelled. The doses are open for booking again.');
        }
    }
    redirect_self();
}

/* ------------------------------------------------------------------
 * Filters: tab and child
 * ------------------------------------------------------------------ */
$tabs = [
    'open'   => ['Open', "a.request_status IN ('Pending', 'Approved') AND a.vaccination_status = 'Pending' AND a.appointment_date >= CURDATE()"],
    'done'   => ['Completed', "a.request_status = 'Approved' AND (a.vaccination_status <> 'Pending' OR a.appointment_date < CURDATE())"],
    'closed' => ['Rejected or cancelled', "a.request_status IN ('Rejected', 'Cancelled')"],
    'all'    => ['All', '1 = 1'],
];
$tab = get('tab', 'open');
if (!isset($tabs[$tab])) {
    $tab = 'open';
}

$children = db_all('SELECT id, name FROM children WHERE parent_id = ? ORDER BY name', [$parentId]);
$childId  = int_param('child');

$baseWhere  = 'a.parent_id = ?';
$baseParams = [$parentId];
if ($childId) {
    $baseWhere   .= ' AND a.child_id = ?';
    $baseParams[] = $childId;
}

// Count for every tab (shown in the tab labels)
$counts = [];
foreach ($tabs as $key => [, $condition]) {
    $counts[$key] = (int) db_value("SELECT COUNT(*) FROM appointments a WHERE $baseWhere AND $condition", $baseParams);
}

$where = $baseWhere . ' AND ' . $tabs[$tab][1];
$pager = paginate($counts[$tab]);
$order = $tab === 'open' ? 'a.appointment_date ASC, a.appointment_time ASC' : 'a.appointment_date DESC, a.appointment_time DESC';

$rows = db_all(
    "SELECT a.*, c.name AS child_name, h.name AS hospital_name, h.phone AS hospital_phone, h.address AS hospital_address
       FROM appointments a
       JOIN children c  ON c.id = a.child_id
       JOIN hospitals h ON h.id = a.hospital_id
      WHERE $where
      ORDER BY $order
      LIMIT ? OFFSET ?",
    array_merge($baseParams, [$pager['per'], $pager['offset']])
);
$vaccines = appointment_vaccine_map(array_column($rows, 'id'));

$pageTitle = 'My bookings';
$activeNav = 'bookings';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <form class="filters" method="get">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div>
            <label class="form-label" for="child">Child</label>
            <select class="form-select" id="child" name="child" data-autosubmit>
                <option value="">All children</option>
                <?php foreach ($children as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"<?= selected($c['id'], $childId) ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button class="btn btn-light" type="submit">Filter</button></noscript>
    </form>
    <a class="btn btn-primary btn-icon" href="<?= url('parent/book.php' . ($childId ? '?child=' . $childId : '')) ?>">
        <i class="bi bi-calendar-plus" aria-hidden="true"></i> New booking
    </a>
</div>

<div class="sheet">
    <nav class="tabs-line" aria-label="Booking status">
        <?php foreach ($tabs as $key => [$label]): ?>
            <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e(query_url(['tab' => $key, 'page' => null])) ?>"
               <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                <?= e($label) ?> <span class="num"><?= $counts[$key] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$rows): ?>
        <?= empty_state('No bookings here', $tab === 'open' ? 'You have no upcoming visits.' : 'Nothing to show for this filter.', url('parent/book.php'), 'Book a hospital') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Booking</th>
                        <th scope="col">Visit</th>
                        <th scope="col">Hospital</th>
                        <th scope="col">Vaccines</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $a): ?>
                        <tr>
                            <td>
                                <div class="cell-title ref"><?= e(booking_ref((int) $a['id'])) ?></div>
                                <div class="cell-sub"><?= e($a['child_name']) ?></div>
                                <div class="cell-sub">Requested <?= e(fmt_date($a['created_at'], 'd M')) ?></div>
                            </td>
                            <td class="text-nowrap">
                                <div class="fw-600"><?= e(fmt_date($a['appointment_date'])) ?></div>
                                <div class="cell-sub"><?= e(fmt_time($a['appointment_time'])) ?> &middot; <?= e(relative_days($a['appointment_date'])) ?></div>
                            </td>
                            <td>
                                <div class="fw-600"><?= e($a['hospital_name']) ?></div>
                                <div class="cell-sub"><?= e($a['hospital_address']) ?></div>
                                <div class="cell-sub"><?= e($a['hospital_phone']) ?></div>
                            </td>
                            <td style="min-width: 150px"><?= vaccine_chips($vaccines[$a['id']] ?? [], true, true) ?></td>
                            <td style="min-width: 190px">
                                <?= booking_stage_badge($a) ?>
                                <?php if ($a['admin_remark']): ?>
                                    <div class="cell-sub mt-1"><strong>Admin:</strong> <?= e($a['admin_remark']) ?></div>
                                <?php endif; ?>
                                <?php if ($a['hospital_remark']): ?>
                                    <div class="cell-sub mt-1"><strong>Hospital:</strong> <?= e($a['hospital_remark']) ?></div>
                                <?php endif; ?>
                                <?php if ($a['parent_note']): ?>
                                    <div class="cell-sub mt-1"><strong>Your note:</strong> <?= e($a['parent_note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (booking_can_cancel($a)): ?>
                                    <form method="post" data-confirm="Cancel booking <?= e(booking_ref((int) $a['id'])) ?> for <?= e($a['child_name']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $a['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                    </form>
                                <?php elseif ($a['vaccination_status'] !== 'Pending'): ?>
                                    <a class="btn btn-sm btn-light" href="<?= url('parent/report.php?child=' . (int) $a['child_id']) ?>">Report</a>
                                <?php elseif ($a['request_status'] === 'Rejected'): ?>
                                    <a class="btn btn-sm btn-light" href="<?= url('parent/book.php?child=' . (int) $a['child_id'] . '&hospital=' . (int) $a['hospital_id']) ?>">Book again</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php render_pagination($pager); ?>

<p class="small-note mt-3 mb-0">
    <span class="dose-cell is-given">Given</span> dose given &nbsp;
    <span class="dose-cell is-missed">Missed</span> not given at the visit &nbsp;
    <span class="dose-cell is-out">Stock</span> currently out of stock
</p>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
