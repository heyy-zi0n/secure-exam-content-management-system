<?php
require_once __DIR__ . '/../includes/auth.php';
$pageTitle = 'Secure PDF Stream';
$breadcrumbs = ['Dashboard' => url('dashboard/index.php')];
$moduleName = 'Secure Document Stream';
$plannedVersion = 'v0.7';
$moduleDescription = 'Stream decrypted exam papers to the secure viewer with audit logging.';
$progressPercent = 0;
$backUrl = url('dashboard/index.php');
$backLabel = '← Back to Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/coming_soon.php';
require_once __DIR__ . '/../includes/footer.php';
