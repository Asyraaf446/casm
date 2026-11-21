<?php
/**
 * Smart Workload Balancer - live check used by the assignment form.
 * GET ?class_id=1&date=2026-11-20&aid=0  ->  {"conflicts":[{"name":"...","total":2}]}
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$me = user();
if (!$me || $me['role'] !== 'lecturer') {
    http_response_code(403);
    echo '{"conflicts":[]}';
    exit;
}

$cid  = (int) ($_GET['class_id'] ?? 0);
$date = (string) ($_GET['date'] ?? '');
$aid  = (int) ($_GET['aid'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !qval('SELECT COUNT(*) FROM classes WHERE id = ? AND lecturer_id = ?', [$cid, $me['id']])) {
    echo '{"conflicts":[]}';
    exit;
}

$out = [];
foreach (workload_conflicts($cid, $date, $aid) as $r) {
    $out[] = ['name' => $r['name'], 'total' => (int) $r['total']];
}
echo json_encode(['conflicts' => $out]);
