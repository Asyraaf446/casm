<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/forum.php';
require_role('lecturer');
$me = user();
$cid = (int) ($_GET['course'] ?? 0);

$ready = forum_ready();
$topics = [];
$myCourses = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$me['id']]);
if ($ready) {
    $where = 'c.lecturer_id = ?';
    $params = [$me['id']];
    if ($cid) { $where .= ' AND c.id = ?'; $params[] = $cid; }
    $topics = qall(
        "SELECT t.id, t.title, t.body, t.created_at, c.code, u.name AS author,
                (SELECT COUNT(*) FROM forum_replies r WHERE r.topic_id = t.id) AS replies
           FROM forum_topics t JOIN classes c ON c.id = t.class_id JOIN users u ON u.id = t.created_by
          WHERE $where ORDER BY t.created_at DESC",
        $params
    );
}

$pageTitle = 'Forum';
$active = 'forum';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Forum</h1></div>
    <?php if ($ready): ?>
    <div class="actions" style="display:flex;gap:10px;flex-wrap:wrap">
        <form method="get" class="inline-form">
            <select name="course" onchange="this.form.submit()">
                <option value="0">All classes</option>
                <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <a class="btn" href="<?= url('lecturer/forum_form.php' . ($cid ? '?class_id=' . $cid : '')) ?>"><?= icon('plus', 18) ?> New topic</a>
    </div>
    <?php endif; ?>
</div>

<?php if (!$ready): ?>
    <div class="notice"><?= icon('help', 20) ?><div>The forum tables are not in the database yet. In phpMyAdmin open database/update_add_forum.sql, paste it in the SQL tab and press Go.</div></div>
<?php elseif (!$topics): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('forum', 28) ?></div><p>No topics yet. Start the first one for your class.</p><a class="btn btn-soft" href="<?= url('lecturer/forum_form.php') ?>">New topic</a></div></div>
<?php else: ?>
    <div class="list"><?php foreach ($topics as $t) forum_card($t, 'lecturer'); ?></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
