/* CASM - interactive calendar (shared by lecturer and student) */
(function () {
    var cfg = window.CASM_CAL;
    if (!cfg) return;

    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    var TYPE_LABEL = { deadline: 'Deadline', assignment: 'Assignment', quiz: 'Quiz', exam: 'Exam', task: 'Task', other: 'Event', meeting: 'Online meeting' };

    var grid = document.getElementById('calGrid');
    var title = document.getElementById('calTitle');
    var selLabel = document.getElementById('selLabel');
    var dayList = document.getElementById('dayList');
    var evDate = document.getElementById('evDate');
    var evDateShow = document.getElementById('evDateShow');

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function nice(s) {
        var p = s.split('-');
        return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }
    function hm(t) {
        if (!t) return '';
        var p = t.split(':'), h = +p[0], ap = h >= 12 ? 'PM' : 'AM';
        return ((h % 12) || 12) + ':' + p[1] + ' ' + ap;
    }
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined) n.textContent = text;
        return n;
    }

    var byDate = {};
    cfg.events.forEach(function (ev) { (byDate[ev.date] = byDate[ev.date] || []).push(ev); });
    Object.keys(byDate).forEach(function (k) { byDate[k].sort(function (a, b) { return (a.time || '').localeCompare(b.time || ''); }); });

    var today = new Date();
    var todayStr = ymd(today);
    var view = new Date(today.getFullYear(), today.getMonth(), 1);
    var selected = todayStr;

    function render() {
        title.textContent = MONTHS[view.getMonth()] + ' ' + view.getFullYear();
        grid.innerHTML = '';

        var first = new Date(view.getFullYear(), view.getMonth(), 1);
        var offset = (first.getDay() + 6) % 7;            // Monday first
        var start = new Date(view.getFullYear(), view.getMonth(), 1 - offset);

        for (var i = 0; i < 42; i++) {
            var d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
            var key = ymd(d);
            var cell = el('button', 'cal-cell');
            cell.type = 'button';
            if (d.getMonth() !== view.getMonth()) cell.classList.add('other');
            if (key === todayStr) cell.classList.add('today');
            if (key === selected) cell.classList.add('selected');
            cell.appendChild(el('span', 'num', String(d.getDate())));

            var list = byDate[key] || [];
            list.slice(0, 2).forEach(function (ev) {
                var chip = el('span', 'chip t-' + ev.type, ev.title.replace(/^Due:\s*/, ''));
                chip.title = ev.title + (ev.time ? ' · ' + hm(ev.time) : '');
                cell.appendChild(chip);
            });
            if (list.length > 2) cell.appendChild(el('span', 'more', '+' + (list.length - 2) + ' more'));

            (function (k) {
                cell.addEventListener('click', function () {
                    selected = k;
                    var p = k.split('-');
                    if (+p[1] - 1 !== view.getMonth()) view = new Date(+p[0], +p[1] - 1, 1);
                    render();
                });
            })(key);
            grid.appendChild(cell);
        }
        renderDay();
    }

    function renderDay() {
        selLabel.textContent = nice(selected);
        if (evDate) evDate.value = selected;
        if (evDateShow) evDateShow.textContent = nice(selected);
        dayList.innerHTML = '';

        var list = byDate[selected] || [];
        if (!list.length) {
            dayList.appendChild(el('p', 'muted', 'Nothing scheduled on this day.'));
            return;
        }
        list.forEach(function (ev) {
            var box = el('div', 'day-ev t-' + ev.type);
            var row = el('div', 'row');
            var left = el('div');

            var name;
            if (ev.url) {
                name = document.createElement('a');
                name.href = ev.url;
                name.className = 'b-link';
                name.style.color = 'inherit';
                name.style.fontWeight = '800';
                name.textContent = ev.title;
            } else {
                name = el('b', '', ev.title);
            }
            left.appendChild(name);
            left.appendChild(el('small', '', (TYPE_LABEL[ev.type] || 'Event') + ' · ' + ev.course + (ev.time ? ' · ' + hm(ev.time) : '')));
            if (ev.desc && ev.desc !== 'Assignment deadline') {
                var d = el('div', 'small', ev.desc);
                d.style.marginTop = '4px';
                left.appendChild(d);
            }
            row.appendChild(left);

            if (cfg.canAdd && ev.own && ev.kind === 'event') {
                var f = document.createElement('form');
                f.method = 'post';
                f.className = 'inline-form';
                f.setAttribute('data-confirm', 'Delete this event?');
                f.addEventListener('submit', function (e) { if (!window.confirm('Delete this event?')) e.preventDefault(); });
                [['csrf', cfg.csrf], ['action', 'delete_event'], ['event_id', ev.id]].forEach(function (p) {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = p[0]; i.value = p[1];
                    f.appendChild(i);
                });
                var b = el('button', 'btn btn-danger btn-sm', 'Delete');
                b.type = 'submit';
                f.appendChild(b);
                row.appendChild(f);
            }
            box.appendChild(row);
            dayList.appendChild(box);
        });
    }

    document.getElementById('calPrev').addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); render(); });
    document.getElementById('calNext').addEventListener('click', function () { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); render(); });
    document.getElementById('calToday').addEventListener('click', function () {
        view = new Date(today.getFullYear(), today.getMonth(), 1);
        selected = todayStr;
        render();
    });

    render();
})();
