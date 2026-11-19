<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/bookings.php
 * Purpose : Helpers shared by every screen that shows or changes
 *           appointments (parent bookings, admin requests and bookings,
 *           hospital appointments).
 *
 * LIFE OF A BOOKING
 *   Parent books        -> request_status = Pending
 *   Admin approves      -> request_status = Approved  (hospital is notified)
 *   Admin rejects       -> request_status = Rejected
 *   Parent cancels      -> request_status = Cancelled (only before the visit)
 *   Hospital records    -> vaccination_status = Vaccinated /
 *                          Partially Vaccinated / Not Vaccinated
 * ---------------------------------------------------------------------
 */

/**
 * Loads the vaccines of many appointments with one query.
 * Returns [appointmentId => [row, row, ...]] ordered by schedule age.
 */
function appointment_vaccine_map(array $appointmentIds): array
{
    $ids = array_values(array_unique(array_map('intval', $appointmentIds)));
    $map = array_fill_keys($ids, []);
    if (!$ids) {
        return $map;
    }

    $rows = db_all(
        'SELECT av.appointment_id, av.vaccine_id, av.status, av.given_at,
                v.short_name, v.name, v.status AS stock_status
           FROM appointment_vaccines av
           JOIN vaccines v ON v.id = av.vaccine_id
          WHERE av.appointment_id IN (' . db_placeholders(count($ids)) . ')
          ORDER BY v.age_days, v.id',
        $ids
    );
    foreach ($rows as $row) {
        $map[(int) $row['appointment_id']][] = $row;
    }
    return $map;
}

/**
 * Small printed cells listing the vaccines of one appointment.
 * With $showResult the cell colour shows whether each dose was given.
 * With $showStock a dose that is currently out of stock is marked.
 */
function vaccine_chips(array $vaccines, bool $showResult = false, bool $showStock = false): string
{
    if (!$vaccines) {
        return '<span class="text-soft">-</span>';
    }
    $html = '<div class="dose-cells">';
    foreach ($vaccines as $v) {
        $class = 'dose-cell';
        $title = $v['name'];
        if ($showResult && $v['status'] === 'Vaccinated') {
            $class .= ' is-given';
            $title .= ' (given)';
        } elseif ($showResult && $v['status'] === 'Not Vaccinated') {
            $class .= ' is-missed';
            $title .= ' (not given)';
        } elseif ($showStock && ($v['stock_status'] ?? '') === 'Unavailable') {
            $class .= ' is-out';
            $title .= ' (currently out of stock)';
        }
        $html .= '<span class="' . $class . '" title="' . e($title) . '">' . e($v['short_name']) . '</span>';
    }
    return $html . '</div>';
}

/**
 * One easy status for an appointment, combining the admin decision and
 * the hospital result. Returns [label, pill css class].
 */
function booking_stage(array $a): array
{
    switch ($a['request_status']) {
        case 'Pending':
            return ['Waiting for approval', 'pill-amber'];
        case 'Rejected':
            return ['Rejected', 'pill-red'];
        case 'Cancelled':
            return ['Cancelled', 'pill-grey'];
    }

    // Approved: the hospital result decides the rest
    $results = [
        'Vaccinated'           => 'pill-ink',
        'Partially Vaccinated' => 'pill-ink-soft',
        'Not Vaccinated'       => 'pill-red',
    ];
    if (isset($results[$a['vaccination_status']])) {
        return [$a['vaccination_status'], $results[$a['vaccination_status']]];
    }
    if ($a['appointment_date'] < today()) {
        return ['Result awaited', 'pill-amber'];
    }
    return [$a['appointment_date'] === today() ? 'Confirmed for today' : 'Confirmed', 'pill-green'];
}

/** The stage above as a coloured pill. */
function booking_stage_badge(array $a): string
{
    [$label, $class] = booking_stage($a);
    return '<span class="pill ' . $class . '">' . e($label) . '</span>';
}

/** True while a parent is still allowed to cancel the booking. */
function booking_can_cancel(array $a): bool
{
    return in_array($a['request_status'], ['Pending', 'Approved'], true)
        && $a['vaccination_status'] === 'Pending'
        && $a['appointment_date'] >= today();
}

/**
 * Number of open bookings (Pending or Approved) in one hospital slot.
 * $ignoreId leaves out the booking that is being changed.
 */
function slot_bookings(int $hospitalId, string $date, string $time, int $ignoreId = 0): int
{
    return (int) db_value(
        "SELECT COUNT(*) FROM appointments
          WHERE hospital_id = ? AND appointment_date = ? AND appointment_time = ?
            AND request_status IN ('Pending', 'Approved') AND id <> ?",
        [$hospitalId, $date, $time . ':00', $ignoreId]
    );
}

/**
 * Checks a visit date and time for a hospital and adds messages to $errors.
 * Rules: real date, not in the past, within MAX_BOOKING_DAYS, one of the
 * listed half-hour slots, not already passed today, slot not full.
 */
function validate_visit_slot(string $date, string $time, int $hospitalId, int $ignoreId, array &$errors): void
{
    if (!valid_date($date)) {
        $errors['appointment_date'] = 'Choose a valid date.';
    } elseif ($date < today()) {
        $errors['appointment_date'] = 'The date cannot be in the past.';
    } elseif ($date > add_days(today(), MAX_BOOKING_DAYS)) {
        $errors['appointment_date'] = 'Visits can be booked up to ' . MAX_BOOKING_DAYS . ' days ahead.';
    }

    if (!in_array($time, time_slots(), true)) {
        $errors['appointment_time'] = 'Choose one of the listed times.';
    } elseif ($date === today() && $time <= date('H:i')) {
        $errors['appointment_time'] = 'That time has already passed today. Please choose a later time.';
    }

    if (!isset($errors['appointment_date']) && !isset($errors['appointment_time']) && $hospitalId > 0
        && slot_bookings($hospitalId, $date, $time, $ignoreId) >= SLOT_CAPACITY) {
        $errors['appointment_time'] = 'This time is fully booked at the selected hospital. Please choose another time.';
    }
}

/** Options for the time <select>: 09:00 => 9:00 AM ... */
function time_slot_options(string $current = ''): string
{
    $html = '';
    foreach (time_slots() as $slot) {
        $html .= '<option value="' . $slot . '"' . selected($slot, $current) . '>' . fmt_time($slot) . '</option>';
    }
    return $html;
}

/**
 * Loads one appointment with child, parent and hospital names.
 * Extra WHERE conditions (e.g. ownership) can be passed in.
 */
function find_appointment(int $id, string $extraWhere = '', array $extraParams = []): ?array
{
    return db_one(
        'SELECT a.*, c.name AS child_name, c.date_of_birth, c.gender,
                p.name AS parent_name, p.phone AS parent_phone, p.email AS parent_email,
                h.name AS hospital_name, h.phone AS hospital_phone, h.address AS hospital_address, h.city AS hospital_city
           FROM appointments a
           JOIN children  c ON c.id = a.child_id
           JOIN parents   p ON p.id = a.parent_id
           JOIN hospitals h ON h.id = a.hospital_id
          WHERE a.id = ? ' . $extraWhere,
        array_merge([$id], $extraParams)
    );
}

/** "18 Sep 2026 at 10:30 AM" */
function visit_when(array $a): string
{
    return fmt_date($a['appointment_date']) . ' at ' . fmt_time($a['appointment_time']);
}
