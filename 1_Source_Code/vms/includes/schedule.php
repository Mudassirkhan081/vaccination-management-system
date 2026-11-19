<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/schedule.php
 * Purpose : The vaccination schedule engine.
 *
 * ALGORITHM (for every child):
 *   1. Load the vaccine catalogue ordered by recommended age.
 *   2. Due date of a dose = child's date of birth + vaccine.age_days.
 *   3. A dose is GIVEN   if a hospital marked it "Vaccinated" or the
 *                        parent recorded it from the old paper card.
 *      A dose is BOOKED  if it is inside a Pending/Approved appointment
 *                        that has not been completed yet.
 *      Otherwise compare the due date with today:
 *        due date < today              -> OVERDUE
 *        due date <= today + 14 days   -> DUE (soon)
 *        later                         -> UPCOMING
 *   4. Doses with the same recommended age form one "visit"
 *      (e.g. the 6-week visit = OPV-1, Penta-1, PCV-1, Rota-1).
 *   5. A dose the hospital could not give ("Not Vaccinated") stays open,
 *      so it can be booked again; the card shows when it was missed.
 * ---------------------------------------------------------------------
 */

/** All vaccines, ordered by recommended age (loaded once per request). */
function vaccine_catalog(): array
{
    static $catalog = null;
    if ($catalog === null) {
        $catalog = db_all('SELECT * FROM vaccines ORDER BY age_days, id');
    }
    return $catalog;
}

/**
 * Loads given, booked and missed doses for many children in 4 queries.
 * Result: [childId => ['given' => [vaccineId => info], 'booked' => [...], 'missed' => [...]]]
 */
function dose_records(array $childIds): array
{
    $records = [];
    foreach ($childIds as $id) {
        $records[$id] = ['given' => [], 'booked' => [], 'missed' => []];
    }
    if (!$childIds) {
        return $records;
    }
    $in = db_placeholders(count($childIds));

    // 1) Doses confirmed by a hospital
    $rows = db_all(
        "SELECT a.child_id, av.vaccine_id, av.given_at, a.id AS appointment_id, h.name AS hospital_name
           FROM appointment_vaccines av
           JOIN appointments a ON a.id = av.appointment_id
           JOIN hospitals h    ON h.id = a.hospital_id
          WHERE av.status = 'Vaccinated' AND a.child_id IN ($in)
          ORDER BY av.given_at",
        $childIds
    );
    foreach ($rows as $r) {
        $records[$r['child_id']]['given'][$r['vaccine_id']] = [
            'date'           => substr((string) $r['given_at'], 0, 10),
            'place'          => $r['hospital_name'],
            'source'         => 'hospital',
            'appointment_id' => (int) $r['appointment_id'],
        ];
    }

    // 2) Doses recorded by the parent (given before joining TeekaCard)
    $rows = db_all("SELECT id, child_id, vaccine_id, given_on, place FROM vaccination_history WHERE child_id IN ($in)", $childIds);
    foreach ($rows as $r) {
        if (!isset($records[$r['child_id']]['given'][$r['vaccine_id']])) {
            $records[$r['child_id']]['given'][$r['vaccine_id']] = [
                'date'       => $r['given_on'],
                'place'      => $r['place'],
                'source'     => 'parent',
                'history_id' => (int) $r['id'],
            ];
        }
    }

    // 3) Doses inside an open booking (waiting for admin or approved)
    $rows = db_all(
        "SELECT a.child_id, av.vaccine_id, a.id AS appointment_id, a.appointment_date, a.appointment_time,
                a.request_status, h.name AS hospital_name
           FROM appointment_vaccines av
           JOIN appointments a ON a.id = av.appointment_id
           JOIN hospitals h    ON h.id = a.hospital_id
          WHERE a.child_id IN ($in)
            AND a.request_status IN ('Pending', 'Approved')
            AND a.vaccination_status = 'Pending'
            AND av.status = 'Pending'",
        $childIds
    );
    foreach ($rows as $r) {
        $records[$r['child_id']]['booked'][$r['vaccine_id']] = [
            'appointment_id' => (int) $r['appointment_id'],
            'date'           => $r['appointment_date'],
            'time'           => $r['appointment_time'],
            'status'         => $r['request_status'],
            'place'          => $r['hospital_name'],
        ];
    }

    // 4) Doses a hospital could not give at a visit (the latest one is kept)
    $rows = db_all(
        "SELECT a.child_id, av.vaccine_id, a.appointment_date, h.name AS hospital_name
           FROM appointment_vaccines av
           JOIN appointments a ON a.id = av.appointment_id
           JOIN hospitals h    ON h.id = a.hospital_id
          WHERE av.status = 'Not Vaccinated' AND a.child_id IN ($in)
          ORDER BY a.appointment_date",
        $childIds
    );
    foreach ($rows as $r) {
        $records[$r['child_id']]['missed'][$r['vaccine_id']] = [
            'date'  => $r['appointment_date'],
            'place' => $r['hospital_name'],
        ];
    }

    return $records;
}

/**
 * Decides the state of a single dose (see algorithm at the top).
 */
function dose_state(string $dueDate, ?array $given, ?array $booked, string $today): array
{
    if ($given) {
        return ['key' => 'given', 'label' => 'Given'];
    }
    if ($booked) {
        $label = $booked['status'] === 'Approved' ? 'Booked' : 'Requested';
        return ['key' => 'booked', 'label' => $label];
    }

    $days = days_between($today, $dueDate);
    if ($days < 0) {
        return ['key' => 'overdue', 'label' => 'Overdue', 'days' => $days];
    }
    if ($days <= DUE_SOON_DAYS) {
        return ['key' => 'due', 'label' => $days === 0 ? 'Due today' : 'Due soon', 'days' => $days];
    }
    return ['key' => 'upcoming', 'label' => 'Upcoming', 'days' => $days];
}

