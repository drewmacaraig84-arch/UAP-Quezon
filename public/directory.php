<?php
require_once __DIR__ . '/../includes/config.php';
header('Location: ' . BASE_URL . '/public/architects.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;

