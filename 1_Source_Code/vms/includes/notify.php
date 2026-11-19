<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/notify.php
 * Purpose : Dashboard notifications. Other modules call notify() when
 *           something happens (booking made, approved, vaccinated ...).
 *           Admin notifications use user_id = 0 (shared by all admins).
 * ---------------------------------------------------------------------
 */

/** Saves one notification for a user. */
function notify(string $role, int $userId, string $title, string $message, ?string $link = null): void
{
    db_exec(
        'INSERT INTO notifications (user_role, user_id, title, message, link) VALUES (?, ?, ?, ?, ?)',
        [$role, $userId, mb_substr($title, 0, 150), mb_substr($message, 0, 255), $link]
    );
}

/** Saves a notification for the admin team. */
function notify_admins(string $title, string $message, ?string $link = null): void
{
    notify('admin', 0, $title, $message, $link);
}

/** Owner id used in queries (admins share id 0). */
function notification_owner_id(string $role, int $userId): int
{
    return $role === 'admin' ? 0 : $userId;
}

/** Number of unread notifications for the logged-in user. */
function unread_notification_count(): int
{
    if (!is_logged_in()) {
        return 0;
    }
    $role = current_role();
    return (int) db_value(
        'SELECT COUNT(*) FROM notifications WHERE user_role = ? AND user_id = ? AND is_read = 0',
        [$role, notification_owner_id($role, current_user_id())]
    );
}

/** Latest notifications for the logged-in user. */
function latest_notifications(int $limit = 6): array
{
    $role = current_role();
    return db_all(
        'SELECT * FROM notifications WHERE user_role = ? AND user_id = ? ORDER BY created_at DESC, id DESC LIMIT ?',
        [$role, notification_owner_id($role, current_user_id()), $limit]
    );
}
