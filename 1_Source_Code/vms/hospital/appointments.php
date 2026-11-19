<?php
/**
 * ---------------------------------------------------------------------
 * File    : hospital/appointments.php
 * Purpose : Hospital module - "Update the vaccine status after booking".
 *           Lists the hospital's confirmed appointments (today, upcoming,
 *           awaiting result, completed) and records the result of a
 *           visit: every booked vaccine is marked Given or Not given.
 *
 * RESULT ALGORITHM
 *   1. The appointment must belong to this hospital, be Approved, have
 *      no result yet, and its date must be today or earlier.
 *   2. Every booked vaccine needs a result (Vaccinated / Not Vaccinated).
 *      A remark is required when any vaccine was not given.
 *   3. In one transaction: save each vaccine result (given_at = visit
 *      time), then the overall status:
 *        all given -> Vaccinated, none -> Not Vaccinated,
 *        otherwise -> Partially Vaccinated.
 *   4. Notify the parent (and the administrators if a dose was missed).
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('hospital');

$hospitalId = current_user_id();
$hospital   = $GLOBALS['authUser'];

/* ------------------------------------------------------------------
 * POST: record the result of a visit
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();

    if (post('action') === 'result') {
        $a = find_appointment(int_param('appointment_id', 'post'), 'AND a.hospital_id = ?', [$hospitalId]);

        if (!$a || $a['request_status'] !== 'Approved') {
            flash('danger', 'That appointment was not found.');
            redirect_self();
        }
        $ref = booking_ref((int) $a['id']);
        if ($a['vaccination_status'] !== 'Pending') {
            flash('warning', 'The result of ' . $ref . ' was already recorded.');
            redirect_self();
        }
        if ($a['appointment_date'] > today()) {
            flash('warning', 'Results can be recorded on or after the visit date (' . fmt_date($a['appointment_date']) . ').');
            redirect_self();
        }

        // Read one result per booked vaccine
        $booked  = appointment_vaccine_map([(int) $a['id']])[(int) $a['id']];
        $posted  = isset($_POST['result']) && is_array($_POST['result']) ? $_POST['result'] : [];
        $remark  = post('hospital_remark');
        $given   = [];
        $missed  = [];
        $invalid = false;

        foreach ($booked as $v) {
            $value = $posted[$v['vaccine_id']] ?? '';
            if ($value === 'Vaccinated') {
                $given[] = $v;
            } elseif ($value === 'Not Vaccinated') {
                $missed[] = $v;
            } else {
                $invalid = true;
            }
        }

        if ($invalid || !$booked) {
            flash('danger', 'Please choose Given or Not given for every vaccine of ' . $ref . '.');
            redirect_self();
        }
        if ($missed && mb_strlen($remark) < 5) {
            flash('danger', 'Please write a short remark explaining why ' . implode(', ', array_column($missed, 'short_name')) . ' could not be given.');
            redirect_self();
        }
        if (mb_strlen($remark) > 255) {
            flash('danger', 'The remark can be up to 255 characters.');
            redirect_self();
        }

        // Doses count as given at the visit time (or now, for today's visits)
        $givenAt = $a['appointment_date'] === today() ? date('Y-m-d H:i:s') : $a['appointment_date'] . ' ' . $a['appointment_time'];
        $overall = !$missed ? 'Vaccinated' : (!$given ? 'Not Vaccinated' : 'Partially Vaccinated');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($given as $v) {
                db_exec(
                    "UPDATE appointment_vaccines SET status = 'Vaccinated', given_at = ? WHERE appointment_id = ? AND vaccine_id = ?",
                    [$givenAt, $a['id'], $v['vaccine_id']]
                );
            }
            foreach ($missed as $v) {
                db_exec(
                    "UPDATE appointment_vaccines SET status = 'Not Vaccinated', given_at = NULL WHERE appointment_id = ? AND vaccine_id = ?",
                    [$a['id'], $v['vaccine_id']]
                );
            }
            db_exec(
                "UPDATE appointments SET vaccination_status = ?, hospital_remark = ?, completed_at = NOW()
                  WHERE id = ? AND vaccination_status = 'Pending'",
                [$overall, $remark !== '' ? $remark : null, $a['id']]
            );
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        // Tell the parent what happened
        $parts = [];
        if ($given) {
            $parts[] = implode(', ', array_column($given, 'short_name')) . ' given';
        }
        if ($missed) {
            $parts[] = implode(', ', array_column($missed, 'short_name')) . ' not given';
        }
        $message = $a['child_name'] . ' at ' . $a['hospital_name'] . ': ' . implode('; ', $parts) . '.'
                 . ($remark !== '' ? ' Remark: ' . $remark : '')
                 . ($missed ? ' Please book the missed dose(s) again.' : '');
        notify('parent', (int) $a['parent_id'], $missed ? 'Vaccination result: ' . strtolower($overall) : 'Vaccination recorded', $message, 'parent/child.php?id=' . (int) $a['child_id']);
        if ($missed) {
            notify_admins('Dose not given', $ref . ' - ' . $message, 'admin/bookings.php?id=' . (int) $a['id']);
        }

        flash('success', $ref . ' saved as ' . strtolower($overall) . '. The parent has been notified.');
    }
    redirect_self();
}

/* ------------------------------------------------------------------
 * Views and filters
 * ------------------------------------------------------------------ */
