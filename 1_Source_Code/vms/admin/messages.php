<?php
/**
 * ---------------------------------------------------------------------
 * File    : admin/messages.php
 * Purpose : Messages sent through the public contact form.
 *           Open (marks as read), mark unread, delete, and reply by
 *           email with one click (mailto link).
 * ---------------------------------------------------------------------
 */
require_once __DIR__ . '/../includes/init.php';
require_role('admin');

/* ------------------------------------------------------------------
 * POST actions
 * ------------------------------------------------------------------ */
if (is_post()) {
    verify_csrf();
    $id     = int_param('message_id', 'post');
    $action = post('action');

    if ($action === 'unread') {
        db_exec('UPDATE contact_messages SET is_read = 0 WHERE id = ?', [$id]);
        flash('success', 'Marked as unread.');
        redirect('admin/messages.php');
    }
    if ($action === 'delete') {
        $count = db_exec('DELETE FROM contact_messages WHERE id = ?', [$id]);
        flash($count ? 'success' : 'danger', $count ? 'The message has been deleted.' : 'That message was not found.');
        redirect('admin/messages.php');
    }
    if ($action === 'read_all') {
        db_exec('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        flash('success', 'All messages are marked as read.');
    }
    redirect_self();
}

/* ------------------------------------------------------------------
 * Selected message (opening it marks it read)
 * ------------------------------------------------------------------ */
$openId  = int_param('id');
$current = $openId ? db_one('SELECT * FROM contact_messages WHERE id = ?', [$openId]) : null;
if ($current && !$current['is_read']) {
    db_exec('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$openId]);
    $current['is_read'] = 1;
}

$show   = get('show') === 'unread' ? 'unread' : 'all';
$where  = $show === 'unread' ? 'is_read = 0' : '1 = 1';
$unread = (int) db_value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
$total  = (int) db_value('SELECT COUNT(*) FROM contact_messages');
$pager  = paginate($show === 'unread' ? $unread : $total);
$list   = db_all(
    "SELECT * FROM contact_messages WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$pager['per'], $pager['offset']]
);

$pageTitle = 'Messages';
$activeNav = 'messages';
require APP_ROOT . '/includes/layout/panel-header.php';
?>

<div class="row g-4">
    <!-- ================= Inbox ================= -->
    <div class="<?= $current ? 'col-xl-5' : 'col-12' ?>">
        <div class="sheet">
            <nav class="tabs-line" aria-label="Filter messages">
                <a class="<?= $show === 'all' ? 'active' : '' ?>" href="<?= e(query_url(['show' => null, 'page' => null])) ?>">All <span class="num"><?= $total ?></span></a>
                <a class="<?= $show === 'unread' ? 'active' : '' ?>" href="<?= e(query_url(['show' => 'unread', 'page' => null])) ?>">Unread <span class="num"><?= $unread ?></span></a>
                <?php if ($unread): ?>
                    <form method="post" class="ms-auto align-self-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="read_all">
                        <button class="btn btn-sm btn-link" type="submit">Mark all read</button>
                    </form>
                <?php endif; ?>
            </nav>
            <?php if (!$list): ?>
                <?= empty_state('No messages', 'Messages from the contact page will appear here.') ?>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($list as $m): ?>
                        <li>
                            <a class="notice-row text-reset text-decoration-none<?= $m['is_read'] ? '' : ' is-unread' ?><?= $openId === (int) $m['id'] ? ' bg-light' : '' ?>"
                               href="<?= e(query_url(['id' => $m['id']])) ?>">
                                <span class="avatar" aria-hidden="true"><?= e(initials($m['name'])) ?></span>
                                <span class="d-block">
                                    <span class="d-block fw-600"><?= e($m['subject']) ?><?= $m['is_read'] ? '' : ' <span class="visually-hidden">(unread)</span>' ?></span>
                                    <span class="d-block cell-sub"><?= e($m['name']) ?> &middot; <?= e(mb_strimwidth($m['message'], 0, 70, '...')) ?></span>
                                </span>
                                <span class="small-note text-nowrap"><?= e(time_ago($m['created_at'])) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php render_pagination($pager); ?>
    </div>

    <!-- ================= Reading pane ================= -->
    <?php if ($current): ?>
        <div class="col-xl-7">
            <article class="sheet">
                <div class="sheet-head">
                    <h2><?= e($current['subject']) ?></h2>
                    <a class="btn btn-sm btn-light" href="<?= e(query_url(['id' => null])) ?>" aria-label="Close message"><i class="bi bi-x-lg" aria-hidden="true"></i></a>
                </div>
                <div class="sheet-body">
                    <p class="small-note">
                        From <strong><?= e($current['name']) ?></strong> &lt;<?= e($current['email']) ?>&gt;<br>
                        <?= e(fmt_datetime($current['created_at'])) ?>
                    </p>
                    <div class="message-body"><?= nl2br(e($current['message'])) ?></div>
                </div>
                <div class="sheet-foot d-flex flex-wrap gap-2 justify-content-end">
                    <a class="btn btn-primary btn-sm btn-icon" href="mailto:<?= e($current['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $current['subject'])) ?>">
                        <i class="bi bi-reply" aria-hidden="true"></i> Reply by email
                    </a>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="unread">
                        <input type="hidden" name="message_id" value="<?= (int) $current['id'] ?>">
                        <button class="btn btn-light btn-sm" type="submit">Mark unread</button>
                    </form>
                    <form method="post" data-confirm="Delete this message?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="message_id" value="<?= (int) $current['id'] ?>">
                        <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                    </form>
                </div>
            </article>
        </div>
    <?php endif; ?>
</div>

<?php require APP_ROOT . '/includes/layout/panel-footer.php'; ?>
