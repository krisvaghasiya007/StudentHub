/* =====================================================
   Admin Live Manager - list / add / update / delete via
   the JSON API (php/api/events.php, php/api/students.php)
   using fetch(), with no full page reload.
   ===================================================== */
(function () {
    var app = document.getElementById("live-app");
    if (!app) return;

    var entity = app.dataset.entity;
    var csrf = app.dataset.csrf;
    var api = "../php/api/" + entity + ".php";
    var list = function (k) { try { return JSON.parse(app.dataset[k]); } catch (e) { return []; } };

    var CONFIG = {
        events: {
            label: "event",
            fields: [
                { name: "title", label: "Title", type: "text" },
                { name: "date", label: "Date", type: "date" },
                { name: "venue", label: "Venue", type: "text" },
                { name: "category", label: "Category", type: "select", options: list("categories") },
                { name: "capacity", label: "Capacity", type: "number" },
                { name: "description", label: "Description", type: "textarea" }
            ],
            columns: [["title", "Title"], ["date", "Date"], ["venue", "Venue"], ["category", "Category"], ["registered", "Registered"]]
        },
        students: {
            label: "student",
            fields: [
                { name: "name", label: "Full Name", type: "text" },
                { name: "email", label: "Email", type: "email" },
                { name: "mobile", label: "Mobile", type: "tel" },
                { name: "roll", label: "Roll Number", type: "text" },
                { name: "course", label: "Course", type: "select", options: list("courses") },
                { name: "year", label: "Year", type: "select", options: list("years") },
                { name: "gender", label: "Gender", type: "select", options: list("genders") },
                { name: "role", label: "Role", type: "select", options: list("roles") },
                { name: "password", label: "Password (blank = keep)", type: "password" }
            ],
            columns: [["roll", "Roll"], ["name", "Name"], ["email", "Email"], ["course", "Course"], ["role", "Role"]]
        }
    }[entity];

    var form = document.getElementById("live-form");
    var table = document.getElementById("live-table");
    var pager = document.getElementById("live-pager");
    var msg = document.getElementById("live-message");
    var totalEl = document.getElementById("live-total");
    var titleEl = document.getElementById("live-form-title");
    var searchEl = document.getElementById("live-search");

    var state = { page: 1, q: "", editId: null, rows: [] };

    function esc(s) {
        var d = document.createElement("div");
        d.textContent = s == null ? "" : String(s);
        return d.innerHTML;
    }

    function showMessage(type, text, errors) {
        var html = '<div class="alert alert-' + type + '"><strong>' + esc(text) + "</strong>";
        if (errors && errors.length) {
            html += "<ul>" + errors.map(function (e) { return "<li>" + esc(e) + "</li>"; }).join("") + "</ul>";
        }
        msg.innerHTML = html + "</div>";
    }

    /* Central fetch wrapper: JSON in/out, CSRF header, session-expiry handling */
    function request(method, url, body) {
        var opts = {
            method: method,
            credentials: "same-origin",
            headers: { "Accept": "application/json", "X-CSRF-Token": csrf }
        };
        if (body) {
            opts.headers["Content-Type"] = "application/json";
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () { return { success: false, message: "Unexpected server response." }; })
                .then(function (data) {
                    if (res.status === 401) {
                        showMessage("warning", data.message || "Session expired. Redirecting to login...");
                        setTimeout(function () { window.location.href = "../login.php"; }, 1500);
                    }
                    data._status = res.status;
                    return data;
                });
        });
    }

    /* ---------- form ---------- */
    function buildForm() {
        form.innerHTML = CONFIG.fields.map(function (f) {
            var id = "lf-" + f.name, input;
            if (f.type === "select") {
                input = '<select id="' + id + '" name="' + f.name + '"><option value="">Select...</option>' +
                    f.options.map(function (o) { return "<option>" + esc(o) + "</option>"; }).join("") + "</select>";
            } else if (f.type === "textarea") {
                input = '<textarea id="' + id + '" name="' + f.name + '" rows="3"></textarea>';
            } else {
                input = '<input type="' + f.type + '" id="' + id + '" name="' + f.name + '"' +
                    (f.type === "password" ? ' autocomplete="new-password"' : "") + ">";
            }
            return '<label for="' + id + '">' + esc(f.label) + "</label>" + input;
        }).join("") +
            '<input type="submit" id="lf-submit" value="Add ' + CONFIG.label + '">' +
            '<input type="button" id="lf-cancel" value="Cancel edit" style="display:none;background:#777;color:#fff;border:none;cursor:pointer;">';

        form.addEventListener("submit", onSubmit);
        document.getElementById("lf-cancel").addEventListener("click", resetForm);
    }

    function formData() {
        var data = {};
        CONFIG.fields.forEach(function (f) { data[f.name] = form.elements[f.name].value; });
        return data;
    }

    function resetForm() {
        form.reset();
        state.editId = null;
        titleEl.textContent = "Add " + CONFIG.label;
        document.getElementById("lf-submit").value = "Add " + CONFIG.label;
        document.getElementById("lf-cancel").style.display = "none";
    }

    function startEdit(id) {
        var row = state.rows.filter(function (r) { return r.id === id; })[0];
        if (!row) return;
        CONFIG.fields.forEach(function (f) {
            form.elements[f.name].value = f.type === "password" ? "" : (row[f.name] == null ? "" : row[f.name]);
        });
        state.editId = id;
        titleEl.textContent = "Edit " + CONFIG.label + " #" + id;
        document.getElementById("lf-submit").value = "Save changes";
        document.getElementById("lf-cancel").style.display = "inline-block";
        form.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    function onSubmit(e) {
        e.preventDefault();
        var editing = state.editId !== null;
        var url = editing ? api + "?id=" + state.editId : api;
        request(editing ? "PUT" : "POST", url, formData()).then(function (res) {
            if (res.success) {
                showMessage("success", res.message);
                resetForm();
                load();
            } else {
                showMessage("error", res.message || "Request failed.", res.errors);
            }
        }).catch(function () { showMessage("error", "Network error. Please try again."); });
    }

    /* ---------- table ---------- */
    function render(total, perPage) {
        var head = "<tr>" + CONFIG.columns.map(function (c) { return "<th>" + esc(c[1]) + "</th>"; }).join("") + "<th>Actions</th></tr>";
        var body = state.rows.map(function (r) {
            return "<tr>" + CONFIG.columns.map(function (c) { return "<td>" + esc(r[c[0]]) + "</td>"; }).join("") +
                '<td class="actions"><button class="btn-sm" data-edit="' + r.id + '">Edit</button> ' +
                '<button class="btn-sm btn-danger" data-del="' + r.id + '">Delete</button></td></tr>';
        }).join("");
        table.innerHTML = head + (body || '<tr><td colspan="' + (CONFIG.columns.length + 1) + '">No records found.</td></tr>');
        totalEl.textContent = "(" + total + ")";

        var pages = Math.max(1, Math.ceil(total / (perPage || 8)));
        var html = "";
        if (pages > 1) {
            html += '<button data-page="' + (state.page - 1) + '"' + (state.page === 1 ? " disabled" : "") + ">&laquo; Prev</button>";
            for (var i = 1; i <= pages; i++) {
                html += '<button data-page="' + i + '"' + (i === state.page ? ' class="active"' : "") + ">" + i + "</button>";
            }
            html += '<button data-page="' + (state.page + 1) + '"' + (state.page === pages ? " disabled" : "") + ">Next &raquo;</button>";
        }
        pager.innerHTML = html;
    }

    function load() {
        var url = api + "?page=" + state.page + "&per_page=8&q=" + encodeURIComponent(state.q);
        table.innerHTML = '<tr><td class="loading-state">Loading...</td></tr>';
        request("GET", url).then(function (res) {
            if (!res.success) {
                table.innerHTML = "";
                showMessage("error", res.message || "Could not load records.");
                return;
            }
            // If the last item on a page was deleted, step back a page
            if (res.data.length === 0 && state.page > 1) { state.page--; return load(); }
            state.rows = res.data;
            render(res.total, res.per_page);
        }).catch(function () {
            table.innerHTML = "";
            showMessage("error", "Network error. Is the server running?");
        });
    }

    table.addEventListener("click", function (e) {
        var edit = e.target.getAttribute("data-edit");
        var del = e.target.getAttribute("data-del");
        if (edit) startEdit(parseInt(edit, 10));
        if (del && confirm("Delete this " + CONFIG.label + "? This cannot be undone.")) {
            request("DELETE", api + "?id=" + del).then(function (res) {
                showMessage(res.success ? "success" : "error", res.message || "Request failed.");
                if (res.success) { if (state.editId === parseInt(del, 10)) resetForm(); load(); }
            }).catch(function () { showMessage("error", "Network error. Please try again."); });
        }
    });

    pager.addEventListener("click", function (e) {
        var p = parseInt(e.target.getAttribute("data-page"), 10);
        if (p) { state.page = p; load(); }
    });

    var timer;
    searchEl.addEventListener("input", function () {
        clearTimeout(timer);
        timer = setTimeout(function () { state.q = searchEl.value.trim(); state.page = 1; load(); }, 300);
    });

    titleEl.textContent = "Add " + CONFIG.label;
    buildForm();
    load();
})();
