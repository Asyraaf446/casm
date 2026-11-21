<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$count = function (string $role) {
    return (int) qval('SELECT COUNT(*) FROM users WHERE role = ?', [$role]);
};
$stats = [
    'students'    => $count('student'),
    'lecturers'   => $count('lecturer'),
    'courses'     => (int) qval('SELECT COUNT(*) FROM courses'),
    'assignments' => (int) qval('SELECT COUNT(*) FROM assignments'),
    'submissions' => (int) qval('SELECT COUNT(*) FROM submissions'),
    'today'       => (int) qval('SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = ?', [date('Y-m-d')]),
];
$logs   = qall('SELECT * FROM activity_logs ORDER BY created_at DESC, id DESC LIMIT 8');
$newest = qall('SELECT * FROM users ORDER BY created_at DESC, id DESC LIMIT 5');

$pageTitle = 'Admin Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Dashboard</h1></div>
    <div class="actions"><a class="btn" href="<?= url('admin/analytics.php') ?>"><?= icon('chart', 18) ?> View statistics &amp; analytics</a></div>
</div>

<div class="grid cols-3 mb">
    <a class="stat lav" href="<?= url('admin/users.php?role=student') ?>"><span class="ico"><?= icon('users', 24) ?></span><div><b><?= $stats['students'] ?></b><span>Students</span></div></a>
    <a class="stat sky" href="<?= url('admin/users.php?role=lecturer') ?>"><span class="ico"><?= icon('user', 24) ?></span><div><b><?= $stats['lecturers'] ?></b><span>Lecturers</span></div></a>
    <a class="stat mint" href="<?= url('admin/courses.php') ?>"><span class="ico"><?= icon('book', 24) ?></span><div><b><?= $stats['courses'] ?></b><span>Courses</span></div></a>
    <a class="stat peach" href="<?= url('admin/analytics.php') ?>"><span class="ico"><?= icon('file', 24) ?></span><div><b><?= $stats['assignments'] ?></b><span>Assignments posted</span></div></a>
    <a class="stat rose" href="<?= url('admin/analytics.php') ?>"><span class="ico"><?= icon('upload', 24) ?></span><div><b><?= $stats['submissions'] ?></b><span>Submissions received</span></div></a>
    <a class="stat sun" href="<?= url('admin/logs.php?from=' . date('Y-m-d') . '&to=' . date('Y-m-d')) ?>"><span class="ico"><?= icon('activity', 24) ?></span><div><b><?= $stats['today'] ?></b><span>Activities today</span></div></a>
</div>

<div class="grid split">
    <div class="card">
        <div class="card-head"><h2>Recent activity</h2><a class="btn btn-soft btn-sm" href="<?= url('admin/logs.php') ?>">View all logs</a></div>
        <?php if (!$logs): ?>
            <div class="empty"><p>No activity recorded yet.</p></div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="nowrap small"><?= e(fmt_datetime($l['created_at'])) ?></td>
                    <td><?= e($l['user_name']) ?></td>
                    <td><?= pill('primary', action_label($l['action'])) ?></td>
                    <td class="small muted"><?= e($l['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Newest accounts</h2><a class="btn btn-soft btn-sm" href="<?= url('admin/user_form.php') ?>"><?= icon('plus', 16) ?> Add</a></div>
        <div class="list">
            <?php foreach ($newest as $u): ?>
                <div class="item">
                    <?= avatar_html($u['name'], $u['avatar'] ?? null, (int) $u['id']) ?>
                    <span class="grow"><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?></small></span>
                    <?= pill($u['role'] === 'admin' ? 'danger' : ($u['role'] === 'lecturer' ? 'info' : 'primary'), ucfirst($u['role'])) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
