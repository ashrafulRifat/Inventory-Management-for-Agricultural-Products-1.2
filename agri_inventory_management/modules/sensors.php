<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
require_roles(['iot', 'field_officer', 'inventory_manager']);

$pdo = get_pdo();
$user = current_user();

$canManageAll = has_role(['iot']);
$canWriteTelemetry = has_role(['iot', 'field_officer', 'inventory_manager']);

$errors = [];

function evaluate_temperature_status(?float $temp): string
{
    if ($temp === null) {
        return 'unknown';
    }
    if ($temp < 2 || $temp > 30) {
        return 'high';
    }
    if ($temp > 18 || $temp < 4) {
        return 'elevated';
    }
    return 'optimal';
}

function evaluate_humidity_status(?float $humidity): string
{
    if ($humidity === null) {
        return 'unknown';
    }
    if ($humidity > 85 || $humidity < 30) {
        return 'high';
    }
    if ($humidity > 75) {
        return 'elevated';
    }
    return 'normal';
}

function recompute_environment_status(PDO $pdo, int $sensorId): void
{
    $sensorStmt = $pdo->prepare('SELECT sensor_id, storage_id, field_id FROM sensors_registry WHERE sensor_id = :sensor_id');
    $sensorStmt->execute(['sensor_id' => $sensorId]);
    $sensor = $sensorStmt->fetch();

    if (!$sensor) {
        return;
    }

    $storageId = $sensor['storage_id'] !== null ? (int) $sensor['storage_id'] : null;
    $fieldId = $sensor['field_id'] !== null ? (int) $sensor['field_id'] : null;

    if ($storageId === null && $fieldId === null) {
        return;
    }

    $locationWhere = $storageId !== null ? 'sr.storage_id = :storage_id' : 'sr.field_id = :field_id';
    $locationParam = $storageId !== null ? ['storage_id' => $storageId] : ['field_id' => $fieldId];

    $tempStmt = $pdo->prepare(
        "SELECT tl.recorded_value
         FROM sensor_telemetry_logs tl
         INNER JOIN sensors_registry sr ON sr.sensor_id = tl.sensor_id
         WHERE {$locationWhere} AND sr.sensor_category = 'temperature'
         ORDER BY tl.log_id DESC
         LIMIT 1"
    );
    $tempStmt->execute($locationParam);
    $tempRaw = $tempStmt->fetchColumn();
    $temp = $tempRaw !== false ? (float) $tempRaw : null;

    $humidityStmt = $pdo->prepare(
        "SELECT tl.recorded_value
         FROM sensor_telemetry_logs tl
         INNER JOIN sensors_registry sr ON sr.sensor_id = tl.sensor_id
         WHERE {$locationWhere} AND sr.sensor_category = 'humidity'
         ORDER BY tl.log_id DESC
         LIMIT 1"
    );
    $humidityStmt->execute($locationParam);
    $humidityRaw = $humidityStmt->fetchColumn();
    $humidity = $humidityRaw !== false ? (float) $humidityRaw : null;

    $tempStatus = evaluate_temperature_status($temp);
    $humidityStatus = evaluate_humidity_status($humidity);

    $overall = 'secure';
    if (in_array($tempStatus, ['high'], true) || in_array($humidityStatus, ['high'], true)) {
        $overall = 'critical';
    } elseif (in_array($tempStatus, ['elevated', 'unknown'], true) || in_array($humidityStatus, ['elevated', 'unknown'], true)) {
        $overall = 'monitor';
    }

    $existingStmt = $pdo->prepare(
        'SELECT status_id
         FROM location_environment_status
         WHERE (storage_id <=> :storage_id) AND (field_id <=> :field_id)
         LIMIT 1'
    );
    $existingStmt->execute(['storage_id' => $storageId, 'field_id' => $fieldId]);
    $statusId = $existingStmt->fetchColumn();

    if ($statusId) {
        $updateStmt = $pdo->prepare(
            'UPDATE location_environment_status
             SET temperature_status = :temperature_status,
                 humidity_status = :humidity_status,
                 overall_condition = :overall_condition,
                 last_evaluated = NOW()
             WHERE status_id = :status_id'
        );
        $updateStmt->execute([
            'temperature_status' => $tempStatus,
            'humidity_status' => $humidityStatus,
            'overall_condition' => $overall,
            'status_id' => (int) $statusId,
        ]);
    } else {
        $insertStmt = $pdo->prepare(
            'INSERT INTO location_environment_status (storage_id, field_id, temperature_status, humidity_status, overall_condition, last_evaluated)
             VALUES (:storage_id, :field_id, :temperature_status, :humidity_status, :overall_condition, NOW())'
        );
        $insertStmt->execute([
            'storage_id' => $storageId,
            'field_id' => $fieldId,
            'temperature_status' => $tempStatus,
            'humidity_status' => $humidityStatus,
            'overall_condition' => $overall,
        ]);
    }
}

