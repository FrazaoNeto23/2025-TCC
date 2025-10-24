<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
require_once CONFIG_PATH . "/config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: cardapio.php");
    exit;
}

$id = intval($_GET['id']);

// Buscar produto - CORRIGIDO
$stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Produto não encontrado!";
    exit;
}

$produto = $result->fetch_assoc();

// Atualizar produto - CORRIGIDO
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome']);
    $preco = floatval($_POST['preco']);
    $descricao = trim($_POST['descricao']);
    $imagem = $produto['imagem'];

    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];

        if (in_array($_FILES['imagem']['type'], $allowed_types)) {
            $filename = time() . "_" . basename($_FILES['imagem']['name']);
            $targetFile = "uploads/" . $filename;

            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetFile)) {
                if ($imagem && file_exists("uploads/" . $imagem)) {
                    unlink("uploads/" . $imagem);
                }
                $imagem = $filename;
            }
        }
    }

    $stmt_update = $conn->prepare("UPDATE produtos SET nome = ?, preco = ?, descricao = ?, imagem = ? WHERE id = ?");
    $stmt_update->bind_param("sdssi", $nome, $preco, $descricao, $imagem, $id);

    if ($stmt_update->execute()) {
        header("Location: cardapio.php?updated=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Editar Produto</title>
    <link rel="stylesheet" href="css/editar_produto.css?e=<?php
require_once __DIR__ . '/../config/paths.php'; echo time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <h1><i class="fa fa-edit"></i> Editar Produto</h1>

        <form method="post" enctype="multipart/form-data" class="form-card">
            <label>Nome do Produto</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($produto['nome']) ?>" required maxlength="100">

            <label>Preço</label>
            <input type="number" step="0.01" name="preco" value="<?= htmlspecialchars($produto['preco']) ?>" required
                min="0.01" max="9999.99">

            <label>Descrição</label>
            <textarea name="descricao" maxlength="500"><?= htmlspecialchars($produto['descricao'] ?? '') ?></textarea>

            <label>Imagem</label>
            <input type="file" name="imagem" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">

            <?php
require_once __DIR__ . '/../config/paths.php'; if ($produto['imagem'] && file_exists("uploads/" . $produto['imagem'])): ?>
                <div class="preview">
                    <p>Pré-visualização atual:</p>
                    <img src="uploads/<?= htmlspecialchars($produto['imagem']) ?>"
                        alt="<?= htmlspecialchars($produto['nome']) ?>" class="card-img-edit">
                </div>
            <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

            <button type="submit" class="btn-save"><i class="fa fa-save"></i> Salvar Alterações</button>
        </form>

        <p class="back-link"><a href="cardapio.php"><i class="fa fa-arrow-left"></i> Voltar ao Cardápio</a></p>
    </div>
</body>

</html>