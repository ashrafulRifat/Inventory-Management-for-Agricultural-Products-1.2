<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function get_value(string $key, mixed $default = null): mixed
{
    return $_GET[$key] ?? $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return [];
    }

    $flashes = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flashes;
}

function csrf_token(): string
{
    if (!isset($_SESSION)) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    if (!isset($_SESSION)) {
        session_start();
    }

    $token = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    return is_string($token) && is_string($expected) && hash_equals($expected, $token);
}

function display_code(string $entity, int $id): string
{
    $map = [
        'field_officer' => ['FO', 2, 0],
        'farmer' => ['FAR', 3, 100],
        'field' => ['FLD', 4, 8000],
        'harvest' => ['HRV', 3, 300],
        'request' => ['REQ', 3, 50],
        'product' => ['PRD', 4, 1000],
        'supplier' => ['SUP', 3, 0],
        'order' => ['ORD', 4, 5000],
        'line_item' => ['LIT', 4, 0],
        'manager' => ['MGR', 3, 100],
        'storage' => ['STR', 3, 0],
        'stock' => ['STK', 4, 5000],
        'sensor' => ['SEN', 3, 0],
        'telemetry' => ['LOG', 7, 9940000],
        'status' => ['STS', 3, 0],
        'qc' => ['QCO', 3, 0],
        'inspection' => ['INSP', 4, 9000],
    ];

    [$prefix, $pad, $offset] = $map[$entity] ?? ['ID', 3, 0];
    $numeric = $id + $offset;

    return $prefix . '-' . str_pad((string) $numeric, $pad, '0', STR_PAD_LEFT);
}

function to_decimal(mixed $value, int $precision = 2): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float) $value, $precision);
}

function table_delete(PDO $pdo, string $table, string $pkName, int $id): bool
{
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$pkName} = :id");
    return $stmt->execute(['id' => $id]);
}

function fetch_pairs(PDO $pdo, string $sql, string $idField, string $labelField): array
{
    $rows = $pdo->query($sql)->fetchAll();
    $pairs = [];

    foreach ($rows as $row) {
        $pairs[(int) $row[$idField]] = (string) $row[$labelField];
    }

    return $pairs;
}

function badge_class(string $value): string
{
    $value = strtolower(trim($value));

    return match ($value) {
        'excellent', 'optimal', 'secure', 'fulfilled', 'delivered', 'approved', 'good', 'online', 'available' => 'badge-good',
        'acceptable', 'processing', 'normal', 'active', 'monitor' => 'badge-info',
        'pending', 'degraded', 'elevated', 'warning' => 'badge-warning',
        'rejected', 'critical', 'cancelled', 'offline', 'alert', 'high' => 'badge-alert',
        default => 'badge-neutral',
    };
}

function validate_required(array $data, array $fields): array
{
    $errors = [];

    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[] = $label . ' is required.';
        }
    }

    return $errors;
}
