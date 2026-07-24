<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Paper Details';
$breadcrumbs = ['Lecturer Workspace' => url('dashboard/lecturer/index.php'), 'Submissions' => url('dashboard/lecturer/submissions.php'), 'Paper Details' => ''];
$moduleName = 'Paper Versions & Moderation';
$plannedVersion = 'v0.7';
$moduleDescription = 'View paper versions, track moderation status, and upload revised exam papers.';
$progressPercent = 0;
$backUrl = url('dashboard/lecturer/submissions.php');
$backLabel = '← Back to Submissions';
requireRole('lecturer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
