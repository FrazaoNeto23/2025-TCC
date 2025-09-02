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

// Filtro de status
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'todos';
$sql = "
    SELECT pedidos.id, pedidos.id_cliente, pedidos.id_produto, pedidos.quantidade, pedidos.total, pedidos.data, pedidos.status,
           produtos.nome AS produto_nome
    FROM pedidos
    JOIN produtos ON pedidos.id_produto = produtos.id
";
if($filtro_status != 'todos'){
    $sql .= " WHERE pedidos.status = '".$conn->real_escape_string($filtro_status)."' ";
}
$sql .= " ORDER BY pedidos.data DESC";
$pedidos = $conn->query($sql);
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

<!-- Filtro por status -->
<div class="filtro">
    <form method="get">
        <label for="status">Filtrar por status:</label>
        <select name="status" id="status" onchange="this.form.submit()">
            <option value="todos" <?= $filtro_status == 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="Em preparo" <?= $filtro_status == 'Em preparo' ? 'selected' : '' ?>>⚙️ Em preparo</option>
            <option value="Em produção" <?= $filtro_status == 'Em produção' ? 'selected' : '' ?>>🛠 Em produção</option>
            <option value="Entregando" <?= $filtro_status == 'Entregando' ? 'selected' : '' ?>>🚚 Entregando</option>
        </select>
    </form>
</div>

<!-- Botão para limpar pedidos antigos -->
<form method="post" style="text-align:center; margin:15px 0;">
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
        $status_icon = '';
        if($pedido['status'] == 'Em preparo'){ 
            $status_class = 'status-preparo'; 
            $status_icon = '⚙️'; 
        }
        elseif($pedido['status'] == 'Em produção'){ 
            $status_class = 'status-producao'; 
            $status_icon = '🛠'; 
        }
        elseif($pedido['status'] == 'Entregando'){ 
            $status_class = 'status-entregando'; 
            $status_icon = '🚚'; 
        }
    ?>
    <tr>
        <td><?= $pedido['id'] ?></td>
        <td><?= $pedido['id_cliente'] ?></td>
        <td><?= $pedido['produto_nome'] ?></td>
        <td><?= $pedido['quantidade'] ?></td>
        <td>R$ <?= number_format($pedido['total'], 2, ',', '.') ?></td>
        <td><?= $pedido['data'] ?></td>
        <td><span class="<?= $status_class ?>"><?= $status_icon ?> <?= $pedido['status'] ?></span></td>
        <td>
            <a href="pedidos.php?update_status=Em preparo&id=<?= $pedido['id'] ?>">Em preparo</a> |
            <a href="pedidos.php?update_status=Em produção&id=<?= $pedido['id'] ?>">Em produção</a> |
            <a href="pedidos.php?update_status=Entregando&id=<?= $pedido['id'] ?>">Entregando</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<p><a href="painel_dono.php">Voltar ao Painel</a></p>
</body>
</html>
