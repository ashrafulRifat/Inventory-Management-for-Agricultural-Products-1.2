<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'AgriManage';
$activePage = $activePage ?? '';
$searchPlaceholder = $searchPlaceholder ?? 'Search records...';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - AgriManage</title>
    <link rel="stylesheet" href="/agri_inventory_management/assets/css/app.css">
</head>
<body>
<div class="app-container">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main-wrapper">
        <header class="top-header">
            <div class="search-bar">
                <input type="text" placeholder="<?= h($searchPlaceholder) ?>" disabled>
            </div>
            <div class="user-profile">
                <?= h($user['username'] ?? 'Guest') ?>
                <span class="user-role"><?= h(strtoupper((string) ($user['role'] ?? 'guest'))) ?></span>
            </div>
        </header>
        <main class="content-area">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
            <?php endforeach; ?>
