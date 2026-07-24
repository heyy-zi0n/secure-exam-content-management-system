<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Role Delegations';
$breadcrumbs = ['HOD Workspace' => url('dashboard/hod/index.php'), 'Delegations' => ''];
$moduleName = 'HOD Role Delegation';
$plannedVersion = 'v0.9';
$moduleDescription = 'Temporarily delegate your HOD responsibilities to a trusted colleague with fine-grained permissions.';
$progressPercent = 0;
$backUrl = url('dashboard/hod/index.php');
$backLabel = '← Back to HOD Workspace';
requireRole('hod');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
?>
