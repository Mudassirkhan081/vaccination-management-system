<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/vaccines.php
 * Purpose : Admin module - "List of vaccines (Available / Unavailable)".
 *           Add, edit and delete vaccines and switch availability.
 *           Unavailable vaccines cannot be booked by parents.
 *           ?action=add        -> add form
 *           ?edit=<id>         -> edit form
 *           ?status=Unavailable -> filter
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$units = ['days' => 1, 'weeks' => 7, 'months' => 30, 'years' => 365];

/** Splits a number of days into the friendliest value + unit for the form. */
function age_parts(int $days): array
{
    if ($days === 0) {
        return [0, 'weeks'];
    }
    if ($days % 365 === 0) {
        return [intdiv($days, 365), 'years'];
    }
    if ($days % 30 === 0 && $days >= 180) {
        return [intdiv($days, 30), 'months'];
    }
    if ($days % 7 === 0) {
        return [intdiv($days, 7), 'weeks'];
    }
    if ($days % 30 === 0) {
        return [intdiv($days, 30), 'months'];
    }
    return [$days, 'days'];
}

$action = get('action');
$editId = int_param('edit');
$editing = null;
if ($editId) {
    $editing = db_one('SELECT * FROM vaccines WHERE id = ?', [$editId]);
    if (!$editing) {
        flash('danger', 'That vaccine was not found.');
        redirect('admin/vaccines.php');
    }
}
$showForm = $action === 'add' || $editing;

// Form values
$form = ['short_name' => '', 'name' => '', 'protects_against' => '', 'age_value' => '0', 'age_unit' => 'weeks',
         'age_label' => 'At birth', 'route' => '', 'description' => '', 'status' => 'Available'];
if ($editing) {
    [$value, $unit] = age_parts((int) $editing['age_days']);
    $form = array_merge($form, [
        'short_name'       => $editing['short_name'],
        'name'             => $editing['name'],
        'protects_against' => $editing['protects_against'],
        'age_value'        => (string) $value,
        'age_unit'         => $unit,
        'age_label'        => $editing['age_label'],
        'route'            => (string) $editing['route'],
        'description'      => (string) $editing['description'],
        'status'           => $editing['status'],
    ]);
}
$errors = [];

/* ------------------------------------------------------------------
 * POST actions
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $postAction = post('action');

    /* ---------- Switch availability ---------- */
    if ($postAction === 'toggle') {
        $v = db_one('SELECT * FROM vaccines WHERE id = ?', [int_param('vaccine_id', 'post')]);
        if ($v) {
            $new = $v['status'] === 'Available' ? 'Unavailable' : 'Available';
            db_exec('UPDATE vaccines SET status = ? WHERE id = ?', [$new, $v['id']]);

            $message = $v['short_name'] . ' is now ' . strtolower($new) . '.';
            if ($new === 'Unavailable') {
                $open = (int) db_value(
                    "SELECT COUNT(*) FROM appointment_vaccines av JOIN appointments a ON a.id = av.appointment_id
                      WHERE av.vaccine_id = ? AND av.status = 'Pending'
                        AND a.request_status IN ('Pending', 'Approved') AND a.vaccination_status = 'Pending'",
                    [$v['id']]
                );
                if ($open) {
                    $message .= ' Note: ' . $open . ' open booking(s) include it. Check them before the visit date.';
                }
            }
            flash('success', $message);
        }
        redirect_self();
    }

    /* ---------- Delete ---------- */
    if ($postAction === 'delete') {
        $v = db_one('SELECT * FROM vaccines WHERE id = ?', [int_param('vaccine_id', 'post')]);
        if ($v) {
            $used = (int) db_value('SELECT COUNT(*) FROM appointment_vaccines WHERE vaccine_id = ?', [$v['id']])
                  + (int) db_value('SELECT COUNT(*) FROM vaccination_history WHERE vaccine_id = ?', [$v['id']]);
            if ($used) {
                flash('danger', $v['short_name'] . ' is used in ' . $used . ' record(s), so it cannot be deleted. Mark it as unavailable instead.');
            } else {
                db_exec('DELETE FROM vaccines WHERE id = ?', [$v['id']]);
                flash('success', $v['short_name'] . ' has been deleted.');
            }
        }
        redirect('admin/vaccines.php');
    }

    /* ---------- Add or edit ---------- */
    if ($postAction === 'save') {
        foreach (array_keys($form) as $key) {
            $form[$key] = post($key);
        }

        if (!preg_match('/^[A-Za-z0-9\-]{2,20}$/', $form['short_name'])) {
            $errors['short_name'] = 'Use 2 to 20 letters, numbers or hyphens (e.g. OPV-1).';
        } elseif (db_value('SELECT id FROM vaccines WHERE short_name = ? AND id <> ?', [$form['short_name'], $editId])) {
            $errors['short_name'] = 'Another vaccine already uses this short name.';
        }
        if (mb_strlen($form['name']) < 3 || mb_strlen($form['name']) > 100) {
            $errors['name'] = 'Enter the full name (3 to 100 characters).';
        }
        if (mb_strlen($form['protects_against']) < 3 || mb_strlen($form['protects_against']) > 150) {
            $errors['protects_against'] = 'Enter the disease(s) it protects against (3 to 150 characters).';
        }
        if (!ctype_digit($form['age_value']) || !isset($units[$form['age_unit']])) {
            $errors['age_value'] = 'Enter a whole number and choose a unit.';
        } else {
            $ageDays = (int) $form['age_value'] * $units[$form['age_unit']];
            if ($ageDays > 365 * MAX_CHILD_AGE_YEARS) {
                $errors['age_value'] = 'The recommended age must be within ' . MAX_CHILD_AGE_YEARS . ' years.';
            }
        }
        if (mb_strlen($form['age_label']) < 2 || mb_strlen($form['age_label']) > 30) {
            $errors['age_label'] = 'Enter how the age is shown, e.g. "6 weeks".';
        }
        if (mb_strlen($form['route']) > 50) {
            $errors['route'] = 'Up to 50 characters.';
        }
        if (mb_strlen($form['description']) > 255) {
            $errors['description'] = 'Up to 255 characters.';
        }
        if (!in_array($form['status'], ['Available', 'Unavailable'], true)) {
            $errors['status'] = 'Choose a status.';
        }

        if (!$errors) {
            $values = [
                $form['short_name'], $form['name'], $form['protects_against'], $ageDays, $form['age_label'],
                $form['route'] !== '' ? $form['route'] : null,
                $form['description'] !== '' ? $form['description'] : null,
                $form['status'],
            ];
            if ($editing) {
                db_exec(
                    'UPDATE vaccines SET short_name = ?, name = ?, protects_against = ?, age_days = ?, age_label = ?, route = ?, description = ?, status = ?
                      WHERE id = ?',
                    array_merge($values, [$editId])
                );
                flash('success', $form['short_name'] . ' has been updated. Every child\'s schedule now uses the new details.');
            } else {
                db_insert(
                    'INSERT INTO vaccines (short_name, name, protects_against, age_days, age_label, route, description, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    $values
                );
                flash('success', $form['short_name'] . ' has been added to the schedule.');
            }
            redirect('admin/vaccines.php');
        }
    }
}

