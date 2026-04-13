<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_roles(['inventory_manager', 'supplier']);

$pdo = get_pdo();
$user = current_user();
$role = $user['role'];

$canManageSuppliers = has_role(['inventory_manager']);
$isSupplierOnly = $role === 'supplier';
$supplierScopeId = $isSupplierOnly ? (int) ($user['supplier_id'] ?? 0) : 0;

$errors = [];

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token.';
    }

    $action = (string) post('action');

    if (!$errors) {
        try {
            if ($action === 'save_supplier' && $canManageSuppliers) {
                $id = (int) post('supplier_id');
                $data = [
                    'company_name' => trim((string) post('company_name')),
                    'email' => trim((string) post('email')),
                    'contact' => trim((string) post('contact')),
                ];

                $errors = array_merge($errors, validate_required($data, [
                    'company_name' => 'Company name',
                    'email' => 'Email',
                    'contact' => 'Contact',
                ]));

                if (!$errors) {
                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE suppliers
                             SET company_name = :company_name, email = :email, contact = :contact
                             WHERE supplier_id = :id'
                        );
                        $stmt->execute($data + ['id' => $id]);
                        set_flash('success', 'Supplier updated successfully.');
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO suppliers (company_name, email, contact)
                             VALUES (:company_name, :email, :contact)'
                        );
                        $stmt->execute($data);
                        set_flash('success', 'Supplier created successfully.');
                    }

                    redirect('/agri_inventory_management/modules/suppliers.php');
                }
            }

            if ($action === 'delete_supplier' && $canManageSuppliers) {
                table_delete($pdo, 'suppliers', 'supplier_id', (int) post('supplier_id'));
                set_flash('success', 'Supplier deleted successfully.');
                redirect('/agri_inventory_management/modules/suppliers.php');
            }

            if ($action === 'save_order' && $canManageSuppliers) {
                $id = (int) post('order_id');
                $supplierId = (int) post('supplier_id');

                $status = (string) post('status', 'pending');
                $validStatus = ['pending', 'processing', 'delivered', 'cancelled'];
                if (!in_array($status, $validStatus, true)) {
                    $status = 'pending';
                }

                $deliveredDate = trim((string) post('delivered_date'));
                if ($status === 'delivered' && $deliveredDate === '') {
                    $deliveredDate = date('Y-m-d');
                }
                if ($status !== 'delivered') {
                    $deliveredDate = null;
                }

                $data = [
                    'supplier_id' => $supplierId,
                    'order_type' => trim((string) post('order_type')),
                    'order_date' => trim((string) post('order_date')),
                    'target_delivery_date' => trim((string) post('target_delivery_date')),
                    'delivered_date' => $deliveredDate,
                    'status' => $status,
                ];

                if ($data['supplier_id'] <= 0) {
                    $errors[] = 'Valid supplier is required.';
                }

                $errors = array_merge($errors, validate_required($data, [
                    'order_type' => 'Order type',
                    'order_date' => 'Order date',
                    'target_delivery_date' => 'Target delivery date',
                ]));

                $lineProductIds = post('line_product_id', []);
                $lineQuantities = post('line_quantity', []);

                $lineItems = [];
                if (is_array($lineProductIds) && is_array($lineQuantities)) {
                    foreach ($lineProductIds as $idx => $prodIdRaw) {
                        $prodId = (int) $prodIdRaw;
                        $qty = to_decimal($lineQuantities[$idx] ?? null);
                        if ($prodId > 0 && $qty !== null && $qty > 0) {
                            $lineItems[] = ['product_id' => $prodId, 'quantity' => $qty];
                        }
                    }
                }

                if (!$errors) {
                    $pdo->beginTransaction();

                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE purchase_orders
                             SET supplier_id = :supplier_id,
                                 order_type = :order_type,
                                 order_date = :order_date,
                                 target_delivery_date = :target_delivery_date,
                                 delivered_date = :delivered_date,
                                 status = :status
                             WHERE order_id = :id'
                        );
                        $stmt->execute($data + ['id' => $id]);

                        $deleteItems = $pdo->prepare('DELETE FROM order_line_items WHERE order_id = :order_id');
                        $deleteItems->execute(['order_id' => $id]);
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO purchase_orders (supplier_id, order_type, order_date, target_delivery_date, delivered_date, status)
                             VALUES (:supplier_id, :order_type, :order_date, :target_delivery_date, :delivered_date, :status)'
                        );
                        $stmt->execute($data);
                        $id = (int) $pdo->lastInsertId();
                    }

                    if ($lineItems) {
                        $insertItem = $pdo->prepare(
                            'INSERT INTO order_line_items (order_id, product_id, quantity)
                             VALUES (:order_id, :product_id, :quantity)'
                        );
                        foreach ($lineItems as $item) {
                            $insertItem->execute([
                                'order_id' => $id,
                                'product_id' => $item['product_id'],
                                'quantity' => $item['quantity'],
                            ]);
                        }
                    }

                    $pdo->commit();

                    set_flash('success', 'Purchase order saved successfully.');
                    redirect('/agri_inventory_management/modules/suppliers.php');
                }
            }

            if ($action === 'delete_order' && $canManageSuppliers) {
                table_delete($pdo, 'purchase_orders', 'order_id', (int) post('order_id'));
                set_flash('success', 'Purchase order deleted successfully.');
                redirect('/agri_inventory_management/modules/suppliers.php');
            }

            if ($action === 'save_line_item' && $canManageSuppliers) {
                $id = (int) post('line_item_id');
                $data = [
                    'order_id' => (int) post('order_id'),
                    'product_id' => (int) post('product_id'),
                    'quantity' => to_decimal(post('quantity')),
                ];

                if ($data['order_id'] <= 0 || $data['product_id'] <= 0) {
                    $errors[] = 'Valid order and product are required.';
                }
                if ($data['quantity'] === null || $data['quantity'] <= 0) {
                    $errors[] = 'Quantity must be greater than zero.';
                }

                if (!$errors) {
                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE order_line_items
                             SET order_id = :order_id, product_id = :product_id, quantity = :quantity
                             WHERE line_item_id = :id'
                        );
                        $stmt->execute($data + ['id' => $id]);
                        set_flash('success', 'Line item updated successfully.');
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO order_line_items (order_id, product_id, quantity)
                             VALUES (:order_id, :product_id, :quantity)'
                        );
                        $stmt->execute($data);
                        set_flash('success', 'Line item created successfully.');
                    }
                    redirect('/agri_inventory_management/modules/suppliers.php');
                }
            }

            if ($action === 'delete_line_item' && $canManageSuppliers) {
                table_delete($pdo, 'order_line_items', 'line_item_id', (int) post('line_item_id'));
                set_flash('success', 'Line item deleted successfully.');
                redirect('/agri_inventory_management/modules/suppliers.php');
            }

            if ($action === 'supplier_update_status' && $isSupplierOnly) {
                $orderId = (int) post('order_id');
                $status = (string) post('status');
                $valid = ['processing', 'delivered'];
                if (!in_array($status, $valid, true)) {
                    $status = 'processing';
                }

                $deliveredDate = $status === 'delivered' ? date('Y-m-d') : null;

                $stmt = $pdo->prepare(
                    'UPDATE purchase_orders
                     SET status = :status, delivered_date = :delivered_date
                     WHERE order_id = :order_id AND supplier_id = :supplier_id'
                );
                $stmt->execute([
                    'status' => $status,
                    'delivered_date' => $deliveredDate,
                    'order_id' => $orderId,
                    'supplier_id' => $supplierScopeId,
                ]);
                set_flash('success', 'Order status updated.');
                redirect('/agri_inventory_management/modules/suppliers.php');
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
    'supplier' => ['table' => 'suppliers', 'pk' => 'supplier_id'],
    'order' => ['table' => 'purchase_orders', 'pk' => 'order_id'],
    'line_item' => ['table' => 'order_line_items', 'pk' => 'line_item_id'],
];

