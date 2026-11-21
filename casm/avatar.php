<?php
/**
 * Serves a user's profile picture.   avatar.php?id=4
 * Pictures live in uploads/avatars/ (blocked from direct access), so they are
 * delivered here - and only to people who are signed in.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id  = (int) ($_GET['id'] ?? 0);
$rel = qval('SELECT avatar FROM users WHERE id = ?', [$id]);

$base = realpath(UPLOAD_DIR);
$path = $rel ? realpath(UPLOAD_DIR . '/' . $rel) : false;

if (!$rel || !$base || !$path || strpos(str_replace('\\', '/', $path), str_replace('\\', '/', $base) . '/avatars/') !== 0 || !is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
