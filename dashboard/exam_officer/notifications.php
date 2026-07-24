<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Notifications';
$breadcrumbs = ['Exam Officer Dashboard' => url('dashboard/exam_officer/index.php'), 'Notifications' => ''];
$moduleName = 'In-App Notifications';
$plannedVersion = 'v1.0';
$moduleDescription = 'Receive real-time alerts and notifications for paper unlocks, print jobs, and system updates.';
$progressPercent = 0;
$backUrl = url('dashboard/exam_officer/index.php');
$backLabel = '← Back to Dashboard';
requireRole('exam_officer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
