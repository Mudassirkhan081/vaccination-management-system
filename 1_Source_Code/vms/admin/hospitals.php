<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/hospitals.php
 * Purpose : Admin module - "Add / Update / Delete / List hospitals".
 *           Also approves self-registered hospitals and switches a
 *           hospital between Active and Inactive.
 *           ?action=add   -> add form      ?edit=<id> -> edit form
 *           ?id=<id>      -> detail view   ?status=Pending -> filter
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$action  = get('action');
$editId  = int_param('edit');
$viewId  = int_param('id');
$editing = $editId ? db_one('SELECT * FROM hospitals WHERE id = ?', [$editId]) : null;
if ($editId && !$editing) {
    flash('danger', 'That hospital was not found.');
    redirect('admin/hospitals.php');
}
$showForm = $action === 'add' || $editing;

$form = ['name' => '', 'email' => '', 'phone' => '', 'city' => 'Karachi', 'address' => '', 'location' => '', 'timings' => '', 'status' => 'Active'];
if ($editing) {
    foreach (array_keys($form) as $key) {
        $form[$key] = (string) ($editing[$key] ?? '');
    }
}
$errors = [];

/* ------------------------------------------------------------------
 * POST actions
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $postAction = post('action');

    /* ---------- Approve / activate / deactivate ---------- */
    if ($postAction === 'status') {
        $h   = db_one('SELECT * FROM hospitals WHERE id = ?', [int_param('hospital_id', 'post')]);
        $new = post('status');
        if ($h && in_array($new, ['Active', 'Inactive'], true) && $h['status'] !== $new) {
            db_exec('UPDATE hospitals SET status = ? WHERE id = ?', [$new, $h['id']]);

            if ($new === 'Active') {
                $title = $h['status'] === 'Pending' ? 'Registration approved' : 'Account re-activated';
                notify('hospital', (int) $h['id'], $title, 'Welcome to ' . APP_NAME . '. Parents can now book vaccination visits at ' . $h['name'] . '.', 'hospital/index.php');
                flash('success', $h['name'] . ($h['status'] === 'Pending' ? ' has been approved and can log in now.' : ' is active again.'));
            } else {
                $upcoming = (int) db_value(
                    "SELECT COUNT(*) FROM appointments WHERE hospital_id = ? AND request_status IN ('Pending', 'Approved')
                        AND vaccination_status = 'Pending' AND appointment_date >= CURDATE()",
                    [$h['id']]
                );
                flash(
                    $upcoming ? 'warning' : 'success',
                    $h['name'] . ' is now inactive and hidden from parents.'
                        . ($upcoming ? ' It still has ' . $upcoming . ' open booking(s); please reject or cancel them.' : '')
                );
            }
        }
        redirect_self();
    }

    /* ---------- Delete ---------- */
    if ($postAction === 'delete') {
        $h = db_one('SELECT * FROM hospitals WHERE id = ?', [int_param('hospital_id', 'post')]);
        if ($h) {
            $used = (int) db_value('SELECT COUNT(*) FROM appointments WHERE hospital_id = ?', [$h['id']]);
            if ($used) {
                flash('danger', $h['name'] . ' has ' . $used . ' booking(s) on record, so it cannot be deleted. Make it inactive instead.');
            } else {
                db_exec('DELETE FROM hospitals WHERE id = ?', [$h['id']]);
                db_exec("DELETE FROM notifications WHERE user_role = 'hospital' AND user_id = ?", [$h['id']]);
                flash('success', $h['name'] . ' has been deleted.');
            }
        }
        redirect('admin/hospitals.php');
    }

    /* ---------- Add or edit ---------- */
    if ($postAction === 'save') {
        $data           = read_account_input('hospital');
        $errors         = validate_account('hospital', $data, $editId);
        $form           = array_merge($form, $data);
        $form['status'] = post('status');

        $allowed = $editing && $editing['status'] === 'Pending' ? ['Pending', 'Active', 'Inactive'] : ['Active', 'Inactive'];
        if (!in_array($form['status'], $allowed, true)) {
            $errors['status'] = 'Choose a status.';
        }

        // Password: required when adding, optional when editing
        $password = (string) ($_POST['password'] ?? '');
        if (!$editing || $password !== '') {
            validate_new_password($password, (string) ($_POST['password_confirm'] ?? ''), $errors);
        }

        if (!$errors) {
            $values = [
                $form['name'], $form['email'], $form['phone'], $form['city'], $form['address'],
                $form['location'] !== '' ? $form['location'] : null,
                $form['timings'] !== '' ? $form['timings'] : null,
                $form['status'],
            ];
            if ($editing) {
                $sql = 'UPDATE hospitals SET name = ?, email = ?, phone = ?, city = ?, address = ?, location = ?, timings = ?, status = ?';
                if ($password !== '') {
                    $sql     .= ', password = ?';
                    $values[] = password_hash($password, PASSWORD_DEFAULT);
                }
                db_exec($sql . ' WHERE id = ?', array_merge($values, [$editId]));
                if ($editing['status'] === 'Pending' && $form['status'] === 'Active') {
                    notify('hospital', $editId, 'Registration approved', 'Welcome to ' . APP_NAME . '. Parents can now book visits at ' . $form['name'] . '.', 'hospital/index.php');
                }
                flash('success', $form['name'] . ' has been updated.' . ($password !== '' ? ' The new password is active.' : ''));
                redirect('admin/hospitals.php?id=' . $editId);
            } else {
                $values[] = password_hash($password, PASSWORD_DEFAULT);
                $newId    = db_insert(
                    'INSERT INTO hospitals (name, email, phone, city, address, location, timings, status, password)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $values
                );
                notify('hospital', $newId, 'Welcome to ' . APP_NAME, 'Your hospital account was created by the administrator. Please change your password under My profile.', 'hospital/profile.php');
                flash('success', $form['name'] . ' has been added. Share the email and password with the hospital.');
                redirect('admin/hospitals.php?id=' . $newId);
            }
        }
    }
}

