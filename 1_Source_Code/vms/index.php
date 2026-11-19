<?php
/**
 * ---------------------------------------------------------------------
 * TeekaCard - Vaccination Management System
 * File    : index.php
 * Purpose : Public home page. Explains the system and shows a sample
 *           vaccination card built from the real vaccine catalogue.
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';

/* ------------------------------------------------------------------
 * Live numbers for the hero
 * ------------------------------------------------------------------ */
$catalog        = vaccine_catalog();
$hospitalCount  = (int) db_value("SELECT COUNT(*) FROM hospitals WHERE status = 'Active'");
$dosesRecorded  = (int) db_value("SELECT COUNT(*) FROM appointment_vaccines WHERE status = 'Vaccinated'")
                + (int) db_value('SELECT COUNT(*) FROM vaccination_history');

/* ------------------------------------------------------------------
 * Sample card: a baby who is exactly 14 weeks old today.
 * Visits are grouped from the catalogue (same age = same visit).
 * ------------------------------------------------------------------ */
$sampleDob = add_days(today(), -98);
$visits    = [];
foreach ($catalog as $v) {
    $visits[(int) $v['age_days']]['label']   = $v['age_label'];
    $visits[(int) $v['age_days']]['doses'][] = $v['short_name'];
}
$visits = array_slice($visits, 0, 5, true);   // first five visits fit the card

$pageTitle = 'Every dose, on time';
$activeNav = 'home';
require __DIR__ . '/includes/layout/public-header.php';
?>

<!-- ================= Hero ================= -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6">
                <h1>Every dose, on time.</h1>
                <p class="hero-lead">
                    <?= e(APP_NAME) ?> turns your child's paper vaccination card into a live schedule.
                    See every due date, book a nearby hospital, and have each dose stamped on the card the day it is given.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-primary btn-lg" href="<?= url('register.php') ?>">Register as parent</a>
                    <a class="btn btn-light btn-lg" href="<?= url('schedule.php') ?>">See the schedule</a>
                </div>

                <div class="hero-facts">
                    <div><strong><?= count($catalog) ?></strong> vaccines tracked from birth to 15 months</div>
                    <div><strong><?= $hospitalCount ?></strong> hospitals taking bookings</div>
                    <div><strong><?= $dosesRecorded ?></strong> doses recorded so far</div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Sample vaccination card: stamps "press" onto the first three visits -->
                <figure class="epi-card mb-0" aria-label="Example of a digital vaccination card">
                    <div class="epi-card__head">
                        <div>
                            <span class="epi-card__title">Child vaccination card</span>
                            <span class="epi-card__child">Sample: Hania, 14 weeks old</span>
                        </div>
                        <span class="small">Born <?= e(fmt_date($sampleDob)) ?></span>
                    </div>
                    <ol class="epi-card__rows">
                        <?php $i = 0; foreach ($visits as $ageDays => $visit): $i++; ?>
                            <?php $due = add_days($sampleDob, $ageDays); ?>
                            <li class="epi-card__row">
                                <div class="epi-card__age">
                                    <?= e($visit['label']) ?>
                                    <small><?= e(fmt_date($due, 'd M')) ?></small>
                                </div>
                                <div class="dose-cells">
                                    <?php foreach ($visit['doses'] as $short): ?>
                                        <span class="dose-cell"><?= e($short) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="epi-card__mark">
                                    <?php if ($i <= 3): ?>
                                        <span class="stamp stamp-sm"><b>GIVEN</b><small><?= e(strtoupper(fmt_date($due, 'd M'))) ?></small></span>
                                    <?php elseif ($i === 4): ?>
                                        <span class="due-chip">Due<br>today</span>
                                    <?php else: ?>
                                        <span class="date-chip">in <?= days_between(today(), $due) ?><br>days</span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <figcaption class="visually-hidden">Three visits are stamped as given, the 14-week visit is due today.</figcaption>
                </figure>
            </div>
        </div>
    </div>
</section>