$views = [
    'today'    => ['Today', "a.appointment_date = CURDATE()", 'a.appointment_time ASC'],
    'upcoming' => ['Upcoming', "a.appointment_date > CURDATE() AND a.vaccination_status = 'Pending'", 'a.appointment_date ASC, a.appointment_time ASC'],
    'awaiting' => ['Result missing', "a.appointment_date < CURDATE() AND a.vaccination_status = 'Pending'", 'a.appointment_date ASC, a.appointment_time ASC'],
    'done'     => ['Completed', "a.vaccination_status <> 'Pending'", 'a.completed_at DESC'],
];
$view = get('view', 'today');
if (!isset($views[$view])) {
    $view = 'today';
}
$q = mb_substr(get('q'), 0, 80);

$base   = "a.hospital_id = ? AND a.request_status = 'Approved'";
$params = [$hospitalId];
if ($q !== '') {
    $base  .= ' AND (c.name LIKE ? OR p.name LIKE ? OR p.phone LIKE ?)';
    $params = array_merge($params, array_fill(0, 3, like_param($q)));
}
$joins = 'FROM appointments a JOIN children c ON c.id = a.child_id JOIN parents p ON p.id = a.parent_id';

$counts = [];
foreach ($views as $key => [, $condition]) {
    $counts[$key] = (int) db_value("SELECT COUNT(*) $joins WHERE $base AND $condition", $params);
}

[$viewLabel, $condition, $order] = $views[$view];
$pager = paginate($counts[$view]);
$rows  = db_all(
    "SELECT a.*, c.name AS child_name, c.date_of_birth, c.gender, c.blood_group, c.notes AS child_notes,
            p.name AS parent_name, p.phone AS parent_phone
       $joins
      WHERE $base AND $condition
      ORDER BY $order
      LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per'], $pager['offset']])
);
$vaccines = appointment_vaccine_map(array_column($rows, 'id'));

$pageTitle = 'Appointments';
$activeNav = 'appointments';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <form class="filters" method="get" role="search">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <div>
            <label class="form-label" for="q">Find a child</label>
            <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Child, parent or phone">
        </div>
        <button class="btn btn-light" type="submit">Search</button>
        <?php if ($q !== ''): ?><a class="btn btn-link" href="<?= e(query_url(['q' => null, 'page' => null])) ?>">Clear</a><?php endif; ?>
    </form>
    <button class="btn btn-light btn-icon" type="button" data-print><i class="bi bi-printer" aria-hidden="true"></i> Print list</button>
</div>

<div class="print-only report-head">
    <div><strong><?= e($hospital['name']) ?></strong> &middot; <?= e($viewLabel) ?> appointments</div>
    <div><?= e(fmt_date(today(), 'l, d M Y')) ?></div>
</div>