if (is_post()) {
    if (!verify_csrf()) {
        $errors[] = 'Invalid security token.';
    }

    $action = (string) post('action');

    if (!$errors) {
        try {
            if ($action === 'save_sensor' && $canManageAll) {
                $id = (int) post('sensor_id');
                $storageId = (int) post('storage_id');
                $fieldId = (int) post('field_id');

                $storage = $storageId > 0 ? $storageId : null;
                $field = $fieldId > 0 ? $fieldId : null;

                if ($storage === null && $field === null) {
                    $errors[] = 'At least one location (storage or field) must be selected.';
                }

                $category = (string) post('sensor_category');
                $validCategories = ['temperature', 'humidity', 'weight', 'moisture', 'gas'];
                if (!in_array($category, $validCategories, true)) {
                    $errors[] = 'Invalid sensor category.';
                }

                $installationDate = trim((string) post('installation_date'));
                if ($installationDate === '') {
                    $errors[] = 'Installation date is required.';
                }

                if (!$errors) {
                    $payload = [
                        'sensor_category' => $category,
                        'storage_id' => $storage,
                        'field_id' => $field,
                        'installation_date' => $installationDate,
                    ];

                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE sensors_registry
                             SET sensor_category = :sensor_category,
                                 storage_id = :storage_id,
                                 field_id = :field_id,
                                 installation_date = :installation_date
                             WHERE sensor_id = :id'
                        );
                        $stmt->execute($payload + ['id' => $id]);
                        set_flash('success', 'Sensor updated successfully.');
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO sensors_registry (sensor_category, storage_id, field_id, installation_date)
                             VALUES (:sensor_category, :storage_id, :field_id, :installation_date)'
                        );
                        $stmt->execute($payload);
                        set_flash('success', 'Sensor registered successfully.');
                    }

                    redirect('/agri_inventory_management/modules/sensors.php');
                }
            }

            if ($action === 'delete_sensor' && $canManageAll) {
                table_delete($pdo, 'sensors_registry', 'sensor_id', (int) post('sensor_id'));
                set_flash('success', 'Sensor deleted successfully.');
                redirect('/agri_inventory_management/modules/sensors.php');
            }

            if ($action === 'save_telemetry' && $canWriteTelemetry) {
                $id = (int) post('log_id');
                $sensorId = (int) post('sensor_id');
                $recordedValue = to_decimal(post('recorded_value'));
                $recordedAt = trim((string) post('recorded_at'));
                $recordedAt = str_replace('T', ' ', $recordedAt);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $recordedAt) === 1) {
                    $recordedAt .= ':00';
                }

                if ($sensorId <= 0) {
                    $errors[] = 'A valid sensor is required.';
                }
                if ($recordedValue === null) {
                    $errors[] = 'Recorded value must be numeric.';
                }
                if ($recordedAt === '') {
                    $errors[] = 'Recorded timestamp is required.';
                }

                if (!$errors) {
                    if ($id > 0 && $canManageAll) {
                        $stmt = $pdo->prepare(
                            'UPDATE sensor_telemetry_logs
                             SET sensor_id = :sensor_id,
                                 recorded_value = :recorded_value,
                                 recorded_at = :recorded_at
                             WHERE log_id = :id'
                        );
                        $stmt->execute([
                            'sensor_id' => $sensorId,
                            'recorded_value' => $recordedValue,
                            'recorded_at' => $recordedAt,
                            'id' => $id,
                        ]);
                        recompute_environment_status($pdo, $sensorId);
                        set_flash('success', 'Telemetry log updated successfully.');
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO sensor_telemetry_logs (sensor_id, recorded_value, recorded_at)
                             VALUES (:sensor_id, :recorded_value, :recorded_at)'
                        );
                        $stmt->execute([
                            'sensor_id' => $sensorId,
                            'recorded_value' => $recordedValue,
                            'recorded_at' => $recordedAt,
                        ]);
                        recompute_environment_status($pdo, $sensorId);
                        set_flash('success', 'Telemetry log created and environment status recomputed.');
                    }
                    redirect('/agri_inventory_management/modules/sensors.php');
                }
            }

            if ($action === 'delete_telemetry' && $canManageAll) {
                table_delete($pdo, 'sensor_telemetry_logs', 'log_id', (int) post('log_id'));
                set_flash('success', 'Telemetry log deleted successfully.');
                redirect('/agri_inventory_management/modules/sensors.php');
            }

            if ($action === 'save_environment' && $canManageAll) {
                $id = (int) post('status_id');
                $storageId = (int) post('status_storage_id');
                $fieldId = (int) post('status_field_id');
                $storage = $storageId > 0 ? $storageId : null;
                $field = $fieldId > 0 ? $fieldId : null;

                if ($storage === null && $field === null) {
                    $errors[] = 'At least one location (storage or field) must be selected for environment status.';
                }

                $overall = (string) post('overall_condition');
                $validOverall = ['secure', 'monitor', 'critical'];
                if (!in_array($overall, $validOverall, true)) {
                    $overall = 'monitor';
                }

                $payload = [
                    'storage_id' => $storage,
                    'field_id' => $field,
                    'temperature_status' => trim((string) post('temperature_status')),
                    'humidity_status' => trim((string) post('humidity_status')),
                    'overall_condition' => $overall,
                    'last_evaluated' => str_replace('T', ' ', trim((string) post('last_evaluated'))),
                ];

                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', (string) $payload['last_evaluated']) === 1) {
                    $payload['last_evaluated'] .= ':00';
                }

                $errors = array_merge($errors, validate_required($payload, [
                    'temperature_status' => 'Temperature status',
                    'humidity_status' => 'Humidity status',
                    'last_evaluated' => 'Last evaluated timestamp',
                ]));

                if (!$errors) {
                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE location_environment_status
                             SET storage_id = :storage_id,
                                 field_id = :field_id,
                                 temperature_status = :temperature_status,
                                 humidity_status = :humidity_status,
                                 overall_condition = :overall_condition,
                                 last_evaluated = :last_evaluated
                             WHERE status_id = :id'
                        );
                        $stmt->execute($payload + ['id' => $id]);
                        set_flash('success', 'Environment status updated successfully.');
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO location_environment_status
                             (storage_id, field_id, temperature_status, humidity_status, overall_condition, last_evaluated)
                             VALUES
                             (:storage_id, :field_id, :temperature_status, :humidity_status, :overall_condition, :last_evaluated)'
                        );
                        $stmt->execute($payload);
                        set_flash('success', 'Environment status created successfully.');
                    }
                    redirect('/agri_inventory_management/modules/sensors.php');
                }
            }

            if ($action === 'delete_environment' && $canManageAll) {
                table_delete($pdo, 'location_environment_status', 'status_id', (int) post('status_id'));
                set_flash('success', 'Environment status deleted successfully.');
                redirect('/agri_inventory_management/modules/sensors.php');
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
    'sensor' => ['table' => 'sensors_registry', 'pk' => 'sensor_id'],
    'telemetry' => ['table' => 'sensor_telemetry_logs', 'pk' => 'log_id'],
    'environment' => ['table' => 'location_environment_status', 'pk' => 'status_id'],
];

