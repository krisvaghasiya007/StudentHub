<?php
/* =====================================================
   Data layer (MySQLi prepared statements) shared by the
   admin pages and the JSON API: students, events, stats.
   ===================================================== */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/* ===================== STUDENTS ===================== */

/** @return array [clean, errors] */
function student_validate(array $in, bool $isNew, int $excludeId = 0): array
{
    $c = [
        'name'     => clean_text($in['name'] ?? '', 50),
        'email'    => strtolower(clean_text($in['email'] ?? '', 100)),
        'mobile'   => clean_text($in['mobile'] ?? '', 10),
        'roll'     => strtoupper(clean_text($in['roll'] ?? '', 15)),
        'course'   => clean_text($in['course'] ?? ''),
        'year'     => clean_text($in['year'] ?? ''),
        'gender'   => clean_text($in['gender'] ?? ''),
        'role'     => clean_text($in['role'] ?? 'student'),
        'password' => (string) ($in['password'] ?? ''),
    ];
    $err = [];
    if (!preg_match(RX_NAME, $c['name']))       $err[] = 'Name should contain only letters and spaces (3-50 characters).';
    if (!preg_match(RX_EMAIL, $c['email']))     $err[] = 'Please enter a valid email address.';
    if (!preg_match(RX_MOBILE, $c['mobile']))   $err[] = 'Mobile must be 10 digits starting with 6-9.';
    if (!preg_match(RX_ROLL, $c['roll']))       $err[] = 'Roll number must be 5-15 letters/digits.';
    if (!in_array($c['course'], COURSES, true)) $err[] = 'Please select a valid course.';
    if (!in_array($c['year'], YEARS, true))     $err[] = 'Please select a valid year.';
    if (!in_array($c['gender'], GENDERS, true)) $err[] = 'Please select a valid gender.';
    if (!in_array($c['role'], ROLES, true))     $err[] = 'Invalid role.';

    if ($isNew || $c['password'] !== '') {
        if (!preg_match(RX_PASSWORD, $c['password'])) {
            $err[] = 'Password needs 8+ characters with a letter, a number and a special character.';
        }
    }

    // Duplicate checks (only when the fields themselves are valid)
    if (!$err || (preg_match(RX_EMAIL, $c['email']) && preg_match(RX_ROLL, $c['roll']))) {
        if (preg_match(RX_EMAIL, $c['email']) &&
            db_one('SELECT student_id FROM students WHERE email = ? AND student_id <> ?', 'si', [$c['email'], $excludeId])) {
            $err[] = 'This email address is already registered.';
        }
        if (preg_match(RX_ROLL, $c['roll']) &&
            db_one('SELECT student_id FROM students WHERE roll_no = ? AND student_id <> ?', 'si', [$c['roll'], $excludeId])) {
            $err[] = 'This roll number is already registered.';
        }
    }
    return [$c, $err];
}

function student_create(array $c): int
{
    db_exec('INSERT INTO students (roll_no, full_name, email, mobile, course, study_year, gender, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        'sssssssss',
        [$c['roll'], $c['name'], $c['email'], $c['mobile'], $c['course'], $c['year'], $c['gender'],
         password_hash($c['password'], PASSWORD_DEFAULT), $c['role']]);
    return db_insert_id();
}

function student_update(int $id, array $c): int
{
    $n = db_exec('UPDATE students SET roll_no=?, full_name=?, email=?, mobile=?, course=?, study_year=?, gender=?, role=? WHERE student_id=?',
        'ssssssssi', [$c['roll'], $c['name'], $c['email'], $c['mobile'], $c['course'], $c['year'], $c['gender'], $c['role'], $id]);
    if ($c['password'] !== '') {
        $n += db_exec('UPDATE students SET password_hash=? WHERE student_id=?', 'si', [password_hash($c['password'], PASSWORD_DEFAULT), $id]);
    }
    return $n;
}

function student_delete(int $id): int
{
    return db_exec('DELETE FROM students WHERE student_id = ?', 'i', [$id]);
}