/**
 * Combines the states of all doses in one visit into a single state.
 */
function visit_state(array $doseKeys): string
{
    $open = array_values(array_diff($doseKeys, ['given']));
    if (!$open) {
        return 'given';
    }
    if (in_array('overdue', $open, true)) {
        return 'overdue';
    }
    if (in_array('due', $open, true)) {
        return 'due';
    }
    if (count(array_unique($open)) === 1 && $open[0] === 'booked') {
        return 'booked';
    }
    return 'upcoming';
}

/**
 * Builds the complete vaccination card for each child in the list.
 * Each child's entry contains: doses, visits (grouped by age) and a summary.
 */
function build_schedules(array $children): array
{
    $catalog  = vaccine_catalog();
    $ids      = array_map('intval', array_column($children, 'id'));
    $records  = dose_records($ids);
    $today    = today();
    $result   = [];

    foreach ($children as $child) {
        $cid     = (int) $child['id'];
        $visits  = [];
        $summary = ['total' => 0, 'given' => 0, 'booked' => 0, 'overdue' => 0, 'due' => 0, 'upcoming' => 0, 'next' => null];

        foreach ($catalog as $v) {
            $vid    = (int) $v['id'];
            $due    = add_days($child['date_of_birth'], (int) $v['age_days']);
            $given  = $records[$cid]['given'][$vid] ?? null;
            $booked = $records[$cid]['booked'][$vid] ?? null;
            $missed = $given ? null : ($records[$cid]['missed'][$vid] ?? null);
            $state  = dose_state($due, $given, $booked, $today);

            $dose = ['vaccine' => $v, 'due_date' => $due, 'state' => $state, 'given' => $given, 'booked' => $booked, 'missed' => $missed];

            // Group doses that share the same recommended age into one visit
            $key = (int) $v['age_days'];
            if (!isset($visits[$key])) {
                $visits[$key] = ['label' => $v['age_label'], 'due_date' => $due, 'doses' => []];
            }
            $visits[$key]['doses'][] = $dose;

            // Counters for the summary strip
            $summary['total']++;
            $summary[$state['key']]++;
            if ($state['key'] !== 'given' && $summary['next'] === null) {
                $summary['next'] = $dose;
            }
        }

        // Work out each visit's combined state and last given date
        foreach ($visits as &$visit) {
            $keys           = array_map(function ($d) { return $d['state']['key']; }, $visit['doses']);
            $visit['state'] = visit_state($keys);
            $givenDates     = array_filter(array_map(function ($d) { return $d['given']['date'] ?? null; }, $visit['doses']));
            $visit['given_on'] = $givenDates ? max($givenDates) : null;
            $bookings       = array_filter(array_map(function ($d) { return $d['booked']; }, $visit['doses']));
            $visit['booking'] = $bookings ? reset($bookings) : null;
        }
        unset($visit);

        $result[$cid] = ['child' => $child, 'visits' => array_values($visits), 'summary' => $summary];
    }

    return $result;
}

/** Vaccination card of one child. */
function child_schedule(array $child): array
{
    $all = build_schedules([$child]);
    return $all[(int) $child['id']];
}

/**
 * Visit-level reminders for a set of children: every visit that is not
 * complete and is due before today + $withinDays (overdue ones included).
 * Used for the "upcoming vaccinations" notices on dashboards.
 */
function schedule_alerts(array $children, int $withinDays = 30): array
{
    $limit  = add_days(today(), $withinDays);
    $alerts = [];

    foreach (build_schedules($children) as $card) {
        foreach ($card['visits'] as $visit) {
            if ($visit['state'] === 'given' || $visit['due_date'] > $limit) {
                continue;
            }
            $open = array_filter($visit['doses'], function ($d) { return $d['state']['key'] !== 'given'; });
            $alerts[] = [
                'child'    => $card['child'],
                'age_days' => (int) $visit['doses'][0]['vaccine']['age_days'],
                'label'    => $visit['label'],
                'due_date' => $visit['due_date'],
                'state'    => $visit['state'],
                'booking'  => $visit['booking'],
                'doses'    => array_map(function ($d) { return $d['vaccine']['short_name']; }, $open),
            ];
        }
    }

    // Earliest due first
    usort($alerts, function ($a, $b) {
        return strcmp($a['due_date'], $b['due_date']);
    });
    return $alerts;
}

/** Text + CSS class used to show a state to users. */
function state_meta(string $key): array
{
    $meta = [
        'given'    => ['Given', 'state-given'],
        'booked'   => ['Booked', 'state-booked'],
        'overdue'  => ['Overdue', 'state-overdue'],
        'due'      => ['Due soon', 'state-due'],
        'upcoming' => ['Upcoming', 'state-upcoming'],
    ];
    return $meta[$key] ?? ['Upcoming', 'state-upcoming'];
}

/**
 * Short sentence describing when a visit or dose is due, e.g.
 * "Due 12 days ago" / "Due today" / "Due in 4 days".
 */
function due_phrase(string $dueDate): string
{
    $d = days_between(today(), $dueDate);
    if ($d === 0) {
        return 'Due today';
    }
    return $d < 0 ? 'Due ' . abs($d) . ' day' . (abs($d) === 1 ? '' : 's') . ' ago' : 'Due in ' . $d . ' day' . ($d === 1 ? '' : 's');
}
