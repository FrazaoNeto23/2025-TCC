<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: cardapio_especial.php");
    exit;
}

$id = intval($_GET['id']);

// Buscar produto - CORRIGIDO
$stmt = $conn->prepare("SELECT * FROM produtos_especiais WHERE id = ?");
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

    $stmt_update = $conn->prepare("UPDATE produtos_especiais SET nome = ?, preco = ?, descricao = ?, imagem = ? WHERE id = ?");
    $stmt_update->bind_param("sdssi", $nome, $preco, $descricao, $imagem, $id);

    if ($stmt_update->execute()) {
        header("Location: cardapio_especial.php?updated=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Editar Produto Especial</title>
    <link rel="stylesheet" href="css/editar_produto.css?e=<?php echo time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        h1,
        .btn-save {
            color: #ffa500 !important;
            text-shadow: 0 0 15px #ffa500 !important;
        }

        .container {
            border: 2px solid #ffa500;
            box-shadow: 0 0 30px rgba(255, 165, 0, 0.4);
        }

        label {
            color: #ffa500 !important;
        }

        input:focus,
        textarea:focus {
            border-color: #ff8c00;
            box-shadow: 0 0 15px rgba(255, 165, 0, 0.5);
        }

        .back-link a {
            border-color: #ffa500;
            color: #ffa500 !important;
        }

        .back-link a:hover {
            color: #ff8c00 !important;
            box-shadow: 0 0 20px rgba(255, 165, 0, 0.5);
        }
    </style>
</head>

<body>
    <div class="container">
        <h1><i class="fa fa-star"></i> Editar Produto Especial</h1>

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

            <?php if ($produto['imagem'] && file_exists("uploads/" . $produto['imagem'])): ?>
                <div class="preview">
                    <p>Pré-visualização atual:</p>
                    <img src="uploads/<?= htmlspecialchars($produto['imagem']) ?>"
                        alt="<?= htmlspecialchars($produto['nome']) ?>" class="card-img-edit">
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-save"><i class="fa fa-save"></i> Salvar Alterações</button>
        </form>

        <p class="back-link"><a href="cardapio_especial.php"><i class="fa fa-arrow-left"></i> Voltar ao Cardápio
                Especial</a></p>
    </div>
</body>

</html>