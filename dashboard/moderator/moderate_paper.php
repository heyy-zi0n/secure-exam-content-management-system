<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Moderate Paper';
$breadcrumbs = ['Moderator Dashboard' => url('dashboard/moderator/index.php'), 'Pending Papers' => url('dashboard/moderator/vetting.php'), 'Moderate Paper' => ''];
$moduleName = 'Paper Moderation Workflow';
$plannedVersion = 'v0.7';
$moduleDescription = 'View paper details, provide feedback, request corrections, and approve for lockdown.';
$progressPercent = 0;
$backUrl = url('dashboard/moderator/vetting.php');
$backLabel = '← Back to Pending Papers';
requireRole('moderator');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
