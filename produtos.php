<?php
session_start();
include "config.php";

// ===== VERIFICAR COLUNA CATEGORIA =====
$check_categoria = $conn->query("SHOW COLUMNS FROM produtos LIKE 'categoria'");
if ($check_categoria->num_rows == 0) {
    $conn->query("ALTER TABLE produtos ADD COLUMN categoria VARCHAR(50) AFTER preco");
}

// ===== BUSCAR CATEGORIA DO FILTRO =====
$categoria_filtro = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';

// ===== MONTAR QUERY =====
$where = ["disponivel = 1"];
$params = [];
$types = "";

if (!empty($categoria_filtro)) {
    $where[] = "categoria = ?";
    $params[] = $categoria_filtro;
    $types .= "s";
}

if (!empty($busca)) {
    $where[] = "(nome LIKE ? OR descricao LIKE ?)";
    $busca_param = "%{$busca}%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where);
$sql = "SELECT * FROM produtos WHERE {$where_sql} ORDER BY nome ASC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== BUSCAR CATEGORIAS =====
$categorias = $conn->query("
    SELECT DISTINCT categoria 
    FROM produtos 
    WHERE disponivel = 1 
    AND categoria IS NOT NULL 
    AND categoria != ''
    ORDER BY categoria ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .filters select, .filters input {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            flex: 1;
            min-width: 200px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #667eea; color: white; }
        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .produto-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .produto-card:hover { transform: translateY(-5px); }
        .produto-imagem {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .produto-info { padding: 15px; }
        .produto-categoria {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin-bottom: 8px;
        }
        .produto-nome {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .produto-preco {
            font-size: 22px;
            font-weight: bold;
            color: #51cf66;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-hamburger"></i> Nossos Produtos</h1>
        </div>

        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap;">
                <select name="categoria">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['categoria']) ?>" 
                                <?= $categoria_filtro == $cat['categoria'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="busca" placeholder="Buscar..." value="<?= htmlspecialchars($busca) ?>">
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>
                
                <a href="produtos.php" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Limpar
                </a>
            </form>
        </div>

        <div class="produtos-grid">
            <?php if (empty($produtos)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; background: white; border-radius: 10px;">
                    <i class="fas fa-box-open" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <h3>Nenhum produto encontrado</h3>
                </div>
            <?php else: ?>
                <?php foreach ($produtos as $produto): ?>
                    <div class="produto-card">
                        <?php if ($produto['imagem'] && file_exists($produto['imagem'])): ?>
                            <img src="<?= $produto['imagem'] ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="produto-imagem">
                        <?php else: ?>
                            <div class="produto-imagem" style="display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-hamburger" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="produto-info">
                            <?php if (!empty($produto['categoria'])): ?>
                                <span class="produto-categoria">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($produto['categoria']) ?>
                                </span>
                            <?php endif; ?>

                            <div class="produto-nome"><?= htmlspecialchars($produto['nome']) ?></div>
                            <div><?= htmlspecialchars($produto['descricao']) ?></div>
                            <div class="produto-preco">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></div>

                            <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
                                <form method="POST" action="carrinho.php" style="margin-top: 10px;">
                                    <input type="hidden" name="id_produto" value="<?= $produto['id'] ?>">
                                    <button type="submit" name="adicionar_carrinho" class="btn btn-primary" style="width: 100%;">
                                        <i class="fas fa-cart-plus"></i> Adicionar ao Carrinho
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
