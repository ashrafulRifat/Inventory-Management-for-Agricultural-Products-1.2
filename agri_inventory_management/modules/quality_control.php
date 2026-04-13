<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_roles(['qc_officer']);

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
                case 'save_qc_officer':
                    $id = (int) post('officer_id');
                    $data = [
                        'name' => trim((string) post('name')),
                        'email' => trim((string) post('email')),
                        'contact' => trim((string) post('contact')),
                    ];

                    $errors = array_merge($errors, validate_required($data, [
                        'name' => 'Name',
                        'email' => 'Email',
                        'contact' => 'Contact',
                    ]));

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare('UPDATE qc_officers SET name = :name, email = :email, contact = :contact WHERE officer_id = :id');
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'QC officer updated successfully.');
                        } else {
                            $stmt = $pdo->prepare('INSERT INTO qc_officers (name, email, contact) VALUES (:name, :email, :contact)');
                            $stmt->execute($data);
                            set_flash('success', 'QC officer created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/quality_control.php');
                    }
                    break;

                case 'delete_qc_officer':
                    table_delete($pdo, 'qc_officers', 'officer_id', (int) post('officer_id'));
                    set_flash('success', 'QC officer deleted successfully.');
                    redirect('/agri_inventory_management/modules/quality_control.php');
                    break;

                case 'save_inspection':
                    $id = (int) post('inspection_id');
                    $condition = (string) post('product_condition');
                    $validConditions = ['excellent', 'acceptable', 'degraded', 'rejected'];
                    if (!in_array($condition, $validConditions, true)) {
                        $condition = 'acceptable';
                    }

                    $data = [
                        'officer_id' => (int) post('officer_id'),
                        'stock_id' => (int) post('stock_id'),
                        'inspection_date' => trim((string) post('inspection_date')),
                        'spoilage_percentage' => to_decimal(post('spoilage_percentage')),
                        'product_condition' => $condition,
                    ];

                    if ($data['officer_id'] <= 0 || $data['stock_id'] <= 0) {
                        $errors[] = 'Valid QC officer and stock reference are required.';
                    }
                    if ($data['inspection_date'] === '') {
                        $errors[] = 'Inspection date is required.';
                    }
                    if ($data['spoilage_percentage'] === null || $data['spoilage_percentage'] < 0 || $data['spoilage_percentage'] > 100) {
                        $errors[] = 'Spoilage percentage must be between 0 and 100.';
                    }

                    if (!$errors) {
                        if ($id > 0) {
                            $stmt = $pdo->prepare(
                                'UPDATE quality_inspections
                                 SET officer_id = :officer_id,
                                     stock_id = :stock_id,
                                     inspection_date = :inspection_date,
                                     spoilage_percentage = :spoilage_percentage,
                                     product_condition = :product_condition
                                 WHERE inspection_id = :id'
                            );
                            $stmt->execute($data + ['id' => $id]);
                            set_flash('success', 'Inspection updated successfully.');
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO quality_inspections (officer_id, stock_id, inspection_date, spoilage_percentage, product_condition)
                                 VALUES (:officer_id, :stock_id, :inspection_date, :spoilage_percentage, :product_condition)'
                            );
                            $stmt->execute($data);
                            set_flash('success', 'Inspection created successfully.');
                        }
                        redirect('/agri_inventory_management/modules/quality_control.php');
                    }
                    break;

                case 'delete_inspection':
                    table_delete($pdo, 'quality_inspections', 'inspection_id', (int) post('inspection_id'));
                    set_flash('success', 'Inspection deleted successfully.');
                    redirect('/agri_inventory_management/modules/quality_control.php');
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

$map = [
    'qc_officer' => ['table' => 'qc_officers', 'pk' => 'officer_id'],
    'inspection' => ['table' => 'quality_inspections', 'pk' => 'inspection_id'],
];