<div class="sheet">
    <nav class="tabs-line no-print" aria-label="Appointment lists">
        <?php foreach ($views as $key => [$label]): ?>
            <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(query_url(['view' => $key, 'page' => null])) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>>
                <?= e($label) ?> <span class="num<?= $key === 'awaiting' && $counts[$key] ? ' text-danger' : '' ?>"><?= $counts[$key] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($view === 'awaiting' && $rows): ?>
        <div class="sheet-body border-bottom small">
            <i class="bi bi-exclamation-circle text-danger me-1" aria-hidden="true"></i>
            These visits have passed but no result was recorded. Please record what happened so the parents' cards stay correct.
        </div>
    <?php endif; ?>

    <?php if (!$rows): ?>
        <?= empty_state(
            $view === 'today' ? 'No appointments today' : 'Nothing to show',
            $view === 'today' ? 'Confirmed visits for today will be listed here.' : 'There are no appointments in this list.'
        ) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-top">
                <thead>
                    <tr>
                        <th scope="col"><?= $view === 'today' ? 'Time' : 'Visit' ?></th>
                        <th scope="col">Child</th>
                        <th scope="col">Parent</th>
                        <th scope="col">Vaccines</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end no-print"><span class="visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $a): ?>
                        <?php
                        $list      = $vaccines[$a['id']] ?? [];
                        $canRecord = $a['vaccination_status'] === 'Pending' && $a['appointment_date'] <= today();
                        ?>
                        <tr>
                            <td class="text-nowrap">
                                <?php if ($view === 'today'): ?>
                                    <div class="fw-600"><?= e(fmt_time($a['appointment_time'])) ?></div>
                                <?php else: ?>
                                    <div class="fw-600"><?= e(fmt_date($a['appointment_date'])) ?></div>
                                    <div class="cell-sub"><?= e(fmt_time($a['appointment_time'])) ?> &middot; <?= e(relative_days($a['appointment_date'])) ?></div>
                                <?php endif; ?>
                                <div class="cell-sub"><?= e(booking_ref((int) $a['id'])) ?></div>
                            </td>
                            <td>
                                <div class="fw-600"><?= e($a['child_name']) ?></div>
                                <div class="cell-sub"><?= e($a['gender']) ?>, <?= e(child_age($a['date_of_birth'])) ?><?= $a['blood_group'] ? ', ' . e($a['blood_group']) : '' ?></div>
                                <?php if ($a['child_notes']): ?>
                                    <div class="cell-sub text-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= e($a['child_notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($a['parent_name']) ?>
                                <div class="cell-sub"><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $a['parent_phone'])) ?>"><?= e($a['parent_phone']) ?></a></div>
                                <?php if ($a['parent_note']): ?><div class="cell-sub"><strong>Note:</strong> <?= e($a['parent_note']) ?></div><?php endif; ?>
                            </td>
                            <td style="min-width: 160px">
                                <?= vaccine_chips($list, true, $a['vaccination_status'] === 'Pending') ?>
                                <?php if ($a['admin_remark']): ?><div class="cell-sub mt-1"><strong>Admin:</strong> <?= e($a['admin_remark']) ?></div><?php endif; ?>
                            </td>
                            <td style="min-width: 150px">
                                <?= booking_stage_badge($a) ?>
                                <?php if ($a['hospital_remark']): ?><div class="cell-sub mt-1"><?= e($a['hospital_remark']) ?></div><?php endif; ?>
                                <?php if ($a['completed_at']): ?><div class="cell-sub">Saved <?= e(fmt_datetime($a['completed_at'])) ?></div><?php endif; ?>
                            </td>
                            <td class="text-end no-print">
                                <?php if ($canRecord): ?>
                                    <?php
                                    $vaccineJson = json_encode(array_map(function ($v) {
                                        return ['id' => (int) $v['vaccine_id'], 'short' => $v['short_name'], 'name' => $v['name']];
                                    }, $list));
                                    $fill = json_encode([
                                        'appointment_id' => (string) $a['id'],
                                        'child'          => $a['child_name'],
                                        'ref'            => booking_ref((int) $a['id']),
                                        'when'           => visit_when($a),
                                    ]);
                                    ?>
                                    <button class="btn btn-sm btn-ink" type="button" data-bs-toggle="modal" data-bs-target="#resultModal"
                                            data-fill="<?= e($fill) ?>" data-vaccines="<?= e($vaccineJson) ?>">
                                        Record result
                                    </button>
                                <?php elseif ($a['vaccination_status'] === 'Pending'): ?>
                                    <span class="small-note">Opens on the visit day</span>
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

<!-- ================= Result modal (rows are built by app.js) ================= -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultTitle" aria-hidden="true" data-fill-form>
    <div class="modal-dialog">
        <form class="modal-content" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="result">
            <input type="hidden" name="appointment_id" value="">
            <div class="modal-header">
                <h2 class="modal-title h4" id="resultTitle">Vaccination result: <span data-slot="child"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small-note mb-2"><span data-slot="ref"></span> &middot; <span data-slot="when"></span></p>
                <p class="mb-1">Mark each vaccine as given or not given:</p>
                <div data-vaccine-list class="mb-3 border-bottom"></div>
                <label class="form-label" for="r-remark">Remark <span class="text-soft fw-normal">(required if a vaccine was not given)</span></label>
                <textarea class="form-control" id="r-remark" name="hospital_remark" rows="2" maxlength="255"
                          placeholder="e.g. Child had fever; MR-1 postponed by a week."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-ink">Save result</button>
            </div>
        </form>
    </div>
</div>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
