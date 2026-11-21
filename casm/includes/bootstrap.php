<?php
/**
 * CASM - core helpers (database, session, security, formatting)
 * Every page starts with: require_once __DIR__ . '/../includes/bootstrap.php';
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('CASMSESSID');
    session_start();
}

/* ============================================================
   DATABASE
   ============================================================ */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => true,
                ]
            );
        } catch (PDOException $ex) {
            http_response_code(500);
            echo '<div style="font-family:Segoe UI,sans-serif;max-width:560px;margin:12vh auto;padding:28px;'
               . 'border-radius:18px;background:#f1f4f8;color:#0f172a;border:1px solid #e5e7ed">'
               . '<h2 style="margin-top:0">Cannot connect to the database</h2>'
               . '<p>Please check that <b>MySQL is running</b> in XAMPP, that you imported '
               . '<code>database/casm.sql</code> in phpMyAdmin, and that the settings in '
               . '<code>config/config.php</code> are correct.</p></div>';
            exit;
        }
    }
    return $pdo;
}

function qall(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function qone(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function qval(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function qrun(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

function last_id(): int
{
    return (int) db()->lastInsertId();
}

/* ============================================================
   REQUEST / RESPONSE HELPERS
   ============================================================ */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Logo picture tag (adds the file's modified time so a replaced image shows up straight away). */
function logo_img(string $file = LOGO_ICON, string $class = 'brand-logo', int $size = 54): string
{
    $v = @filemtime(__DIR__ . '/../' . $file) ?: 1;
    // width/height are set on the tag itself, so the logo stays small even if the browser still has an old style.css cached
    return '<img class="' . e($class) . '" src="' . e(url($file)) . '?v=' . $v . '" width="' . $size . '" height="' . $size . '" alt="' . e(APP_NAME) . ' logo" style="width:' . $size . 'px;height:' . $size . 'px;max-width:none">';
}

/** Sign the current person out (same steps as logout.php) and start a fresh empty session. */
function end_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
}

/** The full logo picture (icon + name). $width is in pixels; the height follows the picture's own shape. */
function logo_full_img(int $width = 210, string $class = 'brand-full-img'): string
{
    $file = LOGO_FULL;
    $path = __DIR__ . '/../' . $file;
    $v = @filemtime($path) ?: 1;
    $size = @getimagesize($path);
    $h = $size ? (int) round($width * $size[1] / $size[0]) : (int) round($width * 0.36);
    return '<img class="' . e($class) . '" src="' . e(url($file)) . '?v=' . $v . '" width="' . $width . '" height="' . $h . '" alt="' . e(APP_NAME) . ' - Learning Management" style="width:' . $width . 'px;height:' . $h . 'px;max-width:100%">';
}

/** URL of a CSS/JS file with its modified time added, so the browser always loads the newest version. */
function asset(string $path): string
{
    $v = @filemtime(__DIR__ . '/../' . ltrim($path, '/')) ?: 1;
    return url($path) . '?v=' . $v;
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

/**
 * New student ID = year registered + 6 random digits, e.g. 2026773856.
 * Checked against the database so two students never share an ID.
 */
function generate_student_id(?string $year = null): string
{
    $year = $year ?: date('Y');
    for ($i = 0; $i < 100; $i++) {
        $id = $year . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if (!qval('SELECT COUNT(*) FROM users WHERE student_id = ?', [$id])) return $id;
    }
    throw new RuntimeException('Could not generate a unique student ID. Please try again.');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ============================================================
   AUTHENTICATION & ROLES
   ============================================================ */
function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function home_for(string $role): string
{
    return $role . '/dashboard.php';
}

function require_login(): void
{
    if (!user()) {
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        flash('error', 'You do not have permission to open that page.');
        redirect(home_for($_SESSION['user']['role']));
    }
}

/** Fetch a class owned by the logged-in lecturer or bounce back. */
function lecturer_course(int $id): array
{
    $c = qone('SELECT * FROM classes WHERE id = ? AND lecturer_id = ?', [$id, user()['id']]);
    if (!$c) {
        flash('error', 'That class could not be found.');
        redirect('lecturer/courses.php');
    }
    return $c;
}

/** Fetch a class the logged-in student is enrolled in or bounce back. */
function student_course(int $id): array
{
    $c = qone(
        'SELECT c.*, u.name AS lecturer_name FROM classes c
         JOIN users u ON u.id = c.lecturer_id
         JOIN enrollments e ON e.class_id = c.id AND e.student_id = ?
         WHERE c.id = ?',
        [user()['id'], $id]
    );
    if (!$c) {
        flash('error', 'You are not enrolled in that class.');
        redirect('student/courses.php');
    }
    return $c;
}

/* ============================================================
   ACTIVITY LOG + NOTIFICATIONS
   ============================================================ */
function log_activity(string $action, string $details = '', ?array $actor = null): void
{
    $u = $actor ?? user();
    try {
        qrun(
            'INSERT INTO activity_logs (user_id, user_name, role, action, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $u['id'] ?? null,
                $u['name'] ?? 'Guest',
                $u['role'] ?? '-',
                $action,
                mb_substr($details, 0, 255),
                $_SERVER['REMOTE_ADDR'] ?? '',
                date('Y-m-d H:i:s'),
            ]
        );
    } catch (Throwable $t) {
        // never break a page because logging failed
    }
}

/** Plain-English name for an activity-log action code (the code itself is what is stored in the database). */
function action_label(string $code): string
{
    static $map = [
        'LOGIN' => 'Signed in', 'LOGOUT' => 'Signed out', 'CHANGE_PASSWORD' => 'Changed password', 'UPDATE_PROFILE' => 'Updated profile',
        'CREATE_USER' => 'Created user', 'UPDATE_USER' => 'Updated user', 'DELETE_USER' => 'Deleted user',
        'ACTIVATE_USER' => 'Activated user', 'DEACTIVATE_USER' => 'Deactivated user',
        'CREATE_COURSE' => 'Created course', 'UPDATE_COURSE' => 'Updated course', 'DELETE_COURSE' => 'Deleted course',
        'CREATE_CLASS' => 'Created class', 'UPDATE_CLASS' => 'Updated class', 'DELETE_CLASS' => 'Deleted class',
        'ASSIGN_LECTURER' => 'Assigned lecturer', 'ENROLL' => 'Joined class', 'LEAVE_COURSE' => 'Left class', 'REMOVE_STUDENT' => 'Removed student',
        'UPLOAD_MATERIAL' => 'Uploaded material', 'UPDATE_MATERIAL' => 'Updated material', 'DELETE_MATERIAL' => 'Deleted material',
        'CREATE_ASSIGNMENT' => 'Created assignment', 'UPDATE_ASSIGNMENT' => 'Updated assignment', 'DELETE_ASSIGNMENT' => 'Deleted assignment',
        'SUBMIT_ASSIGNMENT' => 'Submitted assignment', 'UPDATE_SUBMISSION' => 'Updated submission', 'DELETE_SUBMISSION' => 'Deleted submission',
        'GRADE_SUBMISSION' => 'Graded submission', 'SEND_REMINDER' => 'Sent reminder',
        'CREATE_EVENT' => 'Created calendar event', 'DELETE_EVENT' => 'Deleted calendar event', 'DELETE_DOUBT' => 'Deleted discussion question',
        'CREATE_MEETING' => 'Scheduled meeting', 'UPDATE_MEETING' => 'Updated meeting', 'DELETE_MEETING' => 'Deleted meeting',
        'START_MEETING' => 'Started meeting', 'JOIN_MEETING' => 'Joined meeting', 'END_MEETING' => 'Ended meeting',
        'CREATE_FORUM_TOPIC' => 'Created forum topic', 'UPDATE_FORUM_TOPIC' => 'Edited forum topic', 'DELETE_FORUM_TOPIC' => 'Deleted forum topic', 'FORUM_REPLY' => 'Replied in forum',
        'CREATE_ATTENDANCE' => 'Created attendance session', 'OPEN_ATTENDANCE' => 'Opened attendance', 'CLOSE_ATTENDANCE' => 'Closed attendance',
        'REOPEN_ATTENDANCE' => 'Reopened attendance', 'TICK_ATTENDANCE' => 'Ticked attendance', 'UPDATE_ATTENDANCE' => 'Updated attendance', 'DELETE_ATTENDANCE' => 'Deleted attendance session',
    ];
    return $map[$code] ?? ucfirst(strtolower(str_replace('_', ' ', $code)));
}

function notify(int $userId, string $title, string $message, ?string $link = null): void
{
    qrun(
        'INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, ?)',
        [$userId, mb_substr($title, 0, 150), mb_substr($message, 0, 300), $link, date('Y-m-d H:i:s')]
    );

    // Also send it by email (queued, sent after the page has loaded)
    try {
        if (mail_enabled()) {
            $to = qone('SELECT name, email, role FROM users WHERE id = ? AND is_active = 1', [$userId]);
            if ($to && ($to['role'] === 'student' || (defined('MAIL_STAFF_TOO') && MAIL_STAFF_TOO))) {
                mail_queue($to['email'], $to['name'], $title, $message, $link);
            }
        }
    } catch (Throwable $t) {
        // an email problem must never break the page
    }
}

function enrolled_student_ids(int $courseId): array
{
    return array_map('intval', array_column(
        qall('SELECT student_id FROM enrollments WHERE class_id = ?', [$courseId]),
        'student_id'
    ));
}

/* ============================================================
   FORMATTING
   ============================================================ */
function fmt_date($s): string
{
    return $s ? date('d M Y', strtotime((string) $s)) : '-';
}

function fmt_datetime($s): string
{
    return $s ? date('d M Y, h:i A', strtotime((string) $s)) : '-';
}

function fmt_time($s): string
{
    return $s ? date('h:i A', strtotime((string) $s)) : '-';
}

function time_to_min(string $t): int
{
    $p = explode(':', $t);
    return ((int) $p[0]) * 60 + (int) ($p[1] ?? 0);
}

function day_name(?int $n): string
{
    $d = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    return $d[(int) $n] ?? '-';
}

function due_in_text(string $dt): string
{
    $diff = strtotime($dt) - time();
    $abs  = abs($diff);
    if ($abs < 3600) {
        $t = max(1, (int) round($abs / 60)) . ' min';
    } elseif ($abs < 86400) {
        $t = (int) round($abs / 3600) . ' hr';
    } else {
        $d = (int) floor($abs / 86400);
        $t = $d . ' day' . ($d > 1 ? 's' : '');
    }
    return $diff >= 0 ? 'in ' . $t : $t . ' ago';
}

function time_ago(string $dt): string
{
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return (int) floor($diff / 60) . ' min ago';
    if ($diff < 86400) return (int) floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return (int) floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    return fmt_date($dt);
}

function initials(string $name): string
{
    $name  = preg_replace('/^(Dr|Mr|Mrs|Ms|Prof)\.?\s+/i', '', trim($name));
    $parts = preg_split('/\s+/', $name);
    $out   = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1));
    if (count($parts) > 1) {
        $out .= mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));
    }
    return $out;
}

function first_name(string $name): string
{
    $name = preg_replace('/^(Dr|Mr|Mrs|Ms|Prof)\.?\s+/i', '', trim($name));
    return explode(' ', $name)[0];
}

/** Letters for new classes: A, B, C ... (after Z: AA, AB ...) */
function class_letter(int $n): string
{
    $n = max(1, $n);
    $out = '';
    while ($n > 0) {
        $n--;
        $out = chr(65 + $n % 26) . $out;
        $n = intdiv($n, 26);
    }
    return $out;
}

/** Next free letter inside a course: A, then B, then C ... */
function next_class_name(int $courseId): string
{
    $used = array_column(qall('SELECT class_name FROM classes WHERE course_id = ?', [$courseId]), 'class_name');
    for ($i = 1; $i < 100; $i++) {
        $l = class_letter($i);
        if (!in_array($l, $used, true)) return $l;
    }
    return 'X' . random_int(100, 999);
}

/** Unique join code for a class */
function new_class_join_code(): string
{
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    } while (qval('SELECT COUNT(*) FROM classes WHERE join_code = ?', [$code]));
    return $code;
}

