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

    if ($action === 'delete_product') {
        $productId = (int) post('product_id');

        if ($productId <= 0) {
            $errors[] = 'Invalid product selected.';
        }

        if (!$errors) {
            try {
                table_delete($pdo, 'products', 'product_id', $productId);
                set_flash('success', 'Product deleted successfully.');
            } catch (Throwable $exception) {
                set_flash('error', 'Cannot delete product with linked records.');
            }
            redirect('/agri_inventory_management/modules/products.php');
        }
    }

    if ($action === 'save_product') {
        $productId = (int) post('product_id');
        $data = [
            'product_name' => trim((string) post('product_name')),
            'category' => trim((string) post('category')),
            'unit_of_measurement' => trim((string) post('unit_of_measurement')),
            'base_shelf_life_days' => (int) post('base_shelf_life_days'),
            'optimal_temp_min' => to_decimal(post('optimal_temp_min')),
            'optimal_temp_max' => to_decimal(post('optimal_temp_max')),
        ];

        $errors = array_merge($errors, validate_required($data, [
            'product_name' => 'Product name',
            'category' => 'Category',
            'unit_of_measurement' => 'Unit of measurement',
        ]));

        if ($data['base_shelf_life_days'] < 1) {
            $errors[] = 'Shelf life must be greater than 0.';
        }

        if ($data['optimal_temp_min'] === null || $data['optimal_temp_max'] === null) {
            $errors[] = 'Temperature range values are required and must be numeric.';
        } elseif ($data['optimal_temp_min'] > $data['optimal_temp_max']) {
            $errors[] = 'Minimum optimal temperature cannot be greater than maximum.';
        }

        if (!$errors) {
            if ($productId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE products
                     SET product_name = :product_name,
                         category = :category,
                         unit_of_measurement = :unit_of_measurement,
                         base_shelf_life_days = :base_shelf_life_days,
                         optimal_temp_min = :optimal_temp_min,
                         optimal_temp_max = :optimal_temp_max
                     WHERE product_id = :product_id'
                );
                $stmt->execute($data + ['product_id' => $productId]);
                set_flash('success', 'Product updated successfully.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (product_name, category, unit_of_measurement, base_shelf_life_days, optimal_temp_min, optimal_temp_max)
                     VALUES (:product_name, :category, :unit_of_measurement, :base_shelf_life_days, :optimal_temp_min, :optimal_temp_max)'
                );
                $stmt->execute($data);
                set_flash('success', 'Product created successfully.');
            }

            redirect('/agri_inventory_management/modules/products.php');
        }
    }
}

$editProduct = null;
$editId = (int) get_value('edit_id', 0);

if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = :id');
    $stmt->execute(['id' => $editId]);
    $editProduct = $stmt->fetch();
}

$search = trim((string) get_value('q', ''));
$categoryFilter = trim((string) get_value('category', ''));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(product_name LIKE :search OR category LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($categoryFilter !== '') {
    $where[] = 'category = :category';
    $params['category'] = $categoryFilter;
}

$sql = 'SELECT * FROM products';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY product_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll();

$pageTitle = 'Product Catalog';
$activePage = 'products';
$searchPlaceholder = 'Search products, categories...';
require __DIR__ . '/../includes/header.php';
?>

<div class="module-header">
    <h1 class="page-title">Product Catalog</h1>
</div>

<?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="dashboard-grid">
    <div>
        <div class="section-title"><?= $editProduct ? 'Edit Product' : 'Add Product' ?></div>
        <div class="form-container">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="product_id" value="<?= h((string) ($editProduct['product_id'] ?? 0)) ?>">

                <div class="form-group">
                    <label>Product Name</label>
                    <input class="form-control" type="text" name="product_name" value="<?= h($editProduct['product_name'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <input class="form-control" type="text" name="category" value="<?= h($editProduct['category'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Unit (UoM)</label>
                        <input class="form-control" type="text" name="unit_of_measurement" value="<?= h($editProduct['unit_of_measurement'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Shelf Life (days)</label>
                        <input class="form-control" type="number" min="1" name="base_shelf_life_days" value="<?= h((string) ($editProduct['base_shelf_life_days'] ?? '')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Optimal Temp Min</label>
                        <input class="form-control" type="number" step="0.01" name="optimal_temp_min" value="<?= h((string) ($editProduct['optimal_temp_min'] ?? '')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Optimal Temp Max</label>
                        <input class="form-control" type="number" step="0.01" name="optimal_temp_max" value="<?= h((string) ($editProduct['optimal_temp_max'] ?? '')) ?>" required>
                    </div>
                </div>
                <div class="button-row">
                    <button type="submit" class="btn btn-primary"><?= $editProduct ? 'Update Product' : 'Create Product' ?></button>
                    <?php if ($editProduct): ?>
                        <a class="btn btn-muted" href="/agri_inventory_management/modules/products.php">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div>
        <div class="section-title">Search / Filter</div>
        <div class="card">
            <form method="get" action="" class="inline-filter">
                <div class="form-group" style="margin:0;">
                    <label>Keyword</label>
                    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Name or category">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat['category']) ?>" <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>>
                                <?= h($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">Apply</button>
                <a href="/agri_inventory_management/modules/products.php" class="btn btn-muted">Reset</a>
            </form>
            <p class="small-text">Showing <?= h((string) count($products)) ?> product records.</p>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Products</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Shelf Life</th>
                    <th>Optimal Temp</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('product', (int) $row['product_id'])) ?></strong></td>
                        <td><?= h($row['product_name']) ?></td>
                        <td><span class="badge badge-info"><?= h($row['category']) ?></span></td>
                        <td><?= h($row['unit_of_measurement']) ?></td>
                        <td><?= h((string) $row['base_shelf_life_days']) ?> days</td>
                        <td><?= h((string) $row['optimal_temp_min']) ?> - <?= h((string) $row['optimal_temp_max']) ?> C</td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="/agri_inventory_management/modules/products.php?edit_id=<?= h((string) $row['product_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this product?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?= h((string) $row['product_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                    <tr><td colspan="7">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