if ($editId > 0 && isset($map[$editEntity])) {
    $meta = $map[$editEntity];
    $stmt = $pdo->prepare("SELECT * FROM {$meta['table']} WHERE {$meta['pk']} = :id");
    $stmt->execute(['id' => $editId]);
    $editRecord = $stmt->fetch();
}

$fields = $pdo->query('SELECT field_id, field_type, field_officer_id FROM fields ORDER BY field_id DESC')->fetchAll();
$storage = $pdo->query('SELECT storage_id, storage_name FROM storage_facilities ORDER BY storage_id DESC')->fetchAll();

$fieldFilterSql = '';
$fieldFilterParams = [];

if (($user['role'] ?? '') === 'field_officer' && !empty($user['field_officer_id'])) {
    $fieldFilterSql = ' WHERE sr.field_id IN (SELECT field_id FROM fields WHERE field_officer_id = :field_officer_id)';
    $fieldFilterParams['field_officer_id'] = (int) $user['field_officer_id'];
}

$sensorStmt = $pdo->prepare(
    'SELECT sr.*, sf.storage_name, f.field_type
     FROM sensors_registry sr
     LEFT JOIN storage_facilities sf ON sf.storage_id = sr.storage_id
     LEFT JOIN fields f ON f.field_id = sr.field_id' . $fieldFilterSql . '
     ORDER BY sr.sensor_id DESC'
);
$sensorStmt->execute($fieldFilterParams);
$sensors = $sensorStmt->fetchAll();

