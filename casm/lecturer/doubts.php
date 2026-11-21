<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/doubt_thread.php';
require_role('lecturer');
$me = user();
$cid = (int) ($_GET['course'] ?? 0);

if (is_post()) {
    csrf_verify();
    $back = 'lecturer/doubts.php' . ($cid ? '?course=' . $cid : '');
    if (post('action') === 'delete_reply') {
        qrun('DELETE r FROM doubt_replies r JOIN doubts d ON d.id = r.doubt_id JOIN classes c ON c.id = d.class_id
               WHERE r.id = ? AND c.lecturer_id = ?', [(int) ($_POST['reply_id'] ?? 0), $me['id']]);
        flash('success', 'Reply removed.');
        redirect($back);
    }
    $d = qone('SELECT d.* FROM doubts d JOIN classes c ON c.id = d.class_id WHERE d.id = ? AND c.lecturer_id = ?', [(int) ($_POST['doubt_id'] ?? 0), $me['id']]);
    if ($d) {
        if (post('action') === 'reply') {
            $err = doubt_add_reply($d, $me, post('body'));
            if ($err) {
                flash('error', $err);
            } else {
                // A lecturer reply marks the question as answered
                qrun('UPDATE doubts SET is_resolved = 1 WHERE id = ?', [$d['id']]);
                flash('success', 'Your reply was posted.');
            }
            redirect($back . '#d' . (int) $d['id']);
        } elseif (post('action') === 'delete') {
            qrun('DELETE FROM doubts WHERE id = ?', [$d['id']]);
            log_activity('DELETE_DOUBT', 'Removed a doubt-board question');
            flash('success', 'Question removed.');
        }
    }
    redirect($back);
}

$where = ['c.lecturer_id = ?'];
$params = [$me['id']];
if ($cid) { $where[] = 'c.id = ?'; $params[] = $cid; }

$doubts = qall(
    'SELECT d.id, d.student_id, d.question, d.is_resolved, d.created_at, c.code, c.color,
            u.name AS author, u.avatar AS author_avatar
       FROM doubts d JOIN classes c ON c.id = d.class_id JOIN users u ON u.id = d.student_id
      WHERE ' . implode(' AND ', $where) . ' ORDER BY d.is_resolved ASC, d.created_at DESC',
    $params
);
$replies = doubt_replies_for(array_column($doubts, 'id'));
$myCourses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);

$pageTitle = 'Discussion';
$active = 'doubts';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Discussion</h1></div>
    <form method="get" class="inline-form">
        <select name="course" onchange="this.form.submit()">
            <option value="0">All classes</option>
            <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <?php if (!$doubts): ?>
        <div class="empty"><div class="em-ico"><?= icon('help', 28) ?></div><p>No questions yet. Students can post from their Discussion page.</p></div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($doubts as $d): ?>
            <div class="doubt" style="<?= $d['is_resolved'] ? 'opacity:.6' : '' ?>">
                <div class="q">
                    <div class="asker"><?= avatar_html($d['author'], $d['author_avatar'] ?: null, (int) $d['student_id']) ?><b><?= e($d['author']) ?></b></div>
                    <p><?= e($d['question']) ?></p>
                    <small class="muted"><?= pill('primary', $d['code']) ?> <?= e(time_ago($d['created_at'])) ?> <?= $d['is_resolved'] ? pill('success', 'Answered') : '' ?></small>
                    <?= doubt_thread_html((int) $d['id'], $replies[(int) $d['id']] ?? [], (int) $me['id'], true) ?>
                </div>
                <div class="row-actions" style="flex-direction:column;align-items:stretch">
                    <form method="post" class="inline-form" data-confirm="Remove this question?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="doubt_id" value="<?= (int) $d['id'] ?>">
                        <button class="btn btn-danger btn-sm btn-block" type="submit">Remove</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