if ($editId > 0 && isset($map[$editEntity])) {
    $meta = $map[$editEntity];
    $stmt = $pdo->prepare("SELECT * FROM {$meta['table']} WHERE {$meta['pk']} = :id");
    $stmt->execute(['id' => $editId]);
    $editRecord = $stmt->fetch();
}

$suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY supplier_id DESC')->fetchAll();
$products = $pdo->query('SELECT product_id, product_name FROM products ORDER BY product_name')->fetchAll();

$orderSql =
    'SELECT po.*, s.company_name,
            (SELECT COUNT(*) FROM order_line_items li WHERE li.order_id = po.order_id) AS line_count
     FROM purchase_orders po
     INNER JOIN suppliers s ON s.supplier_id = po.supplier_id';
$orderParams = [];

if ($isSupplierOnly && $supplierScopeId > 0) {
    $orderSql .= ' WHERE po.supplier_id = :supplier_id';
    $orderParams['supplier_id'] = $supplierScopeId;
}

$orderSql .= ' ORDER BY po.order_id DESC';
$orderStmt = $pdo->prepare($orderSql);
$orderStmt->execute($orderParams);
$orders = $orderStmt->fetchAll();

$lineSql =
    'SELECT li.*, po.order_type, po.supplier_id, s.company_name, p.product_name
     FROM order_line_items li
     INNER JOIN purchase_orders po ON po.order_id = li.order_id
     INNER JOIN suppliers s ON s.supplier_id = po.supplier_id
     INNER JOIN products p ON p.product_id = li.product_id';
