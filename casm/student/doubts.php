<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/doubt_thread.php';
require_role('student');
$me  = user();
$cid = (int) ($_GET['course'] ?? 0);

$myCourses = qall(
    'SELECT c.id, c.code, c.title FROM classes c JOIN enrollments e ON e.class_id = c.id AND e.student_id = ? ORDER BY c.code',
    [$me['id']]
);
$myIds = array_map('intval', array_column($myCourses, 'id'));
$back  = 'student/doubts.php' . ($cid ? '?course=' . $cid : '');

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'ask') {
        $courseId = (int) ($_POST['class_id'] ?? 0);
        $question = post('question');
        if (!in_array($courseId, $myIds, true)) {
            flash('error', 'Please choose one of your classes.');
        } elseif (mb_strlen($question) < 8) {
            flash('error', 'Please write your question in a bit more detail (at least 8 characters).');
        } elseif (mb_strlen($question) > 600) {
            flash('error', 'Please keep your question under 600 characters.');
        } else {
            qrun('INSERT INTO doubts (class_id, student_id, question, created_at) VALUES (?,?,?,?)', [$courseId, $me['id'], $question, date('Y-m-d H:i:s')]);
            flash('success', 'Your question was posted.');
        }
        redirect($back);
    }

    if ($action === 'reply') {
        $d = qone('SELECT * FROM doubts WHERE id = ?', [(int) ($_POST['doubt_id'] ?? 0)]);
        if ($d && in_array((int) $d['class_id'], $myIds, true)) {
            $err = doubt_add_reply($d, $me, post('body'));
            if ($err) flash('error', $err); else flash('success', 'Your reply was posted.');
            redirect($back . '#d' . (int) $d['id']);
        }
        redirect($back);
    }

    if ($action === 'delete_reply') {
        if (qrun('DELETE FROM doubt_replies WHERE id = ? AND user_id = ?', [(int) ($_POST['reply_id'] ?? 0), $me['id']])) {
            flash('success', 'Your reply was removed.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        if (qrun('DELETE FROM doubts WHERE id = ? AND student_id = ?', [(int) ($_POST['doubt_id'] ?? 0), $me['id']])) {
            flash('success', 'Your question was removed.');
        }
        redirect($back);
    }
}

$where = $myIds ? 'd.class_id IN (' . implode(',', $myIds) . ')' : '1 = 0';
$params = [];
if ($cid && in_array($cid, $myIds, true)) { $where .= ' AND d.class_id = ?'; $params[] = $cid; }

$doubts = qall(
    "SELECT d.id, d.question, d.is_resolved, d.created_at, d.student_id, c.code,
            u.name AS author, u.avatar AS author_avatar
       FROM doubts d JOIN classes c ON c.id = d.class_id JOIN users u ON u.id = d.student_id
      WHERE $where ORDER BY d.is_resolved ASC, d.created_at DESC",
    $params
);

$replies = doubt_replies_for(array_column($doubts, 'id'));

$pageTitle = 'Discussion';
$active = 'doubts';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Discussion</h1></div>
    <form method="get" class="inline-form">
        <select name="course" onchange="this.form.submit()">
            <option value="0">All my classes</option>
            <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<div class="grid split">
    <div class="card">
        <div class="card-head"><h2>Questions from your classmates</h2></div>
        <?php if (!$doubts): ?>
            <div class="empty"><div class="em-ico"><?= icon('help', 28) ?></div><p>No questions yet. Be the first to ask!</p></div>
        <?php else: ?>
        <div class="list">
            <?php foreach ($doubts as $d): ?>
                <div class="doubt" id="q<?= (int) $d['id'] ?>" style="<?= $d['is_resolved'] ? 'opacity:.65' : '' ?>">
                    <div class="q">
                        <div class="asker"><?= avatar_html($d['author'], $d['author_avatar'] ?: null, (int) $d['student_id']) ?><b><?= e($d['author']) ?><?= (int) $d['student_id'] === (int) $me['id'] ? ' (you)' : '' ?></b></div>
                        <p><?= e($d['question']) ?></p>
                        <small class="muted"><?= pill('primary', $d['code']) ?> <?= e(time_ago($d['created_at'])) ?> <?= $d['is_resolved'] ? pill('success', 'Answered') : '' ?></small>
                        <?= doubt_thread_html((int) $d['id'], $replies[(int) $d['id']] ?? [], (int) $me['id']) ?>
                    </div>
                    <?php if ((int) $d['student_id'] === (int) $me['id']): ?>
                        <form method="post" class="inline-form" data-confirm="Remove your question?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="doubt_id" value="<?= (int) $d['id'] ?>">
                            <button class="btn btn-ghost btn-sm" type="submit" title="Remove my question"><?= icon('trash', 14) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card" style="position:sticky;top:90px">
        <h2><?= icon('help', 20) ?> Ask a question</h2>
        <p class="muted small">Your name is shown with your question so classmates and your lecturer know who is asking.</p>
        <?php if (!$myCourses): ?>
            <p class="muted">Join a class first to post a question.</p>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="ask">
            <div class="field mb"><label for="class_id">Class</label>
                <select id="class_id" name="class_id" required>
                    <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field mb"><label for="question">Your question</label><textarea id="question" name="question" maxlength="600" placeholder="What did you find confusing in the notes?" required></textarea></div>
            <button class="btn btn-block" type="submit"><?= icon('send', 18) ?> Post question</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