if ($editId > 0 && isset($map[$editEntity])) {
    $meta = $map[$editEntity];
    $stmt = $pdo->prepare("SELECT * FROM {$meta['table']} WHERE {$meta['pk']} = :id");
    $stmt->execute(['id' => $editId]);
    $editRecord = $stmt->fetch();
}

$officers = $pdo->query('SELECT * FROM qc_officers ORDER BY officer_id DESC')->fetchAll();

$stockOptions = $pdo->query(
    'SELECT st.stock_id, p.product_name, sf.storage_name, st.current_quantity, st.minimum_threshold_alert
     FROM inventory_stock st
     INNER JOIN products p ON p.product_id = st.product_id
     INNER JOIN storage_facilities sf ON sf.storage_id = st.storage_id
     ORDER BY st.stock_id DESC'
)->fetchAll();

$inspections = $pdo->query(
    'SELECT qi.*, qo.name AS officer_name,
            p.product_name, sf.storage_name, st.current_quantity, st.minimum_threshold_alert
     FROM quality_inspections qi
     INNER JOIN qc_officers qo ON qo.officer_id = qi.officer_id
     INNER JOIN inventory_stock st ON st.stock_id = qi.stock_id
     INNER JOIN products p ON p.product_id = st.product_id
     INNER JOIN storage_facilities sf ON sf.storage_id = st.storage_id
     ORDER BY qi.inspection_id DESC'
)->fetchAll();

$summary = [
    'total' => count($inspections),
    'rejected' => 0,
    'degraded' => 0,
    'avg_spoilage' => 0,
];

if ($inspections) {
    $totalSpoilage = 0.0;
    foreach ($inspections as $row) {
        $condition = (string) $row['product_condition'];
        if ($condition === 'rejected') {
            $summary['rejected']++;
        }
        if ($condition === 'degraded') {
            $summary['degraded']++;
        }
        $totalSpoilage += (float) $row['spoilage_percentage'];
    }
    $summary['avg_spoilage'] = round($totalSpoilage / count($inspections), 2);
}

$pageTitle = 'Quality Control';
$activePage = 'quality_control';
$searchPlaceholder = 'Search inspection IDs or products...';
require __DIR__ . '/../includes/header.php';
?>

<div class="module-header">
    <h1 class="page-title">Quality Control & Assurance</h1>
</div>

