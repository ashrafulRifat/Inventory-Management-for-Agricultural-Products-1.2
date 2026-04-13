-- Agri Inventory Management Database
-- Import this file in phpMyAdmin (XAMPP) to create schema + demo data

DROP DATABASE IF EXISTS `agri_inventory_management`;
CREATE DATABASE `agri_inventory_management`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `agri_inventory_management`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `field_officers` (
  `field_officer_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `contact` VARCHAR(30) NOT NULL,
  UNIQUE KEY `uq_field_officers_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `farmers` (
  `farmer_id` INT AUTO_INCREMENT PRIMARY KEY,
  `farmer_name` VARCHAR(120) NOT NULL,
  `contact_number` VARCHAR(30) NOT NULL,
  `availability` TINYINT(1) NOT NULL DEFAULT 1,
  KEY `idx_farmers_name` (`farmer_name`)
) ENGINE=InnoDB;

CREATE TABLE `products` (
  `product_id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `unit_of_measurement` VARCHAR(30) NOT NULL,
  `base_shelf_life_days` INT NOT NULL,
  `optimal_temp_min` DECIMAL(6,2) NOT NULL,
  `optimal_temp_max` DECIMAL(6,2) NOT NULL,
  KEY `idx_products_name` (`product_name`),
  KEY `idx_products_category` (`category`)
) ENGINE=InnoDB;

CREATE TABLE `suppliers` (
  `supplier_id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `contact` VARCHAR(30) NOT NULL,
  UNIQUE KEY `uq_suppliers_email` (`email`),
  KEY `idx_suppliers_company` (`company_name`)
) ENGINE=InnoDB;

CREATE TABLE `inventory_managers` (
  `manager_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `contact` VARCHAR(30) NOT NULL,
  UNIQUE KEY `uq_inventory_managers_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `qc_officers` (
  `officer_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `contact` VARCHAR(30) NOT NULL,
  UNIQUE KEY `uq_qc_officers_email` (`email`)
) ENGINE=InnoDB;

CREATE TABLE `fields` (
  `field_id` INT AUTO_INCREMENT PRIMARY KEY,
  `farmer_id` INT NOT NULL,
  `field_officer_id` INT NOT NULL,
  `field_type` VARCHAR(120) NOT NULL,
  `planting_date` DATE NOT NULL,
  `target_harvest_date` DATE NOT NULL,
  KEY `idx_fields_farmer_id` (`farmer_id`),
  KEY `idx_fields_field_officer_id` (`field_officer_id`),
  CONSTRAINT `fk_fields_farmer`
    FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`farmer_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_fields_field_officer`
    FOREIGN KEY (`field_officer_id`) REFERENCES `field_officers` (`field_officer_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `harvest_logs` (
  `harvest_id` INT AUTO_INCREMENT PRIMARY KEY,
  `field_id` INT NOT NULL,
  `harvested_percentage` DECIMAL(5,2) NOT NULL,
  `harvested_date` DATE NOT NULL,
  `collected_weight` DECIMAL(12,2) NOT NULL,
  KEY `idx_harvest_logs_field_id` (`field_id`),
  KEY `idx_harvest_logs_date` (`harvested_date`),
  CONSTRAINT `fk_harvest_logs_field`
    FOREIGN KEY (`field_id`) REFERENCES `fields` (`field_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `input_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `field_officer_id` INT NOT NULL,
  `farmer_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `required_quantity` DECIMAL(12,2) NOT NULL,
  `fulfillment_status` ENUM('pending','approved','fulfilled','rejected') NOT NULL DEFAULT 'pending',
  KEY `idx_input_requests_officer` (`field_officer_id`),
  KEY `idx_input_requests_farmer` (`farmer_id`),
  KEY `idx_input_requests_product` (`product_id`),
  KEY `idx_input_requests_status` (`fulfillment_status`),
  CONSTRAINT `fk_input_requests_field_officer`
    FOREIGN KEY (`field_officer_id`) REFERENCES `field_officers` (`field_officer_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_input_requests_farmer`
    FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`farmer_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_input_requests_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `purchase_orders` (
  `order_id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT NOT NULL,
  `order_type` VARCHAR(120) NOT NULL,
  `order_date` DATE NOT NULL,
  `target_delivery_date` DATE NOT NULL,
  `delivered_date` DATE DEFAULT NULL,
  `status` ENUM('pending','processing','delivered','cancelled') NOT NULL DEFAULT 'pending',
  KEY `idx_purchase_orders_supplier` (`supplier_id`),
  KEY `idx_purchase_orders_status` (`status`),
  KEY `idx_purchase_orders_order_date` (`order_date`),
  CONSTRAINT `fk_purchase_orders_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `order_line_items` (
  `line_item_id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL,
  KEY `idx_order_line_items_order` (`order_id`),
  KEY `idx_order_line_items_product` (`product_id`),
  CONSTRAINT `fk_order_line_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `purchase_orders` (`order_id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_order_line_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `storage_facilities` (
  `storage_id` INT AUTO_INCREMENT PRIMARY KEY,
  `manager_id` INT NOT NULL,
  `storage_name` VARCHAR(150) NOT NULL,
  `storage_size` ENUM('small','medium','large') NOT NULL,
  `storage_type` VARCHAR(80) NOT NULL,
  `storage_condition` VARCHAR(80) NOT NULL,
  `capacity` DECIMAL(12,2) NOT NULL,
  KEY `idx_storage_facilities_manager` (`manager_id`),
  KEY `idx_storage_facilities_name` (`storage_name`),
  CONSTRAINT `fk_storage_facilities_manager`
    FOREIGN KEY (`manager_id`) REFERENCES `inventory_managers` (`manager_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `inventory_stock` (
  `stock_id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `storage_id` INT NOT NULL,
  `current_quantity` DECIMAL(12,2) NOT NULL,
  `minimum_threshold_alert` DECIMAL(12,2) NOT NULL,
  KEY `idx_inventory_stock_product` (`product_id`),
  KEY `idx_inventory_stock_storage` (`storage_id`),
  KEY `idx_inventory_stock_alert` (`current_quantity`, `minimum_threshold_alert`),
  CONSTRAINT `fk_inventory_stock_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_inventory_stock_storage`
    FOREIGN KEY (`storage_id`) REFERENCES `storage_facilities` (`storage_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE `sensors_registry` (
  `sensor_id` INT AUTO_INCREMENT PRIMARY KEY,
  `sensor_category` ENUM('temperature','humidity','weight','moisture','gas') NOT NULL,
  `storage_id` INT DEFAULT NULL,
  `field_id` INT DEFAULT NULL,
  `installation_date` DATE NOT NULL,
  KEY `idx_sensors_registry_storage` (`storage_id`),
  KEY `idx_sensors_registry_field` (`field_id`),
  KEY `idx_sensors_registry_category` (`sensor_category`),
  CONSTRAINT `fk_sensors_registry_storage`
    FOREIGN KEY (`storage_id`) REFERENCES `storage_facilities` (`storage_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sensors_registry_field`
    FOREIGN KEY (`field_id`) REFERENCES `fields` (`field_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_sensors_registry_location`
    CHECK (`storage_id` IS NOT NULL OR `field_id` IS NOT NULL)
) ENGINE=InnoDB;

CREATE TABLE `sensor_telemetry_logs` (
  `log_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `sensor_id` INT NOT NULL,
  `recorded_value` DECIMAL(10,2) NOT NULL,
  `recorded_at` DATETIME NOT NULL,
  KEY `idx_sensor_telemetry_logs_sensor` (`sensor_id`),
  KEY `idx_sensor_telemetry_logs_recorded_at` (`recorded_at`),
  CONSTRAINT `fk_sensor_telemetry_logs_sensor`
    FOREIGN KEY (`sensor_id`) REFERENCES `sensors_registry` (`sensor_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `location_environment_status` (
  `status_id` INT AUTO_INCREMENT PRIMARY KEY,
  `storage_id` INT DEFAULT NULL,
  `field_id` INT DEFAULT NULL,
  `temperature_status` VARCHAR(80) NOT NULL,
  `humidity_status` VARCHAR(80) NOT NULL,
  `overall_condition` ENUM('secure','monitor','critical') NOT NULL,
  `last_evaluated` DATETIME NOT NULL,
  KEY `idx_location_environment_storage` (`storage_id`),
  KEY `idx_location_environment_field` (`field_id`),
  KEY `idx_location_environment_condition` (`overall_condition`),
  CONSTRAINT `fk_location_environment_storage`
    FOREIGN KEY (`storage_id`) REFERENCES `storage_facilities` (`storage_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_location_environment_field`
    FOREIGN KEY (`field_id`) REFERENCES `fields` (`field_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_location_environment_location`
    CHECK (`storage_id` IS NOT NULL OR `field_id` IS NOT NULL)
) ENGINE=InnoDB;

CREATE TABLE `quality_inspections` (
  `inspection_id` INT AUTO_INCREMENT PRIMARY KEY,
  `officer_id` INT NOT NULL,
  `stock_id` INT NOT NULL,
  `inspection_date` DATE NOT NULL,
  `spoilage_percentage` DECIMAL(5,2) NOT NULL,
  `product_condition` VARCHAR(80) NOT NULL,
  KEY `idx_quality_inspections_officer` (`officer_id`),
  KEY `idx_quality_inspections_stock` (`stock_id`),
  KEY `idx_quality_inspections_date` (`inspection_date`),
  CONSTRAINT `fk_quality_inspections_officer`
    FOREIGN KEY (`officer_id`) REFERENCES `qc_officers` (`officer_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_quality_inspections_stock`
    FOREIGN KEY (`stock_id`) REFERENCES `inventory_stock` (`stock_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Supporting authentication table (minimal app table)
CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','field_officer','inventory_manager','supplier','qc_officer','iot') NOT NULL,
  `supplier_id` INT DEFAULT NULL,
  `field_officer_id` INT DEFAULT NULL,
  `manager_id` INT DEFAULT NULL,
  `officer_id` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role`),
  CONSTRAINT `fk_users_supplier`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_users_field_officer`
    FOREIGN KEY (`field_officer_id`) REFERENCES `field_officers` (`field_officer_id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_users_manager`
    FOREIGN KEY (`manager_id`) REFERENCES `inventory_managers` (`manager_id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_users_qc_officer`
    FOREIGN KEY (`officer_id`) REFERENCES `qc_officers` (`officer_id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `field_officers` (`full_name`, `email`, `contact`) VALUES
('Kamal Hossain', 'kamal.hossain@agrimanage.local', '+880 1711-223344'),
('Nusrat Jahan', 'nusrat.jahan@agrimanage.local', '+880 1812-556677'),
('Farid Ahmed', 'farid.ahmed@agrimanage.local', '+880 1913-778899'),
('Salma Akter', 'salma.akter@agrimanage.local', '+880 1614-334455'),
('Imran Kabir', 'imran.kabir@agrimanage.local', '+880 1515-998811');

INSERT INTO `farmers` (`farmer_name`, `contact_number`, `availability`) VALUES
('Rahim Uddin', '+880 1700-000101', 0),
('Abdul Karim', '+880 1700-000102', 1),
('Mita Begum', '+880 1700-000103', 1),
('Hasan Ali', '+880 1700-000104', 1),
('Farzana Yasmin', '+880 1700-000105', 0),
('Rafiq Islam', '+880 1700-000106', 1),
('Tanvir Hasan', '+880 1700-000107', 1),
('Jannat Ara', '+880 1700-000108', 1),
('Nayeem Chowdhury', '+880 1700-000109', 0),
('Sabiha Noor', '+880 1700-000110', 1);

INSERT INTO `products` (`product_name`, `category`, `unit_of_measurement`, `base_shelf_life_days`, `optimal_temp_min`, `optimal_temp_max`) VALUES
('Organic Hass Avocados', 'Fruits', 'kg', 14, 3.00, 7.00),
('Russet Potatoes', 'Vegetables', 't', 120, 7.00, 10.00),
('Raw Cow Milk', 'Dairy', 'L', 5, 1.00, 4.00),
('Organic Tomato', 'Vegetables', 'kg', 12, 6.00, 9.00),
('Hybrid Rice Seed', 'Seeds', 'kg', 365, 12.00, 24.00),
('Nitrogen Fertilizer N-20', 'Materials', 'kg', 730, 10.00, 25.00),
('Organic Pesticide', 'Inputs', 'L', 540, 8.00, 30.00),
('Red Chili Peppers', 'Vegetables', 'kg', 20, 5.00, 10.00),
('Fresh Bananas', 'Fruits', 'kg', 10, 12.00, 15.00),
('Onions', 'Vegetables', 'kg', 90, 2.00, 5.00),
('Cucumber', 'Vegetables', 'kg', 8, 5.00, 8.00),
('Pumpkin', 'Vegetables', 'kg', 30, 10.00, 14.00),
('Sweet Corn', 'Vegetables', 'kg', 7, 2.00, 6.00),
('Mango', 'Fruits', 'kg', 16, 10.00, 13.00),
('Ginger Roots', 'Spices', 'kg', 180, 12.00, 18.00);

INSERT INTO `suppliers` (`company_name`, `email`, `contact`) VALUES
('Apex AgriChemicals Ltd.', 'orders@apexagri.local', '+880 1711-000001'),
('Green Valley Seedlings', 'supply@greenvalley.local', '+880 1812-000002'),
('Northern Dairy Link', 'sales@northerndairy.local', '+880 1913-000003'),
('Fresh Inputs Traders', 'procurement@freshinputs.local', '+880 1614-000004'),
('Delta Storage Goods', 'support@deltastorage.local', '+880 1515-000005');

INSERT INTO `inventory_managers` (`name`, `email`, `contact`) VALUES
('Tariq Rahman', 'tariq.rahman@agrimanage.local', '+880 1722-112233'),
('Salma Akter', 'salma.storage@agrimanage.local', '+880 1833-445566'),
('Imtiaz Karim', 'imtiaz.karim@agrimanage.local', '+880 1944-778899'),
('Nadira Sultana', 'nadira.sultana@agrimanage.local', '+880 1655-889900');

INSERT INTO `qc_officers` (`name`, `email`, `contact`) VALUES
('Dr. Amina Rahman', 'amina.rahman@agrimanage.local', '+880 1744-998877'),
('Mr. Hasan Tariq', 'hasan.tariq@agrimanage.local', '+880 1855-667788'),
('Ms. Ruba Ahmed', 'ruba.ahmed@agrimanage.local', '+880 1966-334455'),
('Dr. S. Chowdhury', 's.chowdhury@agrimanage.local', '+880 1577-223344');

INSERT INTO `fields` (`farmer_id`, `field_officer_id`, `field_type`, `planting_date`, `target_harvest_date`) VALUES
(1, 1, 'Potato Field - Vegetation', '2026-01-10', '2026-04-20'),
(2, 1, 'Rice Paddy', '2025-11-01', '2026-03-30'),
(3, 2, 'Tomato Plot', '2026-02-05', '2026-05-10'),
(4, 2, 'Chili Field', '2026-01-18', '2026-04-18'),
(5, 3, 'Mango Orchard', '2020-06-10', '2026-06-15'),
(6, 3, 'Banana Plantation', '2024-08-01', '2026-07-01'),
(7, 4, 'Onion Field', '2026-01-25', '2026-04-28'),
(8, 4, 'Cucumber Zone', '2026-02-15', '2026-05-20'),
(9, 5, 'Pumpkin Bed', '2026-01-12', '2026-04-25'),
(10, 5, 'Corn Block', '2026-01-05', '2026-05-05'),
(2, 2, 'Spinach Patch', '2026-03-01', '2026-04-10'),
(7, 1, 'Ginger Patch', '2025-12-15', '2026-05-30');

INSERT INTO `harvest_logs` (`field_id`, `harvested_percentage`, `harvested_date`, `collected_weight`) VALUES
(1, 45.00, '2026-03-01', 1100.00),
(1, 72.00, '2026-03-20', 1780.00),
(2, 100.00, '2026-03-29', 2400.00),
(3, 30.00, '2026-03-25', 300.00),
(4, 65.00, '2026-03-27', 780.00),
(5, 20.00, '2026-03-22', 520.00),
(6, 55.00, '2026-03-23', 1300.00),
(7, 75.00, '2026-03-26', 900.00),
(8, 25.00, '2026-03-28', 260.00),
(9, 40.00, '2026-03-24', 640.00),
(10, 35.00, '2026-03-21', 700.00),
(11, 85.00, '2026-04-09', 420.00),
(12, 50.00, '2026-03-30', 390.00),
(3, 60.00, '2026-04-10', 640.00),
(7, 100.00, '2026-04-11', 1180.00);

INSERT INTO `input_requests` (`field_officer_id`, `farmer_id`, `product_id`, `required_quantity`, `fulfillment_status`) VALUES
(1, 1, 6, 200.00, 'pending'),
(1, 2, 7, 50.00, 'fulfilled'),
(2, 3, 5, 120.00, 'approved'),
(2, 4, 7, 80.00, 'rejected'),
(3, 5, 6, 300.00, 'pending'),
(3, 6, 5, 90.00, 'fulfilled'),
(4, 7, 8, 60.00, 'pending'),
(4, 8, 11, 40.00, 'approved'),
(5, 9, 12, 70.00, 'pending'),
(5, 10, 13, 55.00, 'fulfilled'),
(1, 2, 15, 35.00, 'pending'),
(2, 3, 4, 45.00, 'approved'),
(3, 6, 9, 85.00, 'pending'),
(4, 8, 10, 100.00, 'fulfilled'),
(5, 9, 6, 250.00, 'rejected');

INSERT INTO `purchase_orders` (`supplier_id`, `order_type`, `order_date`, `target_delivery_date`, `delivered_date`, `status`) VALUES
(1, 'Bulk Fertilizer', '2026-03-10', '2026-03-16', '2026-03-16', 'delivered'),
(2, 'Organic Seeds', '2026-03-12', '2026-03-20', NULL, 'processing'),
(3, 'Dairy Cooling Supplies', '2026-03-08', '2026-03-14', '2026-03-15', 'delivered'),
(4, 'Pesticide Refill', '2026-03-18', '2026-03-25', NULL, 'pending'),
(5, 'Storage Crates', '2026-03-01', '2026-03-12', '2026-03-12', 'delivered'),
(1, 'NPK Fertilizer', '2026-03-22', '2026-03-30', NULL, 'processing'),
(2, 'Seedlings Batch B', '2026-03-24', '2026-04-02', NULL, 'pending'),
(4, 'Bio Inputs', '2026-03-28', '2026-04-05', NULL, 'cancelled'),
(3, 'Milk Testing Kit', '2026-03-26', '2026-04-01', NULL, 'processing'),
(5, 'Warehouse Pallets', '2026-03-29', '2026-04-06', NULL, 'pending');

INSERT INTO `order_line_items` (`order_id`, `product_id`, `quantity`) VALUES
(1, 6, 3000.00),
(1, 7, 200.00),
(1, 5, 500.00),
(2, 5, 700.00),
(2, 14, 300.00),
(3, 3, 800.00),
(3, 10, 1200.00),
(4, 7, 250.00),
(4, 6, 1500.00),
(5, 2, 4000.00),
(5, 1, 2000.00),
(6, 6, 3500.00),
(6, 7, 350.00),
(7, 5, 900.00),
(7, 11, 600.00),
(8, 7, 500.00),
(8, 15, 700.00),
(9, 3, 400.00),
(10, 2, 2500.00),
(10, 10, 1800.00);

INSERT INTO `storage_facilities` (`manager_id`, `storage_name`, `storage_size`, `storage_type`, `storage_condition`, `capacity`) VALUES
(1, 'Cold Storage Alpha', 'large', 'Refrigerated', 'good', 50000.00),
(2, 'Dry Silo Beta', 'medium', 'Ambient Dry', 'monitor', 20000.00),
(1, 'Cold Room Gamma', 'small', 'Refrigerated', 'good', 8000.00),
(3, 'Input Warehouse Delta', 'medium', 'Chemical Storage', 'secure', 15000.00),
(4, 'Dairy Chiller Epsilon', 'small', 'Refrigerated', 'monitor', 6000.00),
(2, 'Grain Depot Zeta', 'large', 'Ambient Dry', 'secure', 45000.00);

INSERT INTO `inventory_stock` (`product_id`, `storage_id`, `current_quantity`, `minimum_threshold_alert`) VALUES
(1, 1, 12500.00, 9000.00),
(2, 2, 18000.00, 5000.00),
(3, 5, 150.00, 500.00),
(4, 1, 2200.00, 1200.00),
(5, 6, 4200.00, 1000.00),
(6, 4, 9000.00, 2000.00),
(7, 4, 300.00, 250.00),
(8, 1, 980.00, 500.00),
(9, 1, 760.00, 600.00),
(10, 2, 2100.00, 1500.00),
(11, 3, 420.00, 450.00),
(12, 2, 660.00, 400.00),
(13, 3, 280.00, 300.00),
(14, 1, 1400.00, 700.00),
(15, 6, 2500.00, 1200.00),
(5, 4, 800.00, 300.00),
(6, 2, 4500.00, 2500.00),
(3, 1, 320.00, 400.00),
(2, 6, 12500.00, 7000.00),
(10, 6, 5400.00, 2200.00);

INSERT INTO `sensors_registry` (`sensor_category`, `storage_id`, `field_id`, `installation_date`) VALUES
('temperature', 1, NULL, '2026-01-10'),
('humidity', 1, NULL, '2026-01-10'),
('humidity', 2, NULL, '2026-01-12'),
('moisture', NULL, 1, '2026-01-15'),
('temperature', NULL, 7, '2026-01-16'),
('gas', 4, NULL, '2026-01-18'),
('weight', NULL, 10, '2026-01-20'),
('temperature', 5, NULL, '2026-01-22'),
('humidity', NULL, 3, '2026-01-24'),
('temperature', 3, NULL, '2026-01-26');

INSERT INTO `location_environment_status` (`storage_id`, `field_id`, `temperature_status`, `humidity_status`, `overall_condition`, `last_evaluated`) VALUES
(1, NULL, 'optimal', 'optimal', 'secure', '2026-04-11 10:10:00'),
(2, NULL, 'elevated', 'high', 'monitor', '2026-04-11 10:11:00'),
(5, NULL, 'elevated', 'normal', 'monitor', '2026-04-11 10:12:00'),
(4, NULL, 'optimal', 'normal', 'secure', '2026-04-11 10:13:00'),
(NULL, 1, 'elevated', 'normal', 'monitor', '2026-04-11 10:14:00'),
(NULL, 3, 'normal', 'high', 'monitor', '2026-04-11 10:15:00'),
(NULL, 7, 'high', 'normal', 'critical', '2026-04-11 10:16:00'),
(NULL, 10, 'normal', 'normal', 'secure', '2026-04-11 10:17:00'),
(3, NULL, 'optimal', 'optimal', 'secure', '2026-04-11 10:18:00'),
(NULL, 8, 'high', 'high', 'critical', '2026-04-11 10:19:00');

INSERT INTO `quality_inspections` (`officer_id`, `stock_id`, `inspection_date`, `spoilage_percentage`, `product_condition`) VALUES
(1, 1, '2026-03-20', 1.20, 'excellent'),
(2, 2, '2026-03-21', 14.50, 'degraded'),
(1, 3, '2026-03-22', 0.20, 'acceptable'),
(3, 4, '2026-03-23', 2.40, 'acceptable'),
(4, 5, '2026-03-24', 0.00, 'excellent'),
(1, 6, '2026-03-25', 4.60, 'acceptable'),
(2, 7, '2026-03-26', 7.80, 'degraded'),
(3, 8, '2026-03-27', 1.10, 'excellent'),
(4, 9, '2026-03-28', 3.30, 'acceptable'),
(1, 10, '2026-03-29', 5.20, 'degraded'),
(2, 11, '2026-03-30', 12.00, 'rejected'),
(3, 12, '2026-04-01', 2.20, 'acceptable'),
(4, 13, '2026-04-02', 9.30, 'degraded'),
(1, 18, '2026-04-03', 15.40, 'rejected'),
(2, 19, '2026-04-04', 1.70, 'excellent');

-- Demo users (password for all accounts: password)
INSERT INTO `users` (`username`, `password_hash`, `role`, `supplier_id`, `field_officer_id`, `manager_id`, `officer_id`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, NULL, NULL),
('field_officer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'field_officer', NULL, 1, NULL, NULL),
('inventory_manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inventory_manager', NULL, NULL, 1, NULL),
('supplier_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supplier', 1, NULL, NULL, NULL),
('qc_officer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'qc_officer', NULL, NULL, NULL, 1),
('iot_user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'iot', NULL, NULL, NULL, NULL);

-- Generate 130 telemetry logs (>= 120 required)
DELIMITER //
CREATE PROCEDURE `seed_sensor_telemetry`()
BEGIN
  DECLARE i INT DEFAULT 0;
  DECLARE sid INT;

  WHILE i < 130 DO
    SET sid = (i MOD 10) + 1;

    INSERT INTO `sensor_telemetry_logs` (`sensor_id`, `recorded_value`, `recorded_at`)
    VALUES (
      sid,
      CASE sid
        WHEN 1 THEN ROUND(3.80 + (i MOD 5) * 0.40, 2)
        WHEN 2 THEN ROUND(76.00 + (i MOD 9) * 1.10, 2)
        WHEN 3 THEN ROUND(68.00 + (i MOD 15) * 1.30, 2)
        WHEN 4 THEN ROUND(35.00 + (i MOD 20) * 1.80, 2)
        WHEN 5 THEN ROUND(24.00 + (i MOD 8) * 0.90, 2)
        WHEN 6 THEN ROUND(0.30 + (i MOD 6) * 0.10, 2)
        WHEN 7 THEN ROUND(180.00 + (i MOD 12) * 6.50, 2)
        WHEN 8 THEN ROUND(5.00 + (i MOD 7) * 0.60, 2)
        WHEN 9 THEN ROUND(72.00 + (i MOD 10) * 1.00, 2)
        ELSE ROUND(4.50 + (i MOD 6) * 0.70, 2)
      END,
      DATE_SUB(NOW(), INTERVAL (130 - i) MINUTE)
    );

    SET i = i + 1;
  END WHILE;
END //
DELIMITER ;

CALL `seed_sensor_telemetry`();
DROP PROCEDURE `seed_sensor_telemetry`;
