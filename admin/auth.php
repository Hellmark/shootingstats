<?php
// admin/auth.php
// Authentication, CSRF protection, and rate limiting

require_once __DIR__ . "/../includes/config.php";

// Uncomment the next two lines temporarily if you're seeing 500 errors:
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie
    ini_set("session.cookie_httponly", "1"); // JS cannot access session cookie
    ini_set("session.cookie_samesite", "Strict"); // Prevent CSRF via cross-site requests
    ini_set('session.cookie_secure', '1');
    session_start();
}

function is_first_run(): bool
{
    return !defined('ADMIN_PASSWORD') || ADMIN_PASSWORD === '';
}

function require_auth(): void
{
    if (is_first_run()) {
        header("Location: login.php");
        exit();
    }
    if (empty($_SESSION["admin_authed"])) {
        header("Location: login.php");
        exit();
    }
}

function attempt_login(string $password): string
{
    // --- Rate limiting ---
    if (is_locked_out()) {
        $remaining = get_lockout_remaining();
        $mins = max(1, (int) ceil($remaining / 60));
        return "Too many failed attempts. Please try again in {$mins} minute" .
            ($mins !== 1 ? "s" : "") .
            ".";
    }

    if (password_verify($password, ADMIN_PASSWORD)) {
        // Successful login — clear failed attempts and regenerate session ID
        $_SESSION["admin_authed"] = true;
        clear_failed_attempts();
        session_regenerate_id(true);
        return "";
    }

    // Failed attempt
    record_failed_attempt();
    return "Incorrect password.";
}

// ── Rate limiting helpers ───────────────────────────────────────────────────

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 900; // 15 minutes

function record_failed_attempt(): void
{
    $_SESSION["login_attempts"] = ($_SESSION["login_attempts"] ?? 0) + 1;
    if (empty($_SESSION["login_first_attempt"])) {
        $_SESSION["login_first_attempt"] = time();
    }
    if ($_SESSION["login_attempts"] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION["login_locked_until"] = time() + LOCKOUT_SECONDS;
    }
}

function clear_failed_attempts(): void
{
    unset(
        $_SESSION["login_attempts"],
        $_SESSION["login_first_attempt"],
        $_SESSION["login_locked_until"],
    );
}

function is_locked_out(): bool
{
    if (empty($_SESSION["login_locked_until"])) {
        return false;
    }
    if (time() >= $_SESSION["login_locked_until"]) {
        // Lockout expired — clear
        clear_failed_attempts();
        return false;
    }
    return true;
}

function get_lockout_remaining(): int
{
    if (!is_locked_out()) {
        return 0;
    }
    return (int) ($_SESSION["login_locked_until"] - time());
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function test_db_connection(string $host, string $name, string $user, string $pass): ?string
{
    try {
        new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return null;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

function write_local_config(
    string $path,
    string $db_host,
    string $db_name,
    string $db_user,
    string $db_pass,
    string $admin_password
): void {
    $hash = password_hash($admin_password, PASSWORD_DEFAULT);
    $content  = "<?php\n";
    $content .= "// includes/config.local.php — never commit this file\n\n";
    $content .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
    $content .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
    $content .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
    $content .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n\n";
    $content .= "define('ADMIN_PASSWORD', " . var_export($hash, true) . ");\n";
    file_put_contents($path, $content);
}

// ── CSRF protection ────────────────────────────────────────────────────────
// Generates a per-session token that must accompany every state-changing request.

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES) .
        '">';
}

/**
 * Validates the CSRF token from POST data. Call this on every POST handler.
 * On failure, halts execution with a 403 response.
 */
function require_csrf(): void
{
    $token = $_POST["_csrf"] ?? "";
    if ($token === "" || !hash_equals($_SESSION["csrf_token"] ?? "", $token)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head>' .
            '<body style="font-family:sans-serif;padding:3rem;text-align:center">' .
            "<h1>403 — Forbidden</h1>" .
            "<p>Invalid or missing security token. Please go back, refresh the page, and try again.</p>" .
            '<p><a href="javascript:history.back()">← Go back</a></p>' .
            "</body></html>";
        exit();
    }
}
