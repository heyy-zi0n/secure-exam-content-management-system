<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Secure Viewer';
$breadcrumbs = ['Dashboard' => url('dashboard/index.php')];
$moduleName = 'Secure Document Viewer';
$plannedVersion = 'v0.7';
$moduleDescription = 'View exam papers in a secure, watermarked viewer with copy/print protection.';
$progressPercent = 0;
$backUrl = url('dashboard/index.php');
$backLabel = '← Back to Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/coming_soon.php';
require_once __DIR__ . '/../includes/footer.php';
