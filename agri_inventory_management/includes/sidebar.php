<?php
declare(strict_types=1);

$user = current_user();
$role = $user['role'] ?? 'guest';

$items = [
    'dashboard' => ['Dashboard Home', '/agri_inventory_management/modules/dashboard.php', ['admin', 'field_officer', 'inventory_manager', 'supplier', 'qc_officer', 'iot']],
    'field_operations' => ['Field Operations', '/agri_inventory_management/modules/field_operations.php', ['admin', 'field_officer']],
    'products' => ['Product Catalog', '/agri_inventory_management/modules/products.php', ['admin', 'inventory_manager']],
    'suppliers' => ['Supplier Management', '/agri_inventory_management/modules/suppliers.php', ['admin', 'inventory_manager', 'supplier']],
    'inventory' => ['Inventory & Storage', '/agri_inventory_management/modules/inventory.php', ['admin', 'inventory_manager']],
    'sensors' => ['IoT Sensor Network', '/agri_inventory_management/modules/sensors.php', ['admin', 'iot', 'field_officer', 'inventory_manager']],
    'quality_control' => ['Quality Control', '/agri_inventory_management/modules/quality_control.php', ['admin', 'qc_officer']],
];
?>
<aside class="sidebar">
    <div class="sidebar-header">AgriManage</div>
    <ul class="nav-menu">
        <?php foreach ($items as $key => $item): ?>
            <?php
            $allowedRoles = $item[2];
            $allowed = $role === 'admin' || in_array($role, $allowedRoles, true);
            if (!$allowed) {
                continue;
            }
            ?>
            <li class="nav-item <?= $activePage === $key ? 'active' : '' ?>">
                <a href="<?= h($item[1]) ?>"><?= h($item[0]) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($user !== null): ?>
        <div class="sidebar-footer">
            <a href="/agri_inventory_management/auth/logout.php" class="logout-link">Logout</a>
        </div>
    <?php endif; ?>
</aside>