/* ------------------------------------------------------------------
 * List with filters
 * ------------------------------------------------------------------ */
$status = get('status');
$q      = mb_substr(get('q'), 0, 60);
$where  = ['1 = 1'];
$params = [];
if (in_array($status, ['Available', 'Unavailable'], true)) {
    $where[]  = 'v.status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if ($q !== '') {
    $where[] = '(v.short_name LIKE ? OR v.name LIKE ? OR v.protects_against LIKE ?)';
    $params  = array_merge($params, [like_param($q), like_param($q), like_param($q)]);
}

$vaccines = db_all(
    "SELECT v.*,
            (SELECT COUNT(*) FROM appointment_vaccines av WHERE av.vaccine_id = v.id AND av.status = 'Vaccinated')
          + (SELECT COUNT(*) FROM vaccination_history vh WHERE vh.vaccine_id = v.id) AS given_count
       FROM vaccines v
      WHERE " . implode(' AND ', $where) . '
      ORDER BY v.age_days, v.id',
    $params
);
$counts = [
    'all'         => (int) db_value('SELECT COUNT(*) FROM vaccines'),
    'Available'   => (int) db_value("SELECT COUNT(*) FROM vaccines WHERE status = 'Available'"),
    'Unavailable' => (int) db_value("SELECT COUNT(*) FROM vaccines WHERE status = 'Unavailable'"),
];

