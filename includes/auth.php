<?php
/**
 * Session hardening, login, CSRF and role gates.
 * Every entry point degrades gracefully when MySQL is stopped.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

if (!defined('AUTH_MAX_ATTEMPTS')) define('AUTH_MAX_ATTEMPTS', 5);
if (!defined('AUTH_WINDOW'))      define('AUTH_WINDOW', 900);      // 15 minutes
if (!defined('AUTH_LOCKOUT'))     define('AUTH_LOCKOUT', 900);

/**
 * Cookie path for the session.
 * BASE_PATH is a filesystem path, so it can never match a request URI.
 * Derive the URL path from BASE_URL instead, keeping any %20 encoding.
 */
function auth_url_path()
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }

    $path = '/';
    if (defined('BASE_URL')) {
        $parsed = parse_url((string) BASE_URL, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $trimmed = trim($parsed, '/');
            $path = $trimmed === '' ? '/' : '/' . $trimmed . '/';
        }
    }
    return $path;
}

/** Boot the session once, with hardened cookie parameters. */
function auth_start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => auth_url_path(),
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('ECOSESSID');
        session_start();
    }

    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    }
    if (!isset($_SESSION['last_seen'])) {
        $_SESSION['last_seen'] = time();
    }

    /* Idle timeout: 2 hours of inactivity ends the session. */
    if (time() - (int) $_SESSION['last_seen'] > 7200) {
        auth_destroy_session();
        session_start();
        $_SESSION['created_at'] = time();
        $_SESSION['last_seen']   = time();
    }
    $_SESSION['last_seen'] = time();
}