/** After a course is edited, keep its classes in step (code, title, lecturer). */
function sync_course_classes(int $courseId): void
{
    $c = qone('SELECT code, title, lecturer_id FROM courses WHERE id = ?', [$courseId]);
    if ($c) {
        qrun("UPDATE classes SET code = CONCAT(?, '-', class_name), title = ?, lecturer_id = ? WHERE course_id = ?",
            [$c['code'], $c['title'], $c['lecturer_id'], $courseId]);
    }
}

/** Remove the stored files (notes and submissions) of one class. Database rows are removed by the caller. */
function delete_class_uploads(int $classId): void
{
    foreach (qall('SELECT file_path FROM materials WHERE class_id = ?', [$classId]) as $f) delete_upload($f['file_path']);
    foreach (qall('SELECT s.file_path FROM submissions s JOIN assignments a ON a.id = s.assignment_id WHERE a.class_id = ?', [$classId]) as $f) delete_upload($f['file_path']);
}

function course_colors(): array
{
    return ['lavender' => 'Lavender', 'sky' => 'Sky', 'mint' => 'Mint', 'peach' => 'Peach', 'rose' => 'Rose', 'sun' => 'Sunshine'];
}

function pill(string $kind, string $text): string
{
    return '<span class="pill pill-' . e($kind) . '">' . e($text) . '</span>';
}

