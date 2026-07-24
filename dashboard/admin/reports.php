<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Reports';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'Reports' => ''];
$moduleName = 'Analytics & Reports';
$plannedVersion = 'v0.9';
$moduleDescription = 'Generate comprehensive system and academic reports with export and visualization capabilities.';
$progressPercent = 0;
$backUrl = url('dashboard/admin/index.php');
$backLabel = '← Back to Admin Dashboard';
requireRole('admin');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
?>
