<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_roles(['inventory_manager']);

$pdo = get_pdo();
$errors = [];

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token.';
    }

    $action = (string) post('action');

    if (!$errors) {
        try {
            switch ($action) {
                case 'save_manager':
                    $id = (int) post('manager_id');
                    $data = [
                        'name' => trim((string) post('name')),
                        'email' => trim((string) post('email')),
                        'contact' => trim((string) post('contact')),
                    ];
                    $errors = array_merge($errors, validate_required($data, [
                        'name' => 'Manager name',
                        'email' => 'Email',
                        'contact' => 'Contact',
                    ]));

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare('UPDATE inventory_managers SET name = :name, email = :email, contact = :contact WHERE manager_id = :id');
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Manager updated successfully.');
                        } else {
                            $stmt = $pdo->prepare('INSERT INTO inventory_managers (name, email, contact) VALUES (:name, :email, :contact)');
                            $stmt->execute($data);
                            set_flash('success', 'Manager created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/inventory.php');
                    }
                    break;

                case 'delete_manager':
                    table_delete($pdo, 'inventory_managers', 'manager_id', (int) post('manager_id'));
                    set_flash('success', 'Manager deleted successfully.');
                    redirect('/agri_inventory_management/modules/inventory.php');
                    break;

                case 'save_storage':
                    $id = (int) post('storage_id');
                    $size = (string) post('storage_size');
                    $size = in_array($size, ['small', 'medium', 'large'], true) ? $size : 'small';

                    $data = [
                        'manager_id' => (int) post('manager_id'),
                        'storage_name' => trim((string) post('storage_name')),
                        'storage_size' => $size,
                        'storage_type' => trim((string) post('storage_type')),
                        'storage_condition' => trim((string) post('storage_condition')),
                        'capacity' => to_decimal(post('capacity')),
                    ];

                    $errors = array_merge($errors, validate_required($data, [
                        'storage_name' => 'Storage name',
                        'storage_type' => 'Storage type',
                        'storage_condition' => 'Storage condition',
                    ]));

                    if ($data['manager_id'] <= 0) {
                        $errors[] = 'Valid manager is required.';
                    }
                    if ($data['capacity'] === null || $data['capacity'] <= 0) {
                        $errors[] = 'Capacity must be greater than zero.';
                    }

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE storage_facilities
                                 SET manager_id = :manager_id,
                                     storage_name = :storage_name,
                                     storage_size = :storage_size,
                                     storage_type = :storage_type,
                                     storage_condition = :storage_condition,
                                     capacity = :capacity
                                 WHERE storage_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Storage facility updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO storage_facilities (manager_id, storage_name, storage_size, storage_type, storage_condition, capacity)
                                 VALUES (:manager_id, :storage_name, :storage_size, :storage_type, :storage_condition, :capacity)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Storage facility created successfully.');
                        }

                        redirect('/agri_inventory_management/modules/inventory.php');
                    }
                    break;

                case 'delete_storage':
                    table_delete($pdo, 'storage_facilities', 'storage_id', (int) post('storage_id'));
                    set_flash('success', 'Storage facility deleted successfully.');
                    redirect('/agri_inventory_management/modules/inventory.php');
                    break;

                case 'save_stock':
                    $id = (int) post('stock_id');
                    $data = [
                        'product_id' => (int) post('product_id'),
                        'storage_id' => (int) post('storage_id'),
                        'current_quantity' => to_decimal(post('current_quantity')),
                        'minimum_threshold_alert' => to_decimal(post('minimum_threshold_alert')),
                    ];

                    if ($data['product_id'] <= 0 || $data['storage_id'] <= 0) {
                        $errors[] = 'Valid product and storage are required.';
                    }
                    if ($data['current_quantity'] === null || $data['current_quantity'] < 0) {
                        $errors[] = 'Current quantity must be numeric and cannot be negative.';
                    }
                    if ($data['minimum_threshold_alert'] === null || $data['minimum_threshold_alert'] < 0) {
                        $errors[] = 'Minimum threshold must be numeric and cannot be negative.';
                    }

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE inventory_stock
                                 SET product_id = :product_id,
                                     storage_id = :storage_id,
                                     current_quantity = :current_quantity,
                                     minimum_threshold_alert = :minimum_threshold_alert
                                 WHERE stock_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Stock record updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO inventory_stock (product_id, storage_id, current_quantity, minimum_threshold_alert)
                                 VALUES (:product_id, :storage_id, :current_quantity, :minimum_threshold_alert)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Stock record created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/inventory.php');
                    }
                    break;

                case 'delete_stock':
                    table_delete($pdo, 'inventory_stock', 'stock_id', (int) post('stock_id'));
                    set_flash('success', 'Stock record deleted successfully.');
                    redirect('/agri_inventory_management/modules/inventory.php');
                    break;

                case 'receive_order_item':
                    $lineItemId = (int) post('line_item_id');
                    $storageId = (int) post('receive_storage_id');

                    if ($lineItemId <= 0 || $storageId <= 0) {
                        $errors[] = 'Valid line item and storage are required.';
                        break;
                    }

                    $stmt = $pdo->prepare(
                        "SELECT li.product_id, li.quantity, po.status
                         FROM order_line_items li
                         INNER JOIN purchase_orders po ON po.order_id = li.order_id
                         WHERE li.line_item_id = :line_item_id"
                    );
                    $stmt->execute(['line_item_id' => $lineItemId]);
                    $line = $stmt->fetch();

                    if (!$line) {
                        $errors[] = 'Line item not found.';
                        break;
                    }

                    if ($line['status'] !== 'delivered') {
                        $errors[] = 'Only delivered purchase orders can be received into stock.';
                        break;
                    }

                    $pdo->beginTransaction();

                    $stockStmt = $pdo->prepare('SELECT stock_id, current_quantity FROM inventory_stock WHERE product_id = :product_id AND storage_id = :storage_id LIMIT 1');
                    $stockStmt->execute([
                        'product_id' => (int) $line['product_id'],
                        'storage_id' => $storageId,
                    ]);
                    $stock = $stockStmt->fetch();

                    if ($stock) {
                        $updateStmt = $pdo->prepare('UPDATE inventory_stock SET current_quantity = :quantity WHERE stock_id = :stock_id');
                        $updateStmt->execute([
                            'quantity' => (float) $stock['current_quantity'] + (float) $line['quantity'],
                            'stock_id' => (int) $stock['stock_id'],
                        ]);
                    } else {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO inventory_stock (product_id, storage_id, current_quantity, minimum_threshold_alert)
                             VALUES (:product_id, :storage_id, :current_quantity, :minimum_threshold_alert)'
                        );
                        $insertStmt->execute([
                            'product_id' => (int) $line['product_id'],
                            'storage_id' => $storageId,
                            'current_quantity' => (float) $line['quantity'],
                            'minimum_threshold_alert' => 0,
                        ]);
                    }

                    $pdo->commit();
                    set_flash('success', 'Delivered line item received into stock.');
                    redirect('/agri_inventory_management/modules/inventory.php');
                    break;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Operation failed: ' . $exception->getMessage();
        }
    }
}

