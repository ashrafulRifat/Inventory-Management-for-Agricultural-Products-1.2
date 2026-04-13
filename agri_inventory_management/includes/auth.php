<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'user_id' => (int) $user['user_id'],
        'username' => (string) $user['username'],
        'role' => (string) $user['role'],
        'supplier_id' => $user['supplier_id'] !== null ? (int) $user['supplier_id'] : null,
        'field_officer_id' => $user['field_officer_id'] !== null ? (int) $user['field_officer_id'] : null,
        'manager_id' => $user['manager_id'] !== null ? (int) $user['manager_id'] : null,
        'officer_id' => $user['officer_id'] !== null ? (int) $user['officer_id'] : null,
    ];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /agri_inventory_management/auth/login.php');
        exit;
    }
}

function has_role(string|array $roles): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }

    $roles = is_array($roles) ? $roles : [$roles];

    if ($user['role'] === 'admin') {
        return true;
    }

    return in_array($user['role'], $roles, true);
}

function require_roles(string|array $roles): void
{
    if (!has_role($roles)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}
