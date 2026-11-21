<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$id   = (int) ($_GET['id'] ?? 0);
$row  = null;
if ($id) {
    $row = qone('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$row) {
        flash('error', 'User not found.');
        redirect('admin/users.php');
    }
}

$detailFields = ['phone', 'ic_number', 'date_of_birth', 'gender', 'address', 'postcode', 'city', 'state', 'programme', 'guardian_name', 'guardian_phone'];
$states = ['Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor', 'Terengganu',
           'Kuala Lumpur', 'Labuan', 'Putrajaya', 'Outside Malaysia'];

$form = [
    'name'      => $row['name'] ?? '',
    'email'     => $row['email'] ?? '',
    'role'      => $row['role'] ?? 'student',
    'is_active' => $row['is_active'] ?? 1,
];
foreach ($detailFields as $f) $form[$f] = $row[$f] ?? '';
$studentId = $row['student_id'] ?? '';
$errors = [];

if (is_post()) {
    csrf_verify();
    $form['name']      = post('name');
    $form['email']     = strtolower(post('email'));
    $form['role']      = post('role');
    $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $password          = (string) ($_POST['password'] ?? '');
    foreach ($detailFields as $f) $form[$f] = post($f);

    if ($id && $id === (int) user()['id']) {
        $form['role'] = $row['role'];       // do not let admins lock themselves out
        $form['is_active'] = 1;
    }

    if ($form['name'] === '') $errors[] = 'Please enter the full name.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!in_array($form['role'], ['admin', 'lecturer', 'student'], true)) $errors[] = 'Please choose a role.';
    if (!$id && strlen($password) < 6) $errors[] = 'The password must be at least 6 characters.';
    if ($id && $password !== '' && strlen($password) < 6) $errors[] = 'The new password must be at least 6 characters.';
    if (!$errors && qval('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$form['email'], $id])) {
        $errors[] = 'Another account already uses that email.';
    }

    // personal details: needed for every role (administrator, lecturer and student)
    $dob = DateTime::createFromFormat('Y-m-d', $form['date_of_birth']);
    if (!in_array($form['gender'], ['male', 'female'], true)) $errors[] = 'Please choose the gender.';
    if (!$dob || $dob->format('Y-m-d') !== $form['date_of_birth'] || $dob > new DateTime('today') || (int) $dob->format('Y') < 1930) {
        $errors[] = 'Please enter a valid date of birth.';
    }
    if (!preg_match('/^[0-9A-Za-z\- ]{6,30}$/', $form['ic_number'])) $errors[] = 'Please enter the IC or passport number.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $form['phone'])) $errors[] = 'Please enter a valid phone number.';
    if ($form['address'] === '' || mb_strlen($form['address']) > 255) $errors[] = 'Please enter the home address (up to 255 characters).';
    if (!preg_match('/^[0-9A-Za-z\- ]{3,10}$/', $form['postcode'])) $errors[] = 'Please enter a valid postcode.';
    if ($form['city'] === '' || mb_strlen($form['city']) > 80) $errors[] = 'Please enter the city or town.';
    if (!in_array($form['state'], $states, true)) $errors[] = 'Please choose the state.';

    // details that depend on the role
    if ($form['role'] === 'student') {
        if ($form['programme'] === '' || mb_strlen($form['programme']) > 120) $errors[] = 'Please enter the programme (course of study).';
        if ($form['guardian_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $form['guardian_phone'])) $errors[] = 'Please enter a valid guardian phone number.';
        if (mb_strlen($form['guardian_name']) > 100) $errors[] = 'The guardian name is too long.';
    }
    if ($form['role'] === 'lecturer' && ($form['programme'] === '' || mb_strlen($form['programme']) > 120)) {
        $errors[] = 'Please enter the lecturer\'s department or faculty.';
    }

    if (!$errors) {
        $isStudent = $form['role'] === 'student';
        $det = [];
        foreach ($detailFields as $f) {
            if (in_array($f, ['guardian_name', 'guardian_phone'], true)) $keep = $isStudent;                       // students only
            elseif ($f === 'programme') $keep = $isStudent || $form['role'] === 'lecturer';                         // programme / department
            else $keep = true;                                                                                       // everyone
            $det[$f] = ($keep && $form[$f] !== '') ? $form[$f] : null;
        }

        if ($id) {
            // students keep their ID; a student without one (older account) gets one now
            $sid = null;
            if ($isStudent) $sid = $studentId !== '' ? $studentId : generate_student_id(substr((string) $row['created_at'], 0, 4));
            qrun('UPDATE users SET name = ?, email = ?, role = ?, is_active = ?, student_id = ?, phone = ?, ic_number = ?, date_of_birth = ?, gender = ?,
                         address = ?, postcode = ?, city = ?, state = ?, programme = ?, guardian_name = ?, guardian_phone = ? WHERE id = ?',
                array_merge([$form['name'], $form['email'], $form['role'], $form['is_active'], $sid],
                    array_values($det), [$id]));
            if ($password !== '') {
                qrun('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            log_activity('UPDATE_USER', 'Updated ' . $form['role'] . ' account: ' . $form['name']);
            flash('success', 'Account updated.');
        } else {
            $sid = $isStudent ? generate_student_id() : null;     // year registered + 6 random digits
            qrun('INSERT INTO users (name, email, password, role, is_active, created_at, student_id, phone, ic_number, date_of_birth, gender,
                                     address, postcode, city, state, programme, guardian_name, guardian_phone)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([$form['name'], $form['email'], password_hash($password, PASSWORD_DEFAULT), $form['role'], $form['is_active'], date('Y-m-d H:i:s'), $sid],
                    array_values($det)));
            log_activity('CREATE_USER', 'Created ' . $form['role'] . ' account: ' . $form['name'] . ($sid ? ' (student ID ' . $sid . ')' : ''));
            flash('success', 'Account created for ' . $form['name'] . ($sid ? '. Student ID: ' . $sid : '') . '.');
        }
        redirect('admin/users.php');
    }
}

$pageTitle = $id ? 'Edit User' : 'Add User';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= url('admin/users.php') ?>">Users</a> / <?= $id ? 'Edit' : 'New' ?></div>
        <h1><?= $id ? 'Edit account' : 'Add a new account' ?></h1>
    </div>
</div>

<div class="card" style="max-width:820px">
    <?php if ($errors): ?><div class="errors"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full"><label for="name">Full name</label><input type="text" id="name" name="name" value="<?= e($form['name']) ?>" required></div>
            <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($form['email']) ?>" required></div>
            <div class="field"><label for="role">Role</label>
                <select id="role" name="role" <?= $id && $id === (int) user()['id'] ? 'disabled' : '' ?>>
                    <?php foreach (['student' => 'Student', 'lecturer' => 'Lecturer', 'admin' => 'Administrator'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $form['role'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="field"><label for="password"><?= $id ? 'New password (optional)' : 'Password' ?></label>
                <input type="password" id="password" name="password" minlength="6" <?= $id ? '' : 'required' ?> placeholder="<?= $id ? 'Leave blank to keep the current one' : 'At least 6 characters' ?>">
            </div>
            <div class="field"><span class="label">Status</span>
                <label style="display:flex;gap:10px;align-items:center;font-weight:700;margin-top:8px">
                    <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?> <?= $id && $id === (int) user()['id'] ? 'disabled' : '' ?>> Account is active
                </label>
            </div>
        </div>

        <fieldset id="detailFields" class="student-fields">
            <legend>Personal details</legend>
            <div class="form-grid">
                <div class="field full" data-roles="student"><label for="student_id">Student ID</label>
                    <input type="text" id="student_id" value="<?= $studentId !== '' ? e($studentId) : 'Generated automatically when you save' ?>" disabled>
                    <span class="hint">Year registered + 6 random digits (for example 2026773856). It cannot be edited.</span>
                </div>
                <div class="field"><label for="ic_number">IC / passport number</label><input type="text" id="ic_number" name="ic_number" value="<?= e($form['ic_number']) ?>" maxlength="30" required></div>
                <div class="field"><label for="date_of_birth">Date of birth</label><input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($form['date_of_birth']) ?>" max="<?= date('Y-m-d') ?>" required></div>
                <div class="field"><label for="gender">Gender</label>
                    <select id="gender" name="gender" required>
                        <option value="">Choose…</option>
                        <option value="male" <?= $form['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $form['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                    </select></div>
                <div class="field"><label for="phone">Phone number</label><input type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="20" placeholder="012-3456789" required></div>
                <div class="field full" data-roles="student"><label for="programme_s">Programme</label><input type="text" id="programme_s" name="programme" value="<?= e($form['programme']) ?>" maxlength="120" placeholder="e.g. Diploma in Information Technology" required></div>
                <div class="field full" data-roles="lecturer"><label for="programme_l">Department / faculty</label><input type="text" id="programme_l" name="programme" value="<?= e($form['programme']) ?>" maxlength="120" placeholder="e.g. Faculty of Computing" required></div>

                <div class="field full"><label for="address">Home address</label><input type="text" id="address" name="address" value="<?= e($form['address']) ?>" maxlength="255" placeholder="House number, street, area" required></div>
                <div class="field"><label for="postcode">Postcode</label><input type="text" id="postcode" name="postcode" value="<?= e($form['postcode']) ?>" maxlength="10" required></div>
                <div class="field"><label for="city">City / town</label><input type="text" id="city" name="city" value="<?= e($form['city']) ?>" maxlength="80" required></div>
                <div class="field full"><label for="state">State</label>
                    <select id="state" name="state" required>
                        <option value="">Choose…</option>
                        <?php foreach ($states as $st): ?><option <?= $form['state'] === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?>
                    </select></div>

                <div class="field" data-roles="student"><label for="guardian_name">Guardian / emergency contact <span class="hint">(optional)</span></label><input type="text" id="guardian_name" name="guardian_name" value="<?= e($form['guardian_name']) ?>" maxlength="100"></div>
                <div class="field" data-roles="student"><label for="guardian_phone">Guardian phone <span class="hint">(optional)</span></label><input type="tel" id="guardian_phone" name="guardian_phone" value="<?= e($form['guardian_phone']) ?>" maxlength="20"></div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $id ? 'Save changes' : 'Create account' ?></button>
            <a class="btn btn-ghost" href="<?= url('admin/users.php') ?>">Cancel</a>
        </div>
    </form>
</div>
<script>
(function () {
    var role = document.getElementById('role');
    if (!role) return;
    function sync() {
        document.querySelectorAll('[data-roles]').forEach(function (box) {
            var on = box.getAttribute('data-roles').split(' ').indexOf(role.value) !== -1;
            box.hidden = !on;
            box.querySelectorAll('input[name], select[name], textarea[name]').forEach(function (el) { el.disabled = !on; });
        });
    }
    role.addEventListener('change', sync); sync();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