/** Status of a submission relative to the deadline: pending | submitted | late */
function submission_status(?string $submittedAt, string $due): string
{
    if (!$submittedAt) {
        return 'pending';
    }
    return strtotime($submittedAt) > strtotime($due) ? 'late' : 'submitted';
}

function status_pill(string $status): string
{
    $map = ['submitted' => ['success', 'Submitted'], 'late' => ['warn', 'Late'], 'pending' => ['danger', 'Pending'], 'graded' => ['info', 'Graded']];
    [$k, $t] = $map[$status] ?? ['muted', ucfirst($status)];
    return pill($k, $t);
}

/* ============================================================
   FILE UPLOADS  (stored with random names, served by download.php)
   ============================================================ */
const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'gif', 'md', 'sql', 'java', 'py'];

function upload_error_text(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_NO_FILE:  return 'Please choose a file to upload.';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return 'The file is too large (maximum ' . MAX_UPLOAD_MB . ' MB).';
        default: return 'The file could not be uploaded. Please try again.';
    }
}

/** @return array{0:string,1:string} [relative stored path, original filename] */
function save_upload(array $file, string $subdir): array
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text($err));
    }
    if ($file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        throw new RuntimeException('The file is too large (maximum ' . MAX_UPLOAD_MB . ' MB).');
    }
    $orig = basename((string) $file['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        throw new RuntimeException('This file type is not allowed. Allowed: ' . implode(', ', ALLOWED_EXT) . '.');
    }
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = bin2hex(random_bytes(16));
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('The file could not be saved on the server.');
    }
    return [$subdir . '/' . $stored, $orig];
}