<!-- ================= How it works ================= -->
<section class="section section-alt" aria-labelledby="how-title">
    <div class="container">
        <div class="section-head">
            <h2 id="how-title">From birth certificate to a stamped card</h2>
            <p class="text-soft mb-0">Five steps, and the system remembers every date for you.</p>
        </div>
        <ol class="steps">
            <li>
                <h3>Register</h3>
                <p>Create a free parent account with your phone number and city.</p>
            </li>
            <li>
                <h3>Add your child</h3>
                <p>Enter the date of birth. Every due date is worked out instantly.</p>
            </li>
            <li>
                <h3>Watch the dates</h3>
                <p>Your dashboard shows what is due soon and what was missed.</p>
            </li>
            <li>
                <h3>Book a hospital</h3>
                <p>Pick a nearby hospital and a time. The administrator confirms it.</p>
            </li>
            <li>
                <h3>Get it stamped</h3>
                <p>The hospital marks each dose as given and your card updates.</p>
            </li>
        </ol>
    </div>
</section>

<!-- ================= Due date check ================= -->
<section class="section" aria-labelledby="check-title">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <h2 id="check-title">When is your child's next vaccine?</h2>
                <p class="text-soft">Enter a date of birth to see all <?= count(array_unique(array_column($catalog, 'age_days'))) ?> visits
                    of the routine schedule with their dates. No account needed.</p>
            </div>
            <div class="col-lg-6">
                <form class="sheet sheet-body d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= url('schedule.php') ?>">
                    <div class="flex-grow-1">
                        <label class="form-label" for="dob">Date of birth</label>
                        <input class="form-control" type="date" id="dob" name="dob" required
                               max="<?= today() ?>" min="<?= e(add_days(today(), -365 * MAX_CHILD_AGE_YEARS)) ?>">
                    </div>
                    <button class="btn btn-primary" type="submit">Show due dates</button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ================= Three roles ================= -->
<section class="section section-alt" aria-labelledby="roles-title">
    <div class="container">
        <div class="section-head">
            <h2 id="roles-title">One record, three desks</h2>
            <p class="text-soft mb-0">Parents, hospitals and the administrator each see exactly what they need.</p>
        </div>
        <div class="role-columns">
            <div>
                <h3><i class="bi bi-people me-2 text-pine" aria-hidden="true"></i>Parents</h3>
                <ul>
                    <li>Add each child and see their vaccination card</li>
                    <li>Reminders for due and missed doses</li>
                    <li>Search hospitals and request a visit</li>
                    <li>Print a vaccination report at any time</li>
                </ul>
                <a class="d-inline-block mt-3" href="<?= url('register.php') ?>">Create a parent account</a>
            </div>
            <div>
                <h3><i class="bi bi-hospital me-2 text-pine" aria-hidden="true"></i>Hospitals</h3>
                <ul>
                    <li>A daily list of confirmed appointments</li>
                    <li>Mark each dose as given or not given</li>
                    <li>Parents are updated automatically</li>
                    <li>Keep your address and timings current</li>
                </ul>
                <a class="d-inline-block mt-3" href="<?= url('hospital-register.php') ?>">Register a hospital</a>
            </div>
            <div>
                <h3><i class="bi bi-shield-check me-2 text-pine" aria-hidden="true"></i>Administrator</h3>
                <ul>
                    <li>Approve or reject booking requests</li>
                    <li>Manage hospitals and vaccine stock</li>
                    <li>See every child's upcoming dates</li>
                    <li>Date-wise vaccination reports</li>
                </ul>
                <a class="d-inline-block mt-3" href="<?= url('login.php?role=admin') ?>">Administrator login</a>
            </div>
        </div>
    </div>
</section>

<!-- ================= Hospital call to action ================= -->
<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2 class="mb-1">Run a hospital or clinic?</h2>
                <p class="mb-0">Join the network and receive confirmed vaccination appointments from parents in your city.</p>
            </div>
            <a class="btn btn-light btn-lg" href="<?= url('hospital-register.php') ?>">Register your hospital</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout/public-footer.php'; ?>