function student_find(int $id): ?array
{
    return db_one('SELECT student_id, roll_no, full_name, email, mobile, course, study_year, gender, role, last_login, created_at
                   FROM students WHERE student_id = ?', 'i', [$id]);
}

function student_public(array $r): array
{
    return [
        'id' => (int) $r['student_id'], 'roll' => $r['roll_no'], 'name' => $r['full_name'], 'email' => $r['email'],
        'mobile' => $r['mobile'], 'course' => $r['course'], 'year' => $r['study_year'], 'gender' => $r['gender'],
        'role' => $r['role'], 'created' => $r['created_at'],
    ];
}

/** @return array [rows, total] */
function student_list(array $f, int $page = 1, int $per = 10): array
{
    $where = [];
    $types = '';
    $params = [];
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(full_name LIKE ? OR email LIKE ? OR roll_no LIKE ? OR mobile LIKE ?)';
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
    }
    foreach (['course' => 'course', 'year' => 'study_year', 'role' => 'role'] as $key => $col) {
        if (!empty($f[$key])) {
            $where[] = "$col = ?";
            $types .= 's';
            $params[] = (string) $f[$key];
        }
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int) db_value("SELECT COUNT(*) FROM students $w", $types, $params);
    $page = max(1, $page);
    $rows = db_all("SELECT student_id, roll_no, full_name, email, mobile, course, study_year, gender, role, created_at
                    FROM students $w ORDER BY student_id DESC LIMIT ? OFFSET ?",
        $types . 'ii', array_merge($params, [$per, ($page - 1) * $per]));
    return [$rows, $total];
}

/* ====================== EVENTS ====================== */

function event_validate(array $in): array
{
    $c = [
        'title'       => clean_text($in['title'] ?? '', 100),
        'date'        => clean_text($in['date'] ?? '', 10),
        'venue'       => clean_text($in['venue'] ?? '', 100),
        'category'    => clean_text($in['category'] ?? ''),
        'description' => clean_text($in['description'] ?? '', 1000),
        'capacity'    => $in['capacity'] ?? 100,
    ];
    $err = [];
    if (mb_strlen($c['title']) < 3)  $err[] = 'Event title must be at least 3 characters.';
    $d = DateTime::createFromFormat('Y-m-d', $c['date']);
    if (!$d || $d->format('Y-m-d') !== $c['date']) $err[] = 'Please enter a valid date (YYYY-MM-DD).';
    if (mb_strlen($c['venue']) < 2)  $err[] = 'Venue is required.';
    if (!in_array($c['category'], EVENT_CATEGORIES, true)) $err[] = 'Please select a valid category.';
    if (mb_strlen($c['description']) < 10) $err[] = 'Description must be at least 10 characters.';
    if (filter_var($c['capacity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]) === false) {
        $err[] = 'Capacity must be a whole number between 1 and 5000.';
    }
    $c['capacity'] = (int) $c['capacity'];
    return [$c, $err];
}

/** @return array [stored filename|null, error|null] */
function poster_handle_upload(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, null];
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'The poster is larger than the server allows.',
        UPLOAD_ERR_FORM_SIZE  => 'The poster is too large.',
        UPLOAD_ERR_PARTIAL    => 'The poster was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Could not write the poster to disk.',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, $messages[$file['error']] ?? 'Poster upload failed.'];
    if ($file['size'] > MAX_POSTER_SIZE)  return [null, 'Poster must be 2 MB or smaller.'];
    if (!is_uploaded_file($file['tmp_name'])) return [null, 'Invalid upload.'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_POSTER_EXT, true)) return [null, 'Poster must be a JPG, PNG or WEBP image.'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_POSTER_MIME, true)) return [null, 'The file is not a valid JPG, PNG or WEBP image.'];
    if (@getimagesize($file['tmp_name']) === false) return [null, 'The file is not a real image.'];

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true)) return [null, 'Upload folder is missing.'];
    $ext = $ext === 'jpeg' ? 'jpg' : $ext;
    $name = bin2hex(random_bytes(12)) . '.' . $ext;      // never trust the client file name
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) return [null, 'Could not save the poster.'];
    @chmod(UPLOAD_DIR . '/' . $name, 0644);
    return [$name, null];
}

function poster_delete(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name)) {
        @unlink(UPLOAD_DIR . '/' . $name);
    }
}

