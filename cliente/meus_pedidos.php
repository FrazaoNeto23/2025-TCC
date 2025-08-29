<?php
session_start();
include "config.php";

if (!isset($_SESSION['cliente_id'])) {
    header("Location: login.php");
    exit;
}

$cliente_id = $_SESSION['cliente_id'];

// Buscar pedidos do cliente
$sql = "SELECT p.id, p.data, p.status, p.total 
        FROM pedidos p 
        WHERE p.cliente_id = $cliente_id 
        ORDER BY p.data DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Meus Pedidos</title>
    <link rel="stylesheet" href="css/cliente.css">
</head>
<body>
    <div class="login-container">
        <h2>🛒 Meus Pedidos</h2>

        <?php if ($result->num_rows > 0): ?>
            <div class="pedidos-container">
                <?php while($pedido = $result->fetch_assoc()): ?>
                    <div class="pedido-card">
                        <h3>Pedido #<?php echo $pedido['id']; ?></h3>
                        <p><strong>Data:</strong> <?php echo date("d/m/Y H:i", strtotime($pedido['data'])); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="status <?php echo strtolower($pedido['status']); ?>">
                                <?php echo ucfirst($pedido['status']); ?>
                            </span>
                        </p>
                        <p><strong>Total:</strong> R$ <?php echo number_format($pedido['total'], 2, ',', '.'); ?></p>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>Você ainda não fez nenhum pedido.</p>
        <?php endif; ?>

        <p><a href="painel.php">⬅ Voltar ao Painel</a></p>
    </div>
</body>
</html>
