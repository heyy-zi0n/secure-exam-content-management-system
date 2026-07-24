<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Archive';
$breadcrumbs = ['Exam Officer Dashboard' => url('dashboard/exam_officer/index.php'), 'Archive' => ''];
$moduleName = 'Exam Paper Archive';
$plannedVersion = 'v0.9';
$moduleDescription = 'Archive old exam papers, store securely, and retrieve historical records.';
$progressPercent = 0;
$backUrl = url('dashboard/exam_officer/index.php');
$backLabel = '← Back to Dashboard';
requireRole('exam_officer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
