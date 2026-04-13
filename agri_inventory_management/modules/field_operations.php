<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_roles(['field_officer']);

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
                case 'save_field_officer':
                    $id = (int) post('field_officer_id');
                    $data = [
                        'full_name' => trim((string) post('full_name')),
                        'email' => trim((string) post('email')),
                        'contact' => trim((string) post('contact')),
                    ];
                    $errors = array_merge($errors, validate_required($data, [
                        'full_name' => 'Full name',
                        'email' => 'Email',
                        'contact' => 'Contact',
                    ]));

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE field_officers
                                 SET full_name = :full_name, email = :email, contact = :contact
                                 WHERE field_officer_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Field officer updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO field_officers (full_name, email, contact)
                                 VALUES (:full_name, :email, :contact)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Field officer created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/field_operations.php');
                    }
                    break;

                case 'delete_field_officer':
                    table_delete($pdo, 'field_officers', 'field_officer_id', (int) post('field_officer_id'));
                    set_flash('success', 'Field officer deleted successfully.');
                    redirect('/agri_inventory_management/modules/field_operations.php');
                    break;

                case 'save_farmer':
                    $id = (int) post('farmer_id');
                    $data = [
                        'farmer_name' => trim((string) post('farmer_name')),
                        'contact_number' => trim((string) post('contact_number')),
                        'availability' => (int) post('availability', 0),
                    ];
                    $errors = array_merge($errors, validate_required($data, [
                        'farmer_name' => 'Farmer name',
                        'contact_number' => 'Contact number',
                    ]));

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE farmers
                                 SET farmer_name = :farmer_name, contact_number = :contact_number, availability = :availability
                                 WHERE farmer_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Farmer updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO farmers (farmer_name, contact_number, availability)
                                 VALUES (:farmer_name, :contact_number, :availability)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Farmer created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/field_operations.php');
                    }
                    break;

                case 'delete_farmer':
                    table_delete($pdo, 'farmers', 'farmer_id', (int) post('farmer_id'));
                    set_flash('success', 'Farmer deleted successfully.');
                    redirect('/agri_inventory_management/modules/field_operations.php');
                    break;

                case 'save_field':
                    $id = (int) post('field_id');
                    $data = [
                        'farmer_id' => (int) post('farmer_id'),
                        'field_officer_id' => (int) post('field_officer_id'),
                        'field_type' => trim((string) post('field_type')),
                        'planting_date' => (string) post('planting_date'),
                        'target_harvest_date' => (string) post('target_harvest_date'),
                    ];
                    $errors = array_merge($errors, validate_required($data, [
                        'field_type' => 'Field type',
                        'planting_date' => 'Planting date',
                        'target_harvest_date' => 'Target harvest date',
                    ]));

                    if ($data['farmer_id'] <= 0 || $data['field_officer_id'] <= 0) {
                        $errors[] = 'Valid farmer and field officer are required.';
                    }

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE fields
                                 SET farmer_id = :farmer_id,
                                     field_officer_id = :field_officer_id,
                                     field_type = :field_type,
                                     planting_date = :planting_date,
                                     target_harvest_date = :target_harvest_date
                                 WHERE field_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Field updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO fields (farmer_id, field_officer_id, field_type, planting_date, target_harvest_date)
                                 VALUES (:farmer_id, :field_officer_id, :field_type, :planting_date, :target_harvest_date)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Field created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/field_operations.php');
                    }
                    break;

                case 'delete_field':
                    table_delete($pdo, 'fields', 'field_id', (int) post('field_id'));
                    set_flash('success', 'Field deleted successfully.');
                    redirect('/agri_inventory_management/modules/field_operations.php');
                    break;

                case 'save_harvest':
                    $id = (int) post('harvest_id');
                    $data = [
                        'field_id' => (int) post('field_id'),
                        'harvested_percentage' => to_decimal(post('harvested_percentage')),
                        'harvested_date' => (string) post('harvested_date'),
                        'collected_weight' => to_decimal(post('collected_weight')),
                    ];

                    if ($data['field_id'] <= 0) {
                        $errors[] = 'A valid field is required.';
                    }
                    if ($data['harvested_percentage'] === null || $data['harvested_percentage'] < 0 || $data['harvested_percentage'] > 100) {
                        $errors[] = 'Harvested percentage must be between 0 and 100.';
                    }
                    if ($data['collected_weight'] === null || $data['collected_weight'] <= 0) {
                        $errors[] = 'Collected weight must be greater than 0.';
                    }
                    if (trim($data['harvested_date']) === '') {
                        $errors[] = 'Harvest date is required.';
                    }

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE harvest_logs
                                 SET field_id = :field_id,
                                     harvested_percentage = :harvested_percentage,
                                     harvested_date = :harvested_date,
                                     collected_weight = :collected_weight
                                 WHERE harvest_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Harvest log updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO harvest_logs (field_id, harvested_percentage, harvested_date, collected_weight)
                                 VALUES (:field_id, :harvested_percentage, :harvested_date, :collected_weight)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Harvest log created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/field_operations.php');
                    }
                    break;

                case 'delete_harvest':
                    table_delete($pdo, 'harvest_logs', 'harvest_id', (int) post('harvest_id'));
                    set_flash('success', 'Harvest log deleted successfully.');
                    redirect('/agri_inventory_management/modules/field_operations.php');
                    break;

                case 'save_input_request':
                    $id = (int) post('request_id');
                    $status = (string) post('fulfillment_status');
                    $validStatuses = ['pending', 'approved', 'fulfilled', 'rejected'];

                    $data = [
                        'field_officer_id' => (int) post('field_officer_id'),
                        'farmer_id' => (int) post('farmer_id'),
                        'product_id' => (int) post('product_id'),
                        'required_quantity' => to_decimal(post('required_quantity')),
                        'fulfillment_status' => in_array($status, $validStatuses, true) ? $status : 'pending',
                    ];

                    if ($data['field_officer_id'] <= 0 || $data['farmer_id'] <= 0 || $data['product_id'] <= 0) {
                        $errors[] = 'Valid officer, farmer, and product are required.';
                    }
                    if ($data['required_quantity'] === null || $data['required_quantity'] <= 0) {
                        $errors[] = 'Required quantity must be greater than 0.';
                    }

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE input_requests
                                 SET field_officer_id = :field_officer_id,
                                     farmer_id = :farmer_id,
                                     product_id = :product_id,
                                     required_quantity = :required_quantity,
                                     fulfillment_status = :fulfillment_status
                                 WHERE request_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Input request updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO input_requests (field_officer_id, farmer_id, product_id, required_quantity, fulfillment_status)
                                 VALUES (:field_officer_id, :farmer_id, :product_id, :required_quantity, :fulfillment_status)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Input request created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/field_operations.php');
                    }
                    break;

                case 'delete_input_request':
                    table_delete($pdo, 'input_requests', 'request_id', (int) post('request_id'));
                    set_flash('success', 'Input request deleted successfully.');
                    redirect('/agri_inventory_management/modules/field_operations.php');
                    break;
            }
        } catch (Throwable $exception) {
            $errors[] = 'Operation failed: ' . $exception->getMessage();
        }
    }
}