$telemetryStmt = $pdo->prepare(
    'SELECT tl.*, sr.sensor_category, sr.storage_id, sr.field_id, sf.storage_name, f.field_type
     FROM sensor_telemetry_logs tl
     INNER JOIN sensors_registry sr ON sr.sensor_id = tl.sensor_id
     LEFT JOIN storage_facilities sf ON sf.storage_id = sr.storage_id
     LEFT JOIN fields f ON f.field_id = sr.field_id' . $fieldFilterSql . '
     ORDER BY tl.log_id DESC
     LIMIT 120'
);
$telemetryStmt->execute($fieldFilterParams);
$telemetry = $telemetryStmt->fetchAll();

$environmentFilterSql = '';
$environmentParams = [];
if (($user['role'] ?? '') === 'field_officer' && !empty($user['field_officer_id'])) {
    $environmentFilterSql = ' WHERE les.field_id IN (SELECT field_id FROM fields WHERE field_officer_id = :field_officer_id)';
    $environmentParams['field_officer_id'] = (int) $user['field_officer_id'];
}

$environmentStmt = $pdo->prepare(
    'SELECT les.*, sf.storage_name, f.field_type
     FROM location_environment_status les
     LEFT JOIN storage_facilities sf ON sf.storage_id = les.storage_id
     LEFT JOIN fields f ON f.field_id = les.field_id' . $environmentFilterSql . '
     ORDER BY les.status_id DESC'
);
$environmentStmt->execute($environmentParams);
$environmentRows = $environmentStmt->fetchAll();

$pageTitle = 'IoT Sensor Network';
$activePage = 'sensors';
$searchPlaceholder = 'Search sensor ID, location, or status...';
require __DIR__ . '/../includes/header.php';
?>

<div class="module-header">
    <h1 class="page-title">IoT Sensor Network</h1>
</div>

