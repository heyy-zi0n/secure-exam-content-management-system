<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Security Logs';
$breadcrumbs = ['Admin Dashboard' => url('dashboard/admin/index.php'), 'Security Logs' => ''];
$moduleName = 'Security Logs';
$plannedVersion = 'v0.8';
$moduleDescription = 'Monitor security events, login attempts, and system anomalies with real-time alerts.';
$progressPercent = 0;
$backUrl = url('dashboard/admin/index.php');
$backLabel = '← Back to Admin Dashboard';
requireRole('admin');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
?>
