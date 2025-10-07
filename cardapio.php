<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

$targetDir = "uploads/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

// Adicionar produto
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adicionar'])) {
    $nome = $_POST['nome'];
    $preco = $_POST['preco'];
    $descricao = $_POST['descricao'];

    $imagem = NULL;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $filename = time() . "_" . basename($_FILES['imagem']['name']);
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetFile)) {
            $imagem = $filename;
        }
    }

    $sql = "INSERT INTO produtos (nome, preco, descricao, imagem) VALUES ('$nome', '$preco', '$descricao', '$imagem')";
    $conn->query($sql);
    header("Location: cardapio.php");
    exit;
}

// Excluir produto
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $res = $conn->query("SELECT imagem FROM produtos WHERE id=$id");
    $row = $res->fetch_assoc();
    if ($row['imagem'] && file_exists($targetDir . $row['imagem'])) {
        unlink($targetDir . $row['imagem']);
    }
    $conn->query("DELETE FROM produtos WHERE id=$id");
    header("Location: cardapio.php");
    exit;
}

$produtos = $conn->query("SELECT * FROM produtos");
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Cardápio</title>
    <link rel="stylesheet" href="css/cardapio.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="header-page">
        <a href="painel_dono.php" class="btn-voltar-top"><i class="fa fa-arrow-left"></i> Voltar</a>
        <h1><i class="fa fa-utensils"></i> Gerenciar Cardápio</h1>
        <div class="spacer"></div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="text" name="nome" placeholder="Nome do produto" required>
        <input type="number" step="0.01" name="preco" placeholder="Preço" required>
        <textarea name="descricao" placeholder="Descrição"></textarea>
        <input type="file" name="imagem" accept="image/*">
        <button type="submit" name="adicionar"><i class="fa fa-plus"></i> Adicionar Produto</button>
    </form>

    <div class="produtos-container">
        <?php while ($row = $produtos->fetch_assoc()): ?>
            <div class="card-produto">
                <?php if ($row['imagem'] && file_exists($targetDir . $row['imagem'])): ?>
                    <img src="<?= $targetDir . $row['imagem'] ?>" alt="<?= $row['nome'] ?>" class="card-img">
                <?php else: ?>
                    <div class="card-img-placeholder">Sem Imagem</div>
                <?php endif; ?>
                <div class="card-info">
                    <h3><?= $row['nome'] ?></h3>
                    <p class="preco">R$ <?= number_format($row['preco'], 2, ',', '.') ?></p>
                    <p class="descricao"><?= $row['descricao'] ?></p>
                </div>
                <div class="card-acoes">
                    <a href="editar_produto.php?id=<?= $row['id'] ?>" class="editar"><i class="fa fa-pen"></i> Editar</a>
                    <a href="cardapio.php?delete=<?= $row['id'] ?>" class="delete"
                        onclick="return confirm('Tem certeza que deseja excluir?')"><i class="fa fa-trash"></i> Excluir</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</body>

</html>