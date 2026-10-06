<?php
$prefix = (strpos($_SERVER['REQUEST_URI'] ?? '', '/gtm-tracker') === 0) ? '/gtm-tracker' : '';
header('Location: ' . $prefix . '/login.php');
exit;
