<?php
$host = "localhost:3306";
$user = "root"; 
$pass = "";
$db   = "burger_house";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
?>
