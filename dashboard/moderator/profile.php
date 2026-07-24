<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Profile';
$breadcrumbs = ['Moderator Dashboard' => url('dashboard/moderator/index.php'), 'Profile' => ''];
$moduleName = 'User Profile & Settings';
$plannedVersion = 'v1.0';
$moduleDescription = 'View and edit your personal information, change password, and manage account settings.';
$progressPercent = 0;
$backUrl = url('dashboard/moderator/index.php');
$backLabel = '← Back to Dashboard';
requireRole('moderator');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
