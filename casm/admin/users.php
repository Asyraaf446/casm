<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$me = user();

/* ----- actions: delete / activate / deactivate ----- */
if (is_post()) {
    csrf_verify();
    $id = (int) ($_POST['user_id'] ?? 0);
    $target = qone('SELECT * FROM users WHERE id = ?', [$id]);

    if (!$target) {
        flash('error', 'User not found.');
    } elseif ($id === (int) $me['id']) {
        flash('error', 'You cannot delete or deactivate your own account.');
    } elseif (post('action') === 'delete') {
        delete_upload($target['avatar'] ?? null);
        if ($target['role'] === 'lecturer') {
            // the lecturer's classes are removed with the account, so remove their stored files too
            foreach (qall('SELECT m.file_path FROM materials m JOIN classes c ON c.id = m.class_id WHERE c.lecturer_id = ?', [$id]) as $f) delete_upload($f['file_path']);
            foreach (qall('SELECT s.file_path FROM submissions s JOIN assignments a ON a.id = s.assignment_id JOIN classes c ON c.id = a.class_id WHERE c.lecturer_id = ?', [$id]) as $f) delete_upload($f['file_path']);
        } elseif ($target['role'] === 'student') {
            foreach (qall('SELECT file_path FROM submissions WHERE student_id = ?', [$id]) as $f) delete_upload($f['file_path']);
        }
        qrun('DELETE FROM users WHERE id = ?', [$id]);
        log_activity('DELETE_USER', 'Deleted ' . $target['role'] . ' account: ' . $target['name'] . ' (' . $target['email'] . ')');
        flash('success', $target['name'] . ' was deleted.');
    } elseif (post('action') === 'toggle') {
        $new = $target['is_active'] ? 0 : 1;
        qrun('UPDATE users SET is_active = ? WHERE id = ?', [$new, $id]);
        log_activity($new ? 'ACTIVATE_USER' : 'DEACTIVATE_USER', ($new ? 'Activated ' : 'Deactivated ') . $target['name']);
        flash('success', $target['name'] . ($new ? ' is now active.' : ' has been deactivated.'));
    }
    redirect('admin/users.php' . (!empty($_POST['back']) ? '?' . ltrim((string) $_POST['back'], '?') : ''));
}

/* ----- search + filter ----- */
$q    = trim((string) ($_GET['q'] ?? ''));
$role = $_GET['role'] ?? '';
$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR student_id LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if (in_array($role, ['admin', 'lecturer', 'student'], true)) {
    $where[] = 'role = ?';
    $params[] = $role;
}
$users = qall('SELECT * FROM users WHERE ' . implode(' AND ', $where) . " ORDER BY FIELD(role,'admin','lecturer','student'), name", $params);
$back  = http_build_query(array_filter(['q' => $q, 'role' => $role]));

$pageTitle = 'Manage Users';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Users</h1></div>
    <div class="actions"><a class="btn" href="<?= url('admin/user_form.php') ?>"><?= icon('plus', 18) ?> Add user</a></div>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or student ID…" style="min-width:240px">
    <select name="role">
        <option value="">All roles</option>
        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrators</option>
        <option value="lecturer" <?= $role === 'lecturer' ? 'selected' : '' ?>>Lecturers</option>
        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Students</option>
    </select>
    <button class="btn btn-soft" type="submit"><?= icon('search', 16) ?> Filter</button>
    <?php if ($q !== '' || $role !== ''): ?><a class="btn btn-ghost" href="<?= url('admin/users.php') ?>">Reset</a><?php endif; ?>
</form>

<div class="card">
    <?php if (!$users): ?>
        <div class="empty"><div class="em-ico"><?= icon('users', 28) ?></div><p>No users match your search.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><div class="person"><?= avatar_html($u['name'], $u['avatar'] ?? null, (int) $u['id']) ?><div><b><a href="<?= url('admin/user_view.php?id=' . (int) $u['id']) ?>" style="color:inherit"><?= e($u['name']) ?></a></b><small><?= e($u['email']) ?><?= !empty($u['student_id']) ? ' · ID ' . e($u['student_id']) : '' ?></small></div></div></td>
                <td><?= pill($u['role'] === 'admin' ? 'danger' : ($u['role'] === 'lecturer' ? 'info' : 'primary'), ucfirst($u['role'])) ?></td>
                <td><?= $u['is_active'] ? pill('success', 'Active') : pill('muted', 'Deactivated') ?></td>
                <td class="small muted nowrap"><?= e(fmt_date($u['created_at'])) ?></td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn-soft btn-sm" href="<?= url('admin/user_view.php?id=' . (int) $u['id']) ?>"><?= icon('user', 15) ?> Details</a>
                        <a class="btn btn-soft btn-sm" href="<?= url('admin/user_form.php?id=' . (int) $u['id']) ?>"><?= icon('edit', 15) ?> Edit</a>
                        <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                                <button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                            <form method="post" class="inline-form" data-confirm="Delete <?= e($u['name']) ?>? <?= $u['role'] === 'lecturer' ? 'Their classes, assignments and materials will be deleted too (reassign the classes first if you want to keep them). ' : '' ?>This cannot be undone.">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                                <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 15) ?> Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