/**
 * Save a profile picture: validates it is a real image, crops it to a square,
 * resizes it to 320x320 and stores it as a JPEG (this also strips any hidden
 * data inside the original file).
 * @return string relative stored path, e.g. "avatars/ab12....jpg"
 */
function save_avatar(array $file): string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('The picture is too large. Please choose a smaller one (under 3 MB).');
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text($err));
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('The picture is too large. Please choose one under 3 MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Please choose a JPG, PNG, GIF or WEBP picture.');
    }
    [$w, $h] = $info;
    if ($w < 50 || $h < 50) {
        throw new RuntimeException('The picture is too small. Please use one at least 50 x 50 pixels.');
    }
    if ($w * $h > 25000000) {
        throw new RuntimeException('The picture dimensions are too large. Please use a smaller photo.');
    }
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('Image processing (GD) is not enabled in PHP. In XAMPP open php.ini and remove the ";" before extension=gd, then restart Apache.');
    }

    switch ($info[2]) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($file['tmp_name']); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($file['tmp_name']);  break;
        case IMAGETYPE_GIF:  $src = @imagecreatefromgif($file['tmp_name']);  break;
        default:             $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false;
    }
    if (!$src) {
        throw new RuntimeException('That picture could not be read. Please try a different file.');
    }

    $side = min($w, $h);
    $sx   = (int) (($w - $side) / 2);
    $sy   = (int) (($h - $side) / 2);
    $size = 320;
    $dst  = imagecreatetruecolor($size, $size);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // white behind transparent PNG/GIF
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);

    $dir = UPLOAD_DIR . '/avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = bin2hex(random_bytes(16)) . '.jpg';
    $ok   = imagejpeg($dst, $dir . '/' . $name, 88);
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok) {
        throw new RuntimeException('The picture could not be saved on the server.');
    }
    return 'avatars/' . $name;
}