/* ==================================================================
 * DETAIL VIEW
 * ================================================================== */
if ($viewId && !$showForm) {
    $h = db_one('SELECT * FROM hospitals WHERE id = ?', [$viewId]);
    if (!$h) {
        flash('danger', 'That hospital was not found.');
        redirect('admin/hospitals.php');
    }
    $stats = db_one(
        "SELECT COUNT(*) AS total,
                SUM(request_status = 'Pending') AS waiting,
                SUM(request_status = 'Approved' AND vaccination_status = 'Pending' AND appointment_date >= CURDATE()) AS upcoming,
                SUM(vaccination_status IN ('Vaccinated', 'Partially Vaccinated')) AS done
           FROM appointments WHERE hospital_id = ?",
        [$viewId]
    );
    $doses = (int) db_value(
        "SELECT COUNT(*) FROM appointment_vaccines av JOIN appointments a ON a.id = av.appointment_id
          WHERE a.hospital_id = ? AND av.status = 'Vaccinated'",
        [$viewId]
    );
    $upcoming = db_all(
        "SELECT a.*, c.name AS child_name FROM appointments a JOIN children c ON c.id = a.child_id
          WHERE a.hospital_id = ? AND a.request_status IN ('Pending', 'Approved') AND a.vaccination_status = 'Pending'
            AND a.appointment_date >= CURDATE()
          ORDER BY a.appointment_date, a.appointment_time LIMIT 10",
        [$viewId]
    );
    $upVaccines = appointment_vaccine_map(array_column($upcoming, 'id'));

    $pageTitle = $h['name'];
    $activeNav = 'hospitals';
    require APP_ROOT . '/includes/layout/panel-header.php';
    ?>
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <a class="btn btn-light btn-sm btn-icon" href="<?= url('admin/hospitals.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> All hospitals</a>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($h['status'] !== 'Active'): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status"><input type="hidden" name="status" value="Active">
                    <input type="hidden" name="hospital_id" value="<?= $viewId ?>">
                    <button class="btn btn-primary btn-sm" type="submit"><?= $h['status'] === 'Pending' ? 'Approve registration' : 'Make active' ?></button>
                </form>
            <?php endif; ?>
            <?php if ($h['status'] !== 'Inactive'): ?>
                <form method="post" data-confirm="<?= $h['status'] === 'Pending' ? 'Decline this registration? The hospital will not be able to log in.' : 'Make this hospital inactive? Parents will not be able to book it.' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status"><input type="hidden" name="status" value="Inactive">
                    <input type="hidden" name="hospital_id" value="<?= $viewId ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit"><?= $h['status'] === 'Pending' ? 'Decline' : 'Make inactive' ?></button>
                </form>
            <?php endif; ?>
            <a class="btn btn-light btn-sm btn-icon" href="<?= url('admin/hospitals.php?edit=' . $viewId) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <section class="sheet">
                <div class="sheet-head"><h2>Details</h2><?= status_badge($h['status']) ?></div>
                <dl class="detail-list sheet-body mb-0">
                    <dt>Email</dt><dd class="text-break"><?= e($h['email']) ?></dd>
                    <dt>Phone</dt><dd><?= e($h['phone']) ?></dd>
                    <dt>City</dt><dd><?= e($h['city']) ?></dd>
                    <dt>Address</dt><dd><?= e($h['address']) ?></dd>
                    <dt>Location</dt><dd class="text-break"><?= e($h['location'] ?: '-') ?></dd>
                    <dt>Timings</dt><dd><?= e($h['timings'] ?: '-') ?></dd>
                    <dt>Registered</dt><dd><?= e(fmt_datetime($h['created_at'])) ?></dd>
                </dl>
            </section>
        </div>
        <div class="col-xl-7">
            <div class="ledger mb-4">
                <div class="ledger-cell"><div class="ledger-num"><?= (int) $stats['total'] ?></div><div class="ledger-label">bookings in total</div></div>
                <div class="ledger-cell<?= $stats['waiting'] ? ' is-amber' : '' ?>"><div class="ledger-num"><?= (int) $stats['waiting'] ?></div><div class="ledger-label">waiting for approval</div></div>
                <div class="ledger-cell"><div class="ledger-num"><?= (int) $stats['upcoming'] ?></div><div class="ledger-label">confirmed upcoming</div></div>
                <div class="ledger-cell is-ink"><div class="ledger-num"><?= $doses ?></div><div class="ledger-label">doses given</div></div>
            </div>
            <section class="sheet">
                <div class="sheet-head">
                    <h2>Open bookings</h2>
                    <a class="small" href="<?= url('admin/bookings.php?hospital=' . $viewId) ?>">All bookings</a>
                </div>
                <?php if (!$upcoming): ?>
                    <?= empty_state('No open bookings', 'Upcoming visits at this hospital will appear here.') ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                                <?php foreach ($upcoming as $a): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= e(visit_when($a)) ?></td>
                                        <td><a href="<?= url('admin/bookings.php?id=' . (int) $a['id']) ?>"><?= e($a['child_name']) ?></a></td>
                                        <td><?= vaccine_chips($upVaccines[$a['id']] ?? []) ?></td>
                                        <td><?= booking_stage_badge($a) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <?php
    require APP_ROOT . '/includes/layout/panel-footer.php';
    exit;
}

