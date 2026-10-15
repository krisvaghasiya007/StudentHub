<?php
/* =====================================================
   Shared helpers: sanitising, validation, CSV/JSON
   storage, redirects, JSON responses, pagination.
   ===================================================== */
require_once __DIR__ . '/config.php';

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trim, strip tags and collapse control characters. */
function clean_text($v, int $max = 0): string
{
    $v = trim(strip_tags((string) $v));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return $max > 0 ? mb_substr($v, 0, $max) : $v;
}

/** URL path of the site root (works from /, /php, /php/api, /admin). */
function base_url(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/(php/api|php|admin)$#', '', $dir);
    return rtrim($dir, '/');
}

function url(string $path): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* ---------- Regex rules (mirror register-validation.js) ---------- */
const RX_NAME     = '/^[A-Za-z][A-Za-z\s]{2,49}$/';
const RX_EMAIL    = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
const RX_MOBILE   = '/^[6-9]\d{9}$/';
const RX_ROLL     = '/^[A-Za-z0-9]{5,15}$/';
const RX_PASSWORD = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,72}$/';

/* ---------- Contact form validation ---------- */
function validate_contact(array $in): array
{
    $c = [
        'name'    => clean_text($in['name'] ?? '', 50),
        'email'   => clean_text($in['email'] ?? '', 100),
        'subject' => clean_text($in['subject'] ?? '', 100),
        'message' => clean_text($in['message'] ?? '', 1000),
    ];
    $err = [];
    if (mb_strlen($c['name']) < 3)                      $err[] = 'Name must be at least 3 characters.';
    if (!preg_match(RX_EMAIL, $c['email']))             $err[] = 'Please enter a valid email address.';
    if (mb_strlen($c['subject']) < 3)                   $err[] = 'Subject must be at least 3 characters.';
    if (mb_strlen($c['message']) < 10)                  $err[] = 'Message must be at least 10 characters.';
    return [$c, $err];
}

/* ---------- File storage (CSV + JSON) ---------- */

/** Stop spreadsheet formula injection: =cmd|... , +, -, @ */
function csv_safe(string $cell): string
{
    return preg_match('/^[=+\-@\t\r]/', $cell) ? "'" . $cell : $cell;
}

function save_record(string $basename, array $record): bool
{
    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0775, true)) {
        return false;
    }
    $record = ['saved_at' => date('Y-m-d H:i:s')] + $record;

    // CSV
    $csv = DATA_DIR . "/$basename.csv";
    $isNew = !file_exists($csv);
    $fh = fopen($csv, 'a');
    if (!$fh) return false;
    flock($fh, LOCK_EX);
    if ($isNew) fputcsv($fh, array_keys($record));
    fputcsv($fh, array_map(fn($v) => csv_safe((string) $v), array_values($record)));
    flock($fh, LOCK_UN);
    fclose($fh);

    // JSON (read-modify-write under lock)
    $json = DATA_DIR . "/$basename.json";
    $fh = fopen($json, 'c+');
    if (!$fh) return false;
    flock($fh, LOCK_EX);
    $existing = json_decode(stream_get_contents($fh) ?: '[]', true);
    if (!is_array($existing)) $existing = [];
    $existing[] = $record;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return true;
}

/* ---------- Flash messages ---------- */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_html(): string
{
    if (empty($_SESSION['flash'])) return '';
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = ['success' => 'alert-success', 'error' => 'alert-error', 'warning' => 'alert-warning'][$f['type']] ?? 'alert-warning';
        $out .= '<div class="alert ' . $cls . '" role="status">' . e($f['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ---------- JSON API helpers ---------- */
function json_out(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

/* ---------- Pagination ---------- */
function pager_html(int $total, int $page, int $perPage, array $params = []): string
{
    $pages = max(1, (int) ceil($total / $perPage));
    if ($pages <= 1) return '';
    $link = function (int $p, string $label, string $cls = '') use ($params) {
        $q = http_build_query(array_filter(array_merge($params, ['page' => $p]), fn($v) => $v !== '' && $v !== null));
        return '<a class="page-link ' . $cls . '" href="?' . e($q) . '">' . $label . '</a>';
    };
    $html = '<div class="pagination">';
    if ($page > 1) $html .= $link($page - 1, '&laquo; Prev');
    for ($i = 1; $i <= $pages; $i++) $html .= $link($i, (string) $i, $i === $page ? 'active' : '');
    if ($page < $pages) $html .= $link($page + 1, 'Next &raquo;');
    return $html . '</div>';
}

/* ---------- Result page used by the form handlers ---------- */
function render_result(string $title, string $type, string $message, array $details = [], array $errors = [], array $links = []): void
{
    require_once __DIR__ . '/layout.php';
    page_start($title, '../');
    echo '<div class="form-box" style="width:60%;">';
    $cls = ['success' => 'alert-success', 'error' => 'alert-error', 'warning' => 'alert-warning'][$type] ?? 'alert-warning';
    echo '<div class="alert ' . $cls . '"><strong>' . e($message) . '</strong>';
    if ($errors) {
        echo '<ul>';
        foreach ($errors as $er) echo '<li>' . e($er) . '</li>';
        echo '</ul>';
    }
    echo '</div>';
    if ($details) {
        echo '<table class="result-details">';
        foreach ($details as $k => $v) echo '<tr><th>' . e($k) . '</th><td>' . e($v) . '</td></tr>';
        echo '</table>';
    }
    foreach ($links as $label => $href) {
        echo '<a class="btn-link" href="' . e($href) . '">' . e($label) . '</a>';
    }
    echo '</div>';
    page_end('../');
    exit;
}
