<?php
require_once __DIR__ . '/../../includes/auth.php';
$pageTitle = 'Pending Papers';
$breadcrumbs = ['Moderator Dashboard' => url('dashboard/moderator/index.php'), 'Pending Papers' => ''];
$moduleName = 'Paper Vetting & Moderation';
$plannedVersion = 'v0.7';
$moduleDescription = 'Review and moderate examination papers, provide feedback, and approve for lockdown.';
$progressPercent = 0;
$backUrl = url('dashboard/moderator/index.php');
$backLabel = '← Back to Dashboard';
requireRole('moderator');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/coming_soon.php';
require_once __DIR__ . '/../../includes/footer.php';
