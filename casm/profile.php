<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$me = user();
$row = qone('SELECT * FROM users WHERE id = ?', [$me['id']]);
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'profile') {
        $name = post('name');
        if ($name === '') {
            $errors[] = 'Please enter your name.';
        } else {
            qrun('UPDATE users SET name = ? WHERE id = ?', [$name, $me['id']]);
            $_SESSION['user']['name'] = $name;
            log_activity('UPDATE_PROFILE', 'Changed display name');
            flash('success', 'Profile updated.');
            redirect('profile.php');
        }
    }

    if ($action === 'avatar') {
        try {
            $stored = save_avatar($_FILES['avatar'] ?? []);
            delete_upload($row['avatar'] ?? null);
            qrun('UPDATE users SET avatar = ? WHERE id = ?', [$stored, $me['id']]);
            log_activity('UPDATE_PROFILE', 'Changed profile picture');
            flash('success', 'Your profile picture was updated.');
            redirect('profile.php');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if ($action === 'remove_avatar') {
        delete_upload($row['avatar'] ?? null);
        qrun('UPDATE users SET avatar = NULL WHERE id = ?', [$me['id']]);
        log_activity('UPDATE_PROFILE', 'Removed profile picture');
        flash('success', 'Your profile picture was removed.');
        redirect('profile.php');
    }

    if ($action === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $rep = (string) ($_POST['repeat'] ?? '');
        if (!password_verify($cur, $row['password'])) {
            $errors[] = 'Your current password is not correct.';
        } elseif (strlen($new) < 6) {
            $errors[] = 'The new password must be at least 6 characters.';
        } elseif ($new !== $rep) {
            $errors[] = 'The new passwords do not match.';
        } else {
            qrun('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            log_activity('CHANGE_PASSWORD', 'Changed password');
            flash('success', 'Password changed.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'My Profile';
$active = '';
require __DIR__ . '/includes/header.php';
$roleLabel = ['admin' => 'Administrator', 'lecturer' => 'Lecturer', 'student' => 'Student'][$row['role']];
?>
<div class="page-head">
    <div><h1>My Profile</h1></div>
</div>

<?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="grid cols-2">
    <div class="card">
        <div class="person mb" style="gap:18px">
            <span id="avatarBox"><?= avatar_html($row['name'], $row['avatar'] ?? null, (int) $row['id'], 'width:92px;height:92px;font-size:1.8rem') ?></span>
            <div><h2 style="margin:0 0 4px"><?= e($row['name']) ?></h2><?= pill('primary', $roleLabel) ?></div>
        </div>

        <form method="post" enctype="multipart/form-data" class="avatar-form mb">
            <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
            <label for="avatar">Profile picture</label>
            <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" data-avatar-preview="avatarBox" required>
            <span class="hint">JPG, PNG, GIF or WEBP, up to 3 MB. It is cropped to a square automatically.</span>
            <div class="form-actions" style="margin-top:4px">
                <button class="btn" type="submit"><?= icon('upload', 18) ?> Upload picture</button>
            </div>
        </form>
        <?php if (!empty($row['avatar'])): ?>
            <form method="post" data-confirm="Remove your profile picture?" class="mb">
                <?= csrf_field() ?><input type="hidden" name="action" value="remove_avatar">
                <button class="btn btn-ghost btn-sm" type="submit"><?= icon('trash', 15) ?> Remove picture</button>
            </form>
        <?php endif; ?>
        <hr>

        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="field mb"><label for="name">Full name</label><input type="text" id="name" name="name" value="<?= e($row['name']) ?>" required></div>
            <div class="field"><label>Email</label><input type="email" value="<?= e($row['email']) ?>" disabled><span class="hint">Only the administrator can change your email.</span></div>
            <div class="form-actions"><button class="btn" type="submit">Save profile</button></div>
        </form>
    </div>

    <div class="card">
        <h2>Change password</h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="field mb"><label for="current">Current password</label><input type="password" id="current" name="current" required></div>
            <div class="field mb"><label for="new">New password</label><input type="password" id="new" name="new" minlength="6" required></div>
            <div class="field"><label for="repeat">Repeat new password</label><input type="password" id="repeat" name="repeat" minlength="6" required></div>
            <div class="form-actions"><button class="btn" type="submit"><?= icon('key', 18) ?> Update password</button></div>
        </form>
    </div>
</div>

<?php if ($row['role'] !== 'admin' ? (!empty($row['address']) || !empty($row['student_id'])) : !empty($row['address'])): ?>
<div class="card" style="margin-top:20px">
    <h2><?= $row['role'] === 'student' ? 'My student details' : 'My details' ?></h2>
    <p class="muted small">These details were entered by the administrator. Please contact the administrator if something needs to be corrected.</p>
    <div class="form-grid">
        <?php
        $detail = [
            'Student ID' => $row['role'] === 'student' ? $row['student_id'] : '',
            ($row['role'] === 'lecturer' ? 'Department' : 'Programme') => $row['programme'], 'IC / passport number' => $row['ic_number'],
            'Date of birth' => $row['date_of_birth'] ? fmt_date($row['date_of_birth']) : '', 'Gender' => ucfirst((string) $row['gender']), 'Phone' => $row['phone'],
            'Address' => trim(implode(', ', array_filter([$row['address'], trim($row['postcode'] . ' ' . $row['city']), $row['state']]))),
            'Guardian' => trim(implode(' · ', array_filter([$row['guardian_name'], $row['guardian_phone']]))),
        ];
        foreach ($detail as $label => $value): if ($value === '' || $value === null) continue; ?>
            <div class="field <?= in_array($label, ['Address'], true) ? 'full' : '' ?>"><span class="label"><?= e($label) ?></span><div><b><?= e($value) ?></b></div></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