/** Wipe the session array and cookie without emitting output. */
function auth_destroy_session()
{
    $_SESSION = [];
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => (bool) $params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/* ------------------------------------------------------------------ CSRF */

/** Session-bound CSRF token; stable for the life of the session. */
function csrf_token()
{
    auth_start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every POST form. */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Constant-time comparison; falls back to the POST body then the X-CSRF-Token header. */
function verify_csrf($token = null)
{
    auth_start_session();

    if ($token === null) {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    }
    if ($token === null && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    $expected = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
    $given    = is_scalar($token) ? (string) $token : '';

    if ($expected === '' || $given === '') {
        return false;
    }
    return hash_equals($expected, $given);
}

/** Rotate the token — called after any privilege change. */
function csrf_rotate()
{
    auth_start_session();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/** Abort a POST that failed its CSRF check. */
function require_csrf($token = null)
{
    if (verify_csrf($token)) {
        return;
    }
    if (is_ajax()) {
        json_response(['ok' => false, 'error' => 'csrf'], 403);
    }
    flash('error', 'Your session expired. Please try that again.');
    $back = isset($_POST['return_to']) ? (string) $_POST['return_to'] : 'dashboard.php';
    redirect(preg_match('#^[a-zA-Z0-9_./-]+$#', $back) ? $back : 'dashboard.php');
}

function is_ajax()
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
}

function json_response(array $payload, $status = 200)
{
    if (!headers_sent()) {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/* ----------------------------------------------------------------- users */

/** The signed-in user row, or null. Cached per request. */
function current_user()
{
    static $cached = false;
    static $user   = null;

    auth_start_session();

    if ($cached) {
        return $user;
    }
    $cached = true;

    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($id < 1) {
        $user = null;
        return $user;
    }

    $row = db_one('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1', [$id]);
    if ($row === null) {
        /* Account was removed or the DB is unreachable. */
        unset($_SESSION['user_id']);
        $user = null;
        return $user;
    }

    $row['id']   = (int) $row['id'];
    $row['role'] = strtolower((string) $row['role']);
    $user        = $row;
    return $user;
}

function is_logged_in()
{
    return current_user() !== null;
}

/* Aliases kept for callers that only need the id or a boolean. */
function current_user_id()
{
    $user = current_user();
    return $user === null ? 0 : (int) $user['id'];
}

function logged_in()
{
    return is_logged_in();
}

/** First letter of the display name, for the CSS avatar. */
function user_initial($name = null)
{
    $name = $name === null ? (current_user()['name'] ?? '') : $name;
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    $first = preg_split('/\s+/u', $name);
    $out   = mb_substr((string) ($first[0] ?? '?'), 0, 1);
    return mb_strtoupper($out);
}

function user_has_role($roles)
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    $allowed = is_array($roles) ? $roles : [$roles];
    foreach ($allowed as $role) {
        if (strtolower(trim((string) $role)) === $user['role']) {
            return true;
        }
    }
    return false;
}

/** Gate for every authenticated page. */
function require_login($returnTo = null)
{
    auth_start_session();
    if (is_logged_in()) {
        return;
    }
    if ($returnTo === null) {
        $returnTo = urldecode((string) ($_SERVER['REQUEST_URI'] ?? ''));
    }
    /* Only ever bounce back to a path inside this app. */
    if (!is_safe_return($returnTo)) {
        $returnTo = '';
    }
    /* Both keys are consulted so any caller can stage an intended URL. */
    $_SESSION['eco_intended'] = $returnTo;
    $_SESSION['eco_return']   = $returnTo;
    flash('info', 'Sign in to reach the monitoring console.');
    redirect('login.php' . ($returnTo !== '' ? '?return=' . rawurlencode($returnTo) : ''));
}

/** Gate for admin-only actions and pages. */
function require_role($roles, $returnTo = null)
{
    require_login($returnTo);
    if (user_has_role($roles)) {
        return;
    }
    flash('error', 'That action needs an administrator role.');
    redirect('dashboard.php');
}

function is_safe_return($path)
{
    $path = (string) $path;
    if ($path === '' || $path[0] !== '/') {
        return false;
    }
    return strpos($path, '//') !== 0;
}

/* -------------------------------------------------------- login throttle */

/** Verify an email + password pair and, on success, open the session. */
function attempt_login($email, $password)
{
    auth_start_session();

    $email    = strtolower(trim((string) $email));
    $password = (string) $password;

    if ($email === '' || $password === '') {
        return ['ok' => false, 'message' => 'Enter your email address and password.'];
    }
    if (!db_available()) {
        return ['ok' => false, 'message' => 'The database is unreachable, so sign-in is unavailable right now.'];
    }

    $key = 'eco_login_' . sha1((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli') . '|' . $email);
    $now = time();

    $tries = isset($_SESSION[$key]) && is_array($_SESSION[$key]) ? $_SESSION[$key] : ['count' => 0, 'first' => $now];
    if ($now - (int) ($tries['first'] ?? $now) > AUTH_WINDOW) {
        $tries = ['count' => 0, 'first' => $now];
    }
    if ((int) $tries['count'] >= AUTH_MAX_ATTEMPTS) {
        $wait = (int) ceil((AUTH_WINDOW - ($now - (int) $tries['first'])) / 60);
        return [
            'ok'      => false,
            'message' => 'Too many failed attempts. Try again in about ' . max(1, $wait) . ' minute' . (max(1, $wait) === 1 ? '' : 's') . '.',
            'locked'  => true,
        ];
    }

    $user = db_one('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1', [$email]);

    /* Constant work whether or not the account exists. */
    $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

    if ($user === null || !password_verify($password, $hash)) {
        $tries['count'] = (int) $tries['count'] + 1;
        $_SESSION[$key]  = $tries;
        return ['ok' => false, 'message' => 'That email and password combination is not recognised.'];
    }

    unset($_SESSION[$key]);

    /* New session id on privilege change. */
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_name']  = (string) $user['name'];
    $_SESSION['user_role']  = strtolower((string) $user['role']);
    $_SESSION['created_at'] = time();
    $_SESSION['last_seen']  = time();
    csrf_rotate();

    /* Remember where the visitor was headed before the detour through login. */
    if (!empty($_SESSION['eco_intended']) && is_string($_SESSION['eco_intended'])) {
        $_SESSION['eco_return'] = $_SESSION['eco_intended'];
    }

    return ['ok' => true, 'user' => $user];
}

/** End the session and hand the visitor back to the login screen. */
function logout()
{
    auth_start_session();
    auth_destroy_session();
}
