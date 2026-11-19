<?php
/**
 * ---------------------------------------------------------------------
 * File    : schedule.php
 * Purpose : Public routine vaccination schedule (grouped by visit) with
 *           a due-date calculator: enter a date of birth and every
 *           visit's date is shown. Vaccine availability is live.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

/* ------------------------------------------------------------------
 * Optional date of birth from the calculator form (GET)
 * ------------------------------------------------------------------ */
$dob      = get('dob');
$dobError = '';
if ($dob !== '') {
    if (!valid_date($dob)) {
        $dobError = 'Please enter a valid date.';
    } elseif ($dob > today()) {
        $dobError = 'The date of birth cannot be in the future.';
    } elseif ($dob < add_days(today(), -365 * MAX_CHILD_AGE_YEARS)) {
        $dobError = 'This schedule is for children up to ' . MAX_CHILD_AGE_YEARS . ' years old.';
    }
}
$hasDob = $dob !== '' && $dobError === '';

/* ------------------------------------------------------------------
 * Group the catalogue into visits (same recommended age = one visit)
 * ------------------------------------------------------------------ */
$visits = [];
foreach (vaccine_catalog() as $v) {
    $age = (int) $v['age_days'];
    if (!isset($visits[$age])) {
        $visits[$age] = ['label' => $v['age_label'], 'vaccines' => []];
    }
    $visits[$age]['vaccines'][] = $v;
}

// With a date of birth: find the next visit that is today or later
$nextAge = null;
if ($hasDob) {
    foreach (array_keys($visits) as $age) {
        if (add_days($dob, $age) >= today()) {
            $nextAge = $age;
            break;
        }
    }
}

$pageTitle = 'Vaccine schedule';
$activeNav = 'schedule';
require __DIR__ . '/includes/layout/public-header.php';
?>

<section class="section pb-4">
    <div class="container">
        <div class="row g-4 align-items-end">
            <div class="col-lg-7">
                <h1>Routine childhood vaccination schedule</h1>
                <p class="text-soft mb-0">
                    <?= count($visits) ?> visits between birth and 15 months, modelled on Pakistan's
                    Expanded Programme on Immunization (EPI). Vaccines given at the same age are given in one visit.
                </p>
            </div>

            <!-- Due date calculator -->
            <div class="col-lg-5">
                <form class="sheet sheet-body" method="get" novalidate>
                    <label class="form-label" for="dob">Work out dates from a date of birth</label>
                    <div class="d-flex gap-2">
                        <input class="form-control<?= $dobError ? ' is-invalid' : '' ?>" type="date" id="dob" name="dob"
                               value="<?= e($dob) ?>" max="<?= today() ?>" required>
                        <button class="btn btn-primary text-nowrap" type="submit">Show dates</button>
                    </div>
                    <?php if ($dobError): ?>
                        <div class="text-danger small mt-1"><?= e($dobError) ?></div>
                    <?php elseif ($hasDob): ?>
                        <div class="small-note mt-1">
                            Child's age today: <strong><?= e(child_age($dob)) ?></strong>.
                            <a href="<?= url('schedule.php') ?>">Clear</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="sheet">
            <div class="table-responsive">
                <table class="table schedule-table">
                    <caption class="visually-hidden">Vaccines by age<?= $hasDob ? ' with due dates' : '' ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Visit</th>
                            <th scope="col">Vaccines</th>
                            <?php if ($hasDob): ?><th scope="col">Due date</th><?php endif; ?>
                            <th scope="col">Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $age => $visit): ?>
                            <?php
                            $unavailable = array_filter($visit['vaccines'], function ($v) { return $v['status'] === 'Unavailable'; });
                            $due         = $hasDob ? add_days($dob, $age) : null;
                            ?>
                            <tr<?= $age === $nextAge ? ' class="table-row-next"' : '' ?>>
                                <th scope="row" class="visit-cell">
                                    <?= e($visit['label']) ?>
                                    <?php if ($age === $nextAge): ?><span class="d-block"><span class="state-chip state-due">Next visit</span></span><?php endif; ?>
                                </th>
                                <td>
                                    <?php foreach ($visit['vaccines'] as $v): ?>
                                        <div class="vaccine-line">
                                            <span class="dose-cell"><?= e($v['short_name']) ?></span>
                                            <span><strong><?= e($v['name']) ?></strong>
                                                <span class="text-soft">&middot; <?= e($v['protects_against']) ?></span>
                                                <?php if ($v['route']): ?><span class="text-soft">&middot; <?= e($v['route']) ?></span><?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <?php if ($hasDob): ?>
                                    <td class="text-nowrap">
                                        <strong><?= e(fmt_date($due)) ?></strong>
                                        <span class="d-block small-note">
                                            <?= $due < today() ? 'was ' . e(relative_days($due)) : e(relative_days($due)) ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php if (!$unavailable): ?>
                                        <?= status_badge('Available') ?>
                                    <?php else: ?>
                                        <span class="pill pill-amber">
                                            <?= e(implode(', ', array_column($unavailable, 'short_name'))) ?> out of stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="sheet-foot small-note">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                This schedule is a guide. If a dose was missed, it can still be given later; your doctor will advise the right catch-up plan.
            </div>
        </div>

        <!-- Next step -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
            <p class="mb-0">Want reminders and a card that updates itself?</p>
            <div class="d-flex gap-2">
                <?php if (is_logged_in() && current_role() === 'parent'): ?>
                    <a class="btn btn-primary" href="<?= url('parent/children.php') ?>">Go to my children</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?= url('register.php') ?>">Register as parent</a>
                <?php endif; ?>
                <a class="btn btn-light" href="<?= url('hospitals.php') ?>">Find a hospital</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/public-footer.php'; ?>