/** Round avatar: the user's picture if they have one, otherwise their initials. */
function avatar_html(string $name, ?string $avatar, int $userId, string $style = ''): string
{
    $st = $style !== '' ? ' style="' . e($style) . '"' : '';
    if ($avatar) {
        $v = substr(md5($avatar), 0, 8);
        return '<span class="avatar has-img"' . $st . '><img src="' . e(url('avatar.php?id=' . $userId . '&v=' . $v)) . '" alt="' . e($name) . '" loading="lazy"></span>';
    }
    return '<span class="avatar"' . $st . '>' . e(initials($name)) . '</span>';
}

function delete_upload(?string $rel): void
{
    if (!$rel || str_starts_with(basename($rel), 'sample_')) {
        return;
    }
    $base = realpath(UPLOAD_DIR);
    $path = realpath(UPLOAD_DIR . '/' . $rel);
    if ($base && $path && str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $base)) && is_file($path)) {
        @unlink($path);
    }
}

/* ============================================================
   SMART WORKLOAD BALANCER
   Students (of $courseId) who already have 2+ OTHER assignments
   due on the same calendar day.
   ============================================================ */
function workload_conflicts(int $courseId, string $date, int $excludeAssignmentId = 0): array
{
    return qall(
        'SELECT u.id, u.name, COUNT(a.id) AS total
           FROM enrollments e1
           JOIN users u        ON u.id = e1.student_id
           JOIN enrollments e2 ON e2.student_id = e1.student_id
           JOIN assignments a  ON a.class_id = e2.class_id
          WHERE e1.class_id = ? AND DATE(a.due_date) = ? AND a.id <> ?
          GROUP BY u.id, u.name
         HAVING COUNT(a.id) >= 2
          ORDER BY total DESC, u.name',
        [$courseId, $date, $excludeAssignmentId]
    );
}

/* ============================================================
   ONLINE MEETINGS
   ============================================================ */

/** True when the meetings tables exist (so pages still work before the database update is run). */
function meetings_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            $ok = count(qall("SHOW TABLES LIKE 'meeting%'")) >= 2;
        } catch (Throwable $t) {
            $ok = false;
        }
    }
    return $ok;
}

/** A live meeting that was never ended is closed automatically after 6 hours. */
function expire_old_meetings(): void
{
    qrun(
        "UPDATE meetings SET status = 'ended', ended_at = DATE_ADD(started_at, INTERVAL 6 HOUR)
          WHERE status = 'live' AND started_at < ?",
        [date('Y-m-d H:i:s', time() - 6 * 3600)]
    );
}

/** Internal unique id stored with each meeting (the video itself is a Google Meet link). */
function new_room_code(string $courseCode): string
{
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $courseCode));
    do {
        $code = 'CASM-' . $slug . '-' . bin2hex(random_bytes(5));
    } while (qval('SELECT COUNT(*) FROM meetings WHERE room_code = ?', [$code]));
    return $code;
}

/** Only real Google Meet room links are accepted, e.g. https://meet.google.com/abc-defg-hij */
function valid_meeting_link(string $link): bool
{
    if (strlen($link) > 255 || preg_match('/\s/', $link)) {
        return false;
    }
    if (!preg_match('#^https://meet\.google\.com/(?!(?:new|landing|about)(?:[/?]|$))([A-Za-z0-9_-]{3,60}(?:/[A-Za-z0-9_-]{1,60})?)(\?[A-Za-z0-9_=&%.-]*)?$#', $link)) {
        return false;
    }
    return filter_var($link, FILTER_VALIDATE_URL) !== false;
}

