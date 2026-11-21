/* CASM - small UI helpers */
document.addEventListener('DOMContentLoaded', function () {

    /* Back button: previous page if there is one on this site, otherwise the dashboard (the link's own href) */
    var backBtn = document.getElementById('backBtn');
    if (backBtn) backBtn.addEventListener('click', function (ev) {
        var ref = document.referrer;
        if (window.history.length > 1 && ref && ref.indexOf(window.location.origin) === 0) {
            ev.preventDefault();
            window.history.back();
        }
    });

    /* mobile sidebar */
    var toggle = document.getElementById('menuToggle');
    var overlay = document.getElementById('overlay');
    if (toggle && document.body.classList.contains('nav-collapsed')) toggle.setAttribute('aria-expanded', 'false');
    if (toggle) toggle.addEventListener('click', function () {
        if (window.innerWidth > 860) {            /* computer: close / open the side menu and remember it */
            var closed = document.body.classList.toggle('nav-collapsed');
            try { localStorage.setItem('casmNav', closed ? 'closed' : 'open'); } catch (e) {}
            toggle.setAttribute('aria-expanded', closed ? 'false' : 'true');
        } else {                                   /* phone: slide the menu over the page */
            document.body.classList.toggle('nav-open');
        }
    });
    if (overlay) overlay.addEventListener('click', function () { document.body.classList.remove('nav-open'); });

    /* confirm before dangerous actions: <form data-confirm="Are you sure?"> */
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
        });
    });

    /* fade out flash messages */
    document.querySelectorAll('.flash').forEach(function (el) {
        setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 600); }, 6500);
    });

    /* show / hide password */
    document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-pw'));
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.textContent = input.type === 'password' ? 'Show' : 'Hide';
        });
    });

    /* live preview of a chosen profile picture */
    document.querySelectorAll('[data-avatar-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var box = document.getElementById(input.getAttribute('data-avatar-preview'));
            var file = input.files && input.files[0];
            if (!box || !file || !/^image\//.test(file.type)) return;
            var holder = box.querySelector('.avatar');
            if (!holder) return;
            var url = URL.createObjectURL(file);
            holder.classList.add('has-img');
            holder.innerHTML = '';
            var img = document.createElement('img');
            img.src = url;
            img.alt = 'Preview';
            holder.appendChild(img);
        });
    });

    /* Smart Workload Balancer: live check while the lecturer picks a due date */
    var box = document.getElementById('workloadBox');
    var due = document.getElementById('due_date');
    var course = document.getElementById('class_id');
    if (box && due && course) {
        var endpoint = box.getAttribute('data-endpoint');
        var aid = box.getAttribute('data-aid') || '0';
        var timer = null;

        var check = function () {
            var day = (due.value || '').substring(0, 10);
            if (!day || !course.value) { box.innerHTML = ''; return; }
            fetch(endpoint + '?class_id=' + encodeURIComponent(course.value) + '&date=' + encodeURIComponent(day) + '&aid=' + encodeURIComponent(aid), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    box.innerHTML = '';
                    if (!data.conflicts || !data.conflicts.length) return;

                    var wrap = document.createElement('div');
                    wrap.className = 'notice';
                    var body = document.createElement('div');
                    var title = document.createElement('b');
                    title.textContent = 'Warning: High student workload detected on this date. Suggest moving deadline.';
                    body.appendChild(title);
                    var sub = document.createElement('span');
                    sub.textContent = data.conflicts.length + ' student(s) already have 2 or more assignments due on ' + day + ':';
                    body.appendChild(sub);
                    var ul = document.createElement('ul');
                    data.conflicts.slice(0, 6).forEach(function (c) {
                        var li = document.createElement('li');
                        li.textContent = c.name + ' (' + c.total + ' other assignments)';
                        ul.appendChild(li);
                    });
                    if (data.conflicts.length > 6) {
                        var more = document.createElement('li');
                        more.textContent = '…and ' + (data.conflicts.length - 6) + ' more';
                        ul.appendChild(more);
                    }
                    body.appendChild(ul);
                    wrap.appendChild(body);
                    box.appendChild(wrap);
                })
                .catch(function () { box.innerHTML = ''; });
        };

        var schedule = function () { clearTimeout(timer); timer = setTimeout(check, 250); };
        due.addEventListener('change', schedule);
        course.addEventListener('change', schedule);
        check();
    }
});


