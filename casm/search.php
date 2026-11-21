<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
$me = user();

$q    = trim((string) ($_GET['q'] ?? ''));
$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'courses', 'materials', 'assignments'], true)) {
    $type = 'all';
}

/* what the current user is allowed to see */
if ($me['role'] === 'lecturer') {
    $scope = 'c.lecturer_id = ?';       $sp = [$me['id']];
} elseif ($me['role'] === 'student') {
    $scope = 'c.id IN (SELECT class_id FROM enrollments WHERE student_id = ?)'; $sp = [$me['id']];
} else {
    $scope = '1 = 1';                   $sp = [];
}

$courses = $materials = $assignments = [];
$enrolledIds = [];

// pressing Search with nothing typed (any option chosen) lists everything of that kind
$listAll = ($q === '' && isset($_GET['type']));
$typeLabels = ['all' => 'results', 'courses' => 'courses', 'materials' => 'notes & materials', 'assignments' => 'assignments'];

if ($q !== '' || $listAll) {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

    if ($type === 'all' || $type === 'courses') {
        if ($me['role'] === 'student') {
            // students may discover every class (to enroll), not only their own
            $courses = qall(
                'SELECT c.*, u.name AS lecturer_name FROM classes c JOIN users u ON u.id = c.lecturer_id
                  WHERE c.code LIKE ? OR c.title LIKE ? OR c.description LIKE ? OR u.name LIKE ? ORDER BY c.code LIMIT 200',
                [$like, $like, $like, $like]
            );
            $enrolledIds = array_map('intval', array_column(qall('SELECT class_id FROM enrollments WHERE student_id = ?', [$me['id']]), 'class_id'));
        } else {
            $courses = qall(
                "SELECT c.*, u.name AS lecturer_name FROM classes c JOIN users u ON u.id = c.lecturer_id
                  WHERE $scope AND (c.code LIKE ? OR c.title LIKE ? OR c.description LIKE ? OR u.name LIKE ?) ORDER BY c.code LIMIT 200",
                array_merge($sp, [$like, $like, $like, $like])
            );
        }
    }
    if ($type === 'all' || $type === 'materials') {
        $materials = qall(
            "SELECT m.*, c.code, c.title AS course_title, c.id AS cid FROM materials m JOIN classes c ON c.id = m.class_id
              WHERE $scope AND (m.title LIKE ? OR m.description LIKE ? OR m.original_name LIKE ?) ORDER BY m.created_at DESC LIMIT 30",
            array_merge($sp, [$like, $like, $like])
        );
    }
    if ($type === 'all' || $type === 'assignments') {
        $assignments = qall(
            "SELECT a.*, c.code, c.title AS course_title FROM assignments a JOIN classes c ON c.id = a.class_id
              WHERE $scope AND (a.title LIKE ? OR a.description LIKE ?) ORDER BY a.due_date DESC LIMIT 30",
            array_merge($sp, [$like, $like])
        );
    }
}
$total = count($courses) + count($materials) + count($assignments);

function course_link(array $c, array $me, array $enrolledIds): string
{
    if ($me['role'] === 'admin') return url('admin/class_view.php?id=' . (int) $c['id']);
    if ($me['role'] === 'lecturer') return url('lecturer/class_view.php?id=' . $c['id']);
    return in_array((int) $c['id'], $enrolledIds, true) ? url('student/class_view.php?id=' . $c['id']) : '';
}

$pageTitle = 'Search';
$active = 'search';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div><h1>Search</h1></div>
</div>

<form class="card flat mb" method="get">
    <div class="filters" style="margin:0">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Type a course name, topic or assignment…" style="flex:1;min-width:220px" autofocus>
        <select name="type">
            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All</option>
            <option value="courses" <?= $type === 'courses' ? 'selected' : '' ?>>Courses</option>
            <option value="materials" <?= $type === 'materials' ? 'selected' : '' ?>>Notes &amp; materials</option>
            <option value="assignments" <?= $type === 'assignments' ? 'selected' : '' ?>>Assignments</option>
        </select>
        <button class="btn" type="submit"><?= icon('search', 18) ?> Search</button>
    </div>
</form>

<?php if ($q === '' && !$listAll): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('search', 28) ?></div><p>Start typing above to search.</p></div></div>
<?php elseif ($total === 0): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('search', 28) ?></div><p><?= $listAll ? 'There are no ' . e($typeLabels[$type]) . ' to show yet.' : 'No results for “' . e($q) . '”. Try a different word.' ?></p></div></div>
<?php else: ?>
    <p class="muted"><?= $listAll ? 'All ' . e($typeLabels[$type]) . ' (' . $total . ')' : $total . ' result' . ($total > 1 ? 's' : '') . ' for “' . e($q) . '”' ?></p>

    <?php if ($courses): ?>
    <div class="result-group">
        <h3><?= icon('book', 20) ?> Courses</h3>
        <div class="grid cols-3">
            <?php foreach ($courses as $c): $href = course_link($c, $me, $enrolledIds); ?>
                <div class="card course-card c-<?= e($c['color']) ?>">
                    <div class="course-top">
                        <span class="code-badge"><?= e($c['code']) ?></span>
                        <h3><?= $href ? '<a href="' . e($href) . '">' . e($c['title']) . '</a>' : e($c['title']) ?></h3>
                        <small class="muted">Class <?= e($c['class_name']) ?> · <?= e($c['lecturer_name']) ?></small>
                    </div>
                    <div class="course-body"><p class="muted small" style="margin:0"><?= e(mb_strimwidth((string) $c['description'], 0, 110, '…')) ?></p></div>
                    <div class="course-foot">
                        <?php if ($me['role'] === 'student' && !in_array((int) $c['id'], $enrolledIds, true)): ?>
                            <form method="post" action="<?= url('student/courses.php') ?>" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="enroll"><input type="hidden" name="class_id" value="<?= (int) $c['id'] ?>">
                                <button class="btn btn-sm" type="submit"><?= icon('plus', 16) ?> Enroll</button>
                            </form>
                        <?php elseif ($href): ?>
                            <a class="btn btn-soft btn-sm" href="<?= e($href) ?>">Open class</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($materials): ?>
    <div class="result-group">
        <h3><?= icon('file', 20) ?> Notes &amp; materials</h3>
        <div class="card"><div class="list">
            <?php foreach ($materials as $m): ?>
                <div class="item c-<?= 'lavender' ?>">
                    <span class="ico"><?= icon('file', 20) ?></span>
                    <span class="grow"><b><?= e($m['title']) ?></b><small><?= e($m['code']) ?> · <?= e($m['course_title']) ?> · <?= e($m['original_name']) ?></small></span>
                    <a class="btn btn-soft btn-sm" href="<?= url('download.php?type=material&id=' . (int) $m['id']) ?>"><?= icon('download', 16) ?> Download</a>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <?php endif; ?>

    <?php if ($assignments): ?>
    <div class="result-group">
        <h3><?= icon('check', 20) ?> Assignments</h3>
        <div class="card"><div class="list">
            <?php foreach ($assignments as $a): ?>
                <div class="item">
                    <span class="ico"><?= icon('file', 20) ?></span>
                    <span class="grow"><b><?= e($a['title']) ?></b><small><?= e($a['code']) ?> · <?= e($a['course_title']) ?> · due <?= e(fmt_datetime($a['due_date'])) ?></small></span>
                    <?php if ($me['role'] !== 'admin'): ?>
                        <a class="btn btn-soft btn-sm" href="<?= url($me['role'] . '/assignment_view.php?id=' . (int) $a['id']) ?>">Open</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