$editEntity = (string) get_value('edit_entity', '');
$editId = (int) get_value('edit_id', 0);
$editRecord = null;

$map = [
    'manager' => ['table' => 'inventory_managers', 'pk' => 'manager_id'],
    'storage' => ['table' => 'storage_facilities', 'pk' => 'storage_id'],
    'stock' => ['table' => 'inventory_stock', 'pk' => 'stock_id'],
];

if ($editId > 0 && isset($map[$editEntity])) {
    $meta = $map[$editEntity];
    $stmt = $pdo->prepare("SELECT * FROM {$meta['table']} WHERE {$meta['pk']} = :id");
    $stmt->execute(['id' => $editId]);
    $editRecord = $stmt->fetch();
}

$managers = $pdo->query('SELECT * FROM inventory_managers ORDER BY manager_id DESC')->fetchAll();

$storage = $pdo->query(
    'SELECT sf.*, im.name AS manager_name,
            COALESCE((SELECT SUM(current_quantity) FROM inventory_stock st WHERE st.storage_id = sf.storage_id), 0) AS used_quantity
     FROM storage_facilities sf
     INNER JOIN inventory_managers im ON im.manager_id = sf.manager_id
     ORDER BY sf.storage_id DESC'
)->fetchAll();

$products = $pdo->query('SELECT product_id, product_name, unit_of_measurement FROM products ORDER BY product_name')->fetchAll();

$stock = $pdo->query(
    'SELECT st.*, p.product_name, p.unit_of_measurement, sf.storage_name, sf.capacity, im.name AS manager_name
     FROM inventory_stock st
     INNER JOIN products p ON p.product_id = st.product_id
     INNER JOIN storage_facilities sf ON sf.storage_id = st.storage_id
     INNER JOIN inventory_managers im ON im.manager_id = sf.manager_id
     ORDER BY st.stock_id DESC'
)->fetchAll();

$deliveredItems = $pdo->query(
    "SELECT li.line_item_id, li.order_id, li.quantity, p.product_name, po.delivered_date
     FROM order_line_items li
     INNER JOIN products p ON p.product_id = li.product_id
     INNER JOIN purchase_orders po ON po.order_id = li.order_id
     WHERE po.status = 'delivered'
     ORDER BY li.line_item_id DESC"
)->fetchAll();

