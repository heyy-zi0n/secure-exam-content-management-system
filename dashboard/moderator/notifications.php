<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Notifications';
$breadcrumbs = ['Moderator Dashboard' => url('dashboard/moderator/index.php'), 'Notifications' => ''];
$moduleName = 'In-App Notifications';
$plannedVersion = 'v1.0';
$moduleDescription = 'Receive real-time alerts and notifications for paper submissions, approvals, and system updates.';
$progressPercent = 0;
$backUrl = url('dashboard/moderator/index.php');
$backLabel = '← Back to Dashboard';
requireRole('moderator');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
