<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Departments';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'Departments' => ''];
$moduleName = 'Department Management';
$plannedVersion = 'v1.0';
$moduleDescription = 'Manage academic departments, configure departmental settings, and view departmental statistics.';
$progressPercent = 0;
$backUrl = url('dashboard/admin/index.php');
$backLabel = '← Back to Admin Dashboard';
requireRole('admin');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
?>
