<?php
$host = "localhost";
$user = "root"; 
$pass = "";
$db   = "burger_house";
$port = 3307;

$conn = new mysqli($host, $user, $pass, $db, $port);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
?>