<?php
/**
 * Weekly timetable partial.
 * Expects $ttCourses: rows with code, title, day_of_week, start_time, end_time, room, color, extra (string)
 */
$hourPx    = 64;
$scheduled = array_values(array_filter($ttCourses, function ($c) {
    return !empty($c['day_of_week']) && !empty($c['start_time']) && !empty($c['end_time']);
}));
$unscheduled = array_values(array_filter($ttCourses, function ($c) {
    return empty($c['day_of_week']) || empty($c['start_time']) || empty($c['end_time']);
}));

$startH = 8;
$endH   = 18;
$maxDay = 5;
foreach ($scheduled as $c) {
    $startH = min($startH, (int) floor(time_to_min($c['start_time']) / 60));
    $endH   = max($endH, (int) ceil(time_to_min($c['end_time']) / 60));
    $maxDay = max($maxDay, (int) $c['day_of_week']);
}
$days     = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
$todayN   = (int) date('N');
$bodyH    = ($endH - $startH) * $hourPx;
?>
<?php if (!$ttCourses): ?>
    <div class="card"><div class="empty"><div class="em-ico"><?= icon('clock', 28) ?></div><p>No classes to show yet.</p></div></div>
<?php else: ?>
<div class="card">
    <div class="tt-scroll">
        <div class="tt" style="--cols:<?= $maxDay ?>;--hh:<?= $hourPx ?>px">
            <div class="tt-head">
                <div></div>
                <?php for ($d = 1; $d <= $maxDay; $d++): ?>
                    <div class="<?= $d === $todayN ? 'today' : '' ?>"><?= $days[$d] ?></div>
                <?php endfor; ?>
            </div>
            <div class="tt-body" style="margin-top:8px">
                <div class="tt-times">
                    <?php for ($h = $startH; $h < $endH; $h++): ?><div><?= date('g A', strtotime(sprintf('%02d:00', $h))) ?></div><?php endfor; ?>
                </div>
                <?php for ($d = 1; $d <= $maxDay; $d++): ?>
                    <div class="tt-col <?= $d === $todayN ? 'today' : '' ?>" style="height:<?= $bodyH ?>px">
                        <?php foreach ($scheduled as $c): if ((int) $c['day_of_week'] !== $d) continue;
                            $s = time_to_min($c['start_time']); $en = time_to_min($c['end_time']);
                            $top = ($s - $startH * 60) / 60 * $hourPx + 2;
                            $h   = max(36, ($en - $s) / 60 * $hourPx - 4); ?>
                            <div class="tt-block c-<?= e($c['color']) ?>" style="top:<?= round($top) ?>px;height:<?= round($h) ?>px">
                                <b><?= e($c['code']) ?> · <?= e($c['title']) ?></b>
                                <span><?= e(fmt_time($c['start_time'])) ?> – <?= e(fmt_time($c['end_time'])) ?></span>
                                <?php if (!empty($c['room'])): ?><span><?= e($c['room']) ?></span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt">
    <div class="card-head"><h2>Active subjects</h2><span class="pill pill-primary"><?= count($ttCourses) ?> class<?= count($ttCourses) > 1 ? 'es' : '' ?></span></div>
    <div class="grid cols-2">
        <?php foreach ($ttCourses as $c): ?>
            <div class="item c-<?= e($c['color']) ?>">
                <span class="ico"><?= icon('book', 20) ?></span>
                <span class="grow">
                    <b><?= e($c['code']) ?> · <?= e($c['title']) ?></b>
                    <small><?= e($c['extra'] ?? '') ?>
                        <?php if (!empty($c['day_of_week']) && !empty($c['start_time'])): ?>
                            · <?= e(day_name((int) $c['day_of_week'])) ?>, <?= e(fmt_time($c['start_time'])) ?>–<?= e(fmt_time($c['end_time'])) ?>
                        <?php else: ?> · schedule not set<?php endif; ?>
                    </small>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($unscheduled): ?><p class="hint" style="margin-top:12px">Classes without a set time are not drawn on the timetable.</p><?php endif; ?>
</div>
<?php endif; ?>
