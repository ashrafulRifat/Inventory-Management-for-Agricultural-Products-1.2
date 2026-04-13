<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

logout_user();

redirect('/agri_inventory_management/auth/login.php');
