/**
 * Public-facing community layer driver. Wires the AJAX placeholders rendered
 * by partials/community/*.blade.php on a biolink page:
 *  - Insider feed (join + paginated post list)
 *  - Comments (load + post + reactions)
 *  - Top fans leaderboard
 *  - Polls (vote + tally render)
 *
 * Uses fetch() with credentials so Laravel's session cookie travels along.
 */
(function () {
    'use strict';

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body || {}),
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; }); });
    }

    function getJson(url) {
        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; }); });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ---------- Insider feed ----------
    function initInsider(root) {
        var feed   = root.querySelector('.insider-feed-list');
        var joinBt = root.querySelector('.insider-join-btn');
        var form   = root.querySelector('.insider-join-form');

        function renderPosts(posts) {
            if (!posts || !posts.length) {
                feed.innerHTML = '<div class="text-xs opacity-50">No posts yet — check back soon.</div>';
                return;
            }
            feed.innerHTML = posts.map(function (p) {
                if (p.locked) {
                    return '<div class="rounded-lg p-3 bg-black/20 text-xs opacity-70"><i class="fas fa-lock mr-1"></i>'
                        + escapeHtml(p.title || 'Insider post') + ' — ' + escapeHtml(p.lock_reason || 'Members only') + '</div>';
                }
                return '<article class="rounded-lg p-3 bg-black/20">'
                    + (p.title ? '<h4 class="font-semibold text-sm mb-1">' + escapeHtml(p.title) + '</h4>' : '')
                    + '<div class="text-xs opacity-90 whitespace-pre-wrap">' + escapeHtml(p.body || '') + '</div>'
                    + (p.media_url
                        ? (p.media_type === 'video'
                            ? '<video src="' + escapeHtml(p.media_url) + '" controls class="mt-2 rounded w-full"></video>'
                            : '<img src="' + escapeHtml(p.media_url) + '" alt="" class="mt-2 rounded w-full">')
                        : '')
                    + '</article>';
            }).join('');
        }

        function loadFeed() {
            var url = feed && feed.getAttribute('data-feed-url');
            if (!url) return;
            getJson(url).then(function (res) {
                if (res.ok) renderPosts(res.json.posts || []);
                else feed.innerHTML = '<div class="text-xs opacity-50">Couldn\u2019t load feed.</div>';
            });
        }

        if (joinBt && form) {
            joinBt.addEventListener('click', function () { form.classList.toggle('hidden'); });
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(form);
                postJson(form.getAttribute('data-action'), {
                    email: fd.get('email'),
                    display_name: fd.get('display_name'),
                }).then(function (res) {
                    if (res.ok) { form.classList.add('hidden'); loadFeed(); }
                    else if (res.status === 402) {
                        form.innerHTML = '<div class="text-xs text-amber-300 p-2">'
                            + escapeHtml(res.json && res.json.error ? res.json.error : 'Paid subscription required.')
                            + '</div>';
                    } else {
                        alert((res.json && res.json.error) || 'Could not join.');
                    }
                });
            });
        }

        loadFeed();
    }

    // ---------- Comments + reactions ----------
    function initComments(root) {
        var list  = root.querySelector('.comments-list');
        var form  = root.querySelector('.comment-form');
        var emos  = root.querySelectorAll('.react-btn');

        function render(comments) {
            if (!comments || !comments.length) {
                list.innerHTML = '<div class="text-xs opacity-50">Be the first to comment.</div>';
                return;
            }
            list.innerHTML = comments.map(function (c) {
                return '<div class="rounded p-2 bg-black/20 text-xs">'
                    + '<strong>' + escapeHtml(c.author_name || 'Guest') + '</strong> '
                    + (c.is_pinned ? '<span class="text-amber-300">\u2605</span>' : '')
                    + '<div class="opacity-90 whitespace-pre-wrap mt-0.5">' + escapeHtml(c.body) + '</div>'
                    + '</div>';
            }).join('');
        }
        function load() {
            var url = list.getAttribute('data-load-url');
            if (!url) return;
            getJson(url).then(function (res) { if (res.ok) render(res.json.comments || []); });
        }
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var fd = new FormData(form);
                postJson(form.getAttribute('data-action'), {
                    body: fd.get('body'),
                    author_name: fd.get('author_name'),
                }).then(function (res) {
                    if (res.ok) { form.reset(); load(); }
                    else alert((res.json && res.json.error) || 'Could not post comment.');
                });
            });
        }
        Array.prototype.forEach.call(emos, function (btn) {
            btn.addEventListener('click', function () {
                postJson(btn.getAttribute('data-action'), { emoji: btn.getAttribute('data-emoji') })
                    .then(function (res) {
                        if (!res.ok && res.json && res.json.error) alert(res.json.error);
                    });
            });
        });
        load();
    }

    // ---------- Leaderboard ----------
    function initLeaderboard(root) {
        var list = root.querySelector('.leaderboard-list');
        if (!list) return;
        var url = list.getAttribute('data-load-url');
        if (!url) return;
        getJson(url).then(function (res) {
            if (res.status === 404 || !res.json.fans || !res.json.fans.length) {
                list.innerHTML = '<li class="text-xs opacity-50">No fans yet.</li>';
                return;
            }
            list.innerHTML = res.json.fans.map(function (f) {
                return '<li class="flex items-center justify-between text-xs">'
                    + '<span><span class="opacity-60 mr-2">#' + f.rank + '</span>' + escapeHtml(f.name) + '</span>'
                    + '<span class="font-semibold text-amber-300">' + f.total + '</span>'
                    + '</li>';
            }).join('');
        });
    }

    // ---------- Polls ----------
    function initPolls(root) {
        var list = root.querySelector('.polls-list');
        var loadUrl = root.getAttribute('data-load-url');
        var voteTpl = root.getAttribute('data-vote-url-template');
        if (!list || !loadUrl) return;

        function render(polls) {
            if (!polls || !polls.length) { list.innerHTML = ''; return; }
            list.innerHTML = polls.map(function (p) {
                var tally = p.tally || { total: 0, options: [] };
                var opts = (tally.options || []).map(function (o) {
                    var inputType = p.multi_select ? 'checkbox' : 'radio';
                    var disabled = p.is_open ? '' : ' disabled';
                    return '<label class="poll-opt flex items-center gap-2 text-xs cursor-pointer">'
                        + '<input type="' + inputType + '" name="poll-' + p.id + '" value="' + o.index + '"' + disabled + '>'
                        + '<span class="flex-1">' + escapeHtml(String(o.label)) + '</span>'
                        + '<span class="opacity-60">' + o.count + ' (' + o.pct + '%)</span>'
                        + '</label>';
                }).join('');
                var btn = p.is_open
                    ? '<button type="button" class="poll-vote-btn mt-2 px-3 py-1 text-xs rounded bg-purple-500/30 hover:bg-purple-500/50" data-poll-id="' + p.id + '">Vote</button>'
                    : '<div class="text-[10px] opacity-50 mt-1">Poll closed</div>';
                return '<div class="poll glass-block rounded-xl p-3" data-poll-id="' + p.id + '">'
                    + '<div class="font-medium text-sm mb-2">' + escapeHtml(p.question || '') + '</div>'
                    + '<div class="space-y-1">' + opts + '</div>'
                    + btn
                    + '<div class="text-[10px] opacity-50 mt-1">' + (tally.total || 0) + ' votes</div>'
                    + '</div>';
            }).join('');
        }

        function load() {
            getJson(loadUrl).then(function (res) {
                if (res.json && res.json.polls) render(res.json.polls);
            });
        }

        list.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.poll-vote-btn');
            if (!btn) return;
            var pollId = btn.getAttribute('data-poll-id');
            var pollEl = btn.closest('.poll');
            var inputs = pollEl.querySelectorAll('input[name="poll-' + pollId + '"]:checked');
            if (!inputs.length) return;
            var picks = Array.prototype.map.call(inputs, function (i) { return parseInt(i.value, 10); });
            btn.disabled = true;
            postJson(voteTpl.replace('__POLL__', pollId), { options: picks })
                .then(function (res) {
                    if (res.ok) load();
                    else btn.disabled = false;
                });
        });

        load();
    }

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }
    // ---------- Share / outbound click tracking ----------
    // Awards leaderboard points for word-of-mouth activity. We attach a
    // single delegated listener on document so it picks up share buttons
    // and outbound `<a>` clicks injected after page load.
    function initEngagementTracking() {
        var linkEl = document.querySelector('[data-biolink-id]') || document.querySelector('.community-leaderboard-block, .community-insider-block, .community-comments-block, .community-polls-block');
        if (!linkEl) return;
        var linkId = linkEl.getAttribute('data-biolink-id') || linkEl.getAttribute('data-link-id');
        if (!linkId) return;
        var trackUrl = '/community/' + linkId + '/engagement';

        function fire(action) {
            try {
                postJson(trackUrl, { action: action });
            } catch (e) {}
        }

        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t || !t.closest) return;
            var shareBtn = t.closest('[data-share], .biolink-share, [data-action="share"]');
            if (shareBtn) { fire('share'); return; }
            var anchor = t.closest('a[href]');
            if (anchor) {
                var href = anchor.getAttribute('href') || '';
                // Treat anything that leaves the page (target=_blank or
                // an external host) as an outbound click worth crediting.
                var external = anchor.target === '_blank'
                    || (/^https?:\/\//i.test(href) && href.indexOf(location.host) === -1);
                if (external) fire('click');
            }
        }, true);
    }

    ready(function () {
        document.querySelectorAll('.community-insider-block').forEach(initInsider);
        document.querySelectorAll('.community-comments-block').forEach(initComments);
        document.querySelectorAll('.community-leaderboard-block').forEach(initLeaderboard);
        document.querySelectorAll('.community-polls-block').forEach(initPolls);
        initEngagementTracking();
    });
})();
