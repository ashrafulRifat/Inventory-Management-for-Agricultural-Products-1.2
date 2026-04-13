<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('/agri_inventory_management/modules/dashboard.php');
}

$errors = [];

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $username = trim((string) post('username'));
    $password = (string) post('password');

    if ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    }

    if (!$errors) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            set_flash('success', 'Welcome back, ' . $user['username'] . '.');
            redirect('/agri_inventory_management/modules/dashboard.php');
        }

        $errors[] = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AgriManage</title>
    <link rel="stylesheet" href="/agri_inventory_management/assets/css/app.css">
    <style>
        .login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(130deg, #1e3d31, #2c3e50);
            min-width: 1024px;
        }

        .login-card {
            width: 460px;
            background: #fff;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
        }

        .login-card h1 {
            margin: 0 0 8px;
        }

        .demo-note {
            margin-top: 12px;
            font-size: 0.86rem;
            color: #6b7d8a;
            background: #f5f8fa;
            padding: 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <h1>AgriManage Login</h1>
        <p class="small-text">Inventory Management for Agricultural Products</p>

        <?php foreach ($errors as $error): ?>
            <div class="flash flash-error"><?= h($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" class="form-control" required>
            </div>
            <div class="button-row">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
        </form>

        <div class="demo-note">
            Demo users are seeded in SQL import. Default password for demo accounts: <strong>password</strong>
        </div>
    </div>
</div>
</body>
</html>