$editEntity = (string) get_value('edit_entity', '');
$editId = (int) get_value('edit_id', 0);
$editRecord = null;

$entityMap = [
    'field_officer' => ['table' => 'field_officers', 'pk' => 'field_officer_id'],
    'farmer' => ['table' => 'farmers', 'pk' => 'farmer_id'],
    'field' => ['table' => 'fields', 'pk' => 'field_id'],
    'harvest' => ['table' => 'harvest_logs', 'pk' => 'harvest_id'],
    'input_request' => ['table' => 'input_requests', 'pk' => 'request_id'],
];

if ($editId > 0 && isset($entityMap[$editEntity])) {
    $meta = $entityMap[$editEntity];
    $stmt = $pdo->prepare("SELECT * FROM {$meta['table']} WHERE {$meta['pk']} = :id");
    $stmt->execute(['id' => $editId]);
    $editRecord = $stmt->fetch();
}

$fieldOfficers = $pdo->query('SELECT * FROM field_officers ORDER BY field_officer_id DESC')->fetchAll();
$farmers = $pdo->query('SELECT * FROM farmers ORDER BY farmer_id DESC')->fetchAll();
$products = $pdo->query('SELECT product_id, product_name FROM products ORDER BY product_name')->fetchAll();

$fields = $pdo->query(
    'SELECT f.*, fr.farmer_name, fo.full_name AS officer_name
     FROM fields f
     INNER JOIN farmers fr ON fr.farmer_id = f.farmer_id
     INNER JOIN field_officers fo ON fo.field_officer_id = f.field_officer_id
     ORDER BY f.field_id DESC'
)->fetchAll();