<?php foreach ($errors as $error): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="dashboard-grid">
    <div>
        <div class="section-title">Location Environment Status</div>
        <?php $environmentEdit = $editEntity === 'environment' ? $editRecord : null; ?>
        <?php if ($canManageAll): ?>
            <div class="form-container">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_environment">
                    <input type="hidden" name="status_id" value="<?= h((string) ($environmentEdit['status_id'] ?? 0)) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Storage (optional)</label>
                            <select class="form-control" name="status_storage_id">
                                <option value="">None</option>
                                <?php foreach ($storage as $row): ?>
                                    <option value="<?= h((string) $row['storage_id']) ?>" <?= (int) ($environmentEdit['storage_id'] ?? 0) === (int) $row['storage_id'] ? 'selected' : '' ?>>
                                        <?= h($row['storage_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Field (optional)</label>
                            <select class="form-control" name="status_field_id">
                                <option value="">None</option>
                                <?php foreach ($fields as $row): ?>
                                    <option value="<?= h((string) $row['field_id']) ?>" <?= (int) ($environmentEdit['field_id'] ?? 0) === (int) $row['field_id'] ? 'selected' : '' ?>>
                                        <?= h(display_code('field', (int) $row['field_id']) . ' - ' . $row['field_type']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Temperature Status</label><input class="form-control" name="temperature_status" required value="<?= h($environmentEdit['temperature_status'] ?? '') ?>"></div>
                        <div class="form-group"><label>Humidity Status</label><input class="form-control" name="humidity_status" required value="<?= h($environmentEdit['humidity_status'] ?? '') ?>"></div>
                        <div class="form-group">
                            <label>Overall Condition</label>
                            <?php $overallValue = (string) ($environmentEdit['overall_condition'] ?? 'monitor'); ?>
                            <select class="form-control" name="overall_condition" required>
                                <?php foreach (['secure', 'monitor', 'critical'] as $overall): ?>
                                    <option value="<?= h($overall) ?>" <?= $overallValue === $overall ? 'selected' : '' ?>><?= h(ucfirst($overall)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>Last Evaluated</label><input class="form-control" type="datetime-local" name="last_evaluated" required value="<?= h(isset($environmentEdit['last_evaluated']) ? str_replace(' ', 'T', substr((string) $environmentEdit['last_evaluated'], 0, 16)) : '') ?>"></div>
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary"><?= $environmentEdit ? 'Update Status' : 'Add Status' ?></button>
                        <?php if ($environmentEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/sensors.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Temp</th>
                    <th>Humidity</th>
                    <th>Overall</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($environmentRows as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('status', (int) $row['status_id'])) ?></strong></td>
                        <td><?= h($row['storage_name'] ? 'Storage: ' . $row['storage_name'] : 'Field: ' . $row['field_type']) ?></td>
                        <td><?= h($row['temperature_status']) ?></td>
                        <td><?= h($row['humidity_status']) ?></td>
                        <td><span class="badge <?= h(badge_class($row['overall_condition'])) ?>"><?= h(ucfirst($row['overall_condition'])) ?></span></td>
                        <td>
                            <?php if ($canManageAll): ?>
                                <a class="btn btn-warning btn-sm" href="?edit_entity=environment&edit_id=<?= h((string) $row['status_id']) ?>">Edit</a>
                                <form method="post" action="" style="display:inline;" data-confirm="Delete this environment status row?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_environment">
                                    <input type="hidden" name="status_id" value="<?= h((string) $row['status_id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="small-text">Read only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$environmentRows): ?>
                    <tr><td colspan="6">No environment status records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="section-title">Sensors Registry</div>
        <?php $sensorEdit = $editEntity === 'sensor' ? $editRecord : null; ?>
        <?php if ($canManageAll): ?>
            <div class="form-container">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_sensor">
                    <input type="hidden" name="sensor_id" value="<?= h((string) ($sensorEdit['sensor_id'] ?? 0)) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <?php $categoryValue = (string) ($sensorEdit['sensor_category'] ?? 'temperature'); ?>
                            <select class="form-control" name="sensor_category" required>
                                <?php foreach (['temperature', 'humidity', 'weight', 'moisture', 'gas'] as $category): ?>
                                    <option value="<?= h($category) ?>" <?= $categoryValue === $category ? 'selected' : '' ?>><?= h(ucfirst($category)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Storage (optional)</label>
                            <select class="form-control" name="storage_id">
                                <option value="">None</option>
                                <?php foreach ($storage as $row): ?>
                                    <option value="<?= h((string) $row['storage_id']) ?>" <?= (int) ($sensorEdit['storage_id'] ?? 0) === (int) $row['storage_id'] ? 'selected' : '' ?>><?= h($row['storage_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Field (optional)</label>
                            <select class="form-control" name="field_id">
                                <option value="">None</option>
                                <?php foreach ($fields as $row): ?>
                                    <option value="<?= h((string) $row['field_id']) ?>" <?= (int) ($sensorEdit['field_id'] ?? 0) === (int) $row['field_id'] ? 'selected' : '' ?>><?= h(display_code('field', (int) $row['field_id']) . ' - ' . $row['field_type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>Installation Date</label><input class="form-control" type="date" name="installation_date" required value="<?= h($sensorEdit['installation_date'] ?? '') ?>"></div>
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary"><?= $sensorEdit ? 'Update Sensor' : 'Register Sensor' ?></button>
                        <?php if ($sensorEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/sensors.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Sensor ID</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Installed</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sensors as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('sensor', (int) $row['sensor_id'])) ?></strong></td>
                        <td><?= h(ucfirst($row['sensor_category'])) ?></td>
                        <td><?= h($row['storage_name'] ? 'Storage: ' . $row['storage_name'] : 'Field: ' . $row['field_type']) ?></td>
                        <td><?= h($row['installation_date']) ?></td>
                        <td>
                            <?php if ($canManageAll): ?>
                                <a class="btn btn-warning btn-sm" href="?edit_entity=sensor&edit_id=<?= h((string) $row['sensor_id']) ?>">Edit</a>
                                <form method="post" action="" style="display:inline;" data-confirm="Delete this sensor and all telemetry logs?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_sensor">
                                    <input type="hidden" name="sensor_id" value="<?= h((string) $row['sensor_id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="small-text">Read only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$sensors): ?>
                    <tr><td colspan="5">No sensor records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Recent Telemetry Logs (Live Feed)</div>
        <?php $telemetryEdit = $editEntity === 'telemetry' ? $editRecord : null; ?>
        <?php if ($canWriteTelemetry): ?>
            <div class="form-container">
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_telemetry">
                    <input type="hidden" name="log_id" value="<?= h((string) ($telemetryEdit['log_id'] ?? 0)) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Sensor</label>
                            <select class="form-control" name="sensor_id" required>
                                <option value="">Select Sensor</option>
                                <?php foreach ($sensors as $row): ?>
                                    <option value="<?= h((string) $row['sensor_id']) ?>" <?= (int) ($telemetryEdit['sensor_id'] ?? 0) === (int) $row['sensor_id'] ? 'selected' : '' ?>>
                                        <?= h(display_code('sensor', (int) $row['sensor_id']) . ' - ' . ucfirst($row['sensor_category'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Recorded Value</label><input class="form-control" type="number" step="0.01" name="recorded_value" required value="<?= h((string) ($telemetryEdit['recorded_value'] ?? '')) ?>"></div>
                        <div class="form-group"><label>Timestamp</label><input class="form-control" type="datetime-local" name="recorded_at" required value="<?= h(isset($telemetryEdit['recorded_at']) ? str_replace(' ', 'T', substr((string) $telemetryEdit['recorded_at'], 0, 16)) : date('Y-m-d\TH:i')) ?>"></div>
                    </div>
                    <div class="button-row">
                        <button type="submit" class="btn btn-secondary"><?= $telemetryEdit && $canManageAll ? 'Update Telemetry' : 'Add Telemetry' ?></button>
                        <?php if ($telemetryEdit): ?><a class="btn btn-muted" href="/agri_inventory_management/modules/sensors.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="data-table-container" style="margin-top:12px;">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Sensor</th>
                    <th>Category</th>
                    <th>Value</th>
                    <th>Location</th>
                    <th>Recorded At</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($telemetry as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('telemetry', (int) $row['log_id'])) ?></strong></td>
                        <td><?= h(display_code('sensor', (int) $row['sensor_id'])) ?></td>
                        <td><?= h(ucfirst($row['sensor_category'])) ?></td>
                        <td><?= h((string) $row['recorded_value']) ?></td>
                        <td><?= h($row['storage_name'] ? 'Storage: ' . $row['storage_name'] : 'Field: ' . $row['field_type']) ?></td>
                        <td><?= h($row['recorded_at']) ?></td>
                        <td>
                            <?php if ($canManageAll): ?>
                                <a class="btn btn-warning btn-sm" href="?edit_entity=telemetry&edit_id=<?= h((string) $row['log_id']) ?>">Edit</a>
                                <form method="post" action="" style="display:inline;" data-confirm="Delete this telemetry log?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_telemetry">
                                    <input type="hidden" name="log_id" value="<?= h((string) $row['log_id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="small-text">Read only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$telemetry): ?>
                    <tr><td colspan="7">No telemetry logs found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