/** The Google Meet link of a meeting ('' when the lecturer has not added one). */
function meeting_link(array $m): string
{
    return !empty($m['custom_link']) && valid_meeting_link($m['custom_link']) ? $m['custom_link'] : '';
}

/** live | upcoming | ended | missed  (missed = scheduled but never started, more than 3 hours ago) */
function meeting_state(array $m): string
{
    if ($m['status'] === 'live') return 'live';
    if ($m['status'] === 'ended') return 'ended';
    return strtotime($m['scheduled_at']) >= time() - 3 * 3600 ? 'upcoming' : 'missed';
}

function meeting_pill(string $state): string
{
    $map = ['live' => ['live', 'Live now'], 'upcoming' => ['info', 'Scheduled'], 'ended' => ['muted', 'Ended'], 'missed' => ['warn', 'Not held']];
    [$k, $t] = $map[$state] ?? ['muted', ucfirst($state)];
    return pill($k, $t);
}

/** The meeting, but only if this lecturer owns the class or this student is enrolled in it. */
function meeting_for_user(int $id, array $me): ?array
{
    $m = qone(
        'SELECT m.*, c.code, c.title AS course_title, c.color, c.lecturer_id
           FROM meetings m JOIN classes c ON c.id = m.class_id WHERE m.id = ?',
        [$id]
    );
    if (!$m) {
        return null;
    }
    if ($me['role'] === 'lecturer') {
        return (int) $m['lecturer_id'] === (int) $me['id'] ? $m : null;
    }
    if ($me['role'] === 'student') {
        return qval('SELECT COUNT(*) FROM enrollments WHERE class_id = ? AND student_id = ?', [$m['class_id'], $me['id']]) ? $m : null;
    }
    return null;
}

function record_attendance(int $meetingId, int $userId): void
{
    qrun(
        'INSERT INTO meeting_attendance (meeting_id, user_id, joined_at) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE meeting_id = meeting_id',
        [$meetingId, $userId, date('Y-m-d H:i:s')]
    );
}

/* ============================================================
   CLASS ATTENDANCE  (the lecturer opens a session, students tick it)
   Table attendance_sessions = one row per class session
   Table attendance_records  = one row per student per session
   ============================================================ */
const ATTENDANCE_MIN_RATE = 80;   // students below this percentage are flagged as "Low"

/** True when the attendance tables exist (so pages still work before the database update is run). */
function attendance_ready(): bool
{
    static $ok = null;
    if ($ok === null) {
        try {
            $ok = count(qall("SHOW TABLES LIKE 'attendance%'")) >= 2;
        } catch (Throwable $t) {
            $ok = false;
        }
    }
    return $ok;
}

/** The three attendance statuses: value stored in the database => label shown on screen. */
function attendance_statuses(): array
{
    return ['present' => 'Present', 'absent' => 'Absent', 'absent_permission' => 'Absent with permission'];
}

/** Coloured pill for a record status. null (no record yet) shows "Not marked". */
function attendance_pill(?string $status): string
{
    $map = [
        'present'           => ['att-present', 'Present'],
        'absent'            => ['att-absent', 'Absent'],
        'absent_permission' => ['att-perm', 'Absent with permission'],
    ];
    if ($status === null || !isset($map[$status])) {
        return pill('muted', 'Not marked');
    }
    return pill($map[$status][0], $map[$status][1]);
}

/** Pill for the state of a session: pending | open | closed */
function attendance_state_pill(string $status): string
{
    $map = ['open' => ['live', 'Open'], 'pending' => ['info', 'Not opened yet'], 'closed' => ['muted', 'Closed']];
    [$k, $t] = $map[$status] ?? ['muted', ucfirst($status)];
    return pill($k, $t);
}

/** Attendance rate in percent = present / all recorded sessions. */
function attendance_rate(int $present, int $total): int
{
    return $total > 0 ? (int) round($present / $total * 100) : 0;
}