$lineParams = [];

if ($isSupplierOnly && $supplierScopeId > 0) {
    $lineSql .= ' WHERE po.supplier_id = :supplier_id';
    $lineParams['supplier_id'] = $supplierScopeId;
}

$lineSql .= ' ORDER BY li.line_item_id DESC';
$lineStmt = $pdo->prepare($lineSql);
$lineStmt->execute($lineParams);
$lineItems = $lineStmt->fetchAll();

$pageTitle = 'Supplier Management';
$activePage = 'suppliers';
$searchPlaceholder = 'Search suppliers, orders, or contacts...';
require __DIR__ . '/../includes/header.php';
?>

<div class="module-header">
    <h1 class="page-title">Supplier Management</h1>
</div>

<?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="dashboard-grid">
    <div class="full-width">
        <div class="section-title">Suppliers</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Company</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($suppliers as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('supplier', (int) $row['supplier_id'])) ?></strong></td>
                        <td><?= h($row['company_name']) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td><?= h($row['contact']) ?></td>
                        <td>
                            <?php if ($canManageSuppliers): ?>
                                <a class="btn btn-warning btn-sm" href="?edit_entity=supplier&edit_id=<?= h((string) $row['supplier_id']) ?>">Edit</a>
                                <form method="post" action="" style="display:inline;" data-confirm="Delete this supplier?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_supplier">
                                    <input type="hidden" name="supplier_id" value="<?= h((string) $row['supplier_id']) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                </form>
                            <?php else: ?>
                                <?php if ($supplierScopeId === (int) $row['supplier_id']): ?>
                                    <span class="badge badge-info">Your Profile</span>
                                <?php else: ?>
                                    <span class="small-text">Read only</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$suppliers): ?>
                    <tr><td colspan="5">No supplier records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($canManageSuppliers): ?>
        <div>
            <div class="section-title"><?= $editEntity === 'supplier' ? 'Edit Supplier' : 'Add Supplier' ?></div>
            <?php $supplierEdit = $editEntity === 'supplier' ? $editRecord : null; ?>
            <div class="form-container">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_supplier">
                    <input type="hidden" name="supplier_id" value="<?= h((string) ($supplierEdit['supplier_id'] ?? 0)) ?>">
                    <div class="form-group"><label>Company Name</label><input class="form-control" name="company_name" required value="<?= h($supplierEdit['company_name'] ?? '') ?>"></div>
                    <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required value="<?= h($supplierEdit['email'] ?? '') ?>"></div>
                    <div class="form-group"><label>Contact</label><input class="form-control" name="contact" required value="<?= h($supplierEdit['contact'] ?? '') ?>"></div>
                    <div class="button-row">
                        <button class="btn btn-primary" type="submit"><?= $supplierEdit ? 'Update Supplier' : 'Create Supplier' ?></button>
                        <?php if ($supplierEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/suppliers.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="full-width">
        <div class="section-title">Purchase Orders</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Supplier</th>
                    <th>Type</th>
                    <th>Order Date</th>
                    <th>Target Delivery</th>
                    <th>Delivered</th>
                    <th>Status</th>
                    <th>Line Items</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('order', (int) $row['order_id'])) ?></strong></td>
                        <td><?= h($row['company_name']) ?></td>
                        <td><?= h($row['order_type']) ?></td>
                        <td><?= h($row['order_date']) ?></td>
                        <td><?= h($row['target_delivery_date']) ?></td>
                        <td><?= h($row['delivered_date'] ?? '-') ?></td>
                        <td><span class="badge <?= h(badge_class($row['status'])) ?>"><?= h(ucfirst($row['status'])) ?></span></td>
                        <td><?= h((string) $row['line_count']) ?></td>
                        <td>
                            <?php if ($canManageSuppliers): ?>
                                <a class="btn btn-warning btn-sm" href="?edit_entity=order&edit_id=<?= h((string) $row['order_id']) ?>">Edit</a>
                                <form method="post" action="" style="display:inline;" data-confirm="Delete this purchase order?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_order">
                                    <input type="hidden" name="order_id" value="<?= h((string) $row['order_id']) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                </form>
                            <?php elseif ($isSupplierOnly): ?>
                                <form method="post" action="" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="supplier_update_status">
                                    <input type="hidden" name="order_id" value="<?= h((string) $row['order_id']) ?>">
                                    <select name="status" class="form-control" style="width:auto; display:inline-block;">
                                        <option value="processing" <?= $row['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="delivered" <?= $row['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                    </select>
                                    <button class="btn btn-secondary btn-sm" type="submit">Update</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="9">No purchase order records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($canManageSuppliers): ?>
        <div class="full-width">
            <div class="section-title"><?= $editEntity === 'order' ? 'Edit Purchase Order' : 'Create Purchase Order' ?></div>
            <?php $orderEdit = $editEntity === 'order' ? $editRecord : null; ?>
            <div class="form-container">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_order">
                    <input type="hidden" name="order_id" value="<?= h((string) ($orderEdit['order_id'] ?? 0)) ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier</label>
                            <select class="form-control" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $row): ?>
                                    <option value="<?= h((string) $row['supplier_id']) ?>" <?= (int) ($orderEdit['supplier_id'] ?? 0) === (int) $row['supplier_id'] ? 'selected' : '' ?>>
                                        <?= h(display_code('supplier', (int) $row['supplier_id']) . ' - ' . $row['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Order Type</label>
                            <input class="form-control" name="order_type" required value="<?= h($orderEdit['order_type'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <?php $statusValue = (string) ($orderEdit['status'] ?? 'pending'); ?>
                            <select class="form-control" name="status" required>
                                <?php foreach (['pending', 'processing', 'delivered', 'cancelled'] as $status): ?>
                                    <option value="<?= h($status) ?>" <?= $statusValue === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group"><label>Order Date</label><input class="form-control" type="date" name="order_date" required value="<?= h($orderEdit['order_date'] ?? '') ?>"></div>
                        <div class="form-group"><label>Target Delivery Date</label><input class="form-control" type="date" name="target_delivery_date" required value="<?= h($orderEdit['target_delivery_date'] ?? '') ?>"></div>
                        <div class="form-group"><label>Delivered Date (optional)</label><input class="form-control" type="date" name="delivered_date" value="<?= h($orderEdit['delivered_date'] ?? '') ?>"></div>
                    </div>

                    <hr class="soft">
                    <p class="small-text">For new orders, add multiple line items below. For order edits, this section rewrites line items.</p>
                    <div id="line-items-container">
                        <div class="form-row line-item-row">
                            <div class="form-group">
                                <label>Product</label>
                                <select class="form-control" name="line_product_id[]">
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $row): ?>
                                        <option value="<?= h((string) $row['product_id']) ?>"><?= h($row['product_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input class="form-control" type="number" min="0" step="0.01" name="line_quantity[]" placeholder="0.00">
                            </div>
                            <div class="form-group" style="max-width:120px; align-self:end;">
                                <button type="button" class="btn btn-danger btn-sm remove-line-item">Remove</button>
                            </div>
                        </div>
                    </div>
                    <template id="line-item-template">
                        <div class="form-row line-item-row">
                            <div class="form-group">
                                <select class="form-control" name="line_product_id[]">
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $row): ?>
                                        <option value="<?= h((string) $row['product_id']) ?>"><?= h($row['product_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <input class="form-control" type="number" min="0" step="0.01" name="line_quantity[]" placeholder="0.00">
                            </div>
                            <div class="form-group" style="max-width:120px; align-self:end;">
                                <button type="button" class="btn btn-danger btn-sm remove-line-item">Remove</button>
                            </div>
                        </div>
                    </template>
                    <div class="button-row">
                        <button type="button" id="add-line-item" class="btn btn-secondary">+ Add Line Item</button>
                    </div>

                    <div class="button-row" style="margin-top:16px;">
                        <button class="btn btn-primary" type="submit"><?= $orderEdit ? 'Update Order' : 'Create Order' ?></button>
                        <?php if ($orderEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/suppliers.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canManageSuppliers): ?>
        <div class="full-width">
            <div class="section-title">Order Line Items</div>
            <?php $lineEdit = $editEntity === 'line_item' ? $editRecord : null; ?>
            <div class="form-container">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_line_item">
                    <input type="hidden" name="line_item_id" value="<?= h((string) ($lineEdit['line_item_id'] ?? 0)) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Order</label>
                            <select class="form-control" name="order_id" required>
                                <option value="">Select Order</option>
                                <?php foreach ($orders as $row): ?>
                                    <option value="<?= h((string) $row['order_id']) ?>" <?= (int) ($lineEdit['order_id'] ?? 0) === (int) $row['order_id'] ? 'selected' : '' ?>>
                                        <?= h(display_code('order', (int) $row['order_id']) . ' - ' . $row['order_type']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Product</label>
                            <select class="form-control" name="product_id" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products as $row): ?>
                                    <option value="<?= h((string) $row['product_id']) ?>" <?= (int) ($lineEdit['product_id'] ?? 0) === (int) $row['product_id'] ? 'selected' : '' ?>>
                                        <?= h($row['product_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input class="form-control" type="number" step="0.01" min="0" name="quantity" required value="<?= h((string) ($lineEdit['quantity'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="button-row">
                        <button class="btn btn-primary" type="submit"><?= $lineEdit ? 'Update Line Item' : 'Add Line Item' ?></button>
                        <?php if ($lineEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/suppliers.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="data-table-container" style="margin-top:12px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Line ID</th>
                            <th>Order</th>
                            <th>Supplier</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lineItems as $row): ?>
                        <tr>
                            <td><strong><?= h(display_code('line_item', (int) $row['line_item_id'])) ?></strong></td>
                            <td><?= h(display_code('order', (int) $row['order_id'])) ?> (<?= h($row['order_type']) ?>)</td>
                            <td><?= h($row['company_name']) ?></td>
                            <td><?= h($row['product_name']) ?></td>
                            <td><?= h((string) $row['quantity']) ?></td>
                            <td>
                                <a class="btn btn-warning btn-sm" href="?edit_entity=line_item&edit_id=<?= h((string) $row['line_item_id']) ?>">Edit</a>
                                <form method="post" action="" style="display:inline;" data-confirm="Delete this line item?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_line_item">
                                    <input type="hidden" name="line_item_id" value="<?= h((string) $row['line_item_id']) ?>">
                                    <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$lineItems): ?>
                        <tr><td colspan="6">No line items found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
