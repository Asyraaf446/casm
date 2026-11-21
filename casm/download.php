<?php
/**
 * Permission-checked file download.
 *   download.php?type=material&id=1
 *   download.php?type=submission&id=1
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$me   = user();
$type = $_GET['type'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);
$file = null;

if ($type === 'material') {
    $m = qone('SELECT m.*, c.lecturer_id FROM materials m JOIN classes c ON c.id = m.class_id WHERE m.id = ?', [$id]);
    if ($m) {
        $allowed = $me['role'] === 'admin'
            || ($me['role'] === 'lecturer' && (int) $m['lecturer_id'] === (int) $me['id'])
            || ($me['role'] === 'student' && qval('SELECT COUNT(*) FROM enrollments WHERE class_id = ? AND student_id = ?', [$m['class_id'], $me['id']]));
        if ($allowed) {
            $file = $m;
        }
    }
} elseif ($type === 'submission') {
    $s = qone(
        'SELECT s.*, c.lecturer_id FROM submissions s
           JOIN assignments a ON a.id = s.assignment_id
           JOIN classes c ON c.id = a.class_id WHERE s.id = ?',
        [$id]
    );
    if ($s) {
        $allowed = $me['role'] === 'admin'
            || ($me['role'] === 'lecturer' && (int) $s['lecturer_id'] === (int) $me['id'])
            || ($me['role'] === 'student' && (int) $s['student_id'] === (int) $me['id']);
        if ($allowed) {
            $file = $s;
        }
    }
}

$base = realpath(UPLOAD_DIR);
$path = $file ? realpath(UPLOAD_DIR . '/' . $file['file_path']) : false;

if (!$file || !$base || !$path || strpos(str_replace('\\', '/', $path), str_replace('\\', '/', $base)) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('File not found, or you do not have permission to open it.');
}

$name = preg_replace('/[^\w.\- ()]+/u', '_', $file['original_name']);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
