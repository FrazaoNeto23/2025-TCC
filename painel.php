<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel do Dono</title>
    <link rel="stylesheet" href="css/styles_painel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <h1>Bem-vindo, <?php echo $_SESSION['usuario']; ?>!</h1>

    <div class="dashboard-container">
        <a href="cardapio.php" class="dashboard-card">
            <i class="fa fa-utensils fa-2x"></i>
            <span>Gerenciar Cardápio</span>
        </a>
        <a href="#" class="dashboard-card">
            <i class="fa fa-users fa-2x"></i>
            <span>Gerenciar Funcionários</span>
        </a>
        <a href="#" class="dashboard-card">
            <i class="fa fa-receipt fa-2x"></i>
            <span>Ver Pedidos</span>
        </a>
        <a href="logout.php" class="dashboard-card logout">
            <i class="fa fa-right-from-bracket fa-2x"></i>
            <span>Sair</span>
        </a>
    </div>
</body>
</html>
