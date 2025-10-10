<?php
$host = "localhost:3307";
$user = "root"; 
$pass = "";
$db   = "burger_house";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
?>