/**
 * Close a session. Every enrolled student who did not tick (and was not marked by the lecturer)
 * is recorded as Absent, so the reports are complete.
 */
function close_attendance_session(int $id): void
{
    $s = qone('SELECT id, class_id FROM attendance_sessions WHERE id = ?', [$id]);
    if (!$s) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    qrun("UPDATE attendance_sessions SET status = 'closed', closed_at = ? WHERE id = ?", [$now, $id]);
    qrun(
        "INSERT INTO attendance_records (session_id, student_id, status, marked_by, marked_at)
         SELECT ?, e.student_id, 'absent', 'system', ? FROM enrollments e WHERE e.class_id = ?
         ON DUPLICATE KEY UPDATE session_id = session_id",
        [$id, $now, (int) $s['class_id']]
    );
}

/** A session that was left open is closed automatically 24 hours after it was opened. */
function expire_old_attendance(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $rows = qall(
        "SELECT id FROM attendance_sessions WHERE status = 'open' AND opened_at < ?",
        [date('Y-m-d H:i:s', time() - 24 * 3600)]
    );
    foreach ($rows as $r) {
        close_attendance_session((int) $r['id']);
    }
}

/** The session, but only if the person may see it: admin = any, lecturer = own class, student = enrolled (and opened). */
function attendance_session_for_user(int $id, array $me): ?array
{
    $s = qone(
        'SELECT s.*, c.code, c.title AS course_title, c.color, c.lecturer_id, u.name AS lecturer_name
           FROM attendance_sessions s
           JOIN classes c ON c.id = s.class_id
           JOIN users u ON u.id = c.lecturer_id
          WHERE s.id = ?',
        [$id]
    );
    if (!$s) {
        return null;
    }
    if ($me['role'] === 'admin') {
        return $s;
    }
    if ($me['role'] === 'lecturer') {
        return (int) $s['lecturer_id'] === (int) $me['id'] ? $s : null;
    }
    if ($me['role'] === 'student' && $s['status'] !== 'pending') {
        return qval('SELECT COUNT(*) FROM enrollments WHERE class_id = ? AND student_id = ?', [$s['class_id'], $me['id']]) ? $s : null;
    }
    return null;
}

/** Roll-call for one session: every enrolled student with their record (if any). */
function attendance_roll(int $sessionId, int $courseId): array
{
    return qall(
        'SELECT u.id, u.name, u.email, u.avatar, r.status, r.reason, r.marked_by, r.marked_at
           FROM enrollments e
           JOIN users u ON u.id = e.student_id
           LEFT JOIN attendance_records r ON r.session_id = ? AND r.student_id = u.id
          WHERE e.class_id = ?
          ORDER BY u.name',
        [$sessionId, $courseId]
    );
}

/** Count a roll-call by status (plus "none" for students without a record). */
function attendance_counts(array $roll): array
{
    $c = ['present' => 0, 'absent' => 0, 'absent_permission' => 0, 'none' => 0];
    foreach ($roll as $r) {
        $k = $r['status'] ?? 'none';
        $c[isset($c[$k]) ? $k : 'none']++;
    }
    return $c;
}

/* ============================================================
   CALENDAR DATA  (assignment deadlines + lecturer events + meetings)
   ============================================================ */
