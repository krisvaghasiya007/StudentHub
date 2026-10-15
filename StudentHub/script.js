/* =====================================================
   StudentHub — Global UI behaviors
   Hamburger menu, theme switcher, notification banner,
   generic modal + FAQ accordion helpers, hero slider.
   ===================================================== */

document.addEventListener("DOMContentLoaded", function () {
    initHamburgerMenu();
    initThemeSwitcher();
    initNotificationBanner();
    initFaqAccordions();
    initModals();
    initSliders();
    highlightActiveNavLink();
});

/* ---------- Hamburger menu ---------- */
function initHamburgerMenu() {
    var toggle = document.querySelector(".nav-toggle");
    var links = document.querySelector(".nav-links");
    if (!toggle || !links) return;

    toggle.addEventListener("click", function () {
        var isOpen = links.classList.toggle("open");
        toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    links.querySelectorAll("a").forEach(function (a) {
        a.addEventListener("click", function () {
            links.classList.remove("open");
        });
    });
}

/* ---------- Light / dark theme switcher ---------- */
function initThemeSwitcher() {
    var btn = document.querySelector(".theme-toggle");
    var saved = localStorage.getItem("studenthub-theme");

    if (saved === "dark") {
        document.body.classList.add("dark");
    }
    updateThemeLabel(btn);

    if (!btn) return;
    btn.addEventListener("click", function () {
        document.body.classList.toggle("dark");
        var mode = document.body.classList.contains("dark") ? "dark" : "light";
        localStorage.setItem("studenthub-theme", mode);
        updateThemeLabel(btn);
    });
}

function updateThemeLabel(btn) {
    if (!btn) return;
    btn.textContent = document.body.classList.contains("dark") ? "☀ Light" : "🌙 Dark";
}

/* ---------- Dismissible notification banner ---------- */
function initNotificationBanner() {
    var banner = document.querySelector(".notify-banner");
    if (!banner) return;
    var key = "studenthub-banner-" + (banner.dataset.id || "default");

    if (sessionStorage.getItem(key) === "closed") {
        banner.style.display = "none";
        return;
    }
    var closeBtn = banner.querySelector("button");
    if (closeBtn) {
        closeBtn.addEventListener("click", function () {
            banner.style.display = "none";
            sessionStorage.setItem(key, "closed");
        });
    }
}

/* ---------- Collapsible FAQ accordion (static markup) ---------- */
function initFaqAccordions() {
    document.querySelectorAll(".faq-question").forEach(function (q) {
        q.addEventListener("click", function () {
            toggleFaqItem(q);
        });
    });
}

function toggleFaqItem(question) {
    var answer = question.nextElementSibling;
    var isOpen = question.classList.contains("open");
    question.classList.toggle("open", !isOpen);
    if (answer) {
        answer.style.maxHeight = !isOpen ? answer.scrollHeight + "px" : null;
    }
}

/* ---------- Generic modal popup ---------- */
function initModals() {
    document.querySelectorAll("[data-modal-open]").forEach(function (trigger) {
        trigger.addEventListener("click", function () {
            openModal(trigger.getAttribute("data-modal-open"));
        });
    });
    document.querySelectorAll(".modal-overlay").forEach(function (overlay) {
        overlay.addEventListener("click", function (e) {
            if (e.target === overlay) closeModal(overlay.id);
        });
        overlay.querySelectorAll(".modal-close").forEach(function (btn) {
            btn.addEventListener("click", function () { closeModal(overlay.id); });
        });
    });
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            document.querySelectorAll(".modal-overlay.open").forEach(function (o) {
                closeModal(o.id);
            });
        }
    });
}

function openModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add("open");
}

function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove("open");
}

/* ---------- Hero / content slider ---------- */
function initSliders() {
    document.querySelectorAll(".slider").forEach(function (slider) {
        var track = slider.querySelector(".slider-track");
        var slides = slider.querySelectorAll(".slide");
        var dotsWrap = slider.parentElement.querySelector(".slider-dots");
        var prev = slider.querySelector(".slider-btn.prev");
        var next = slider.querySelector(".slider-btn.next");
        if (!track || slides.length === 0) return;

        var index = 0;
        var timer = null;

        if (dotsWrap) {
            dotsWrap.innerHTML = "";
            slides.forEach(function (_, i) {
                var dot = document.createElement("span");
                if (i === 0) dot.classList.add("active");
                dot.addEventListener("click", function () { goTo(i); });
                dotsWrap.appendChild(dot);
            });
        }

        function render() {
            track.style.transform = "translateX(-" + index * 100 + "%)";
            if (dotsWrap) {
                dotsWrap.querySelectorAll("span").forEach(function (d, i) {
                    d.classList.toggle("active", i === index);
                });
            }
        }

        function goTo(i) {
            index = (i + slides.length) % slides.length;
            render();
        }

        if (next) next.addEventListener("click", function () { goTo(index + 1); resetTimer(); });
        if (prev) prev.addEventListener("click", function () { goTo(index - 1); resetTimer(); });

        function resetTimer() {
            if (timer) clearInterval(timer);
            timer = setInterval(function () { goTo(index + 1); }, 5000);
        }

        resetTimer();
        render();
    });
}

/* ---------- Highlight current page in nav ---------- */
function highlightActiveNavLink() {
    var current = window.location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".nav-links a").forEach(function (a) {
        var href = a.getAttribute("href");
        if (href === current) a.classList.add("active");
    });
}
