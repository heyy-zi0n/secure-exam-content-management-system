<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Notifications';
$breadcrumbs = ['Lecturer Workspace' => url('dashboard/lecturer/index.php'), 'Notifications' => ''];
$moduleName = 'In-App Notifications';
$plannedVersion = 'v1.0';
$moduleDescription = 'Receive real-time alerts and notifications for paper submissions, approvals, and system updates.';
$progressPercent = 0;
$backUrl = url('dashboard/lecturer/index.php');
$backLabel = '← Back to Lecturer Workspace';
requireRole('lecturer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
