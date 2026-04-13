<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    redirect('/agri_inventory_management/auth/login.php');
}

redirect('/agri_inventory_management/modules/dashboard.php');
