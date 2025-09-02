<?php
session_start();
include "config.php";

// Verifica se usuário está logado
if (!isset($_SESSION['usuario'])) {
    header("Location: acesso.php");
    exit;
}

// Atualizar status via GET
if(isset($_GET['update_status'], $_GET['id'])){
    $novo_status = $_GET['update_status'];
    $id_pedido = intval($_GET['id']);
    $stmt = $conn->prepare("UPDATE pedidos SET status=? WHERE id=?");
    $stmt->bind_param("si", $novo_status, $id_pedido);
    $stmt->execute();
    header("Location: pedidos.php");
    exit;
}

// Limpar pedidos com mais de 15 minutos
if(isset($_POST['limpar_antigos'])){
    $stmt = $conn->prepare("DELETE FROM pedidos WHERE data < (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute();
    header("Location: pedidos.php");
    exit;
}

// Busca pedidos
$pedidos = $conn->query("
    SELECT pedidos.id, pedidos.id_cliente, pedidos.id_produto, pedidos.quantidade, pedidos.total, pedidos.data, pedidos.status,
           produtos.nome AS produto_nome
    FROM pedidos
    JOIN produtos ON pedidos.id_produto = produtos.id
    ORDER BY pedidos.data DESC
");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Pedidos</title>
<link rel="stylesheet" href="css/pedidos.css?e=<?php echo rand(0,10000)?>">
</head>
<body>
<h1>Lista de Pedidos</h1>

<!-- Botão para limpar pedidos antigos -->
<form method="post" style="text-align:center; margin-bottom:20px;">
    <button type="submit" name="limpar_antigos" class="btn-limpar" onclick="return confirm('Tem certeza que deseja limpar os pedidos com mais de 15 minutos?')">Limpar pedidos com mais de 15 minutos</button>
</form>

<table>
    <tr>
        <th>ID</th>
        <th>Cliente</th>
        <th>Produto</th>
        <th>Quantidade</th>
        <th>Total</th>
        <th>Data</th>
        <th>Status</th>
        <th>Ações</th>
    </tr>

    <?php while ($pedido = $pedidos->fetch_assoc()): 
        $status_class = '';
        if($pedido['status'] == 'Em preparo') $status_class = 'status-preparo';
        elseif($pedido['status'] == 'Em produção') $status_class = 'status-producao';
        elseif($pedido['status'] == 'Entregando') $status_class = 'status-entregando';
    ?>
    <tr class="<?= $status_class ?>">
        <td><?= $pedido['id'] ?></td>
        <td><?= $pedido['id_cliente'] ?></td>
        <td><?= $pedido['produto_nome'] ?></td>
        <td><?= $pedido['quantidade'] ?></td>
        <td>R$ <?= number_format($pedido['total'], 2, ',', '.') ?></td>
        <td><?= $pedido['data'] ?></td>
        <td class="badge"><span class="badge-text"><?= $pedido['status'] ?></span></td>
        <td>
            <a href="pedidos.php?update_status=Em preparo&id=<?= $pedido['id'] ?>" class="btn-preparo">Em preparo</a>
            <a href="pedidos.php?update_status=Em produção&id=<?= $pedido['id'] ?>" class="btn-producao">Em produção</a>
            <a href="pedidos.php?update_status=Entregando&id=<?= $pedido['id'] ?>" class="btn-entregando">Entregando</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<p><a href="painel_dono.php">Voltar ao Painel</a></p>
</body>
</html>
