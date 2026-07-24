<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Audit Logs';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'Audit Logs' => ''];
$moduleName = 'Audit Trail';
$plannedVersion = 'v0.8';
$moduleDescription = 'Full audit log of all system activities with filters and export capabilities.';
$progressPercent = 0;
$backUrl = url('dashboard/admin/index.php');
$backLabel = '← Back to Admin Dashboard';
requireRole('admin');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
?>
