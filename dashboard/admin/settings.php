<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'System Settings';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'System Settings' => ''];
$moduleName = 'System Settings';
$plannedVersion = 'v1.0';
$moduleDescription = 'Configure global system parameters, user roles, and institutional preferences.';
$progressPercent = 0;
$backUrl = url('dashboard/admin/index.php');
$backLabel = '← Back to Admin Dashboard';
requireRole('admin');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
?>
