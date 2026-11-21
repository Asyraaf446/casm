<?php
/**
 * Calendar partial.
 * Expects: $calEvents (array), $calCanAdd (bool), $calCourses (array, lecturer only)
 */
$json = json_encode(
    ['events' => $calEvents, 'canAdd' => (bool) $calCanAdd, 'csrf' => csrf_token()],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>
<div class="cal-wrap">
    <div class="card">
        <div class="cal-head">
            <button class="icon-btn" id="calPrev" type="button" aria-label="Previous month"><?= icon('left', 18) ?></button>
            <h2 id="calTitle"></h2>
            <button class="btn btn-soft btn-sm" id="calToday" type="button">Today</button>
            <button class="icon-btn" id="calNext" type="button" aria-label="Next month"><?= icon('right', 18) ?></button>
        </div>
        <div class="cal-legend">
            <span class="l-deadline"><i></i>Assignment due</span>
            <span class="l-quiz"><i></i>Quiz</span>
            <span class="l-exam"><i></i>Exam</span>
            <span class="l-task"><i></i>Task</span>
            <span class="l-assignment"><i></i>Assignment event</span>
            <span class="l-meeting"><i></i>Online meeting</span>
            <span class="l-other"><i></i>Other</span>
        </div>
        <div class="cal-dow"><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div></div>
        <div class="cal-grid" id="calGrid"></div>
    </div>

    <aside class="card cal-side">
        <h3 id="selLabel"></h3>
        <div id="dayList"></div>

        <?php if ($calCanAdd): ?>
            <hr>
            <h4><?= icon('plus', 16) ?> Schedule on <span id="evDateShow"></span></h4>
            <?php if (!$calCourses): ?>
                <p class="muted small">No class has been assigned to you yet, so there is nothing to schedule events for.</p>
            <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_event">
                <input type="hidden" name="event_date" id="evDate" value="">
                <div class="field mb"><label for="ev_course">Class</label>
                    <select id="ev_course" name="class_id" required>
                        <?php foreach ($calCourses as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['code'] . ' · ' . $c['title']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="field mb"><label for="ev_title">Title</label><input type="text" id="ev_title" name="title" maxlength="150" placeholder="e.g. Quiz 2: SQL Joins" required></div>
                <div class="form-grid mb">
                    <div class="field"><label for="ev_type">Type</label>
                        <select id="ev_type" name="event_type">
                            <option value="quiz">Quiz</option><option value="exam">Exam</option><option value="task">Task</option>
                            <option value="assignment">Assignment deadline</option><option value="other">Other</option>
                        </select></div>
                    <div class="field"><label for="ev_time">Time (optional)</label><input type="time" id="ev_time" name="start_time"></div>
                </div>
                <div class="field mb"><label for="ev_desc">Notes (optional)</label><textarea id="ev_desc" name="description" style="min-height:70px"></textarea></div>
                <button class="btn btn-block" type="submit">Add to calendar</button>
                <p class="hint" style="margin:8px 0 0">Students in the class are notified automatically.</p>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </aside>
</div>
<script>window.CASM_CAL = <?= $json ?>;</script>
<script src="<?= url('assets/js/calendar.js') ?>"></script>
