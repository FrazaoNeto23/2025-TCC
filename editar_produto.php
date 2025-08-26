<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") { header("Location: index.php"); exit; }
if (!isset($_GET['id'])) { header("Location: cardapio.php"); exit; }

$id = $_GET['id'];
$result = $conn->query("SELECT * FROM cardapio WHERE id=$id");
if ($result->num_rows == 0) { echo "Produto não encontrado!"; exit; }
$produto = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome']; $preco = $_POST['preco']; $descricao = $_POST['descricao'];
    $imagem = $produto['imagem'];

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $filename = time() . "_" . basename($_FILES['imagem']['name']);
        $targetFile = "uploads/" . $filename;
        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetFile)) {
            if ($imagem && file_exists("uploads/" . $imagem)) { unlink("uploads/" . $imagem); }
            $imagem = $filename;
        }
    }

    $sql = "UPDATE cardapio SET nome='$nome', preco='$preco', descricao='$descricao', imagem='$imagem' WHERE id=$id";
    $conn->query($sql);
    header("Location: cardapio.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Editar Produto</title>
<link rel="stylesheet" href="css/styles.css?e=<?php echo rand(0,10000)?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<h1>Editar Produto</h1>
<form method="post" enctype="multipart/form-data">
    <input type="text" name="nome" value="<?= $produto['nome'] ?>" required>
    <input type="number" step="0.01" name="preco" value="<?= $produto['preco'] ?>" required>
    <textarea name="descricao"><?= $produto['descricao'] ?></textarea>
    <input type="file" name="imagem" accept="image/*">
    <?php if ($produto['imagem'] && file_exists("uploads/" . $produto['imagem'])): ?>
        <img src="uploads/<?= $produto['imagem'] ?>" alt="<?= $produto['nome'] ?>" class="card-img-edit">
    <?php endif; ?>
    <button type="submit"><i class="fa fa-save"></i> Salvar Alterações</button>
</form>
<p><a href="cardapio.php"><i class="fa fa-arrow-left"></i> Voltar</a></p>
</body>
</html>