function event_create(array $c, ?string $poster): int
{
    db_exec('INSERT INTO events (title, event_date, venue, category, description, capacity, poster) VALUES (?, ?, ?, ?, ?, ?, ?)',
        'sssssis', [$c['title'], $c['date'], $c['venue'], $c['category'], $c['description'], $c['capacity'], $poster]);
    return db_insert_id();
}

function event_update(int $id, array $c, ?string $newPoster = null, bool $removePoster = false): void
{
    $old = db_value('SELECT poster FROM events WHERE event_id = ?', 'i', [$id]);
    $poster = $old;
    if ($newPoster !== null)  $poster = $newPoster;
    elseif ($removePoster)    $poster = null;
    db_exec('UPDATE events SET title=?, event_date=?, venue=?, category=?, description=?, capacity=?, poster=? WHERE event_id=?',
        'sssssisi', [$c['title'], $c['date'], $c['venue'], $c['category'], $c['description'], $c['capacity'], $poster, $id]);
    if ($old && $old !== $poster) poster_delete($old);
}

function event_delete(int $id): int
{
    $old = db_value('SELECT poster FROM events WHERE event_id = ?', 'i', [$id]);
    $n = db_exec('DELETE FROM events WHERE event_id = ?', 'i', [$id]);
    if ($n && $old) poster_delete($old);
    return $n;
}

function event_find(int $id): ?array
{
    return db_one('SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.status = \'registered\') AS registered
                   FROM events e WHERE e.event_id = ?', 'i', [$id]);
}

function event_public(array $r): array
{
    return [
        'id' => (int) $r['event_id'], 'name' => $r['title'], 'title' => $r['title'], 'date' => $r['event_date'],
        'venue' => $r['venue'], 'category' => $r['category'], 'description' => $r['description'],
        'capacity' => (int) $r['capacity'], 'registered' => (int) ($r['registered'] ?? 0),
        'poster' => $r['poster'] ? UPLOAD_URL . '/' . $r['poster'] : null,
    ];
}

/** @return array [rows, total]; $per = 0 returns everything */
function event_list(array $f, int $page = 1, int $per = 0): array
{
    $where = [];
    $types = '';
    $params = [];
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(e.title LIKE ? OR e.venue LIKE ?)';
        $types .= 'ss';
        array_push($params, $like, $like);
    }
    if (!empty($f['category'])) {
        $where[] = 'e.category = ?';
        $types .= 's';
        $params[] = (string) $f['category'];
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int) db_value("SELECT COUNT(*) FROM events e $w", $types, $params);
    $sql = "SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.status = 'registered') AS registered
            FROM events e $w ORDER BY e.event_date ASC, e.event_id ASC";
    if ($per > 0) {
        $sql .= ' LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $per;
        $params[] = (max(1, $page) - 1) * $per;
    }
    return [db_all($sql, $types, $params), $total];
}

/* ================= ADMIN DASHBOARD STATS ================= */

function admin_stats(): array
{
    return [
        'users'          => (int) db_value('SELECT COUNT(*) FROM students'),
        'students'       => (int) db_value("SELECT COUNT(*) FROM students WHERE role = 'student'"),
        'admins'         => (int) db_value("SELECT COUNT(*) FROM students WHERE role = 'admin'"),
        'events'         => (int) db_value('SELECT COUNT(*) FROM events'),
        'upcoming'       => (int) db_value('SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()'),
        'registrations'  => (int) db_value("SELECT COUNT(*) FROM registrations WHERE status = 'registered'"),
        'latest_regs'    => db_all("SELECT r.registered_at, s.full_name, s.roll_no, e.title
                                    FROM registrations r
                                    JOIN students s ON s.student_id = r.student_id
                                    JOIN events e ON e.event_id = r.event_id
                                    ORDER BY r.registered_at DESC, r.registration_id DESC LIMIT 5"),
        'latest_students' => db_all('SELECT full_name, roll_no, email, course, created_at FROM students ORDER BY student_id DESC LIMIT 5'),
        'per_event'      => db_all("SELECT e.title, e.capacity,
                                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.event_id AND r.status = 'registered') AS cnt
                                    FROM events e ORDER BY e.event_date ASC LIMIT 8"),
    ];
}
