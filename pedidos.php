<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] !== 'dono') {
    header("Location: index.php");
    exit;
}

// Atualizar status do pedido
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == "atualizar") {
    $id = intval($_POST['id']);
    $status = $_POST['status'];

    $sql = "UPDATE pedidos SET status=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    header("Location: pedidos.php");
    exit;
}

// Listar pedidos
$result = $conn->query("SELECT * FROM pedidos ORDER BY data DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Pedidos</title>
    <link rel="stylesheet" href="css/style_pedidos.css?e=<?php echo rand(0,10000)?>">
</head>
<body>
    <h1>Gerenciar Pedidos</h1>

    <div class="pedidos-container">
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="card-pedido">
                <div class="card-info">
                    <h3>Pedido #<?= $row['id'] ?></h3>
                    <p><strong>Cliente:</strong> <?= htmlspecialchars($row['cliente_nome']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($row['cliente_email']) ?></p>
                    <p><strong>Itens:</strong> <?= nl2br(htmlspecialchars($row['itens'])) ?></p>
                    <p><strong>Total:</strong> R$ <?= number_format($row['valor_total'], 2, ',', '.') ?></p>
                    <p><strong>Status:</strong> <?= ucfirst($row['status']) ?></p>
                    <p><small><em><?= date("d/m/Y H:i", strtotime($row['data_pedido'])) ?></em></small></p>
                </div>

                <form method="POST" class="form-status">
                    <input type="hidden" name="acao" value="atualizar">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <select name="status" required>
                        <option value="pendente" <?= $row['status']=="pendente"?"selected":"" ?>>Pendente</option>
                        <option value="em preparo" <?= $row['status']=="em preparo"?"selected":"" ?>>Em Preparo</option>
                        <option value="entregue" <?= $row['status']=="entregue"?"selected":"" ?>>Entregue</option>
                    </select>
                    <button type="submit">Atualizar</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>

    <p><a href="painel.php">⬅ Voltar ao Painel</a></p>
</body>
</html>
