<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Reports & Analytics';
$breadcrumbs = ['HOD Workspace' => url('dashboard/hod/index.php'), 'Reports' => ''];
$moduleName = 'Departmental Analytics';
$plannedVersion = 'v0.9';
$moduleDescription = 'Generate detailed reports on lecturer activity, moderator performance, and system audit trails.';
$progressPercent = 0;
$backUrl = url('dashboard/hod/index.php');
$backLabel = '← Back to HOD Workspace';
requireRole('hod');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
?>