/* ==================================================================
 * LIST VIEW (+ form)
 * ================================================================== */
$status = get('status');
$city   = get('city');
$q      = mb_substr(get('q'), 0, 80);
$where  = ['1 = 1'];
$params = [];
if (in_array($status, ['Pending', 'Active', 'Inactive'], true)) {
    $where[]  = 'h.status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if (in_array($city, city_list(), true)) {
    $where[]  = 'h.city = ?';
    $params[] = $city;
} else {
    $city = '';
}
if ($q !== '') {
    $where[] = '(h.name LIKE ? OR h.email LIKE ? OR h.address LIKE ? OR h.phone LIKE ?)';
    $params  = array_merge($params, array_fill(0, 4, like_param($q)));
}
$whereSql = implode(' AND ', $where);

$pager     = paginate((int) db_value("SELECT COUNT(*) FROM hospitals h WHERE $whereSql", $params));
$hospitals = db_all(
    "SELECT h.*,
            (SELECT COUNT(*) FROM appointments a WHERE a.hospital_id = h.id) AS booking_count,
            (SELECT COUNT(*) FROM appointments a WHERE a.hospital_id = h.id AND a.request_status = 'Approved'
                AND a.vaccination_status = 'Pending' AND a.appointment_date >= CURDATE()) AS upcoming_count
       FROM hospitals h
      WHERE $whereSql
      ORDER BY FIELD(h.status, 'Pending', 'Active', 'Inactive'), h.name
      LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per'], $pager['offset']])
);
$statusCounts = db_all('SELECT status, COUNT(*) AS total FROM hospitals GROUP BY status');
$statusCounts = array_column($statusCounts, 'total', 'status');

