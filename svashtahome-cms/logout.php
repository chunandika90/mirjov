<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/auth.php';
logout();
header('Location: login.php');
exit;
