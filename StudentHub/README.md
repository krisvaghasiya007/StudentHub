# StudentHub Portal — PHP + MySQL edition (Tasks 1-8)

## Quick start (XAMPP / WAMP, PHP 7.4+)
1. Copy the `StudentHub` folder into `htdocs` (XAMPP) or `www` (WAMP).
2. Start **Apache** and **MySQL**.
3. phpMyAdmin → Import → `database/studenthub.sql`
   (or `mysql -u root -p < database/studenthub.sql`).
4. Check `php/config.php` (`DB_USER`, `DB_PASS`; `USE_DB` must be `true`).
5. Test the connection: http://localhost/StudentHub/php/db_test.php
6. Open http://localhost/StudentHub/index.html  (always through `http://localhost`, never by double-clicking).
7. Make sure `data/` and `uploads/posters/` are writable (Linux: `chmod 775 data uploads/posters`).

**Default admin:** `admin@studenthub.local` / `Admin@123` — change it after first login.
If login fails, open `php/create_admin.php` once on localhost to reset it.
Delete `php/db_test.php` and `php/create_admin.php` before publishing the site.

## What was added, per task
| # | Task | Where |
|---|------|-------|
| 1 | Registration/contact processing, server validation + sanitising, CSV + JSON storage, success/error pages | `php/register_process.php`, `php/contact_process.php`, `php/helpers.php`, `data/` |
| 2 | Database design + MySQLi connection | `database/studenthub.sql`, `php/db.php` |
| 3 | Secure registration (MySQLi prepared statements, duplicate email/roll check, `password_hash`) | `php/register_process.php`, `php/models.php` |
| 4 | Login/logout with sessions, `password_verify`, `session_regenerate_id`, restricted dashboard, role redirect, 30-min timeout, remember-me | `login.php`, `php/login_process.php`, `php/logout.php`, `php/auth.php`, `dashboard.php` |
| 5 | Student management: add / view / edit / delete / search / filter / paginate | `admin/students.php`, `admin/student_form.php` |
| 6 | Event management with poster upload (type + size + content validation) | `admin/events.php`, `admin/event_form.php`, `uploads/posters/` |
| 7 | JSON API + Fetch API CRUD without reload | `php/api/events.php`, `php/api/students.php`, `admin/live.php`, `admin-live.js` |
| 8 | Admin dashboard (stats, latest registrations), admin-only, role-based menu | `admin/dashboard.php`, `php/auth.php` (`nav_items`), `auth.js` |

## Folder structure
```
StudentHub/
├── index.html, about.html, register.html, courses.html, assignments.html,
│   events.html, profile.html, contact.html        (static pages; menu is role-aware via auth.js)
├── login.php, dashboard.php                        (replace login.html / dashboard.html)
├── admin/        dashboard, students, student_form, events, event_form, live   (admin only)
├── php/          config, db, helpers, auth, layout, models,
│                 register_process, contact_process, login_process, logout, event_register,
│                 api/ (events.php, students.php, session.php), db_test, create_admin
├── database/studenthub.sql
├── data/         CSV + JSON copies of registrations/contacts (web-blocked)
├── uploads/posters/   event posters (script execution blocked)
└── style.css, script.js, auth.js, admin-live.js, events.js, faq.js, notices.js, register-validation.js
```

## Database
```
students (PK student_id; roll_no UNIQUE, email UNIQUE, role student|admin, password_hash)
events   (PK event_id; title, event_date, venue, category, description, capacity, poster)
registrations (PK registration_id; FK student_id, FK event_id; UNIQUE(student_id,event_id))
remember_tokens (selector + SHA-256 hashed validator, expiry)
```
students ↔ events is many-to-many, resolved by `registrations` (ON DELETE CASCADE).
The roll number acts as the unique username; users can log in with email or roll number.

## JSON API
All responses: `{ "success": true|false, "message": "...", "data": ... }`

| Endpoint | Method | Access |
|----------|--------|--------|
| `php/api/events.php?q=&category=&page=&per_page=` | GET list | public |
| `php/api/events.php?id=3` | GET one | public |
| `php/api/events.php` (JSON body: title, date, venue, category, capacity, description) | POST | admin |
| `php/api/events.php?id=3` | PUT / DELETE | admin |
| `php/api/students.php?q=&course=&year=&role=&page=` | GET list | admin |
| `php/api/students.php` (name, email, mobile, roll, course, year, gender, role, password) | POST | admin |
| `php/api/students.php?id=3` | GET / PUT / DELETE | admin |

Write requests must send the `X-CSRF-Token` header (the Live Manager page does this automatically).
The public Events page loads from this API and falls back to `events.json` if PHP is unavailable.

## Security notes
- All SQL uses MySQLi **prepared statements**; output is escaped with `htmlspecialchars`.
- Passwords: `password_hash()` / `password_verify()` (+ automatic rehash); never stored in CSV/JSON.
- Login: session ID regenerated, HttpOnly + SameSite cookies, 30-minute inactivity timeout, generic error message, server-side CAPTCHA, lock after 5 failed tries, CSRF tokens on admin forms and API writes.
- Remember-me: random selector + hashed validator, rotated on every use, deleted on logout.
- Role checks happen on the server for every admin page and API call (the menu is only a convenience).
- Uploads: size limit (2 MB), extension whitelist, real MIME check (`finfo`), `getimagesize`, random file names, script execution disabled in `uploads/posters/`.
- CSV cells starting with `= + - @` are neutralised; honeypot field catches simple bots.
- `.htaccess` files protect `data/` and include files (Apache). On Nginx add equivalent `deny` rules.
- Not included (good next steps): password reset by email, CSRF token on the public event sign-up form (protected by SameSite cookies), HTTPS enforcement, rate limiting by IP.
