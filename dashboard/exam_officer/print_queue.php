<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Printing Queue';
$breadcrumbs = ['Exam Officer Dashboard' => url('dashboard/exam_officer/index.php'), 'Printing Queue' => ''];
$moduleName = 'Secure Printing Queue';
$plannedVersion = 'v0.9';
$moduleDescription = 'Manage print queues, track print status, and initiate secure printing of exam papers.';
$progressPercent = 0;
$backUrl = url('dashboard/exam_officer/index.php');
$backLabel = '← Back to Dashboard';
requireRole('exam_officer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
