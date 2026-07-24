<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Print History';
$breadcrumbs = ['Exam Officer Dashboard' => url('dashboard/exam_officer/index.php'), 'Print History' => ''];
$moduleName = 'Print Audit History';
$plannedVersion = 'v0.9';
$moduleDescription = 'Track and audit all print operations, including who printed, when, and how many times.';
$progressPercent = 0;
$backUrl = url('dashboard/exam_officer/index.php');
$backLabel = '← Back to Dashboard';
requireRole('exam_officer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
