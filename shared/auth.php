<?php
/**
 * Auth sederhana untuk admin panel (session-based).
 */

require_once __DIR__ . '/db.php';

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function current_admin(): ?array
{
    start_admin_session();
    return $_SESSION['admin'] ?? null;
}

function current_admin_id(): ?int
{
    return current_admin()['id'] ?? null;
}

function require_login(): array
{
    $admin = current_admin();
    if (!$admin) {
        header('Location: login.php');
        exit;
    }
    return $admin;
}

function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => $user['id'], 'username' => $user['username']];
    return true;
}

function logout(): void
{
    start_admin_session();
    $_SESSION = [];
    session_destroy();
}

// --- CSRF token, dipakai di setiap form POST di admin panel ---
function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function require_csrf(): void
{
    start_admin_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Sesi tidak valid, silakan reload halaman dan coba lagi.');
    }
}
