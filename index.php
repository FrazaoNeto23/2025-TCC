<?php
session_start();
include "config.php";

// ===== VERIFICAR E ADICIONAR COLUNA CATEGORIA SE NÃO EXISTIR =====
$check_categoria = $conn->query("SHOW COLUMNS FROM produtos LIKE 'categoria'");
if ($check_categoria->num_rows == 0) {
    $conn->query("ALTER TABLE produtos ADD COLUMN categoria VARCHAR(50) AFTER preco");
    $conn->query("ALTER TABLE produtos ADD INDEX idx_categoria (categoria)");
}

// ===== PROCESSAR FILTROS =====
$categoria_filtro = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';

// ===== BUSCAR PRODUTOS COM FILTROS =====
$where_conditions = ["disponivel = 1"];
$params = [];
$types = "";

if (!empty($categoria_filtro)) {
    $where_conditions[] = "categoria = ?";
    $params[] = $categoria_filtro;
    $types .= "s";
}

if (!empty($busca)) {
    $where_conditions[] = "(nome LIKE ? OR descricao LIKE ?)";
    $busca_param = "%{$busca}%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_conditions);

$sql = "SELECT * FROM produtos WHERE {$where_sql} ORDER BY nome ASC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== BUSCAR CATEGORIAS ÚNICAS =====
$categorias = $conn->query("
    SELECT DISTINCT categoria 
    FROM produtos 
    WHERE disponivel = 1 AND categoria IS NOT NULL AND categoria != ''
    ORDER BY categoria ASC
")->fetch_all(MYSQLI_ASSOC);

// ===== MENSAGENS =====
$mensagem_sucesso = $_SESSION['sucesso'] ?? '';
$mensagem_erro = $_SESSION['erro'] ?? '';
unset($_SESSION['sucesso'], $_SESSION['erro']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Burger House - Os Melhores Hambúrgueres</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #51cf66;
            color: white;
        }

        .btn-success:hover {
            background: #40c057;
        }

        .btn-danger {
            background: #ff6b6b;
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .hero {
            background: white;
            padding: 60px 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .hero h1 {
            font-size: 48px;
            color: #333;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 20px;
            color: #666;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .filters form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters input,
        .filters select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .filters input:focus,
        .filters select:focus {
            border-color: #667eea;
        }

        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .produto-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .produto-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .produto-imagem {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }

        .produto-info {
            padding: 20px;
        }

        .produto-categoria {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .produto-nome {
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .produto-descricao {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .produto-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .produto-preco {
            font-size: 28px;
            font-weight: bold;
            color: #51cf66;
        }

        .empty-state {
            background: white;
            padding: 60px;
            border-radius: 15px;
            text-align: center;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .carrinho-badge {
            position: relative;
        }

        .carrinho-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 32px;
            }

            .produtos-grid {
                grid-template-columns: 1fr;
            }

            .filters form {
                flex-direction: column;
            }

            .filters input,
            .filters select,
            .filters button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-hamburger"></i> BURGER HOUSE
            </div>

            <div class="nav-buttons">
                <?php if (isset($_SESSION['usuario'])): ?>
                    <?php if ($_SESSION['tipo'] == 'cliente'): ?>
                        <a href="carrinho.php" class="btn btn-primary carrinho-badge">
                            <i class="fas fa-shopping-cart"></i> Carrinho
                            <?php
                            $count = $conn->query("SELECT COUNT(*) as total FROM carrinho WHERE id_cliente = " . $_SESSION['id_usuario'])->fetch_assoc()['total'];
                            if ($count > 0):
                            ?>
                                <span class="badge"><?= $count ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="painel_dono.php" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt"></i> Painel
                        </a>
                    <?php endif; ?>
                    <span>Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>!</span>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Entrar
                    </a>
                    <a href="cadastro.php" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Cadastrar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- HERO -->
        <div class="hero">
            <h1>🍔 Os Melhores Hambúrgueres da Cidade!</h1>
            <p>Sabor incomparável, qualidade garantida</p>
        </div>

        <!-- MENSAGENS -->
        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $mensagem_sucesso ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $mensagem_erro ?>
            </div>
        <?php endif; ?>

        <!-- FILTROS -->
        <div class="filters">
            <form method="GET">
                <select name="categoria">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <?php if (!empty($cat['categoria'])): ?>
                            <option value="<?= htmlspecialchars($cat['categoria']) ?>" 
                                    <?= $categoria_filtro == $cat['categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['categoria']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <input type="text" 
                       name="busca" 
                       placeholder="Buscar produtos..." 
                       value="<?= htmlspecialchars($busca) ?>">

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Buscar
                </button>

                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Limpar
                </a>
            </form>
        </div>

        <!-- GRID DE PRODUTOS -->
        <div class="produtos-grid">
            <?php if (empty($produtos)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h2>Nenhum produto encontrado</h2>
                    <p>Tente buscar por outro termo ou categoria</p>
                </div>
            <?php else: ?>
                <?php foreach ($produtos as $produto): ?>
                    <div class="produto-card">
                        <?php if ($produto['imagem'] && file_exists($produto['imagem'])): ?>
                            <img src="<?= $produto['imagem'] ?>" 
                                 alt="<?= htmlspecialchars($produto['nome']) ?>" 
                                 class="produto-imagem">
                        <?php else: ?>
                            <div class="produto-imagem" style="display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-hamburger" style="font-size: 64px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="produto-info">
                            <?php if (!empty($produto['categoria'])): ?>
                                <span class="produto-categoria">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($produto['categoria']) ?>
                                </span>
                            <?php endif; ?>

                            <div class="produto-nome"><?= htmlspecialchars($produto['nome']) ?></div>
                            <div class="produto-descricao"><?= htmlspecialchars($produto['descricao']) ?></div>

                            <div class="produto-footer">
                                <div class="produto-preco">
                                    R$ <?= number_format($produto['preco'], 2, ',', '.') ?>
                                </div>

                                <?php if (isset($_SESSION['usuario']) && $_SESSION['tipo'] == 'cliente'): ?>
                                    <form method="POST" action="carrinho.php">
                                        <input type="hidden" name="id_produto" value="<?= $produto['id'] ?>">
                                        <input type="hidden" name="redirect" value="index.php">
                                        <button type="submit" name="adicionar_carrinho" class="btn btn-success">
                                            <i class="fas fa-cart-plus"></i> Adicionar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Login
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