$harvestLogs = $pdo->query(
    'SELECT hl.*, f.field_type, fr.farmer_name
     FROM harvest_logs hl
     INNER JOIN fields f ON f.field_id = hl.field_id
     INNER JOIN farmers fr ON fr.farmer_id = f.farmer_id
     ORDER BY hl.harvest_id DESC'
)->fetchAll();

$inputRequests = $pdo->query(
    'SELECT ir.*, fo.full_name AS officer_name, fr.farmer_name, p.product_name
     FROM input_requests ir
     INNER JOIN field_officers fo ON fo.field_officer_id = ir.field_officer_id
     INNER JOIN farmers fr ON fr.farmer_id = ir.farmer_id
     INNER JOIN products p ON p.product_id = ir.product_id
     ORDER BY ir.request_id DESC'
)->fetchAll();

$pageTitle = 'Field Operations';
$activePage = 'field_operations';
$searchPlaceholder = 'Search fields, farmers, or officers...';
require __DIR__ . '/../includes/header.php';
?>

<div class="module-header">
    <h1 class="page-title">Field Operations</h1>
</div>

<?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="dashboard-grid">
    <div>
        <div class="section-title">Field Officers</div>
        <div class="form-container">
            <?php $officerEdit = $editEntity === 'field_officer' ? $editRecord : null; ?>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_field_officer">
                <input type="hidden" name="field_officer_id" value="<?= h((string) ($officerEdit['field_officer_id'] ?? 0)) ?>">
                <div class="form-group"><label>Full Name</label><input class="form-control" name="full_name" required value="<?= h($officerEdit['full_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required value="<?= h($officerEdit['email'] ?? '') ?>"></div>
                <div class="form-group"><label>Contact</label><input class="form-control" name="contact" required value="<?= h($officerEdit['contact'] ?? '') ?>"></div>
                <div class="button-row">
                    <button class="btn btn-primary" type="submit"><?= $officerEdit ? 'Update Officer' : 'Add Officer' ?></button>
                    <?php if ($officerEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/field_operations.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($fieldOfficers as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('field_officer', (int) $row['field_officer_id'])) ?></strong></td>
                        <td><?= h($row['full_name']) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td><?= h($row['contact']) ?></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=field_officer&edit_id=<?= h((string) $row['field_officer_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this field officer?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_field_officer">
                                <input type="hidden" name="field_officer_id" value="<?= h((string) $row['field_officer_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$fieldOfficers): ?><tr><td colspan="5">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="section-title">Farmers</div>
        <div class="form-container">
            <?php $farmerEdit = $editEntity === 'farmer' ? $editRecord : null; ?>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_farmer">
                <input type="hidden" name="farmer_id" value="<?= h((string) ($farmerEdit['farmer_id'] ?? 0)) ?>">
                <div class="form-group"><label>Farmer Name</label><input class="form-control" name="farmer_name" required value="<?= h($farmerEdit['farmer_name'] ?? '') ?>"></div>
                <div class="form-group"><label>Contact</label><input class="form-control" name="contact_number" required value="<?= h($farmerEdit['contact_number'] ?? '') ?>"></div>
                <div class="form-group">
                    <label>Availability</label>
                    <select class="form-control" name="availability">
                        <option value="1" <?= (string) ($farmerEdit['availability'] ?? '1') === '1' ? 'selected' : '' ?>>Available</option>
                        <option value="0" <?= (string) ($farmerEdit['availability'] ?? '1') === '0' ? 'selected' : '' ?>>Occupied</option>
                    </select>
                </div>
                <div class="button-row">
                    <button class="btn btn-primary" type="submit"><?= $farmerEdit ? 'Update Farmer' : 'Add Farmer' ?></button>
                    <?php if ($farmerEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/field_operations.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($farmers as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('farmer', (int) $row['farmer_id'])) ?></strong></td>
                        <td><?= h($row['farmer_name']) ?></td>
                        <td><?= h($row['contact_number']) ?></td>
                        <td><span class="badge <?= h((int) $row['availability'] === 1 ? 'badge-good' : 'badge-warning') ?>"><?= h((int) $row['availability'] === 1 ? 'Available' : 'Occupied') ?></span></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=farmer&edit_id=<?= h((string) $row['farmer_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this farmer?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_farmer">
                                <input type="hidden" name="farmer_id" value="<?= h((string) $row['farmer_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$farmers): ?><tr><td colspan="5">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Fields</div>
        <div class="form-container">
            <?php $fieldEdit = $editEntity === 'field' ? $editRecord : null; ?>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_field">
                <input type="hidden" name="field_id" value="<?= h((string) ($fieldEdit['field_id'] ?? 0)) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Farmer</label>
                        <select class="form-control" name="farmer_id" required>
                            <option value="">Select Farmer</option>
                            <?php foreach ($farmers as $row): ?>
                                <option value="<?= h((string) $row['farmer_id']) ?>" <?= (int) ($fieldEdit['farmer_id'] ?? 0) === (int) $row['farmer_id'] ? 'selected' : '' ?>>
                                    <?= h(display_code('farmer', (int) $row['farmer_id']) . ' - ' . $row['farmer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Field Officer</label>
                        <select class="form-control" name="field_officer_id" required>
                            <option value="">Select Officer</option>
                            <?php foreach ($fieldOfficers as $row): ?>
                                <option value="<?= h((string) $row['field_officer_id']) ?>" <?= (int) ($fieldEdit['field_officer_id'] ?? 0) === (int) $row['field_officer_id'] ? 'selected' : '' ?>>
                                    <?= h(display_code('field_officer', (int) $row['field_officer_id']) . ' - ' . $row['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Field Type</label>
                        <input class="form-control" name="field_type" required value="<?= h($fieldEdit['field_type'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Planting Date</label><input class="form-control" type="date" name="planting_date" value="<?= h($fieldEdit['planting_date'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Target Harvest Date</label><input class="form-control" type="date" name="target_harvest_date" value="<?= h($fieldEdit['target_harvest_date'] ?? '') ?>" required></div>
                </div>
                <div class="button-row">
                    <button type="submit" class="btn btn-primary"><?= $fieldEdit ? 'Update Field' : 'Add Field' ?></button>
                    <?php if ($fieldEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/field_operations.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead><tr><th>Field ID</th><th>Farmer</th><th>Officer</th><th>Type</th><th>Planting</th><th>Target Harvest</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($fields as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('field', (int) $row['field_id'])) ?></strong></td>
                        <td><?= h($row['farmer_name']) ?></td>
                        <td><?= h($row['officer_name']) ?></td>
                        <td><?= h($row['field_type']) ?></td>
                        <td><?= h($row['planting_date']) ?></td>
                        <td><?= h($row['target_harvest_date']) ?></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=field&edit_id=<?= h((string) $row['field_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this field?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_field">
                                <input type="hidden" name="field_id" value="<?= h((string) $row['field_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$fields): ?><tr><td colspan="7">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="section-title">Harvest Logs</div>
        <div class="form-container">
            <?php $harvestEdit = $editEntity === 'harvest' ? $editRecord : null; ?>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_harvest">
                <input type="hidden" name="harvest_id" value="<?= h((string) ($harvestEdit['harvest_id'] ?? 0)) ?>">
                <div class="form-group">
                    <label>Field</label>
                    <select class="form-control" name="field_id" required>
                        <option value="">Select Field</option>
                        <?php foreach ($fields as $row): ?>
                            <option value="<?= h((string) $row['field_id']) ?>" <?= (int) ($harvestEdit['field_id'] ?? 0) === (int) $row['field_id'] ? 'selected' : '' ?>>
                                <?= h(display_code('field', (int) $row['field_id']) . ' - ' . $row['field_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Harvested %</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="harvested_percentage" value="<?= h((string) ($harvestEdit['harvested_percentage'] ?? '')) ?>" required></div>
                    <div class="form-group"><label>Date</label><input class="form-control" type="date" name="harvested_date" value="<?= h($harvestEdit['harvested_date'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Collected Weight</label><input class="form-control" type="number" step="0.01" min="0" name="collected_weight" value="<?= h((string) ($harvestEdit['collected_weight'] ?? '')) ?>" required></div>
                </div>
                <div class="button-row">
                    <button class="btn btn-primary" type="submit"><?= $harvestEdit ? 'Update Log' : 'Add Log' ?></button>
                    <?php if ($harvestEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/field_operations.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead><tr><th>Harvest ID</th><th>Field</th><th>Farmer</th><th>Yield %</th><th>Weight</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($harvestLogs as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('harvest', (int) $row['harvest_id'])) ?></strong></td>
                        <td><?= h($row['field_type']) ?></td>
                        <td><?= h($row['farmer_name']) ?></td>
                        <td><?= h((string) $row['harvested_percentage']) ?>%</td>
                        <td><?= h((string) $row['collected_weight']) ?></td>
                        <td><?= h($row['harvested_date']) ?></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=harvest&edit_id=<?= h((string) $row['harvest_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this harvest log?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_harvest">
                                <input type="hidden" name="harvest_id" value="<?= h((string) $row['harvest_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$harvestLogs): ?><tr><td colspan="7">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="section-title">Input Requests</div>
        <div class="form-container">
            <?php $requestEdit = $editEntity === 'input_request' ? $editRecord : null; ?>
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_input_request">
                <input type="hidden" name="request_id" value="<?= h((string) ($requestEdit['request_id'] ?? 0)) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Officer</label>
                        <select class="form-control" name="field_officer_id" required>
                            <option value="">Select Officer</option>
                            <?php foreach ($fieldOfficers as $row): ?>
                                <option value="<?= h((string) $row['field_officer_id']) ?>" <?= (int) ($requestEdit['field_officer_id'] ?? 0) === (int) $row['field_officer_id'] ? 'selected' : '' ?>>
                                    <?= h($row['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Farmer</label>
                        <select class="form-control" name="farmer_id" required>
                            <option value="">Select Farmer</option>
                            <?php foreach ($farmers as $row): ?>
                                <option value="<?= h((string) $row['farmer_id']) ?>" <?= (int) ($requestEdit['farmer_id'] ?? 0) === (int) $row['farmer_id'] ? 'selected' : '' ?>>
                                    <?= h($row['farmer_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Product</label>
                        <select class="form-control" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $row): ?>
                                <option value="<?= h((string) $row['product_id']) ?>" <?= (int) ($requestEdit['product_id'] ?? 0) === (int) $row['product_id'] ? 'selected' : '' ?>>
                                    <?= h($row['product_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Required Quantity</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="required_quantity" value="<?= h((string) ($requestEdit['required_quantity'] ?? '')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <?php $statusValue = (string) ($requestEdit['fulfillment_status'] ?? 'pending'); ?>
                        <select class="form-control" name="fulfillment_status" required>
                            <?php foreach (['pending', 'approved', 'fulfilled', 'rejected'] as $status): ?>
                                <option value="<?= h($status) ?>" <?= $statusValue === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="button-row">
                    <button class="btn btn-primary" type="submit"><?= $requestEdit ? 'Update Request' : 'Add Request' ?></button>
                    <?php if ($requestEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/field_operations.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead><tr><th>Request ID</th><th>Officer</th><th>Farmer</th><th>Product</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($inputRequests as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('request', (int) $row['request_id'])) ?></strong></td>
                        <td><?= h($row['officer_name']) ?></td>
                        <td><?= h($row['farmer_name']) ?></td>
                        <td><?= h($row['product_name']) ?></td>
                        <td><?= h((string) $row['required_quantity']) ?></td>
                        <td><span class="badge <?= h(badge_class($row['fulfillment_status'])) ?>"><?= h(ucfirst($row['fulfillment_status'])) ?></span></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=input_request&edit_id=<?= h((string) $row['request_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this input request?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_input_request">
                                <input type="hidden" name="request_id" value="<?= h((string) $row['request_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$inputRequests): ?><tr><td colspan="7">No records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
