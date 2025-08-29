<?php
session_start();
include "config.php";

if (!isset($_SESSION['cliente_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Cliente</title>
    <link rel="stylesheet" href="css/cliente.css">
</head>
<body>
    <div class="login-container">
        <h2>Bem-vindo, <?php echo $_SESSION['cliente_nome']; ?> 👋</h2>
        <p>Aqui você pode ver o cardápio e fazer seus pedidos.</p>

        <p><a href="../cardapio.php">📋 Ver Cardápio</a></p>
        <p><a href="meus_pedidos.php">🛒 Meus Pedidos</a></p>
        <p><a href="logout.php">🚪 Sair</a></p>
    </div>
</body>
</html>
