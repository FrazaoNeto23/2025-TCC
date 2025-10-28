<?php
session_start();
session_destroy();
header("Location: index.php"); // ✅ não precisa de barra /
exit;
?>