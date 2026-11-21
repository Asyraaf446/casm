<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('lecturer');
$me = user();

$courses = qall('SELECT * FROM courses WHERE lecturer_id = ? ORDER BY code', [$me['id']]);

$classes = [];
foreach (qall(
    'SELECT c.*,
            (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id) AS students,
            (SELECT COUNT(*) FROM materials m WHERE m.class_id = c.id) AS materials,
            (SELECT COUNT(*) FROM assignments a WHERE a.class_id = c.id) AS assignments
       FROM classes c WHERE c.lecturer_id = ? ORDER BY c.class_name',
    [$me['id']]
) as $c) {
    $classes[(int) $c['course_id']][] = $c;
}

$pageTitle = 'My Courses';
$active = 'courses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>My Courses</h1><p class="sub">Open a class to share notes, set assignments, hold meetings and take attendance.</p></div>
</div>

<?php if (!$courses): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('book', 28) ?></div><p>No course has been assigned to you yet. Please ask the administrator to create one for you.</p></div></div>
<?php else: ?>
    <?php foreach ($courses as $co): $list = $classes[(int) $co['id']] ?? []; $tone = $list ? $list[0]['color'] : 'lavender'; ?>
    <div class="card mb">
        <div class="card-head" style="align-items:flex-start">
            <div class="c-<?= e($tone) ?>">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <span class="code-badge" style="margin:0"><?= e($co['code']) ?></span>
                    <h2><?= e($co['title']) ?></h2>
                </div>
                <?php if ($co['description']): ?><p class="muted small" style="margin:8px 0 0"><?= e($co['description']) ?></p><?php endif; ?>
            </div>
            <span class="muted small" style="white-space:nowrap"><?= count($list) ?> class<?= count($list) === 1 ? '' : 'es' ?></span>
        </div>
        <?php if (!$list): ?>
            <div class="empty"><p>This course has no classes yet. The administrator can add them.</p></div>
        <?php else: ?>
        <div class="grid cols-3">
            <?php foreach ($list as $c): ?>
            <div class="card course-card c-<?= e($c['color']) ?>">
                <div class="course-top">
                    <span class="code-badge"><?= e($c['code']) ?></span>
                    <h3><a href="<?= url('lecturer/class_view.php?id=' . (int) $c['id']) ?>">Class <?= e($c['class_name']) ?></a></h3>
                    <small class="muted"><?= (int) $c['students'] ?> student<?= (int) $c['students'] === 1 ? '' : 's' ?> enrolled</small>
                </div>
                <div class="course-body">
                    <div class="meta"><?= icon('clock', 16) ?> <?= $c['day_of_week'] ? e(day_name((int) $c['day_of_week'])) . ', ' . e(fmt_time($c['start_time'])) . ' – ' . e(fmt_time($c['end_time'])) : 'Schedule not set' ?></div>
                    <?php if ($c['room']): ?><div class="meta"><?= icon('pin', 16) ?> <?= e($c['room']) ?></div><?php endif; ?>
                    <div class="meta"><?= icon('file', 16) ?> <?= (int) $c['materials'] ?> note<?= (int) $c['materials'] === 1 ? '' : 's' ?> · <?= (int) $c['assignments'] ?> assignment<?= (int) $c['assignments'] === 1 ? '' : 's' ?></div>
                    <div class="meta"><?= icon('key', 16) ?> Join code: <code><?= e($c['join_code']) ?></code></div>
                </div>
                <div class="course-foot">
                    <a class="btn btn-sm" href="<?= url('lecturer/class_view.php?id=' . (int) $c['id']) ?>">Open class</a>
                    <a class="btn btn-soft btn-sm" href="<?= url('lecturer/class_form.php?id=' . (int) $c['id']) ?>"><?= icon('edit', 15) ?> Edit</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
