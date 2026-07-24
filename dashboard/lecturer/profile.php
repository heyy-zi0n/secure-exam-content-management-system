<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Profile';
$breadcrumbs = ['Lecturer Workspace' => url('dashboard/lecturer/index.php'), 'Profile' => ''];
$moduleName = 'User Profile & Settings';
$plannedVersion = 'v1.0';
$moduleDescription = 'View and edit your personal information, change password, and manage account settings.';
$progressPercent = 0;
$backUrl = url('dashboard/lecturer/index.php');
$backLabel = '← Back to Lecturer Workspace';
requireRole('lecturer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