function calendar_events(array $me): array
{
    if ($me['role'] === 'lecturer') {
        $scope = 'c.lecturer_id = ?';
        $p = [$me['id']];
    } elseif ($me['role'] === 'student') {
        $scope = 'c.id IN (SELECT class_id FROM enrollments WHERE student_id = ?)';
        $p = [$me['id']];
    } else {
        $scope = '1 = 1';
        $p = [];
    }

    $out = [];
    foreach (qall("SELECT a.id, a.title, a.due_date, c.code FROM assignments a JOIN classes c ON c.id = a.class_id WHERE $scope", $p) as $a) {
        $out[] = [
            'id' => (int) $a['id'], 'kind' => 'deadline',
            'date' => substr($a['due_date'], 0, 10), 'time' => substr($a['due_date'], 11, 5),
            'title' => 'Due: ' . $a['title'], 'type' => 'deadline', 'course' => $a['code'],
            'desc' => 'Assignment deadline', 'own' => false,
            'url' => $me['role'] === 'admin' ? '' : url($me['role'] . '/assignment_view.php?id=' . $a['id']),
        ];
    }
    foreach (qall("SELECT ev.*, c.code FROM events ev JOIN classes c ON c.id = ev.class_id WHERE $scope", $p) as $ev) {
        $out[] = [
            'id' => (int) $ev['id'], 'kind' => 'event',
            'date' => $ev['event_date'], 'time' => $ev['start_time'] ? substr($ev['start_time'], 0, 5) : '',
            'title' => $ev['title'], 'type' => $ev['event_type'], 'course' => $ev['code'],
            'desc' => (string) $ev['description'],
            'own' => $me['role'] === 'lecturer' && (int) $ev['created_by'] === (int) $me['id'],
            'url' => '',
        ];
    }
    if (meetings_ready()) {
        foreach (qall("SELECT m.id, m.title, m.scheduled_at, c.code FROM meetings m JOIN classes c ON c.id = m.class_id WHERE $scope", $p) as $m) {
            $out[] = [
                'id' => (int) $m['id'], 'kind' => 'meeting',
                'date' => substr($m['scheduled_at'], 0, 10), 'time' => substr($m['scheduled_at'], 11, 5),
                'title' => 'Meeting: ' . $m['title'], 'type' => 'meeting', 'course' => $m['code'],
                'desc' => 'Online meeting', 'own' => false,
                'url' => $me['role'] === 'lecturer' ? url('lecturer/meeting_view.php?id=' . $m['id'])
                       : ($me['role'] === 'student' ? url('student/meetings.php') : ''),
            ];
        }
    }
    return $out;
}

/* ============================================================
   SMALL UI HELPERS
   ============================================================ */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}

function pagination_html(array $p, array $query = []): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $q = http_build_query(array_merge($query, ['page' => $i]));
        $html .= '<a class="' . ($i === $p['page'] ? 'current' : '') . '" href="?' . e($q) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** SVG donut chart. $segments = [[label, value, colour], ...] */
function donut_svg(array $segments, string $centerTop = '', string $centerBottom = ''): string
{
    $total = array_sum(array_column($segments, 1));
    $svg   = '<svg class="donut" viewBox="0 0 42 42" role="img" aria-label="Submission chart">';
    $svg  .= '<circle cx="21" cy="21" r="15.91549431" fill="none" stroke="#e6ebf3" stroke-width="5"></circle>';
    if ($total > 0) {
        $cum = 0.0;
        foreach ($segments as [$label, $value, $color]) {
            if ($value <= 0) {
                continue;
            }
            $pct = $value / $total * 100;
            $svg .= sprintf(
                '<circle cx="21" cy="21" r="15.91549431" fill="none" stroke="%s" stroke-width="5" stroke-dasharray="%.3f %.3f" stroke-dashoffset="%.3f"><title>%s: %d</title></circle>',
                e($color), $pct, 100 - $pct, 100 - $cum + 25, e($label), $value
            );
            $cum += $pct;
        }
    }
    $svg .= '<text x="21" y="21.5" text-anchor="middle" class="donut-num">' . e($centerTop) . '</text>';
    $svg .= '<text x="21" y="26" text-anchor="middle" class="donut-lbl">' . e($centerBottom) . '</text>';
    return $svg . '</svg>';
}

/** Minimal inline icon set (Feather-style strokes). */
function icon(string $name, int $size = 20): string
{
    static $paths = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/>',
        'book'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="3"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'help'     => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="3" y1="20" x2="21" y2="20"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit'     => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'upload'   => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'up'       => '<polyline points="18 15 12 9 6 15"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'alert'    => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'send'     => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'left'     => '<polyline points="15 18 9 12 15 6"/>',
        'right'    => '<polyline points="9 18 15 12 9 6"/>',
        'x'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'menu'     => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'star'     => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'key'      => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
        'forum'    => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="13" y2="13"/>',
        'video'    => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="3" ry="3"/>',
        'back'     => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'clipboard'=> '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><polyline points="9 14 11 16 15 12"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}
