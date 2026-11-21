<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$q      = trim((string) ($_GET['q'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$role   = $_GET['role'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(user_name LIKE ? OR details LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
if (in_array($role, ['admin', 'lecturer', 'student'], true)) { $where[] = 'role = ?'; $params[] = $role; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'created_at <= ?'; $params[] = $to . ' 23:59:59'; }
$w = implode(' AND ', $where);

/* CSV export of the current filter */
if (isset($_GET['export'])) {
    $rows = qall("SELECT created_at, user_name, role, action, details, ip_address FROM activity_logs WHERE $w ORDER BY created_at DESC, id DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="casm_activity_log_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Time', 'User', 'Role', 'Action', 'Details', 'IP']);
    foreach ($rows as $r) { $r['action'] = action_label($r['action']); fputcsv($out, $r); }
    fclose($out);
    exit;
}

$total = (int) qval("SELECT COUNT(*) FROM activity_logs WHERE $w", $params);
$pg    = paginate($total, 20, (int) ($_GET['page'] ?? 1));
$logs  = qall("SELECT * FROM activity_logs WHERE $w ORDER BY created_at DESC, id DESC LIMIT " . (int) $pg['per'] . ' OFFSET ' . (int) $pg['offset'], $params);
$actions = array_column(qall('SELECT DISTINCT action FROM activity_logs'), 'action');
usort($actions, function ($a, $b) { return strcasecmp(action_label($a), action_label($b)); });
$query = array_filter(['q' => $q, 'action' => $action, 'role' => $role, 'from' => $from, 'to' => $to]);

$pageTitle = 'Activity Logs';
$active = 'logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div><h1>Activity Logs</h1></div>
    <div class="actions"><a class="btn btn-soft" href="?<?= e(http_build_query($query + ['export' => 1])) ?>"><?= icon('download', 18) ?> Export CSV</a></div>
</div>

<form class="filters" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search user or details…">
    <select name="action">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(action_label($a)) ?></option><?php endforeach; ?>
    </select>
    <select name="role">
        <option value="">All roles</option>
        <?php foreach (['admin', 'lecturer', 'student'] as $r): ?><option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($from) ?>" title="From date">
    <input type="date" name="to" value="<?= e($to) ?>" title="To date">
    <button class="btn btn-soft" type="submit"><?= icon('search', 16) ?> Filter</button>
    <?php if ($query): ?><a class="btn btn-ghost" href="<?= url('admin/logs.php') ?>">Reset</a><?php endif; ?>
</form>

<div class="card">
    <?php if (!$logs): ?>
        <div class="empty"><div class="em-ico"><?= icon('activity', 28) ?></div><p>No log entries match your filters.</p></div>
    <?php else: ?>
    <p class="muted small"><?= $total ?> entr<?= $total === 1 ? 'y' : 'ies' ?></p>
    <div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td class="nowrap small"><?= e(fmt_datetime($l['created_at'])) ?></td>
                <td><b><?= e($l['user_name']) ?></b></td>
                <td><?= pill($l['role'] === 'admin' ? 'danger' : ($l['role'] === 'lecturer' ? 'info' : 'primary'), ucfirst($l['role'])) ?></td>
                <td><?= pill('muted', action_label($l['action'])) ?></td>
                <td class="small"><?= e($l['details']) ?></td>
                <td class="small muted"><?= e($l['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= pagination_html($pg, $query) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