/* Copy buttons: <button data-copy="text"> copies the text to the clipboard */
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-copy]') : null;
    if (!btn) return;
    var text = btn.getAttribute('data-copy');
    var done = function () {
        var old = btn.getAttribute('data-label') || btn.textContent;
        btn.setAttribute('data-label', old);
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = old; }, 1600);
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () {});
    } else {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
    }
});

/* Join buttons that open a new tab: refresh this page shortly after so the status updates */
document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f && f.classList && f.classList.contains('js-join') && f.target === '_blank') {
        setTimeout(function () { window.location.reload(); }, 900);
    }
});


/* Searchable dropdown: <select data-searchable> becomes a box you can type in to filter the options.
   The original select stays in the page (hidden), so the form is submitted exactly as before. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('select[data-searchable]').forEach(function (sel) {
        var items = Array.prototype.filter.call(sel.options, function (o) { return o.value !== ''; })
            .map(function (o) { return { value: o.value, label: o.textContent.trim() }; });

        var wrap = document.createElement('div'); wrap.className = 'ss';
        var input = document.createElement('input');
        input.type = 'text'; input.autocomplete = 'off'; input.spellcheck = false;
        input.placeholder = sel.getAttribute('data-search-placeholder') || 'Type to search…';
        input.setAttribute('role', 'combobox'); input.setAttribute('aria-expanded', 'false');
        if (sel.id) { input.id = sel.id + '_search'; var lb = document.querySelector('label[for="' + sel.id + '"]'); if (lb) lb.setAttribute('for', input.id); }
        var list = document.createElement('ul'); list.className = 'ss-list'; list.hidden = true; list.setAttribute('role', 'listbox');

        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(input); wrap.appendChild(list); wrap.appendChild(sel);
        sel.classList.add('ss-native'); sel.tabIndex = -1;

        var shown = [], active = -1;

        function labelOf(v) { var f = items.filter(function (i) { return i.value === v; })[0]; return f ? f.label : ''; }
        function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
        function pick(i) {
            sel.value = shown[i].value; input.value = shown[i].label; close();
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function paint() {
            Array.prototype.forEach.call(list.children, function (li, i) { li.classList.toggle('on', i === active); });
            var el = list.children[active]; if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
        }
        function render(q) {
            q = (q || '').toLowerCase().trim();
            shown = items.filter(function (i) { return q === '' || i.label.toLowerCase().indexOf(q) !== -1; });
            list.innerHTML = '';
            shown.forEach(function (it, i) {
                var li = document.createElement('li'); li.textContent = it.label; li.setAttribute('role', 'option');
                if (it.value === sel.value) li.classList.add('sel');
                li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(i); });
                list.appendChild(li);
            });
            if (!shown.length) {
                var e = document.createElement('li'); e.className = 'ss-empty';
                e.textContent = sel.getAttribute('data-search-empty') || 'Nothing found'; list.appendChild(e);
            }
            active = shown.length ? 0 : -1; list.hidden = false; input.setAttribute('aria-expanded', 'true'); paint();
        }

        input.value = labelOf(sel.value);
        input.addEventListener('focus', function () { input.select(); render(''); });
        input.addEventListener('click', function () { if (list.hidden) render(''); });
        input.addEventListener('input', function () { sel.value = ''; render(input.value); });
        input.addEventListener('blur', function () { input.value = labelOf(sel.value); close(); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                if (list.hidden) { render(input.value === labelOf(sel.value) ? '' : input.value); return; }
                if (shown.length) { active = (active + (ev.key === 'ArrowDown' ? 1 : -1) + shown.length) % shown.length; paint(); }
            } else if (ev.key === 'Enter') {
                if (!list.hidden && active > -1 && shown[active]) { ev.preventDefault(); pick(active); }
            } else if (ev.key === 'Escape') { close(); }
        });
    });
});
