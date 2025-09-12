<?php
session_start();
include "config.php";

if(!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente"){
    header("Location: painel_cliente.php");
    exit;
}

// Adicionar pedido
if(isset($_POST['pedido'])){
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);
    
    $produto = $conn->query("SELECT preco FROM produtos WHERE id=$id_produto")->fetch_assoc();
    $total = $produto['preco'] * $quantidade;

    $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, id_produto, quantidade, total) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiid", $_SESSION['id_usuario'], $id_produto, $quantidade, $total);
    $stmt->execute();
    $msg = "Pedido realizado com sucesso!";
}

// Buscar produtos
$produtos = $conn->query("SELECT * FROM produtos");

// Buscar pedidos do cliente
$pedidos = $conn->query("SELECT pedidos.*, produtos.nome AS produto_nome 
                         FROM pedidos 
                         JOIN produtos ON pedidos.id_produto = produtos.id 
                         WHERE pedidos.id_cliente=".$_SESSION['id_usuario']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Painel do Cliente</title>
<link rel="stylesheet" href="css/cliente.css?e=<?php echo rand(0,10000)?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="container">
<h1>Bem-vindo, <?php echo $_SESSION['usuario']; ?></h1>
<a href="logout.php" class="btn-sair">Sair</a>

<?php if(isset($msg)) echo "<p class='msg'>$msg</p>"; ?>

<h2>Cardápio</h2>
<div class="produtos">
<?php while($p = $produtos->fetch_assoc()): ?>
    <div class="produto">
        <?php if($p['imagem']) echo "<img src='uploads/{$p['imagem']}' alt='{$p['nome']}'>"; ?>
        <h3><?php echo $p['nome']; ?></h3>
        <p><?php echo $p['descricao']; ?></p>
        <p class="preco">R$ <?php echo $p['preco']; ?></p>
        <form method="POST">
            <input type="hidden" name="id_produto" value="<?php echo $p['id']; ?>">
            <input type="number" name="quantidade" value="1" min="1" required>
            <button type="submit" name="pedido">Comprar</button>
        </form>
    </div>
<?php endwhile; ?>
</div>

<h2>Meus Pedidos</h2>
<table>
<tr><th>ID</th><th>Produto</th><th>Qtd</th><th>Total</th><th>Data</th></tr>
<?php while($pd = $pedidos->fetch_assoc()): ?>
<tr>
    <td><?php echo $pd['id']; ?></td>
    <td><?php echo $pd['produto_nome']; ?></td>
    <td><?php echo $pd['quantidade']; ?></td>
    <td>R$ <?php echo $pd['total']; ?></td>
    <td><?php echo $pd['data']; ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>
</body>
</html>
