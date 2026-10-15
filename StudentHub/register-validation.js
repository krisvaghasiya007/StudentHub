/* =====================================================
   Student Registration — client-side validation using
   Regular Expressions. Blocks submission until every
   field passes; once valid, the form is POSTed to
   php/register_process.php, which re-validates on the
   server (client checks are only a convenience).
   ===================================================== */

(function () {
    var form = document.getElementById("register-form");
    if (!form) return;

    var successBox = document.getElementById("reg-success");

    var patterns = {
        name: /^[A-Za-z][A-Za-z\s]{2,49}$/,
        email: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
        mobile: /^[6-9]\d{9}$/,
        roll: /^[A-Za-z0-9]{5,15}$/,
        password: /^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/
    };

    var fields = {
        name: document.getElementById("reg-name"),
        email: document.getElementById("reg-email"),
        mobile: document.getElementById("reg-mobile"),
        roll: document.getElementById("reg-roll"),
        course: document.getElementById("reg-course"),
        year: document.getElementById("reg-year"),
        password: document.getElementById("reg-password"),
        confirm: document.getElementById("reg-confirm"),
        terms: document.getElementById("reg-terms")
    };

    function setError(el, errId, show) {
        var errEl = document.getElementById(errId);
        if (errEl) errEl.classList.toggle("show", show);
        if (el) {
            el.classList.toggle("invalid", show);
            el.classList.toggle("valid", !show && el.value);
        }
    }

    function validateName() {
        var ok = patterns.name.test(fields.name.value.trim());
        setError(fields.name, "err-name", !ok);
        return ok;
    }

    function validateEmail() {
        var ok = patterns.email.test(fields.email.value.trim());
        setError(fields.email, "err-email", !ok);
        return ok;
    }

    function validateMobile() {
        var ok = patterns.mobile.test(fields.mobile.value.trim());
        setError(fields.mobile, "err-mobile", !ok);
        return ok;
    }

    function validateRoll() {
        var ok = patterns.roll.test(fields.roll.value.trim());
        setError(fields.roll, "err-roll", !ok);
        return ok;
    }

    function validateCourse() {
        var ok = fields.course.value !== "";
        setError(fields.course, "err-course", !ok);
        return ok;
    }

    function validateYear() {
        var ok = fields.year.value !== "";
        setError(fields.year, "err-year", !ok);
        return ok;
    }

    function validateGender() {
        var checked = form.querySelector('input[name="gender"]:checked');
        var errEl = document.getElementById("err-gender");
        if (errEl) errEl.classList.toggle("show", !checked);
        return !!checked;
    }

    function validatePassword() {
        var ok = patterns.password.test(fields.password.value);
        setError(fields.password, "err-password", !ok);
        return ok;
    }

    function validateConfirm() {
        var ok = fields.confirm.value !== "" && fields.confirm.value === fields.password.value;
        setError(fields.confirm, "err-confirm", !ok);
        return ok;
    }

    function validateTerms() {
        var ok = fields.terms.checked;
        var errEl = document.getElementById("err-terms");
        if (errEl) errEl.classList.toggle("show", !ok);
        return ok;
    }

    fields.name.addEventListener("input", validateName);
    fields.email.addEventListener("input", validateEmail);
    fields.mobile.addEventListener("input", validateMobile);
    fields.roll.addEventListener("input", validateRoll);
    fields.course.addEventListener("change", validateCourse);
    fields.year.addEventListener("change", validateYear);
    fields.password.addEventListener("input", function () {
        validatePassword();
        if (fields.confirm.value) validateConfirm();
    });
    fields.confirm.addEventListener("input", validateConfirm);
    fields.terms.addEventListener("change", validateTerms);
    form.querySelectorAll('input[name="gender"]').forEach(function (r) {
        r.addEventListener("change", validateGender);
    });

    form.addEventListener("submit", function (e) {
        if (successBox) successBox.classList.remove("show");

        var results = [
            validateName(), validateEmail(), validateMobile(), validateRoll(),
            validateCourse(), validateYear(), validateGender(),
            validatePassword(), validateConfirm(), validateTerms()
        ];

        var allValid = results.every(Boolean);

        if (!allValid) {
            // Stop the request; show inline errors.
            e.preventDefault();
            var firstInvalid = form.querySelector(".invalid, .field-error.show");
            if (firstInvalid) firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        // If everything is valid, the browser submits the form to PHP.
    });

    form.addEventListener("reset", function () {
        form.querySelectorAll(".field-error").forEach(function (el) { el.classList.remove("show"); });
        form.querySelectorAll(".invalid, .valid").forEach(function (el) { el.classList.remove("invalid", "valid"); });
        if (successBox) successBox.classList.remove("show");
    });
})();
