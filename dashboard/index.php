<?php
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/auth_helper.php';

requireAuth();

$user = currentUser();
$targetDashboard = getRoleDashboardPath($user['role']);

header("Location: " . url($targetDashboard));
exit;