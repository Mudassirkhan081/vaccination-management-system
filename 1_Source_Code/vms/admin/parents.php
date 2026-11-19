<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/parents.php
 * Purpose : Parent accounts: list with search, a detail view with the
 *           parent's children and bookings, and block / unblock.
 *           A blocked parent is logged out on the next page load.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

/* ------------------------------------------------------------------
 * POST: block or unblock
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $parent = db_one('SELECT * FROM parents WHERE id = ?', [int_param('parent_id', 'post')]);
    $new    = post('status');

    if ($parent && in_array($new, ['Active', 'Blocked'], true) && $parent['status'] !== $new) {
        db_exec('UPDATE parents SET status = ? WHERE id = ?', [$new, $parent['id']]);
        if ($new === 'Blocked') {
            $open = (int) db_value("SELECT COUNT(*) FROM appointments WHERE parent_id = ? AND request_status = 'Pending'", [$parent['id']]);
            flash('success', $parent['name'] . ' has been blocked and can no longer log in.'
                . ($open ? ' They still have ' . $open . ' request(s) waiting in Requests.' : ''));
        } else {
            notify('parent', (int) $parent['id'], 'Account unblocked', 'Your account is active again. Welcome back.', 'parent/index.php');
            flash('success', $parent['name'] . ' can log in again.');
        }
    }
    redirect_self();
}

$viewId = int_param('id');

/* ==================================================================
 * DETAIL VIEW
 * ================================================================== */
if ($viewId) {
    $parent = db_one('SELECT * FROM parents WHERE id = ?', [$viewId]);
    if (!$parent) {
        flash('danger', 'That parent was not found.');
        redirect('admin/parents.php');
    }
    $children  = db_all('SELECT * FROM children WHERE parent_id = ? ORDER BY date_of_birth DESC', [$viewId]);
    $schedules = build_schedules($children);
    $bookings  = db_all(
        'SELECT a.*, c.name AS child_name, h.name AS hospital_name FROM appointments a
           JOIN children c ON c.id = a.child_id JOIN hospitals h ON h.id = a.hospital_id
          WHERE a.parent_id = ? ORDER BY a.appointment_date DESC LIMIT 10',
        [$viewId]
    );
    $bookingVaccines = appointment_vaccine_map(array_column($bookings, 'id'));

    $pageTitle = $parent['name'];
    $activeNav = 'parents';
    require APP_ROOT . '/includes/layout/panel-header.php';
    ?>
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <a class="btn btn-light btn-sm btn-icon" href="<?= url('admin/parents.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> All parents</a>
        <form method="post" data-confirm="<?= $parent['status'] === 'Active' ? 'Block this parent? They will not be able to log in.' : 'Unblock this parent?' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="parent_id" value="<?= $viewId ?>">
            <input type="hidden" name="status" value="<?= $parent['status'] === 'Active' ? 'Blocked' : 'Active' ?>">
            <button class="btn btn-sm <?= $parent['status'] === 'Active' ? 'btn-outline-danger' : 'btn-primary' ?>" type="submit">
                <?= $parent['status'] === 'Active' ? 'Block account' : 'Unblock account' ?>
            </button>
        </form>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <section class="sheet">
                <div class="sheet-body d-flex gap-3 align-items-center">
                    <span class="avatar avatar-lg" aria-hidden="true"><?= e(initials($parent['name'])) ?></span>
                    <div>
                        <div class="fw-600"><?= e($parent['name']) ?></div>
                        <?= status_badge($parent['status']) ?>
                    </div>
                </div>
                <dl class="detail-list sheet-body border-top mb-0 detail-list-stack">
                    <dt>Email</dt><dd class="text-break"><?= e($parent['email']) ?></dd>
                    <dt>Phone</dt><dd><?= e($parent['phone']) ?></dd>
                    <dt>CNIC</dt><dd><?= e($parent['cnic'] ?: '-') ?></dd>
                    <dt>City</dt><dd><?= e($parent['city']) ?></dd>
                    <dt>Address</dt><dd><?= e($parent['address'] ?: '-') ?></dd>
                    <dt>Joined</dt><dd><?= e(fmt_date($parent['created_at'])) ?></dd>
                </dl>
            </section>
        </div>
        <div class="col-xl-8">
            <section class="sheet">
                <div class="sheet-head"><h2>Children (<?= count($children) ?>)</h2></div>
                <?php if (!$children): ?>
                    <?= empty_state('No children yet', 'This parent has not added a child.') ?>
                <?php else: ?>
                    <?php foreach ($children as $c): ?>
                        <?php $s = $schedules[$c['id']]['summary']; $percent = $s['total'] ? (int) round($s['given'] / $s['total'] * 100) : 0; ?>
                        <a class="child-tile" href="<?= url('admin/children.php?id=' . (int) $c['id']) ?>">
                            <span class="avatar <?= $c['gender'] === 'Female' ? 'avatar-girl' : 'avatar-boy' ?>" aria-hidden="true"><?= e(initials($c['name'])) ?></span>
                            <span class="flex-grow-1">
                                <span class="d-flex justify-content-between"><span class="fw-600"><?= e($c['name']) ?></span><span class="small-note"><?= e(child_age($c['date_of_birth'])) ?></span></span>
                                <span class="progress-rail d-block my-1"><span style="width: <?= $percent ?>%"></span></span>
                                <span class="small-note"><?= (int) $s['given'] ?>/<?= (int) $s['total'] ?> doses<?= $s['overdue'] ? ' &middot; <span class="text-danger fw-600">' . (int) $s['overdue'] . ' overdue</span>' : '' ?></span>
                            </span>
                            <i class="bi bi-chevron-right text-soft" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="sheet">
                <div class="sheet-head"><h2>Latest bookings</h2></div>
                <?php if (!$bookings): ?>
                    <?= empty_state('No bookings yet', 'Bookings by this parent will appear here.') ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td><a class="fw-600 ref" href="<?= url('admin/bookings.php?id=' . (int) $b['id']) ?>"><?= e(booking_ref((int) $b['id'])) ?></a></td>
                                        <td><?= e($b['child_name']) ?></td>
                                        <td><?= e($b['hospital_name']) ?><div class="cell-sub"><?= e(visit_when($b)) ?></div></td>
                                        <td><?= vaccine_chips($bookingVaccines[$b['id']] ?? [], true) ?></td>
                                        <td><?= booking_stage_badge($b) ?></td>
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
 * LIST VIEW
 * ================================================================== */
$q      = mb_substr(get('q'), 0, 80);
$status = get('status');
$city   = get('city');
$where  = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.cnic LIKE ?)';
    $params  = array_merge($params, array_fill(0, 4, like_param($q)));
}
if (in_array($status, ['Active', 'Blocked'], true)) {
    $where[]  = 'p.status = ?';
    $params[] = $status;
} else {
    $status = '';
}
if (in_array($city, city_list(), true)) {
    $where[]  = 'p.city = ?';
    $params[] = $city;
} else {
    $city = '';
}
$whereSql = implode(' AND ', $where);

