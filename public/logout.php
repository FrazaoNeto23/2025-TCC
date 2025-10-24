<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
session_destroy();
header("Location: index.php");
exit;
?>
