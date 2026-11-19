<?php
/**
 * ---------------------------------------------------------------------
 * File    : hospitals.php
 * Purpose : Public directory of active hospitals. Visitors can search by
 *           name / area and filter by city, then book a visit
 *           (booking asks parents to log in first).
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

/* ------------------------------------------------------------------
 * Filters from the query string
 * ------------------------------------------------------------------ */
$q    = mb_substr(get('q'), 0, 80);
$city = get('city');
if ($city !== '' && !in_array($city, city_list(), true)) {
    $city = '';
}

$where  = ["status = 'Active'"];
$params = [];
if ($q !== '') {
    // One search box looks in name, address and location
    $where[]  = '(name LIKE ? OR address LIKE ? OR location LIKE ?)';
    $like     = like_param($q);
    $params   = array_merge($params, [$like, $like, $like]);
}
if ($city !== '') {
    $where[]  = 'city = ?';
    $params[] = $city;
}
$whereSql = implode(' AND ', $where);

$pager     = paginate((int) db_value("SELECT COUNT(*) FROM hospitals WHERE $whereSql", $params), 10);
$hospitals = db_all(
    "SELECT id, name, phone, city, address, location, timings FROM hospitals
      WHERE $whereSql ORDER BY city, name LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per'], $pager['offset']])
);

// Cities that actually have active hospitals (for the quick links)
$cityCounts = db_all("SELECT city, COUNT(*) AS total FROM hospitals WHERE status = 'Active' GROUP BY city ORDER BY total DESC, city");

/** A location may be a map link; show it as a link only when it is one. */
function location_html(?string $location): string
{
    if (empty($location)) {
        return '';
    }
    if (preg_match('#^https?://#i', $location)) {
        return '<a href="' . e($location) . '" target="_blank" rel="noopener">Open map</a>';
    }
    return e($location);
}

$pageTitle = 'Hospitals';
$activeNav = 'hospitals';
require __DIR__ . '/includes/layout/public-header.php';
?>

<section class="section pb-4">
    <div class="container">
        <h1>Vaccination hospitals</h1>
        <p class="text-soft">Every hospital below is approved by the administrator and accepts bookings through <?= e(APP_NAME) ?>.</p>

        <!-- Search form -->
        <form class="filters mt-3" method="get" role="search">
            <div class="grow">
                <label class="form-label" for="q">Hospital name or area</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="e.g. Gulshan, Clifton, children's">
            </div>
            <div>
                <label class="form-label" for="city">City</label>
                <select class="form-select" id="city" name="city" data-autosubmit>
                    <?= city_options($city, 'All cities') ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button>
            <?php if ($q !== '' || $city !== ''): ?>
                <a class="btn btn-light" href="<?= url('hospitals.php') ?>">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Quick city links -->
        <?php if ($cityCounts): ?>
            <p class="small-note mt-3 mb-0">
                Hospitals by city:
                <?php foreach ($cityCounts as $i => $c): ?>
                    <?= $i ? '&middot;' : '' ?>
                    <a href="<?= e(url('hospitals.php?city=' . urlencode($c['city']))) ?>"><?= e($c['city']) ?> (<?= (int) $c['total'] ?>)</a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="sheet">
            <div class="sheet-head">
                <h2><?= (int) $pager['total'] ?> hospital<?= $pager['total'] === 1 ? '' : 's' ?> found</h2>
            </div>

            <?php if (!$hospitals): ?>
                <?= empty_state('No hospitals match your search', 'Try another area or choose "All cities".', url('hospitals.php'), 'Show all hospitals') ?>
            <?php else: ?>
                <?php foreach ($hospitals as $h): ?>
                    <article class="hospital-row">
                        <div>
                            <h3><?= e($h['name']) ?></h3>
                            <div class="hospital-meta">
                                <span><i class="bi bi-geo-alt" aria-hidden="true"></i><?= e($h['address']) ?>, <?= e($h['city']) ?></span>
                                <?php if ($h['location']): ?>
                                    <span><i class="bi bi-signpost" aria-hidden="true"></i><?= location_html($h['location']) ?></span>
                                <?php endif; ?>
                                <span><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $h['phone'])) ?>"><?= e($h['phone']) ?></a></span>
                                <?php if ($h['timings']): ?>
                                    <span><i class="bi bi-clock" aria-hidden="true"></i><?= e($h['timings']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a class="btn btn-primary" href="<?= e(url('parent/book.php?hospital=' . (int) $h['id'])) ?>">
                            Book a visit<span class="visually-hidden"> at <?= e($h['name']) ?></span>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php render_pagination($pager); ?>

        <p class="mt-4 mb-0">
            Can't find your hospital?
            <a href="<?= e(url('contact.php?subject=' . urlencode('Hospital suggestion'))) ?>">Tell us which one to add</a>,
            or ask the hospital to <a href="<?= url('hospital-register.php') ?>">register here</a>.
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/public-footer.php'; ?>
