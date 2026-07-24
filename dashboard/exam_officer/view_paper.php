<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'View Paper';
$breadcrumbs = ['Exam Officer Dashboard' => url('dashboard/exam_officer/index.php'), 'Print Queue' => url('dashboard/exam_officer/print_queue.php'), 'View Paper' => ''];
$moduleName = 'Secure Paper Preview';
$plannedVersion = 'v0.9';
$moduleDescription = 'Preview locked exam papers in a secure viewer one day before the exam date.';
$progressPercent = 0;
$backUrl = url('dashboard/exam_officer/print_queue.php');
$backLabel = '← Back to Print Queue';
requireRole('exam_officer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
