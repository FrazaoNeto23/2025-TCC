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

// ===== ADICIONAR PRODUTO - CORRIGIDO COM PREPARED STATEMENTS =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adicionar'])) {
    $nome = trim($_POST['nome']);
    $preco = floatval($_POST['preco']);
    $descricao = trim($_POST['descricao']);

    // Validações básicas
    if (empty($nome) || $preco <= 0) {
        $erro = "Nome e preço são obrigatórios!";
    } else {
        $imagem = NULL;

        // Upload de imagem com validação
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $file_type = $_FILES['imagem']['type'];

            if (in_array($file_type, $allowed_types)) {
                $filename = time() . "_" . basename($_FILES['imagem']['name']);
                $targetFile = $targetDir . $filename;

                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $targetFile)) {
                    $imagem = $filename;
                }
            } else {
                $erro = "Tipo de arquivo não permitido!";
            }
        }

        // ✅ CORREÇÃO: Usar prepared statement
        if (!isset($erro)) {
            $stmt = $conn->prepare("INSERT INTO produtos (nome, preco, descricao, imagem) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdss", $nome, $preco, $descricao, $imagem);

            if ($stmt->execute()) {
                header("Location: cardapio.php?success=1");
                exit;
            } else {
                $erro = "Erro ao adicionar produto!";
            }
        }
    }
}

// ===== EXCLUIR PRODUTO - CORRIGIDO =====
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Buscar imagem antes de deletar
    $stmt = $conn->prepare("SELECT imagem FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Deletar arquivo de imagem
        if ($row['imagem'] && file_exists($targetDir . $row['imagem'])) {
            unlink($targetDir . $row['imagem']);
        }

        // Deletar do banco
        $stmt_delete = $conn->prepare("DELETE FROM produtos WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();

        header("Location: cardapio.php?deleted=1");
        exit;
    }
}

// ✅ Buscar produtos de forma segura
$produtos = $conn->query("SELECT * FROM produtos ORDER BY nome ASC");
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

    <?php if (isset($_GET['success'])): ?>
        <div
            style="background: #00cc55; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <i class="fa fa-check-circle"></i> Produto adicionado com sucesso!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div
            style="background: #ff6b6b; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <i class="fa fa-trash"></i> Produto excluído!
        </div>
    <?php endif; ?>

    <?php if (isset($erro)): ?>
        <div
            style="background: #ff4c4c; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="text" name="nome" placeholder="Nome do produto" required maxlength="100">
        <input type="number" step="0.01" name="preco" placeholder="Preço" required min="0.01" max="9999.99">
        <textarea name="descricao" placeholder="Descrição" maxlength="500"></textarea>
        <input type="file" name="imagem" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
        <button type="submit" name="adicionar"><i class="fa fa-plus"></i> Adicionar Produto</button>
    </form>

    <div class="produtos-container">
        <?php if ($produtos && $produtos->num_rows > 0): ?>
            <?php while ($row = $produtos->fetch_assoc()): ?>
                <div class="card-produto">
                    <?php if ($row['imagem'] && file_exists($targetDir . $row['imagem'])): ?>
                        <img src="<?= htmlspecialchars($targetDir . $row['imagem']) ?>" alt="<?= htmlspecialchars($row['nome']) ?>"
                            class="card-img">
                    <?php else: ?>
                        <div class="card-img-placeholder">Sem Imagem</div>
                    <?php endif; ?>
                    <div class="card-info">
                        <h3><?= htmlspecialchars($row['nome']) ?></h3>
                        <p class="preco">R$ <?= number_format($row['preco'], 2, ',', '.') ?></p>
                        <p class="descricao"><?= htmlspecialchars($row['descricao'] ?? '') ?></p>
                    </div>
                    <div class="card-acoes">
                        <a href="editar_produto.php?id=<?= $row['id'] ?>" class="editar">
                            <i class="fa fa-pen"></i> Editar
                        </a>
                        <a href="cardapio.php?delete=<?= $row['id'] ?>" class="delete"
                            onclick="return confirm('Tem certeza que deseja excluir?')">
                            <i class="fa fa-trash"></i> Excluir
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; background: #1e1e1e; border-radius: 12px; grid-column: 1 / -1;">
                <i class="fa fa-inbox" style="font-size: 60px; color: #555; margin-bottom: 20px;"></i>
                <p style="color: #aaa; font-size: 18px;">Nenhum produto cadastrado</p>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>