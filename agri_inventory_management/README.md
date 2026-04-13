# Inventory Management for Agricultural Products

A full-stack DBMS demo project built for XAMPP using PHP 8+, PDO, MySQL/MariaDB, HTML/CSS, and vanilla JavaScript.

The original static frontend style was reused and refactored into shared PHP layout components with live database-backed CRUD pages.

## Features

- Role-based login with PHP sessions (`admin`, `field_officer`, `inventory_manager`, `supplier`, `qc_officer`, `iot`)
- Shared layout (`header`, `sidebar`, `footer`) and centralized styles/scripts
- Dynamic dashboard with live metrics and joined activity tables
- Full CRUD across major ER entities:
  - Field operations: officers, farmers, fields, harvest logs, input requests
  - Product catalog: products with search/filter
  - Supplier management: suppliers, purchase orders, line items (supports multi-item order create)
  - Inventory/storage: managers, facilities, stock, low-stock highlighting, receive-delivered flow
  - IoT module: sensor registry, telemetry logs, environment status, telemetry-driven status recompute
  - Quality control: QC officers and stock-referenced quality inspections
- Prepared statements throughout
- Server-side validation and escaped output
- Flash messages and delete confirmations

## Project Structure

```text
agri_inventory_management/
  assets/
    css/app.css
    js/app.js
  auth/
    login.php
    logout.php
  config/
    database.php
  database/
    agri_inventory_management.sql
  includes/
    auth.php
    bootstrap.php
    footer.php
    header.php
    helpers.php
    sidebar.php
  modules/
    dashboard.php
    field_operations.php
    products.php
    suppliers.php
    inventory.php
    sensors.php
    quality_control.php
  index.php
  README.md

  # Existing original mockup files kept in workspace as UI reference:
  index.html
  field_operations.html
  product_catalog.html
  supplier_management.html
  inventory_storage.html
  iot_network.html
  quality_control.html
```

## XAMPP Setup Instructions

1. Copy project folder to XAMPP htdocs:
   - `C:\xampp\htdocs\agri_inventory_management`
2. Start Apache and MySQL from XAMPP Control Panel.
3. Open phpMyAdmin (`http://localhost/phpmyadmin`).
4. Import SQL file:
   - Select `Import`
   - Choose `database/agri_inventory_management.sql`
   - Run import
5. Open app:
   - `http://localhost/agri_inventory_management/`

## Database Credentials

Update credentials in `config/database.php` if needed:

- Host: `127.0.0.1`
- Database: `agri_inventory_management`
- Username: `root`
- Password: `` (empty by default in XAMPP)

## Demo Login Credentials

All seeded demo users use the same password:

- Password: `password`

Usernames:

- `admin`
- `field_officer`
- `inventory_manager`
- `supplier_user`
- `qc_officer`
- `iot_user`

## ER Mapping and Data Integrity Notes

- Business schema follows the provided ER diagram with integer primary keys and foreign keys.
- UI display IDs are formatted codes (e.g., `PRD-1001`) generated in PHP helper functions.
- `quality_inspections` references `stock_id` (not product id directly).
- Sensor/location integrity:
  - `sensors_registry`: at least one of `storage_id` or `field_id`
  - `location_environment_status`: at least one of `storage_id` or `field_id`
- CHECK constraints are included in SQL, and app-level validation is also implemented.

## Enum Values Used

- `input_requests.fulfillment_status`: `pending`, `approved`, `fulfilled`, `rejected`
- `purchase_orders.status`: `pending`, `processing`, `delivered`, `cancelled`
- `storage_facilities.storage_size`: `small`, `medium`, `large`
- `sensors_registry.sensor_category`: `temperature`, `humidity`, `weight`, `moisture`, `gas`
- `location_environment_status.overall_condition`: `secure`, `monitor`, `critical`

## Seed Data Coverage

The SQL import seeds relational demo data exceeding requested minimums, including:

- Field officers, farmers, fields, harvest logs, input requests
- Products
- Suppliers, purchase orders, order line items
- Inventory managers, storage facilities, stock rows
- Sensors
- 130 telemetry logs
- Environment status records
- QC officers and quality inspections
- Role-based demo users

## Assumptions

1. Project runs under `/agri_inventory_management` in XAMPP `htdocs`.
2. MySQL/MariaDB accepts CHECK constraints; app-level validation is also enforced for compatibility.
3. Delivered purchase orders auto-set `delivered_date` when status changes to `delivered`.
4. Receive-into-stock flow is additive and demo-oriented (does not maintain a separate receipt ledger table).
5. Supplier role can view own profile/orders and update own order status for demo behavior.

## Quick Demo Flow

1. Login as `admin`.
2. Open Dashboard and verify live counts/tables.
3. Create/update records in each module.
4. In Supplier Management, create an order with line items.
5. Mark an order as delivered.
6. In Inventory, receive delivered line item into stock.
7. In IoT module, add telemetry and observe environment status updates.
8. In Quality Control, create inspection using stock reference (product + storage display).
