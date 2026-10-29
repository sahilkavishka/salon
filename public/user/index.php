<?php
session_start();
require_once __DIR__ . '/../../config.php';
header('Location: ' . url('user/salon_view.php'));
exit;
