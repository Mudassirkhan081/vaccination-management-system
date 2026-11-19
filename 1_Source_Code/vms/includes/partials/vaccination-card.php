<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/partials/vaccination-card.php
 * Purpose : Prints a child's digital vaccination card:
 *           one row per visit (recommended age), one cell per dose,
 *           and a violet "GIVEN" stamp once the whole visit is complete.
 *           Used by the parent panel and the admin panel.
 * Expects : $card        array returned by child_schedule()
 *           $cardBookUrl string (optional). When set, "Book" buttons are
 *                        shown and "&visit=<age in days>" is appended.
 * ---------------------------------------------------------------------
 */
$cardBookUrl = $cardBookUrl ?? '';
$cardChild   = $card['child'];
$cardSummary = $card['summary'];
$cardPercent = $cardSummary['total'] ? (int) round($cardSummary['given'] / $cardSummary['total'] * 100) : 0;
$bookLimit   = add_days(today(), MAX_BOOKING_DAYS);
?>
<section class="sheet vaccination-card" aria-label="Vaccination card of <?= e($cardChild['name']) ?>">

    <!-- Child details and overall progress -->
    <div class="sheet-body">
        <div class="child-head">
            <span class="avatar avatar-lg <?= $cardChild['gender'] === 'Female' ? 'avatar-girl' : 'avatar-boy' ?>" aria-hidden="true">
                <?= e(initials($cardChild['name'])) ?>
            </span>
            <div class="flex-grow-1">
                <h2 class="h3 mb-1"><?= e($cardChild['name']) ?></h2>
                <div class="child-facts">
                    <span><b>Age</b> <?= e(child_age($cardChild['date_of_birth'])) ?></span>
                    <span><b>Born</b> <?= e(fmt_date($cardChild['date_of_birth'])) ?></span>
                    <span><b>Gender</b> <?= e($cardChild['gender']) ?></span>
                    <span><b>Blood group</b> <?= e($cardChild['blood_group'] ?: 'Not recorded') ?></span>
                    <?php if (!empty($cardChild['birth_weight'])): ?>
                        <span><b>Birth weight</b> <?= e((float) $cardChild['birth_weight']) ?> kg</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($cardChild['notes'])): ?>
                    <p class="small-note mb-0 mt-1"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= e($cardChild['notes']) ?></p>
                <?php endif; ?>
            </div>
            <div class="card-progress">
                <div class="d-flex justify-content-between gap-3 small mb-1">
                    <strong><?= (int) $cardSummary['given'] ?> of <?= (int) $cardSummary['total'] ?> doses given</strong>
                    <span class="text-soft"><?= $cardPercent ?>%</span>
                </div>
                <div class="progress-rail" role="progressbar" aria-label="Doses given"
                     aria-valuenow="<?= $cardPercent ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: <?= $cardPercent ?>%"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- One row per visit -->
    <div class="border-top">
        <?php foreach ($card['visits'] as $visit): ?>
            <?php
            // Text for the visit's combined state
            [, $visitClass] = state_meta($visit['state']);
            switch ($visit['state']) {
                case 'given':
                    $visitLabel = 'Complete';
                    break;
                case 'booked':
                    $visitLabel = ($visit['booking']['status'] ?? '') === 'Approved' ? 'Booked' : 'Requested';
                    break;
                case 'overdue':
                    $visitLabel = 'Overdue';
                    break;
                case 'due':
                    $visitLabel = due_phrase($visit['due_date']);
                    break;
                default:
                    $visitLabel = 'Upcoming';
            }
            ?>
            <div class="card-visit">
                <!-- Column 1: age and due date -->
                <div>
                    <div class="visit-age"><?= e($visit['label']) ?></div>
                    <div class="visit-date">Due <?= e(fmt_date($visit['due_date'])) ?></div>
                    <span class="state-chip <?= $visitClass ?>"><?= e($visitLabel) ?></span>
                </div>

                <!-- Column 2: every dose of this visit -->
                <div class="visit-doses">
                    <?php foreach ($visit['doses'] as $dose): ?>
                        <?php
                        $v        = $dose['vaccine'];
                        $key      = $dose['state']['key'];
                        [, $doseClass] = state_meta($key);
                        ?>
                        <div class="dose <?= $doseClass ?>">
                            <div class="dose-top">
                                <span class="dose-name"><?= e($v['short_name']) ?></span>
                                <span class="state-chip <?= $doseClass ?>"><?= e($dose['state']['label']) ?></span>
                            </div>
                            <div class="dose-meta">
                                <?php if ($v['name'] !== $v['short_name']): ?><?= e($v['name']) ?><br><?php endif; ?>
                                <?php if ($key === 'given'): ?>
                                    Given <?= e(fmt_date($dose['given']['date'])) ?>
                                    <?php if (!empty($dose['given']['place'])): ?>at <?= e($dose['given']['place']) ?><?php endif; ?>
                                    <?php if ($dose['given']['source'] === 'parent'): ?><em>(from paper card)</em><?php endif; ?>
                                <?php elseif ($key === 'booked'): ?>
                                    <?= $dose['booked']['status'] === 'Approved' ? 'Booked' : 'Requested' ?> for
                                    <?= e(fmt_date($dose['booked']['date'], 'd M')) ?>, <?= e(fmt_time($dose['booked']['time'])) ?>
                                <?php elseif ($key === 'overdue'): ?>
                                    Was due <?= e(fmt_date($dose['due_date'])) ?>
                                <?php else: ?>
                                    Due <?= e(fmt_date($dose['due_date'])) ?>
                                <?php endif; ?>
                                <?php if (!empty($dose['missed'])): ?>
                                    <span class="d-block text-danger">Not given on <?= e(fmt_date($dose['missed']['date'], 'd M')) ?> at <?= e($dose['missed']['place']) ?></span>
                                <?php endif; ?>
                                <?php if ($key !== 'given' && $v['status'] === 'Unavailable'): ?>
                                    <span class="d-block text-warning-emphasis fw-600">Out of stock right now</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Column 3: stamp, booking mark or book button -->
                <div class="visit-mark">
                    <?php if ($visit['state'] === 'given'): ?>
                        <span class="stamp" title="All doses of this visit were given">
                            <b>GIVEN</b><small><?= e(strtoupper(fmt_date($visit['given_on'], 'd M y'))) ?></small>
                        </span>
                    <?php elseif ($visit['state'] === 'booked'): ?>
                        <span class="text-center">
                            <span class="stamp-line"><?= ($visit['booking']['status'] ?? '') === 'Approved' ? 'BOOKED' : 'REQUESTED' ?></span>
                            <span class="d-block small-note mt-1"><?= e(fmt_date($visit['booking']['date'] ?? '', 'd M')) ?></span>
                        </span>
                    <?php elseif ($cardBookUrl !== '' && in_array($visit['state'], ['overdue', 'due'], true)): ?>
                        <a class="btn btn-sm btn-primary" href="<?= e($cardBookUrl . '&visit=' . (int) $visit['doses'][0]['vaccine']['age_days']) ?>">Book</a>
                    <?php elseif ($cardBookUrl !== '' && $visit['due_date'] <= $bookLimit): ?>
                        <a class="btn btn-sm btn-light" href="<?= e($cardBookUrl . '&visit=' . (int) $visit['doses'][0]['vaccine']['age_days']) ?>">Book</a>
                    <?php else: ?>
                        <span class="text-soft" aria-hidden="true">&ndash;</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Colour legend -->
    <div class="sheet-foot legend">
        <span><span class="state-chip state-given">Given</span> dose recorded</span>
        <span><span class="state-chip state-booked">Booked</span> visit requested or approved</span>
        <span><span class="state-chip state-due">Due soon</span> within <?= (int) DUE_SOON_DAYS ?> days</span>
        <span><span class="state-chip state-overdue">Overdue</span> date has passed</span>
        <span><span class="state-chip state-upcoming">Upcoming</span> later</span>
    </div>
</section>