$pageTitle = $showForm ? ($editing ? 'Edit hospital' : 'Add a hospital') : 'Hospitals';
$activeNav = 'hospitals';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<?php if ($showForm): ?>
    <!-- ================= Add / edit form ================= -->
    <form class="sheet" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="sheet-head"><h2><?= $editing ? e($editing['name']) : 'New hospital' ?></h2></div>
        <div class="sheet-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label required" for="f-name">Hospital name</label>
                    <input class="form-control<?= invalid($errors, 'name') ?>" id="f-name" name="name" value="<?= e($form['name']) ?>" required>
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="f-status">Status</label>
                    <select class="form-select<?= invalid($errors, 'status') ?>" id="f-status" name="status">
                        <?php if ($editing && $editing['status'] === 'Pending'): ?><option<?= selected('Pending', $form['status']) ?>>Pending</option><?php endif; ?>
                        <option<?= selected('Active', $form['status']) ?>>Active</option>
                        <option<?= selected('Inactive', $form['status']) ?>>Inactive</option>
                    </select>
                    <?= field_error($errors, 'status') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="f-email">Login email</label>
                    <input class="form-control<?= invalid($errors, 'email') ?>" type="email" id="f-email" name="email" value="<?= e($form['email']) ?>" required autocomplete="off">
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label required" for="f-phone">Phone</label>
                    <input class="form-control<?= invalid($errors, 'phone') ?>" type="tel" id="f-phone" name="phone" value="<?= e($form['phone']) ?>" required>
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="f-city">City</label>
                    <select class="form-select<?= invalid($errors, 'city') ?>" id="f-city" name="city"><?= city_options($form['city']) ?></select>
                    <?= field_error($errors, 'city') ?>
                </div>
                <div class="col-md-8">
                    <label class="form-label required" for="f-address">Address</label>
                    <input class="form-control<?= invalid($errors, 'address') ?>" id="f-address" name="address" value="<?= e($form['address']) ?>" required>
                    <?= field_error($errors, 'address') ?>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="f-location">Location / landmark or map link</label>
                    <input class="form-control<?= invalid($errors, 'location') ?>" id="f-location" name="location" value="<?= e($form['location']) ?>">
                    <?= field_error($errors, 'location') ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="f-timings">Timings</label>
                    <input class="form-control<?= invalid($errors, 'timings') ?>" id="f-timings" name="timings" value="<?= e($form['timings']) ?>" placeholder="Mon to Sat, 9 AM to 5 PM">
                    <?= field_error($errors, 'timings') ?>
                </div>

                <?php if ($editing): ?>
                    <!-- Optional password reset -->
                    <div class="col-md-6">
                        <label class="form-label" for="f-password">New password <span class="text-soft fw-normal">(leave empty to keep)</span></label>
                        <div class="password-field">
                            <input class="form-control<?= invalid($errors, 'password') ?>" type="password" id="f-password" name="password" autocomplete="new-password">
                            <button class="password-toggle" type="button" data-target="#f-password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                            <?= field_error($errors, 'password') ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="f-password_confirm">Repeat new password</label>
                        <input class="form-control<?= invalid($errors, 'password_confirm') ?>" type="password" id="f-password_confirm" name="password_confirm" autocomplete="new-password">
                        <?= field_error($errors, 'password_confirm') ?>
                    </div>
                <?php else: ?>
                    <div class="col-md-6"><?= password_input('password', 'Password', $errors, 'new-password', 'At least 8 characters with a letter and a number.') ?></div>
                    <div class="col-md-6"><?= password_input('password_confirm', 'Repeat password', $errors) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="sheet-foot d-flex justify-content-between gap-2 flex-wrap">
            <?php if ($editing): ?>
                <span class="small-note align-self-center">Registered <?= e(fmt_date($editing['created_at'])) ?></span>
            <?php else: ?><span></span><?php endif; ?>
            <div class="d-flex gap-2">
                <a class="btn btn-light" href="<?= url($editing ? 'admin/hospitals.php?id=' . $editId : 'admin/hospitals.php') ?>">Cancel</a>
                <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add hospital' ?></button>
            </div>
        </div>
    </form>

