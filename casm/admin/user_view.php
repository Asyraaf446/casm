<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$u = qone('SELECT * FROM users WHERE id = ?', [$id]);
if (!$u) {
    flash('error', 'User not found.');
    redirect('admin/users.php');
}

$role = $u['role'];
$address = trim(implode(', ', array_filter([$u['address'], trim(($u['postcode'] ?? '') . ' ' . ($u['city'] ?? '')), $u['state']])));
$age = '';
if (!empty($u['date_of_birth'])) {
    try { $age = (new DateTime($u['date_of_birth']))->diff(new DateTime('today'))->y; } catch (Throwable $t) { $age = ''; }
}
$missing = $address === '' || empty($u['phone']) || empty($u['ic_number']) || empty($u['date_of_birth']) || empty($u['gender']);

/* classes: the ones a student is in, or the ones a lecturer teaches */
$classes = [];
if ($role === 'student') {
    $classes = qall('SELECT c.id, c.code, c.title FROM classes c JOIN enrollments e ON e.class_id = c.id WHERE e.student_id = ? ORDER BY c.code', [$id]);
} elseif ($role === 'lecturer') {
    $classes = qall('SELECT id, code, title FROM classes WHERE lecturer_id = ? ORDER BY code', [$id]);
}

/** one label + value row; empty values show a soft "Not provided" */
$row = function (string $label, $value) {
    $has = $value !== null && $value !== '';
    return '<dt>' . e($label) . '</dt><dd>' . ($has ? '<b>' . e((string) $value) . '</b>' : '<span class="muted">Not provided</span>') . '</dd>';
};

$pageTitle = $u['name'];
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('admin/users.php') ?>">Users</a> / <?= e($u['name']) ?></div>
        <div class="person" style="gap:16px">
            <?= avatar_html($u['name'], $u['avatar'] ?? null, (int) $u['id'], 'width:64px;height:64px;font-size:1.3rem') ?>
            <div>
                <h1 style="margin:0"><?= e($u['name']) ?></h1>
                <div style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap">
                    <?= pill($role === 'admin' ? 'danger' : ($role === 'lecturer' ? 'info' : 'primary'), $role === 'admin' ? 'Administrator' : ucfirst($role)) ?>
                    <?= $u['is_active'] ? pill('success', 'Active') : pill('muted', 'Deactivated') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="actions"><a class="btn" href="<?= url('admin/user_form.php?id=' . (int) $u['id']) ?>"><?= icon('edit', 17) ?> Edit details</a></div>
</div>

<?php if ($missing): ?>
    <div class="notice"><?= icon('help', 20) ?><div>Some personal details are missing for this user. Press "Edit details" to add the address, phone number and the rest.</div></div>
<?php endif; ?>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>Account</h2></div>
        <dl class="kv">
            <?= $row('Email', $u['email']) ?>
            <?= $row('Role', $role === 'admin' ? 'Administrator' : ucfirst($role)) ?>
            <?php if ($role === 'student') echo $row('Student ID', $u['student_id']); ?>
            <?= $row('Status', $u['is_active'] ? 'Active' : 'Deactivated') ?>
            <?= $row('Joined', fmt_date($u['created_at'])) ?>
        </dl>
    </div>

    <div class="card">
        <div class="card-head"><h2>Personal details</h2></div>
        <dl class="kv">
            <?= $row('IC / passport', $u['ic_number']) ?>
            <?= $row('Date of birth', !empty($u['date_of_birth']) ? fmt_date($u['date_of_birth']) . ($age !== '' ? ' (' . $age . ' years old)' : '') : '') ?>
            <?= $row('Gender', ucfirst((string) $u['gender'])) ?>
            <?= $row('Phone', $u['phone']) ?>
            <?php if ($role === 'student') echo $row('Programme', $u['programme']); ?>
            <?php if ($role === 'lecturer') echo $row('Department', $u['programme']); ?>
        </dl>
    </div>
</div>

<div class="grid cols-2 mb">
    <div class="card">
        <div class="card-head"><h2>Address</h2></div>
        <dl class="kv">
            <?= $row('Home address', $u['address']) ?>
            <?= $row('Postcode', $u['postcode']) ?>
            <?= $row('City / town', $u['city']) ?>
            <?= $row('State', $u['state']) ?>
        </dl>
        <?php if ($address !== ''): ?><p class="muted small" style="margin-bottom:0"><?= e($address) ?></p><?php endif; ?>
    </div>

    <?php if ($role === 'student'): ?>
    <div class="card">
        <div class="card-head"><h2>Guardian / emergency contact</h2></div>
        <dl class="kv">
            <?= $row('Name', $u['guardian_name']) ?>
            <?= $row('Phone', $u['guardian_phone']) ?>
        </dl>
    </div>
    <?php endif; ?>
</div>

<?php if ($role !== 'admin'): ?>
<div class="card">
    <div class="card-head"><h2><?= $role === 'lecturer' ? 'Classes taught' : 'Classes joined' ?> (<?= count($classes) ?>)</h2></div>
    <?php if (!$classes): ?>
        <div class="empty"><p><?= $role === 'lecturer' ? 'This lecturer has no classes yet.' : 'This student has not joined any class yet.' ?></p></div>
    <?php else: ?>
        <div class="list">
        <?php foreach ($classes as $c): ?>
            <a class="item" href="<?= url('admin/class_view.php?id=' . (int) $c['id']) ?>" style="color:inherit;text-decoration:none">
                <span class="ico"><?= icon('book', 20) ?></span>
                <span class="grow"><b><?= e($c['code']) ?></b><small><?= e($c['title']) ?></small></span>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
