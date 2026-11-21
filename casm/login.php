<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (user()) {
    redirect(home_for(user()['role']));
}

$error = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email = strtolower(post('email'));
    $pass  = (string) ($_POST['password'] ?? '');

    $u = qone('SELECT * FROM users WHERE email = ?', [$email]);

    if ($u && password_verify($pass, $u['password'])) {
        if (!(int) $u['is_active']) {
            $error = 'This account has been deactivated. Please contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'    => (int) $u['id'],
                'name'  => $u['name'],
                'email' => $u['email'],
                'role'  => $u['role'],
            ];
            log_activity('LOGIN', 'Signed in');
            redirect(home_for($u['role']));
        }
    } else {
        $error = 'Incorrect email or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= asset('assets/img/logo-icon.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>
<div class="login-page">
    <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>

    <div class="login-box">
        <a class="brand brand-full" href="<?= url('index.php') ?>">
            <?= logo_full_img(230) ?>
        </a>

        <h1>Welcome back</h1>
        <p class="muted">Sign in with your school account to continue.</p>

        <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
        <?php foreach (take_flashes() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" autocomplete="on">
            <?= csrf_field() ?>
            <div class="field" style="margin-bottom:14px">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@school.edu" required autofocus>
            </div>
            <div class="field" style="margin-bottom:18px">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password" placeholder="Your password" required>
                    <button type="button" data-toggle-pw="password">Show</button>
                </div>
            </div>
            <button class="btn btn-block" type="submit">Sign in</button>
        </form>

        <a class="login-back" href="<?= url('index.php') ?>"><?= icon('left', 15) ?> Back to home</a>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