<?php else: ?>
    <!-- ================= Filters ================= -->
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <form class="filters" method="get" role="search">
            <div>
                <label class="form-label" for="q">Search</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, area">
            </div>
            <div>
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" data-autosubmit>
                    <option value="">All statuses</option>
                    <?php foreach (['Pending', 'Active', 'Inactive'] as $s): ?>
                        <option value="<?= $s ?>"<?= selected($s, $status) ?>><?= $s ?> (<?= (int) ($statusCounts[$s] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="city">City</label>
                <select class="form-select" id="city" name="city" data-autosubmit><?= city_options($city, 'All cities') ?></select>
            </div>
            <button class="btn btn-light" type="submit">Search</button>
        </form>
        <a class="btn btn-primary btn-icon" href="<?= url('admin/hospitals.php?action=add') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add hospital</a>
    </div>

    <?php if (!empty($statusCounts['Pending']) && $status !== 'Pending'): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><?= (int) $statusCounts['Pending'] ?> hospital registration(s) waiting for approval.</span>
            <a class="btn btn-sm btn-light" href="<?= url('admin/hospitals.php?status=Pending') ?>">Review now</a>
        </div>
    <?php endif; ?>

    <div class="sheet">
        <?php if (!$hospitals): ?>
            <?= empty_state('No hospitals found', 'Try another search or add a hospital.', url('admin/hospitals.php?action=add'), 'Add hospital') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th scope="col">Hospital</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Location</th>
                            <th scope="col">Bookings</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hospitals as $h): ?>
                            <tr>
                                <td><a class="cell-title" href="<?= url('admin/hospitals.php?id=' . (int) $h['id']) ?>"><?= e($h['name']) ?></a><div class="cell-sub"><?= e($h['timings'] ?: 'Timings not listed') ?></div></td>
                                <td><?= e($h['phone']) ?><div class="cell-sub text-break"><?= e($h['email']) ?></div></td>
                                <td><?= e($h['city']) ?><div class="cell-sub"><?= e($h['address']) ?></div></td>
                                <td><?= (int) $h['booking_count'] ?><div class="cell-sub"><?= (int) $h['upcoming_count'] ?> upcoming</div></td>
                                <td><?= status_badge($h['status']) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <?php if ($h['status'] === 'Pending'): ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="status"><input type="hidden" name="status" value="Active">
                                                <input type="hidden" name="hospital_id" value="<?= (int) $h['id'] ?>">
                                                <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                                            </form>
                                        <?php endif; ?>
                                        <a class="btn btn-sm btn-light" href="<?= url('admin/hospitals.php?id=' . (int) $h['id']) ?>">View</a>
                                        <a class="btn btn-sm btn-light" href="<?= url('admin/hospitals.php?edit=' . (int) $h['id']) ?>" aria-label="Edit <?= e($h['name']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                        <form method="post" data-confirm="Delete <?= e($h['name']) ?>? This cannot be undone.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="hospital_id" value="<?= (int) $h['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete <?= e($h['name']) ?>"
                                                <?= $h['booking_count'] ? 'disabled title="Has bookings - make it inactive instead"' : '' ?>><i class="bi bi-trash3" aria-hidden="true"></i></button>
                                        </form>
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
<?php endif; ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
