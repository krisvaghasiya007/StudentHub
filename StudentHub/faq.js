/* =====================================================
   Home page — FAQ loaded dynamically via Fetch API
   with live search filtering.
   ===================================================== */

(function () {
    var listEl = document.getElementById("faq-list");
    var searchEl = document.getElementById("faq-search");
    var faqs = [];

    if (!listEl) return;

    fetch("faqs.json")
        .then(function (res) {
            if (!res.ok) throw new Error("Network response was not ok");
            return res.json();
        })
        .then(function (data) {
            faqs = data;
            renderFaqs(faqs);
        })
        .catch(function (err) {
            listEl.innerHTML = '<p class="empty-state">Could not load FAQs right now. Please try again later.</p>';
            console.error(err);
        });

    if (searchEl) {
        searchEl.addEventListener("input", function () {
            var term = searchEl.value.trim().toLowerCase();
            var filtered = faqs.filter(function (f) {
                return f.question.toLowerCase().indexOf(term) !== -1 ||
                       f.answer.toLowerCase().indexOf(term) !== -1;
            });
            renderFaqs(filtered);
        });
    }

    function renderFaqs(items) {
        if (!items.length) {
            listEl.innerHTML = '<p class="empty-state">No FAQs match your search.</p>';
            return;
        }
        listEl.innerHTML = items.map(function (f) {
            return '' +
                '<div class="faq-item">' +
                '<button class="faq-question">' + escapeHtml(f.question) + '</button>' +
                '<div class="faq-answer"><p>' + escapeHtml(f.answer) + '</p></div>' +
                '</div>';
        }).join("");

        listEl.querySelectorAll(".faq-question").forEach(function (q) {
            q.addEventListener("click", function () { toggleFaqItem(q); });
        });
    }

    function escapeHtml(str) {
        var div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }
})();
