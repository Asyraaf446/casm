<?php
/** One forum topic with its replies. Lecturer of the class and enrolled students only. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/forum.php';
require_login();
$me = user();

$t = forum_ready() && in_array($me['role'], ['lecturer', 'student'], true)
    ? forum_topic_for_user((int) ($_GET['id'] ?? 0), $me) : null;
if (!$t) {
    http_response_code(404);
    exit('Topic not found.');
}
$id = (int) $t['id'];
$isOwner = $me['role'] === 'lecturer';
$list = $isOwner ? 'lecturer/forum.php' : 'student/forum.php';

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'reply') {
        $body = post('body');
        if (mb_strlen($body) < 2) {
            flash('error', 'Please write something before replying.');
        } elseif (mb_strlen($body) > 1000) {
            flash('error', 'Please keep your reply under 1000 characters.');
        } else {
            qrun('INSERT INTO forum_replies (topic_id, user_id, body, created_at) VALUES (?,?,?,?)', [$id, $me['id'], $body, date('Y-m-d H:i:s')]);
            log_activity('FORUM_REPLY', 'Replied to forum topic "' . $t['title'] . '" (' . $t['code'] . ')');
            $link = 'forum_topic.php?id=' . $id;
            if (!$isOwner) {
                notify((int) $t['created_by'], 'New reply on your forum topic', $me['name'] . ' replied to "' . $t['title'] . '"', $link);
            } else {
                // tell the students who already replied in this topic
                $ids = qall('SELECT DISTINCT user_id FROM forum_replies WHERE topic_id = ? AND user_id <> ?', [$id, $me['id']]);
                foreach ($ids as $r) {
                    notify((int) $r['user_id'], 'Your lecturer replied in the forum', $t['code'] . ' · ' . $t['title'], $link);
                }
            }
            flash('success', 'Your reply was posted.');
        }
        redirect('forum_topic.php?id=' . $id . '#replies');
    }

    if ($action === 'delete_reply') {
        $rid = (int) ($_POST['reply_id'] ?? 0);
        if ($isOwner) {
            qrun('DELETE FROM forum_replies WHERE id = ? AND topic_id = ?', [$rid, $id]);
        } else {
            qrun('DELETE FROM forum_replies WHERE id = ? AND topic_id = ? AND user_id = ?', [$rid, $id, $me['id']]);
        }
        flash('success', 'Reply removed.');
        redirect('forum_topic.php?id=' . $id . '#replies');
    }

    if ($action === 'delete_topic' && $isOwner) {
        qrun('DELETE FROM forum_topics WHERE id = ?', [$id]);
        log_activity('DELETE_FORUM_TOPIC', 'Deleted forum topic "' . $t['title'] . '" (' . $t['code'] . ')');
        flash('success', 'Topic deleted.');
        redirect($list);
    }
    redirect('forum_topic.php?id=' . $id);
}

$replies = qall(
    'SELECT r.id, r.user_id, r.body, r.created_at, u.name, u.role, u.avatar
       FROM forum_replies r JOIN users u ON u.id = r.user_id
      WHERE r.topic_id = ? ORDER BY r.created_at ASC, r.id ASC',
    [$id]
);

$pageTitle = $t['title'];
$active = 'forum';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url($list) ?>">Forum</a> / <?= e($t['code']) ?></div>
        <h1><?= e($t['title']) ?></h1>
    </div>
    <?php if ($isOwner): ?>
    <div class="actions" style="display:flex;gap:10px">
        <a class="btn btn-soft btn-sm" href="<?= url('lecturer/forum_form.php?id=' . $id) ?>">Edit</a>
        <form class="inline-form" method="post" data-confirm="Delete this topic and all its replies?">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_topic"><button class="btn btn-danger btn-sm" type="submit">Delete</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="card" style="max-width:860px">
    <div class="asker" style="margin-bottom:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap"><?= avatar_html($t['author'], $t['author_avatar'] ?: null, (int) $t['created_by']) ?>
        <b><?= e($t['author']) ?></b> <?= pill('primary', 'Lecturer') ?> <?= pill('muted', $t['code']) ?> <small class="muted"><?= e(fmt_datetime($t['created_at'])) ?></small></div>
    <p class="forum-body"><?= nl2br(e($t['body'])) ?></p>
</div>

<div class="card" style="max-width:860px;margin-top:18px" id="replies">
    <div class="card-head"><h2><?= count($replies) ?> <?= count($replies) === 1 ? 'reply' : 'replies' ?></h2></div>
    <div class="replies" style="margin-top:0">
        <?php if (!$replies): ?><p class="muted">No replies yet.<?= $isOwner ? '' : ' Be the first to reply.' ?></p><?php endif; ?>
        <?php foreach ($replies as $r): $isLect = $r['role'] === 'lecturer'; ?>
            <div class="reply<?= $isLect ? ' by-lecturer' : '' ?>">
                <?= avatar_html($r['name'], $r['avatar'] ?: null, (int) $r['user_id']) ?>
                <div class="rb">
                    <div class="rh"><b><?= e($r['name']) ?><?= (int) $r['user_id'] === (int) $me['id'] ? ' (you)' : '' ?></b> <?= $isLect ? pill('primary', 'Lecturer') : '' ?> <small class="muted"><?= e(time_ago($r['created_at'])) ?></small></div>
                    <p><?= nl2br(e($r['body'])) ?></p>
                </div>
                <?php if ($isOwner || (int) $r['user_id'] === (int) $me['id']): ?>
                    <form method="post" class="inline-form" data-confirm="Remove this reply?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete_reply"><input type="hidden" name="reply_id" value="<?= (int) $r['id'] ?>">
                        <button class="btn btn-ghost btn-sm" type="submit" title="Remove reply"><?= icon('trash', 14) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <form method="post" class="reply-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="reply">
            <textarea name="body" maxlength="1000" rows="2" placeholder="Write a reply..." required></textarea>
            <button class="btn btn-soft btn-sm" type="submit"><?= icon('send', 15) ?> Reply</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
