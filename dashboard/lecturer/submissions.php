<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Examination Submissions';
$breadcrumbs = ['Lecturer Workspace' => url('dashboard/lecturer/index.php'), 'Submissions' => ''];
$moduleName = 'Examination Paper Submissions';
$plannedVersion = 'v0.7';
$moduleDescription = 'Upload and manage examination question papers, track moderation status, and view paper versions.';
$progressPercent = 0;
$backUrl = url('dashboard/lecturer/index.php');
$backLabel = '← Back to Lecturer Workspace';
requireRole('lecturer');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
