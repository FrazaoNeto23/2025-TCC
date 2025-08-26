<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

// Adicionar produto
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adicionar'])) {
    $nome = $_POST['nome'];
    $preco = $_POST['preco'];
    $descricao = $_POST['descricao'];

    $sql = "INSERT INTO cardapio (nome, preco, descricao) VALUES ('$nome', '$preco', '$descricao')";
    $conn->query($sql);
    header("Location: cardapio.php");
    exit;
}

// Excluir produto
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM cardapio WHERE id=$id");
    header("Location: cardapio.php");
    exit;
}

$produtos = $conn->query("SELECT * FROM cardapio");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Cardápio</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <h1>Gerenciar Cardápio</h1>

    <form method="post">
        <input type="text" name="nome" placeholder="Nome do produto" required>
        <input type="number" step="0.01" name="preco" placeholder="Preço" required>
        <textarea name="descricao" placeholder="Descrição"></textarea>
        <button type="submit" name="adicionar"><i class="fa fa-plus"></i> Adicionar Produto</button>
    </form>

    <div class="produtos-container">
        <?php while($row = $produtos->fetch_assoc()): ?>
        <div class="card-produto">
            <div class="card-info">
                <h3><?= $row['nome'] ?></h3>
                <p class="preco">R$ <?= number_format($row['preco'], 2, ',', '.') ?></p>
                <p class="descricao"><?= $row['descricao'] ?></p>
            </div>
            <div class="card-acoes">
                <a href="editar_produto.php?id=<?= $row['id'] ?>" class="editar"><i class="fa fa-pen"></i> Editar</a>
                <a href="cardapio.php?delete=<?= $row['id'] ?>" class="delete" onclick="return confirm('Tem certeza que deseja excluir?')"><i class="fa fa-trash"></i> Excluir</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <p><a href="painel.php"><i class="fa fa-arrow-left"></i> Voltar ao Painel</a></p>
</body>
</html>
