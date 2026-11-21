<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/forum.php';
require_role('student');
$me = user();
$cid = (int) ($_GET['course'] ?? 0);

$ready = forum_ready();
$topics = [];
$myCourses = qall('SELECT c.id, c.code, c.title FROM classes c JOIN enrollments e ON e.class_id = c.id WHERE e.student_id = ? ORDER BY c.code', [$me['id']]);
if ($ready) {
    $where = 'e.student_id = ?';
    $params = [$me['id']];
    if ($cid) { $where .= ' AND c.id = ?'; $params[] = $cid; }
    $topics = qall(
        "SELECT t.id, t.title, t.body, t.created_at, c.code, u.name AS author,
                (SELECT COUNT(*) FROM forum_replies r WHERE r.topic_id = t.id) AS replies
           FROM forum_topics t JOIN classes c ON c.id = t.class_id
           JOIN enrollments e ON e.class_id = c.id JOIN users u ON u.id = t.created_by
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
    <form method="get" class="inline-form">
        <select name="course" onchange="this.form.submit()">
            <option value="0">All my classes</option>
            <?php foreach ($myCourses as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if (!$ready): ?>
    <div class="notice"><?= icon('help', 20) ?><div>The forum is not set up yet. Please ask your administrator.</div></div>
<?php elseif (!$topics): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('forum', 28) ?></div><p>No forum topics yet. Your lecturers will post them here.</p></div></div>
<?php else: ?>
    <div class="list"><?php foreach ($topics as $t) forum_card($t, 'student'); ?></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
