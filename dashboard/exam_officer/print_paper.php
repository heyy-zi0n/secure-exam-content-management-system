<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Print Paper';
$breadcrumbs = ['Exam Officer Dashboard' => url('dashboard/exam_officer/index.php'), 'Print Queue' => url('dashboard/exam_officer/print_queue.php'), 'Print Paper' => ''];
$moduleName = 'Secure Print Stream';
$plannedVersion = 'v0.9';
$moduleDescription = 'Stream decrypted exam papers to the printer in a secure, auditable way one day before the exam.';
$progressPercent = 0;
$backUrl = url('dashboard/exam_officer/print_queue.php');
$backLabel = '← Back to Print Queue';
requireRole('exam_officer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
