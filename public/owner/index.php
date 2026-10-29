<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth_check.php';
checkAuth('owner');
header('Location: ' . url('owner/dashboard.php'));
exit;