$pageTitle = 'Vaccines';
$activeNav = 'vaccines';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<?php if ($showForm): ?>
    <!-- ================= Add / edit form ================= -->
    <form class="sheet mb-4" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="sheet-head"><h2><?= $editing ? 'Edit ' . e($editing['short_name']) : 'Add a vaccine' ?></h2></div>
        <div class="sheet-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label required" for="short_name">Short name</label>
                    <input class="form-control<?= invalid($errors, 'short_name') ?>" id="short_name" name="short_name" value="<?= e($form['short_name']) ?>" maxlength="20" placeholder="OPV-1" required>
                    <?= field_error($errors, 'short_name') ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label required" for="name">Full name</label>
                    <input class="form-control<?= invalid($errors, 'name') ?>" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="100" placeholder="Oral Polio Vaccine (dose 1)" required>
                    <?= field_error($errors, 'name') ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label required" for="protects_against">Protects against</label>
                    <input class="form-control<?= invalid($errors, 'protects_against') ?>" id="protects_against" name="protects_against" value="<?= e($form['protects_against']) ?>" maxlength="150" required>
                    <?= field_error($errors, 'protects_against') ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label required" for="age_value">Recommended age</label>
                    <div class="input-group has-validation">
                        <input class="form-control<?= invalid($errors, 'age_value') ?>" type="number" min="0" step="1" id="age_value" name="age_value" value="<?= e($form['age_value']) ?>" required>
                        <select class="form-select" id="age_unit" name="age_unit" aria-label="Age unit">
                            <?php foreach (array_keys($units) as $u): ?>
                                <option value="<?= $u ?>"<?= selected($u, $form['age_unit']) ?>><?= ucfirst($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'age_value') ?>
                    </div>
                    <div class="form-text">0 means at birth. A month counts as 30 days.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="age_label">Shown as</label>
                    <input class="form-control<?= invalid($errors, 'age_label') ?>" id="age_label" name="age_label" value="<?= e($form['age_label']) ?>" maxlength="30" required
                           <?= $editing ? 'data-touched="1"' : '' ?>>
                    <?= field_error($errors, 'age_label') ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="route">Route</label>
                    <input class="form-control<?= invalid($errors, 'route') ?>" id="route" name="route" value="<?= e($form['route']) ?>" maxlength="50" placeholder="Injection, left thigh" list="route-list">
                    <datalist id="route-list">
                        <option value="Oral drops"><option value="Injection, left thigh"><option value="Injection, right thigh"><option value="Injection, left upper arm"><option value="Injection, right upper arm">
                    </datalist>
                    <?= field_error($errors, 'route') ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label required" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option<?= selected('Available', $form['status']) ?>>Available</option>
                        <option<?= selected('Unavailable', $form['status']) ?>>Unavailable</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Notes</label>
                    <input class="form-control<?= invalid($errors, 'description') ?>" id="description" name="description" value="<?= e($form['description']) ?>" maxlength="255">
                    <?= field_error($errors, 'description') ?>
                </div>
            </div>
        </div>
        <div class="sheet-foot d-flex justify-content-end gap-2">
            <a class="btn btn-light" href="<?= url('admin/vaccines.php') ?>">Cancel</a>
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add vaccine' ?></button>
        </div>
    </form>
<?php endif; ?>

<!-- ================= Filters ================= -->
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <form class="filters" method="get" role="search">
        <div>
            <label class="form-label" for="q">Search</label>
            <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name or disease">
        </div>
        <div>
            <label class="form-label" for="f-status">Status</label>
            <select class="form-select" id="f-status" name="status" data-autosubmit>
                <option value="">All (<?= $counts['all'] ?>)</option>
                <option value="Available"<?= selected('Available', $status) ?>>Available (<?= $counts['Available'] ?>)</option>
                <option value="Unavailable"<?= selected('Unavailable', $status) ?>>Unavailable (<?= $counts['Unavailable'] ?>)</option>
            </select>
        </div>
        <button class="btn btn-light" type="submit">Search</button>
    </form>
    <?php if (!$showForm): ?>
        <a class="btn btn-primary btn-icon" href="<?= url('admin/vaccines.php?action=add') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add vaccine</a>
    <?php endif; ?>
</div>

<!-- ================= Vaccine table ================= -->
<div class="sheet">
    <?php if (!$vaccines): ?>
        <?= empty_state('No vaccines found', 'Try a different search.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col">Given at</th>
                        <th scope="col">Vaccine</th>
                        <th scope="col">Protects against</th>
                        <th scope="col">Route</th>
                        <th scope="col">Doses given</th>
                        <th scope="col">Availability</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaccines as $v): ?>
                        <tr<?= $editId === (int) $v['id'] ? ' class="table-active"' : '' ?>>
                            <td class="text-nowrap"><span class="fw-600"><?= e($v['age_label']) ?></span><div class="cell-sub"><?= (int) $v['age_days'] ?> days</div></td>
                            <td><span class="dose-cell<?= $v['status'] === 'Unavailable' ? ' is-out' : '' ?>"><?= e($v['short_name']) ?></span><div class="cell-sub mt-1"><?= e($v['name']) ?></div></td>
                            <td><?= e($v['protects_against']) ?></td>
                            <td class="cell-sub"><?= e($v['route'] ?: '-') ?></td>
                            <td><?= (int) $v['given_count'] ?></td>
                            <td>
                                <form method="post" class="d-flex align-items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="vaccine_id" value="<?= (int) $v['id'] ?>">
                                    <?= status_badge($v['status']) ?>
                                    <button class="btn btn-sm btn-link p-0" type="submit">
                                        <?= $v['status'] === 'Available' ? 'Mark unavailable' : 'Mark available' ?><span class="visually-hidden"> (<?= e($v['short_name']) ?>)</span>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-light" href="<?= url('admin/vaccines.php?edit=' . (int) $v['id']) ?>" aria-label="Edit <?= e($v['short_name']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                                    <form method="post" data-confirm="Delete <?= e($v['short_name']) ?>? This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="vaccine_id" value="<?= (int) $v['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete <?= e($v['short_name']) ?>" <?= $v['given_count'] ? 'disabled title="Used in records"' : '' ?>><i class="bi bi-trash3" aria-hidden="true"></i></button>
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
<p class="small-note mt-3 mb-0">Changing a recommended age moves the due dates on every child's card straight away.</p>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
