<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/children.php
 * Purpose : Admin module - "All Child Details".
 *           List of every registered child with search and filters
 *           (vaccination state, gender, city). ?id=<id> opens the
 *           child's vaccination card with parent details and bookings.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$viewId = int_param('id');

/* ==================================================================
 * DETAIL VIEW
 * ================================================================== */
if ($viewId) {
    $child = db_one('SELECT * FROM children WHERE id = ?', [$viewId]);
    if (!$child) {
        flash('danger', 'That child was not found.');
        redirect('admin/children.php');
    }
    $parent   = db_one('SELECT * FROM parents WHERE id = ?', [$child['parent_id']]);
    $card     = child_schedule($child);
    $bookings = db_all(
        'SELECT a.*, h.name AS hospital_name FROM appointments a JOIN hospitals h ON h.id = a.hospital_id
          WHERE a.child_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC',
        [$viewId]
    );
    $bookingVaccines = appointment_vaccine_map(array_column($bookings, 'id'));

    $pageTitle = $child['name'];
    $activeNav = 'children';
    require APP_ROOT . '/includes/layout/panel-header.php';
    ?>
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3 no-print">
        <a class="btn btn-light btn-sm btn-icon" href="<?= url('admin/children.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> All children</a>
        <button class="btn btn-light btn-sm btn-icon" type="button" data-print><i class="bi bi-printer" aria-hidden="true"></i> Print card</button>
    </div>

    <div class="row g-4">
        <div class="col-xxl-9">
            <?php
            $cardBookUrl = '';   // the admin views the card read-only
            require APP_ROOT . '/includes/partials/vaccination-card.php';
            ?>
        </div>
        <div class="col-xxl-3">
            <div class="sheet">
                <div class="sheet-head"><h2>Parent</h2></div>
                <dl class="detail-list sheet-body mb-0 detail-list-stack">
                    <dt>Name</dt><dd><a href="<?= url('admin/parents.php?id=' . (int) $parent['id']) ?>"><?= e($parent['name']) ?></a> <?= $parent['status'] !== 'Active' ? status_badge($parent['status']) : '' ?></dd>
                    <dt>Phone</dt><dd><?= e($parent['phone']) ?></dd>
                    <dt>Email</dt><dd class="text-break"><?= e($parent['email']) ?></dd>
                    <dt>City</dt><dd><?= e($parent['city']) ?></dd>
                    <?php if ($parent['address']): ?><dt>Address</dt><dd><?= e($parent['address']) ?></dd><?php endif; ?>
                    <dt>Child added</dt><dd><?= e(fmt_date($child['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <section class="sheet mt-4" aria-labelledby="bk-title">
        <div class="sheet-head"><h2 id="bk-title">Booking history</h2></div>
        <?php if (!$bookings): ?>
            <?= empty_state('No bookings yet', 'This child has not been booked at any hospital.') ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th scope="col">Booking</th><th scope="col">Visit</th><th scope="col">Hospital</th><th scope="col">Vaccines</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td><a class="fw-600 ref" href="<?= url('admin/bookings.php?id=' . (int) $b['id']) ?>"><?= e(booking_ref((int) $b['id'])) ?></a></td>
                                <td class="text-nowrap"><?= e(visit_when($b)) ?></td>
                                <td><?= e($b['hospital_name']) ?></td>
                                <td><?= vaccine_chips($bookingVaccines[$b['id']] ?? [], true) ?></td>
                                <td><?= booking_stage_badge($b) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php
    require APP_ROOT . '/includes/layout/panel-footer.php';
    exit;
}

/* ==================================================================
 * LIST VIEW
 * ================================================================== */
$q      = mb_substr(get('q'), 0, 80);
$gender = get('gender');
$city   = get('city');
$state  = get('state');

$where  = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[]  = '(c.name LIKE ? OR p.name LIKE ? OR p.phone LIKE ?)';
    $like     = like_param($q);
    $params   = array_merge($params, [$like, $like, $like]);
}
if (in_array($gender, ['Male', 'Female'], true)) {
    $where[]  = 'c.gender = ?';
    $params[] = $gender;
} else {
    $gender = '';
}
if (in_array($city, city_list(), true)) {
    $where[]  = 'p.city = ?';
    $params[] = $city;
} else {
    $city = '';
}

$children = db_all(
    'SELECT c.*, p.name AS parent_name, p.phone AS parent_phone, p.city AS parent_city
       FROM children c JOIN parents p ON p.id = c.parent_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY c.name',
    $params
);
$schedules = build_schedules($children);

// Vaccination state filter works on the computed schedule
$states = [
    'overdue'  => 'Has overdue doses',
    'due'      => 'Due within ' . DUE_SOON_DAYS . ' days',
    'booked'   => 'Has booked doses',
    'ontrack'  => 'On track (nothing overdue)',
    'complete' => 'Schedule complete',
];
if (isset($states[$state])) {
    $children = array_values(array_filter($children, function ($c) use ($schedules, $state) {
        $s = $schedules[$c['id']]['summary'];
        switch ($state) {
            case 'overdue':
                return $s['overdue'] > 0;
            case 'due':
                return $s['due'] > 0;
            case 'booked':
                return $s['booked'] > 0;
            case 'ontrack':
                return $s['overdue'] === 0 && $s['next'] !== null;
            default:
                return $s['next'] === null;
        }
    }));
} else {
    $state = '';
}

// Paginate the filtered list in PHP
$pager = paginate(count($children));
$page  = array_slice($children, $pager['offset'], $pager['per']);

$pageTitle = 'Children';
$activeNav = 'children';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<form class="filters mb-3" method="get" role="search">
    <div class="grow">
        <label class="form-label" for="q">Search</label>
        <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Child, parent or phone">
    </div>
    <div>
        <label class="form-label" for="state">Vaccination</label>
        <select class="form-select" id="state" name="state" data-autosubmit>
            <option value="">Any state</option>
            <?php foreach ($states as $key => $label): ?>
                <option value="<?= $key ?>"<?= selected($key, $state) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label" for="gender">Gender</label>
        <select class="form-select" id="gender" name="gender" data-autosubmit>
            <option value="">All</option>
            <option<?= selected('Female', $gender) ?>>Female</option>
            <option<?= selected('Male', $gender) ?>>Male</option>
        </select>
    </div>
    <div>
        <label class="form-label" for="city">City</label>
        <select class="form-select" id="city" name="city" data-autosubmit><?= city_options($city, 'All cities') ?></select>
    </div>
    <button class="btn btn-primary" type="submit">Search</button>
    <?php if ($q !== '' || $state !== '' || $gender !== '' || $city !== ''): ?>
        <a class="btn btn-light" href="<?= url('admin/children.php') ?>">Clear</a>
    <?php endif; ?>
</form>

<div class="sheet">
    <div class="sheet-head">
        <h2><?= (int) $pager['total'] ?> child<?= $pager['total'] === 1 ? '' : 'ren' ?></h2>
        <a class="small" href="<?= url('admin/due.php') ?>">Upcoming vaccination dates</a>
    </div>
    <?php if (!$page): ?>
        <?= empty_state('No children found', 'Try a different search or filter.') ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th scope="col">Child</th>
                        <th scope="col">Parent</th>
                        <th scope="col">Born</th>
                        <th scope="col">Progress</th>
                        <th scope="col">Next vaccine</th>
                        <th scope="col"><span class="visually-hidden">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($page as $c): ?>
                        <?php
                        $s       = $schedules[$c['id']]['summary'];
                        $percent = $s['total'] ? (int) round($s['given'] / $s['total'] * 100) : 0;
                        $next    = $s['next'];
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar <?= $c['gender'] === 'Female' ? 'avatar-girl' : 'avatar-boy' ?>" aria-hidden="true"><?= e(initials($c['name'])) ?></span>
                                    <div>
                                        <a class="cell-title" href="<?= url('admin/children.php?id=' . (int) $c['id']) ?>"><?= e($c['name']) ?></a>
                                        <div class="cell-sub"><?= e($c['gender']) ?><?= $c['blood_group'] ? ', ' . e($c['blood_group']) : '' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($c['parent_name']) ?><div class="cell-sub"><?= e($c['parent_phone']) ?> &middot; <?= e($c['parent_city']) ?></div></td>
                            <td class="text-nowrap"><?= e(fmt_date($c['date_of_birth'])) ?><div class="cell-sub"><?= e(child_age($c['date_of_birth'])) ?></div></td>
                            <td style="min-width: 150px">
                                <div class="small mb-1"><?= (int) $s['given'] ?>/<?= (int) $s['total'] ?> doses</div>
                                <div class="progress-rail"><span style="width: <?= $percent ?>%"></span></div>
                                <?php if ($s['overdue']): ?><div class="small text-danger mt-1"><?= (int) $s['overdue'] ?> overdue</div><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$next): ?>
                                    <span class="stamp-line">COMPLETE</span>
                                <?php else: ?>
                                    <?php [, $cls] = state_meta($next['state']['key']); ?>
                                    <div class="fw-600"><?= e($next['vaccine']['age_label']) ?> &middot; <?= e(fmt_date($next['due_date'])) ?></div>
                                    <span class="state-chip <?= $cls ?>"><?= e($next['state']['key'] === 'booked' ? $next['state']['label'] : due_phrase($next['due_date'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><a class="btn btn-sm btn-light" href="<?= url('admin/children.php?id=' . (int) $c['id']) ?>">View card</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php render_pagination($pager); ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
