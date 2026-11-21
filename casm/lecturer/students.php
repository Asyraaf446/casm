<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

/* all of this lecturer's classes */
$classes = qall(
    'SELECT c.id, c.code, c.title, c.class_name, c.color
       FROM classes c WHERE c.lecturer_id = ? ORDER BY c.code, c.class_name',
    [$me['id']]
);

/* students per class */
$byClass = [];
foreach (qall(
    'SELECT e.class_id, u.id, u.name, u.email, u.avatar, e.enrolled_at
       FROM enrollments e
       JOIN classes c ON c.id = e.class_id
       JOIN users u ON u.id = e.student_id
      WHERE c.lecturer_id = ?
      ORDER BY u.name',
    [$me['id']]
) as $r) {
    $byClass[(int) $r['class_id']][] = $r;
}

$totalStudents = (int) qval(
    'SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN classes c ON c.id = e.class_id WHERE c.lecturer_id = ?',
    [$me['id']]
);

$pageTitle = 'My Students';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>My Students</h1><p class="sub"><?= $totalStudents ?> student<?= $totalStudents === 1 ? '' : 's' ?> across your classes.</p></div>
</div>

<?php if (!$classes): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('users', 28) ?></div><p>You don't have any classes yet.</p></div></div>
<?php else: ?>
    <?php foreach ($classes as $c): $list = $byClass[(int) $c['id']] ?? []; ?>
    <div class="card mb c-<?= e($c['color']) ?>">
        <div class="card-head">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="code-badge" style="margin:0"><?= e($c['code']) ?></span>
                <h2>Class <?= e($c['class_name']) ?> <span class="muted small" style="font-weight:600">· <?= e($c['title']) ?></span></h2>
            </div>
            <span class="muted small" style="white-space:nowrap"><?= count($list) ?> student<?= count($list) === 1 ? '' : 's' ?></span>
        </div>
        <?php if (!$list): ?>
            <div class="empty"><div class="em-ico"><?= icon('users', 28) ?></div><p>No students have joined this class yet.</p></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Student</th><th>Enrolled</th></tr></thead>
            <tbody>
            <?php foreach ($list as $s): ?>
                <tr>
                    <td><div class="person"><?= avatar_html($s['name'], $s['avatar'] ?? null, (int) $s['id']) ?><div><b><?= e($s['name']) ?></b><small><?= e($s['email']) ?></small></div></div></td>
                    <td class="small muted"><?= e(fmt_date($s['enrolled_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
