/* =====================================================
   Static pages: ask the server who is logged in and
   rebuild the navigation menu for that role.
   If PHP is not running (file:// or no server) the
   default static menu is simply left untouched.
   ===================================================== */
(function () {
    var nav = document.querySelector(".nav-links");
    if (!nav) return;

    fetch("php/api/session.php", { credentials: "same-origin" })
        .then(function (res) {
            if (!res.ok) throw new Error("no session endpoint");
            return res.json();
        })
        .then(function (data) {
            if (!data || !data.success || !Array.isArray(data.menu)) return;
            var current = window.location.pathname.split("/").pop() || "index.html";
            nav.innerHTML = "";
            data.menu.forEach(function (item) {
                var li = document.createElement("li");
                var a = document.createElement("a");
                a.textContent = item.label;
                a.href = item.href;
                if (item.href.split("/").pop() === current) a.className = "active";
                li.appendChild(a);
                nav.appendChild(li);
            });
            window.StudentHubUser = data.user;
        })
        .catch(function () { /* keep static menu */ });
})();
