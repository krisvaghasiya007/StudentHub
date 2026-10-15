/* =====================================================
   Events page — data loaded via Fetch API from events.json.
   Supports search, category filter, sorting, pagination,
   and a modal popup with full event details.
   The modal also contains a registration form that posts
   to php/event_register.php (stores into MySQL). Events are loaded
   from php/api/events.php (falls back to events.json).
   ===================================================== */

(function () {
    var listEl = document.getElementById("event-list");
    var searchEl = document.getElementById("event-search");
    var filterEl = document.getElementById("event-filter");
    var sortEl = document.getElementById("event-sort");
    var pagerEl = document.getElementById("event-pagination");
    var modalContent = document.getElementById("event-modal-content");

    if (!listEl) return;

    var PAGE_SIZE = 3;
    var currentPage = 1;
    var allEvents = [];

    // Load from the PHP/MySQL API; fall back to the static events.json if PHP is unavailable
    function loadEvents() {
        return fetch("php/api/events.php")
            .then(function (res) {
                if (!res.ok) throw new Error("API not available");
                return res.json();
            })
            .then(function (json) {
                if (!json.success) throw new Error("API error");
                return json.data;
            })
            .catch(function () {
                return fetch("events.json").then(function (res) {
                    if (!res.ok) throw new Error("Network response was not ok");
                    return res.json();
                });
            });
    }

    loadEvents()
        .then(function (data) {
            allEvents = data;
            render();
        })
        .catch(function (err) {
            listEl.innerHTML = '<p class="empty-state">Could not load events right now. Please try again later.</p>';
            console.error(err);
        });

    [searchEl, filterEl, sortEl].forEach(function (el) {
        if (!el) return;
        el.addEventListener("input", function () { currentPage = 1; render(); });
        el.addEventListener("change", function () { currentPage = 1; render(); });
    });

    function getFilteredEvents() {
        var term = (searchEl && searchEl.value.trim().toLowerCase()) || "";
        var category = (filterEl && filterEl.value) || "";
        var sort = (sortEl && sortEl.value) || "date-asc";

        var result = allEvents.filter(function (ev) {
            var matchesTerm = !term ||
                ev.name.toLowerCase().indexOf(term) !== -1 ||
                ev.venue.toLowerCase().indexOf(term) !== -1;
            var matchesCategory = !category || ev.category === category;
            return matchesTerm && matchesCategory;
        });

        result.sort(function (a, b) {
            switch (sort) {
                case "date-desc": return new Date(b.date) - new Date(a.date);
                case "name-asc": return a.name.localeCompare(b.name);
                case "name-desc": return b.name.localeCompare(a.name);
                default: return new Date(a.date) - new Date(b.date);
            }
        });

        return result;
    }

    function render() {
        var filtered = getFilteredEvents();
        var totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        currentPage = Math.min(currentPage, totalPages);
        var start = (currentPage - 1) * PAGE_SIZE;
        var pageItems = filtered.slice(start, start + PAGE_SIZE);

        if (!pageItems.length) {
            listEl.innerHTML = '<p class="empty-state">No events match your search.</p>';
        } else {
            listEl.innerHTML = pageItems.map(function (ev) {
                return '' +
                    '<div class="event-card" data-id="' + ev.id + '">' +
                    '<h3>' + escapeHtml(ev.name) + '<span class="badge">' + escapeHtml(ev.category) + '</span></h3>' +
                    '<div class="meta">' + formatDate(ev.date) + ' &middot; ' + escapeHtml(ev.venue) + '</div>' +
                    '</div>';
            }).join("");

            listEl.querySelectorAll(".event-card").forEach(function (card) {
                card.addEventListener("click", function () {
                    showEventDetail(card.getAttribute("data-id"));
                });
            });
        }

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (!pagerEl) return;
        var html = '';
        html += '<button ' + (currentPage === 1 ? 'disabled' : '') + ' data-page="prev">&laquo; Prev</button>';
        for (var i = 1; i <= totalPages; i++) {
            html += '<button class="' + (i === currentPage ? 'active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        html += '<button ' + (currentPage === totalPages ? 'disabled' : '') + ' data-page="next">Next &raquo;</button>';
        pagerEl.innerHTML = html;

        pagerEl.querySelectorAll("button").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var p = btn.getAttribute("data-page");
                if (p === "prev") currentPage--;
                else if (p === "next") currentPage++;
                else currentPage = parseInt(p, 10);
                render();
            });
        });
    }

    function showEventDetail(id) {
        var ev = allEvents.find(function (e) { return String(e.id) === String(id); });
        if (!ev || !modalContent) return;
        modalContent.innerHTML = '' +
            '<h2>' + escapeHtml(ev.name) + '</h2>' +
            '<p><strong>Date:</strong> ' + formatDate(ev.date) + '</p>' +
            '<p><strong>Venue:</strong> ' + escapeHtml(ev.venue) + '</p>' +
            '<p><strong>Category:</strong> ' + escapeHtml(ev.category) + '</p>' +
            (ev.capacity ? '<p><strong>Seats:</strong> ' + (ev.registered || 0) + ' / ' + ev.capacity + ' registered</p>' : '') +
            (ev.poster ? '<img src="' + escapeHtml(ev.poster) + '" alt="Poster for ' + escapeHtml(ev.name) + '" style="max-height:260px;margin:10px auto;">' : '') +
            '<p>' + escapeHtml(ev.description) + '</p>' +
            '<hr>' +
            '<h3>Register for this event</h3>' +
            '<p style="font-size:13px;">You must be logged in. Use your own roll number.</p>' +
            '<form method="post" action="php/event_register.php">' +
            '<input type="hidden" name="event_id" value="' + parseInt(ev.id, 10) + '">' +
            '<label for="ev-roll">Roll Number</label>' +
            '<input type="text" id="ev-roll" name="roll_no" placeholder="e.g. 25DCS138" required pattern="[A-Za-z0-9]{5,15}">' +
            '<input type="submit" value="Register">' +
            '</form>';
        openModal("event-modal");
    }

    function formatDate(iso) {
        var d = new Date(iso);
        return d.toLocaleDateString(undefined, { day: "2-digit", month: "long", year: "numeric" });
    }

    function escapeHtml(str) {
        var div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }
})();