$lowStockCount = (int) $pdo->query('SELECT COUNT(*) FROM inventory_stock WHERE current_quantity <= minimum_threshold_alert')->fetchColumn();

$pageTitle = 'Inventory & Storage';
$activePage = 'inventory';
$searchPlaceholder = 'Search facilities, stock IDs, or products...';
require __DIR__ . '/../includes/header.php';
?>

<div class="module-header">
    <h1 class="page-title">Inventory & Storage</h1>
</div>

<div class="dashboard-grid-3">
    <div class="metric-card warning">
        <h3>Low Stock Alerts</h3>
        <div class="value"><?= h((string) $lowStockCount) ?></div>
    </div>
    <div class="metric-card">
        <h3>Storage Facilities</h3>
        <div class="value"><?= h((string) count($storage)) ?></div>
    </div>
    <div class="metric-card">
        <h3>Total Stock Records</h3>
        <div class="value"><?= h((string) count($stock)) ?></div>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="flash flash-error" style="margin-top:12px;"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="dashboard-grid" style="margin-top:20px;">
    <div>
        <div class="section-title">Inventory Managers</div>
        <?php $managerEdit = $editEntity === 'manager' ? $editRecord : null; ?>
        <div class="form-container">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_manager">
                <input type="hidden" name="manager_id" value="<?= h((string) ($managerEdit['manager_id'] ?? 0)) ?>">
                <div class="form-group"><label>Name</label><input class="form-control" name="name" required value="<?= h($managerEdit['name'] ?? '') ?>"></div>
                <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required value="<?= h($managerEdit['email'] ?? '') ?>"></div>
                <div class="form-group"><label>Contact</label><input class="form-control" name="contact" required value="<?= h($managerEdit['contact'] ?? '') ?>"></div>
                <div class="button-row">
                    <button type="submit" class="btn btn-primary"><?= $managerEdit ? 'Update Manager' : 'Add Manager' ?></button>
                    <?php if ($managerEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/inventory.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($managers as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('manager', (int) $row['manager_id'])) ?></strong></td>
                        <td><?= h($row['name']) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td><?= h($row['contact']) ?></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=manager&edit_id=<?= h((string) $row['manager_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this manager?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_manager">
                                <input type="hidden" name="manager_id" value="<?= h((string) $row['manager_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$managers): ?><tr><td colspan="5">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="section-title">Receive Delivered Items</div>
        <div class="form-container">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="receive_order_item">
                <div class="form-group">
                    <label>Delivered Order Line Item</label>
                    <select class="form-control" name="line_item_id" required>
                        <option value="">Select Delivered Item</option>
                        <?php foreach ($deliveredItems as $item): ?>
                            <option value="<?= h((string) $item['line_item_id']) ?>">
                                <?= h(display_code('line_item', (int) $item['line_item_id']) . ' - ' . $item['product_name'] . ' (' . $item['quantity'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Target Storage Facility</label>
                    <select class="form-control" name="receive_storage_id" required>
                        <option value="">Select Storage</option>
                        <?php foreach ($storage as $row): ?>
                            <option value="<?= h((string) $row['storage_id']) ?>"><?= h($row['storage_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="button-row">
                    <button class="btn btn-secondary" type="submit">Receive Into Stock</button>
                </div>
                <p class="small-text">Delivered date is controlled by purchase order status. This flow increases stock quantities.</p>
            </form>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Storage Facilities</div>
        <?php $storageEdit = $editEntity === 'storage' ? $editRecord : null; ?>
        <div class="form-container">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_storage">
                <input type="hidden" name="storage_id" value="<?= h((string) ($storageEdit['storage_id'] ?? 0)) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Manager</label>
                        <select class="form-control" name="manager_id" required>
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $row): ?>
                                <option value="<?= h((string) $row['manager_id']) ?>" <?= (int) ($storageEdit['manager_id'] ?? 0) === (int) $row['manager_id'] ? 'selected' : '' ?>>
                                    <?= h($row['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Storage Name</label><input class="form-control" name="storage_name" required value="<?= h($storageEdit['storage_name'] ?? '') ?>"></div>
                    <div class="form-group">
                        <label>Storage Size</label>
                        <?php $sizeValue = (string) ($storageEdit['storage_size'] ?? 'small'); ?>
                        <select class="form-control" name="storage_size">
                            <?php foreach (['small', 'medium', 'large'] as $size): ?>
                                <option value="<?= h($size) ?>" <?= $sizeValue === $size ? 'selected' : '' ?>><?= h(ucfirst($size)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Storage Type</label><input class="form-control" name="storage_type" required value="<?= h($storageEdit['storage_type'] ?? '') ?>"></div>
                    <div class="form-group"><label>Condition</label><input class="form-control" name="storage_condition" required value="<?= h($storageEdit['storage_condition'] ?? '') ?>"></div>
                    <div class="form-group"><label>Capacity</label><input class="form-control" type="number" step="0.01" min="0" name="capacity" required value="<?= h((string) ($storageEdit['capacity'] ?? '')) ?>"></div>
                </div>
                <div class="button-row">
                    <button type="submit" class="btn btn-primary"><?= $storageEdit ? 'Update Storage' : 'Add Storage' ?></button>
                    <?php if ($storageEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/inventory.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Storage ID</th>
                    <th>Name</th>
                    <th>Manager</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Capacity</th>
                    <th>Utilization</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($storage as $row): ?>
                    <?php
                    $capacity = (float) $row['capacity'];
                    $used = (float) $row['used_quantity'];
                    $util = $capacity > 0 ? round(($used / $capacity) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><strong><?= h(display_code('storage', (int) $row['storage_id'])) ?></strong></td>
                        <td><?= h($row['storage_name']) ?></td>
                        <td><?= h($row['manager_name']) ?></td>
                        <td><?= h($row['storage_type']) ?></td>
                        <td><?= h(ucfirst($row['storage_size'])) ?></td>
                        <td><?= h((string) $row['capacity']) ?></td>
                        <td>
                            <span class="badge <?= h($util > 90 ? 'badge-alert' : ($util > 70 ? 'badge-warning' : 'badge-good')) ?>">
                                <?= h((string) $util) ?>%
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=storage&edit_id=<?= h((string) $row['storage_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this storage facility?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_storage">
                                <input type="hidden" name="storage_id" value="<?= h((string) $row['storage_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$storage): ?><tr><td colspan="8">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Inventory Stock</div>
        <?php $stockEdit = $editEntity === 'stock' ? $editRecord : null; ?>
        <div class="form-container">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_stock">
                <input type="hidden" name="stock_id" value="<?= h((string) ($stockEdit['stock_id'] ?? 0)) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Product</label>
                        <select class="form-control" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $row): ?>
                                <option value="<?= h((string) $row['product_id']) ?>" <?= (int) ($stockEdit['product_id'] ?? 0) === (int) $row['product_id'] ? 'selected' : '' ?>>
                                    <?= h($row['product_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Storage</label>
                        <select class="form-control" name="storage_id" required>
                            <option value="">Select Storage</option>
                            <?php foreach ($storage as $row): ?>
                                <option value="<?= h((string) $row['storage_id']) ?>" <?= (int) ($stockEdit['storage_id'] ?? 0) === (int) $row['storage_id'] ? 'selected' : '' ?>>
                                    <?= h($row['storage_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Current Quantity</label><input class="form-control" type="number" step="0.01" min="0" name="current_quantity" value="<?= h((string) ($stockEdit['current_quantity'] ?? '')) ?>" required></div>
                    <div class="form-group"><label>Minimum Threshold</label><input class="form-control" type="number" step="0.01" min="0" name="minimum_threshold_alert" value="<?= h((string) ($stockEdit['minimum_threshold_alert'] ?? '')) ?>" required></div>
                </div>
                <div class="button-row">
                    <button type="submit" class="btn btn-primary"><?= $stockEdit ? 'Update Stock' : 'Add Stock' ?></button>
                    <?php if ($stockEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/inventory.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Stock ID</th>
                    <th>Product</th>
                    <th>Storage</th>
                    <th>Manager</th>
                    <th>Current Qty</th>
                    <th>Threshold</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stock as $row): ?>
                    <?php $isLow = (float) $row['current_quantity'] <= (float) $row['minimum_threshold_alert']; ?>
                    <tr class="<?= $isLow ? 'low-stock' : '' ?>">
                        <td><strong><?= h(display_code('stock', (int) $row['stock_id'])) ?></strong></td>
                        <td><?= h($row['product_name']) ?> (<?= h($row['unit_of_measurement']) ?>)</td>
                        <td><?= h($row['storage_name']) ?></td>
                        <td><?= h($row['manager_name']) ?></td>
                        <td><?= h((string) $row['current_quantity']) ?></td>
                        <td><?= h((string) $row['minimum_threshold_alert']) ?></td>
                        <td><span class="badge <?= h($isLow ? 'badge-alert' : 'badge-good') ?>"><?= h($isLow ? 'Low' : 'Optimal') ?></span></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=stock&edit_id=<?= h((string) $row['stock_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this stock record?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_stock">
                                <input type="hidden" name="stock_id" value="<?= h((string) $row['stock_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$stock): ?><tr><td colspan="8">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
