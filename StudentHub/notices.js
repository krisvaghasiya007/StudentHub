/* =====================================================
   Dashboard page — notifications loaded via Fetch API
   from notices.json, with search, type filter, and
   pagination.
   ===================================================== */

(function () {
    var listEl = document.getElementById("notice-list");
    var searchEl = document.getElementById("notice-search");
    var filterEl = document.getElementById("notice-filter");
    var pagerEl = document.getElementById("notice-pagination");

    if (!listEl) return;

    var PAGE_SIZE = 4;
    var currentPage = 1;
    var allNotices = [];

    fetch("notices.json")
        .then(function (res) {
            if (!res.ok) throw new Error("Network response was not ok");
            return res.json();
        })
        .then(function (data) {
            allNotices = data.sort(function (a, b) { return new Date(b.date) - new Date(a.date); });
            render();
        })
        .catch(function (err) {
            listEl.innerHTML = '<p class="empty-state">Could not load notifications right now.</p>';
            console.error(err);
        });

    [searchEl, filterEl].forEach(function (el) {
        if (!el) return;
        el.addEventListener("input", function () { currentPage = 1; render(); });
        el.addEventListener("change", function () { currentPage = 1; render(); });
    });

    function getFiltered() {
        var term = (searchEl && searchEl.value.trim().toLowerCase()) || "";
        var type = (filterEl && filterEl.value) || "";
        return allNotices.filter(function (n) {
            var matchesTerm = !term ||
                n.title.toLowerCase().indexOf(term) !== -1 ||
                n.message.toLowerCase().indexOf(term) !== -1;
            var matchesType = !type || n.type === type;
            return matchesTerm && matchesType;
        });
    }

    function render() {
        var filtered = getFiltered();
        var totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        currentPage = Math.min(currentPage, totalPages);
        var start = (currentPage - 1) * PAGE_SIZE;
        var pageItems = filtered.slice(start, start + PAGE_SIZE);

        listEl.innerHTML = pageItems.length ? pageItems.map(function (n) {
            return '' +
                '<div class="notice-card">' +
                '<h3>' + escapeHtml(n.title) + '<span class="badge">' + escapeHtml(n.type) + '</span></h3>' +
                '<div class="meta">' + formatDate(n.date) + '</div>' +
                '<p>' + escapeHtml(n.message) + '</p>' +
                '</div>';
        }).join("") : '<p class="empty-state">No notifications match your search.</p>';

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        if (!pagerEl) return;
        if (totalPages <= 1) { pagerEl.innerHTML = ""; return; }
        var html = '<button ' + (currentPage === 1 ? 'disabled' : '') + ' data-page="prev">&laquo; Prev</button>';
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

    function formatDate(iso) {
        var d = new Date(iso);
        return d.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" });
    }

    function escapeHtml(str) {
        var div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }
})();