<div class="dashboard-grid-3">
    <div class="metric-card">
        <h3>Total Inspections</h3>
        <div class="value"><?= h((string) $summary['total']) ?></div>
    </div>
    <div class="metric-card warning">
        <h3>Degraded/Rejected</h3>
        <div class="value"><?= h((string) ($summary['degraded'] + $summary['rejected'])) ?></div>
    </div>
    <div class="metric-card">
        <h3>Avg Spoilage %</h3>
        <div class="value"><?= h((string) $summary['avg_spoilage']) ?></div>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="flash flash-error" style="margin-top:12px;"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="dashboard-grid" style="margin-top:20px;">
    <div>
        <div class="section-title">QC Officers</div>
        <?php $officerEdit = $editEntity === 'qc_officer' ? $editRecord : null; ?>
        <div class="form-container">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_qc_officer">
                <input type="hidden" name="officer_id" value="<?= h((string) ($officerEdit['officer_id'] ?? 0)) ?>">
                <div class="form-group"><label>Name</label><input class="form-control" name="name" required value="<?= h($officerEdit['name'] ?? '') ?>"></div>
                <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required value="<?= h($officerEdit['email'] ?? '') ?>"></div>
                <div class="form-group"><label>Contact</label><input class="form-control" name="contact" required value="<?= h($officerEdit['contact'] ?? '') ?>"></div>
                <div class="button-row">
                    <button class="btn btn-primary" type="submit"><?= $officerEdit ? 'Update Officer' : 'Add Officer' ?></button>
                    <?php if ($officerEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/quality_control.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead><tr><th>Officer ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($officers as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('qc', (int) $row['officer_id'])) ?></strong></td>
                        <td><?= h($row['name']) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td><?= h($row['contact']) ?></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=qc_officer&edit_id=<?= h((string) $row['officer_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this QC officer?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_qc_officer">
                                <input type="hidden" name="officer_id" value="<?= h((string) $row['officer_id']) ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$officers): ?><tr><td colspan="5">No officer records found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="section-title">Submit Inspection Report</div>
        <?php $inspectionEdit = $editEntity === 'inspection' ? $editRecord : null; ?>
        <div class="form-container">
            <form method="post" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_inspection">
                <input type="hidden" name="inspection_id" value="<?= h((string) ($inspectionEdit['inspection_id'] ?? 0)) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>QC Officer</label>
                        <select class="form-control" name="officer_id" required>
                            <option value="">Select Officer</option>
                            <?php foreach ($officers as $row): ?>
                                <option value="<?= h((string) $row['officer_id']) ?>" <?= (int) ($inspectionEdit['officer_id'] ?? 0) === (int) $row['officer_id'] ? 'selected' : '' ?>>
                                    <?= h(display_code('qc', (int) $row['officer_id']) . ' - ' . $row['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Inspection Date</label>
                        <input class="form-control" type="date" name="inspection_date" required value="<?= h($inspectionEdit['inspection_date'] ?? date('Y-m-d')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Stock Reference (Product + Storage)</label>
                    <select class="form-control" name="stock_id" required>
                        <option value="">Select Stock</option>
                        <?php foreach ($stockOptions as $row): ?>
                            <option value="<?= h((string) $row['stock_id']) ?>" <?= (int) ($inspectionEdit['stock_id'] ?? 0) === (int) $row['stock_id'] ? 'selected' : '' ?>>
                                <?= h(display_code('stock', (int) $row['stock_id']) . ' - ' . $row['product_name'] . ' @ ' . $row['storage_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Spoilage Percentage (%)</label>
                        <input class="form-control" type="number" min="0" max="100" step="0.01" name="spoilage_percentage" required value="<?= h((string) ($inspectionEdit['spoilage_percentage'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Product Condition</label>
                        <?php $conditionValue = (string) ($inspectionEdit['product_condition'] ?? 'acceptable'); ?>
                        <select class="form-control" name="product_condition" required>
                            <?php foreach (['excellent', 'acceptable', 'degraded', 'rejected'] as $condition): ?>
                                <option value="<?= h($condition) ?>" <?= $conditionValue === $condition ? 'selected' : '' ?>><?= h(ucfirst($condition)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="button-row">
                    <button type="submit" class="btn btn-primary"><?= $inspectionEdit ? 'Update Inspection' : 'Log Final Report' ?></button>
                    <?php if ($inspectionEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/quality_control.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Final Inspection Ledger</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Inspection ID</th>
                    <th>Officer</th>
                    <th>Stock Ref</th>
                    <th>Product + Storage</th>
                    <th>Date</th>
                    <th>Spoilage</th>
                    <th>Condition</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($inspections as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('inspection', (int) $row['inspection_id'])) ?></strong></td>
                        <td><?= h($row['officer_name']) ?></td>
                        <td><?= h(display_code('stock', (int) $row['stock_id'])) ?></td>
                        <td><?= h($row['product_name']) ?> @ <?= h($row['storage_name']) ?></td>
                        <td><?= h($row['inspection_date']) ?></td>
                        <td><?= h((string) $row['spoilage_percentage']) ?>%</td>
                        <td><span class="badge <?= h(badge_class($row['product_condition'])) ?>"><?= h(ucfirst($row['product_condition'])) ?></span></td>
                        <td>
                            <a class="btn btn-warning btn-sm" href="?edit_entity=inspection&edit_id=<?= h((string) $row['inspection_id']) ?>">Edit</a>
                            <form method="post" action="" style="display:inline;" data-confirm="Delete this inspection?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_inspection">
                                <input type="hidden" name="inspection_id" value="<?= h((string) $row['inspection_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$inspections): ?><tr><td colspan="8">No inspection records found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