$pager   = paginate((int) db_value("SELECT COUNT(*) FROM parents p WHERE $whereSql", $params));
$parents = db_all(
    "SELECT p.*,
            (SELECT COUNT(*) FROM children c WHERE c.parent_id = p.id) AS child_count,
            (SELECT COUNT(*) FROM appointments a WHERE a.parent_id = p.id) AS booking_count
       FROM parents p
      WHERE $whereSql
      ORDER BY p.created_at DESC
      LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per'], $pager['offset']])
);

$pageTitle = 'Parents';
$activeNav = 'parents';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<form class="filters mb-3" method="get" role="search">
    <div class="grow">
        <label class="form-label" for="q">Search</label>
        <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone or CNIC">
    </div>
    <div>
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status" data-autosubmit>
            <option value="">All</option>
            <option<?= selected('Active', $status) ?>>Active</option>
            <option<?= selected('Blocked', $status) ?>>Blocked</option>
        </select>
    </div>
    <div>
        <label class="form-label" for="city">City</label>
        <select class="form-select" id="city" name="city" data-autosubmit><?= city_options($city, 'All cities') ?></select>
    </div>
    <button class="btn btn-primary" type="submit">Search</button>
</form>

<div class="sheet">
    <div class="sheet-head"><h2><?= (int) $pager['total'] ?> parent account<?= $pager['total'] === 1 ? '' : 's' ?></h2></div>
    <?php if (!$parents): ?>
        <?= empty_state('No parents found', 'Try a different search.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th scope="col">Parent</th><th scope="col">Contact</th><th scope="col">City</th><th scope="col">Children</th><th scope="col">Bookings</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Open</span></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($parents as $p): ?>
                        <tr>
                            <td><a class="cell-title" href="<?= url('admin/parents.php?id=' . (int) $p['id']) ?>"><?= e($p['name']) ?></a><div class="cell-sub">joined <?= e(fmt_date($p['created_at'])) ?></div></td>
                            <td><?= e($p['phone']) ?><div class="cell-sub text-break"><?= e($p['email']) ?></div></td>
                            <td><?= e($p['city']) ?></td>
                            <td><?= (int) $p['child_count'] ?></td>
                            <td><?= (int) $p['booking_count'] ?></td>
                            <td><?= status_badge($p['status']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('admin/parents.php?id=' . (int) $p['id']) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php render_pagination($pager); ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
