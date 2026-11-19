<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/pages/notifications.php
 * Purpose : Notifications page shared by the admin, parent and hospital
 *           panels. The role file (e.g. parent/notifications.php) calls
 *           require_role() first and then includes this file.
 * ---------------------------------------------------------------------
 */
$role    = current_role();
$ownerId = notification_owner_id($role, current_user_id());

/* ------------------------------------------------------------------
 * Opening one notification: mark it read and follow its link
 * ------------------------------------------------------------------ */
$openId = int_param('open');
if ($openId > 0) {
    $item = db_one(
        'SELECT * FROM notifications WHERE id = ? AND user_role = ? AND user_id = ?',
        [$openId, $role, $ownerId]
    );
    if ($item) {
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ?', [$openId]);
        // Links are saved by the system as project paths, e.g. "parent/bookings.php"
        if (!empty($item['link']) && preg_match('#^[a-z]+/[a-z\-]+\.php(\?[\w=&%\-]*)?$#i', $item['link'])) {
            redirect($item['link']);
        }
    }
    redirect($role . '/notifications.php');
}

/* ------------------------------------------------------------------
 * Actions: mark everything read / remove read notifications
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'read_all') {
        $count = db_exec(
            'UPDATE notifications SET is_read = 1 WHERE user_role = ? AND user_id = ? AND is_read = 0',
            [$role, $ownerId]
        );
        flash('success', $count ? 'All notifications are marked as read.' : 'Everything was already read.');
    } elseif ($action === 'clear_read') {
        $count = db_exec(
            'DELETE FROM notifications WHERE user_role = ? AND user_id = ? AND is_read = 1',
            [$role, $ownerId]
        );
        flash('success', $count ? $count . ' read notification(s) removed.' : 'There were no read notifications to remove.');
    }
    redirect_self();
}

/* ------------------------------------------------------------------
 * List (all or unread only), newest first, with pagination
 * ------------------------------------------------------------------ */
$show   = get('show') === 'unread' ? 'unread' : 'all';
$where  = 'user_role = ? AND user_id = ?' . ($show === 'unread' ? ' AND is_read = 0' : '');
$params = [$role, $ownerId];

$allCount    = (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_role = ? AND user_id = ?', $params);
$unreadCount = (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_role = ? AND user_id = ? AND is_read = 0', $params);

$pager = paginate($show === 'unread' ? $unreadCount : $allCount);
$items = db_all(
    "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per'], $pager['offset']])
);

/** Picks an icon from the notification title. */
function notification_icon(string $title): string
{
    $t = strtolower($title);
    $icons = [
        'approved'   => 'bi-check2-circle',
        'rejected'   => 'bi-x-circle',
        'cancel'     => 'bi-calendar-x',
        'vaccinat'   => 'bi-shield-check',
        'not given'  => 'bi-exclamation-circle',
        'result'     => 'bi-clipboard2-check',
        'hospital'   => 'bi-hospital',
        'message'    => 'bi-envelope',
        'appointment'=> 'bi-calendar-event',
        'booking'    => 'bi-calendar-plus',
        'request'    => 'bi-calendar-plus',
        'welcome'    => 'bi-emoji-smile',
        'account'    => 'bi-person-check',
    ];
    foreach ($icons as $word => $icon) {
        if (str_contains($t, $word)) {
            return $icon;
        }
    }
    return 'bi-bell';
}

$pageTitle = 'Notifications';
$activeNav = 'notifications';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<div class="sheet">
    <!-- Filter tabs -->
    <nav class="tabs-line" aria-label="Filter notifications">
        <a href="<?= e(query_url(['show' => null, 'page' => null])) ?>" class="<?= $show === 'all' ? 'active' : '' ?>">
            All <span class="num"><?= $allCount ?></span>
        </a>
        <a href="<?= e(query_url(['show' => 'unread', 'page' => null])) ?>" class="<?= $show === 'unread' ? 'active' : '' ?>">
            Unread <span class="num"><?= $unreadCount ?></span>
        </a>
    </nav>

    <!-- Bulk actions -->
    <div class="sheet-head">
        <p class="mb-0 text-soft">Updates about bookings, approvals and vaccinations appear here.</p>
        <div class="d-flex gap-2 flex-wrap">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="read_all">
                <button class="btn btn-sm btn-light btn-icon" type="submit" <?= $unreadCount ? '' : 'disabled' ?>>
                    <i class="bi bi-check2-all" aria-hidden="true"></i> Mark all as read
                </button>
            </form>
            <form method="post" data-confirm="Remove all notifications you have already read?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear_read">
                <button class="btn btn-sm btn-outline-danger btn-icon" type="submit" <?= $allCount > $unreadCount ? '' : 'disabled' ?>>
                    <i class="bi bi-trash3" aria-hidden="true"></i> Remove read
                </button>
            </form>
        </div>
    </div>

    <!-- Notification rows -->
    <?php if (!$items): ?>
        <?= empty_state(
            $show === 'unread' ? 'You are all caught up' : 'No notifications yet',
            $show === 'unread' ? 'There are no unread notifications.' : 'You will see updates here as soon as something happens.'
        ) ?>
    <?php else: ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($items as $n): ?>
                <li class="notice-row<?= $n['is_read'] ? '' : ' is-unread' ?>">
                    <span class="notice-icon"><i class="bi <?= notification_icon($n['title']) ?>" aria-hidden="true"></i></span>
                    <div>
                        <div class="fw-600">
                            <?= e($n['title']) ?>
                            <?php if (!$n['is_read']): ?><span class="visually-hidden">(unread)</span><?php endif; ?>
                        </div>
                        <div class="text-soft"><?= e($n['message']) ?></div>
                        <div class="small-note"><?= e(fmt_datetime($n['created_at'])) ?> &middot; <?= e(time_ago($n['created_at'])) ?></div>
                    </div>
                    <?php if (!empty($n['link']) || !$n['is_read']): ?>
                        <a class="btn btn-sm btn-light" href="<?= e(url($role . '/notifications.php?open=' . (int) $n['id'])) ?>">
                            <?= !empty($n['link']) ? 'Open' : 'Mark read' ?>
                        </a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php render_pagination($pager); ?>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
