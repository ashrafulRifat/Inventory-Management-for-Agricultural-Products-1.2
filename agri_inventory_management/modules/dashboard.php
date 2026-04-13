<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$pdo = get_pdo();

$summary = [
    'pending_requests' => 0,
    'low_stock_alerts' => 0,
    'active_sensors' => 0,
    'critical_conditions' => 0,
];

$recentOrders = [];
$recentInspections = [];
$recentTelemetry = [];
$conditionAlerts = [];

try {
    $summary['pending_requests'] = (int) $pdo->query("SELECT COUNT(*) FROM input_requests WHERE fulfillment_status = 'pending'")->fetchColumn();
    $summary['low_stock_alerts'] = (int) $pdo->query('SELECT COUNT(*) FROM inventory_stock WHERE current_quantity <= minimum_threshold_alert')->fetchColumn();
    $summary['active_sensors'] = (int) $pdo->query('SELECT COUNT(*) FROM sensors_registry')->fetchColumn();
    $summary['critical_conditions'] = (int) $pdo->query("SELECT COUNT(*) FROM location_environment_status WHERE overall_condition IN ('monitor', 'critical')")->fetchColumn();

    $recentOrders = $pdo->query(
        "SELECT po.order_id, po.order_type, po.order_date, po.target_delivery_date, po.delivered_date, po.status, s.company_name
         FROM purchase_orders po
         INNER JOIN suppliers s ON s.supplier_id = po.supplier_id
         ORDER BY po.order_id DESC
         LIMIT 6"
    )->fetchAll();

    $recentInspections = $pdo->query(
        "SELECT qi.inspection_id, qi.inspection_date, qi.spoilage_percentage, qi.product_condition,
                qo.name AS officer_name, p.product_name, sf.storage_name
         FROM quality_inspections qi
         INNER JOIN qc_officers qo ON qo.officer_id = qi.officer_id
         INNER JOIN inventory_stock st ON st.stock_id = qi.stock_id
         INNER JOIN products p ON p.product_id = st.product_id
         INNER JOIN storage_facilities sf ON sf.storage_id = st.storage_id
         ORDER BY qi.inspection_id DESC
         LIMIT 6"
    )->fetchAll();

    $recentTelemetry = $pdo->query(
        "SELECT tl.log_id, tl.recorded_value, tl.recorded_at, sr.sensor_id, sr.sensor_category,
                sf.storage_name, f.field_type
         FROM sensor_telemetry_logs tl
         INNER JOIN sensors_registry sr ON sr.sensor_id = tl.sensor_id
         LEFT JOIN storage_facilities sf ON sf.storage_id = sr.storage_id
         LEFT JOIN fields f ON f.field_id = sr.field_id
         ORDER BY tl.log_id DESC
         LIMIT 10"
    )->fetchAll();

    $conditionAlerts = $pdo->query(
        "SELECT les.status_id, les.temperature_status, les.humidity_status, les.overall_condition, les.last_evaluated,
                sf.storage_name, f.field_type
         FROM location_environment_status les
         LEFT JOIN storage_facilities sf ON sf.storage_id = les.storage_id
         LEFT JOIN fields f ON f.field_id = les.field_id
         WHERE les.overall_condition IN ('monitor', 'critical')
         ORDER BY les.last_evaluated DESC
         LIMIT 8"
    )->fetchAll();
} catch (Throwable $exception) {
    set_flash('error', 'Dashboard queries failed: ' . $exception->getMessage());
}

$pageTitle = 'Dashboard Home';
$activePage = 'dashboard';
$searchPlaceholder = 'Search database IDs, sensors, or fields...';
require __DIR__ . '/../includes/header.php';
?>

<div class="module-header">
    <h1 class="page-title">Dashboard Home</h1>
</div>

<div class="dashboard-grid-3">
    <div class="metric-card">
        <h3>Pending Input Requests</h3>
        <div class="value"><?= h((string) $summary['pending_requests']) ?></div>
    </div>
    <div class="metric-card warning">
        <h3>Low Stock Alerts</h3>
        <div class="value"><?= h((string) $summary['low_stock_alerts']) ?></div>
    </div>
    <div class="metric-card">
        <h3>Active Sensor Deployments</h3>
        <div class="value"><?= h((string) $summary['active_sensors']) ?></div>
    </div>
</div>

<div class="dashboard-grid" style="margin-top: 20px;">
    <div>
        <div class="section-title">Recent Purchase Orders</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Supplier</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Target Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('order', (int) $row['order_id'])) ?></strong></td>
                        <td><?= h($row['company_name']) ?></td>
                        <td><?= h($row['order_type']) ?></td>
                        <td><span class="badge <?= h(badge_class($row['status'])) ?>"><?= h(ucfirst($row['status'])) ?></span></td>
                        <td><?= h($row['target_delivery_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentOrders): ?>
                    <tr><td colspan="5">No purchase order records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="section-title">Recent Quality Inspections</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Inspection</th>
                        <th>Stock Context</th>
                        <th>Spoilage</th>
                        <th>Condition</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentInspections as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('inspection', (int) $row['inspection_id'])) ?></strong><br><span class="small-text"><?= h($row['inspection_date']) ?></span></td>
                        <td><?= h($row['product_name']) ?> @ <?= h($row['storage_name']) ?><br><span class="small-text"><?= h($row['officer_name']) ?></span></td>
                        <td><?= h((string) $row['spoilage_percentage']) ?>%</td>
                        <td><span class="badge <?= h(badge_class($row['product_condition'])) ?>"><?= h(ucfirst($row['product_condition'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentInspections): ?>
                    <tr><td colspan="4">No inspection records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Recent Telemetry Logs</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Sensor</th>
                        <th>Category</th>
                        <th>Value</th>
                        <th>Location</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentTelemetry as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('telemetry', (int) $row['log_id'])) ?></strong></td>
                        <td><?= h(display_code('sensor', (int) $row['sensor_id'])) ?></td>
                        <td><?= h(ucfirst($row['sensor_category'])) ?></td>
                        <td><?= h((string) $row['recorded_value']) ?></td>
                        <td>
                            <?= $row['storage_name'] ? h('Storage: ' . $row['storage_name']) : h('Field: ' . $row['field_type']) ?>
                        </td>
                        <td><?= h($row['recorded_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentTelemetry): ?>
                    <tr><td colspan="6">No telemetry logs found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="full-width">
        <div class="section-title">Environment Condition Alerts (<?= h((string) $summary['critical_conditions']) ?>)</div>
        <div class="data-table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status ID</th>
                        <th>Location</th>
                        <th>Temperature</th>
                        <th>Humidity</th>
                        <th>Overall</th>
                        <th>Last Evaluated</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($conditionAlerts as $row): ?>
                    <tr>
                        <td><strong><?= h(display_code('status', (int) $row['status_id'])) ?></strong></td>
                        <td>
                            <?= $row['storage_name'] ? h('Storage: ' . $row['storage_name']) : h('Field: ' . $row['field_type']) ?>
                        </td>
                        <td><?= h($row['temperature_status']) ?></td>
                        <td><?= h($row['humidity_status']) ?></td>
                        <td><span class="badge <?= h(badge_class($row['overall_condition'])) ?>"><?= h(ucfirst($row['overall_condition'])) ?></span></td>
                        <td><?= h($row['last_evaluated']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$conditionAlerts): ?>
                    <tr><td colspan="6">No alert records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
