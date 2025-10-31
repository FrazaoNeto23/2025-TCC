<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

// ===== PROCESSAR AÇÕES =====
$mensagem = '';
$erro = '';

// ADICIONAR PRODUTO
if (isset($_POST['adicionar'])) {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $preco = floatval($_POST['preco']);
    $categoria = trim($_POST['categoria']);
    $disponivel = isset($_POST['disponivel']) ? 1 : 0;
    
    if (empty($nome) || $preco <= 0) {
        $erro = "Nome e preço são obrigatórios!";
    } else {
        // Upload da imagem
        $imagem = null;
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['imagem']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $imagem = 'uploads/' . time() . '_' . $filename;
                $upload_dir = 'uploads/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                move_uploaded_file($_FILES['imagem']['tmp_name'], $imagem);
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO produtos (nome, descricao, preco, categoria, imagem, disponivel) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssdssi", $nome, $descricao, $preco, $categoria, $imagem, $disponivel);
        
        if ($stmt->execute()) {
            $mensagem = "Produto adicionado com sucesso!";
        } else {
            $erro = "Erro ao adicionar produto: " . $conn->error;
        }
    }
}

// EDITAR PRODUTO
if (isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $preco = floatval($_POST['preco']);
    $categoria = trim($_POST['categoria']);
    $disponivel = isset($_POST['disponivel']) ? 1 : 0;
    
    if (empty($nome) || $preco <= 0) {
        $erro = "Nome e preço são obrigatórios!";
    } else {
        // Upload da nova imagem (se houver)
        $imagem_atual = $conn->query("SELECT imagem FROM produtos WHERE id = $id")->fetch_assoc()['imagem'];
        $imagem = $imagem_atual;
        
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['imagem']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $imagem = 'uploads/' . time() . '_' . $filename;
                $upload_dir = 'uploads/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                move_uploaded_file($_FILES['imagem']['tmp_name'], $imagem);
                
                // Deletar imagem antiga
                if ($imagem_atual && file_exists($imagem_atual)) {
                    unlink($imagem_atual);
                }
            }
        }
        
        $stmt = $conn->prepare("
            UPDATE produtos 
            SET nome = ?, descricao = ?, preco = ?, categoria = ?, imagem = ?, disponivel = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssdssii", $nome, $descricao, $preco, $categoria, $imagem, $disponivel, $id);
        
        if ($stmt->execute()) {
            $mensagem = "Produto atualizado com sucesso!";
        } else {
            $erro = "Erro ao atualizar produto: " . $conn->error;
        }
    }
}

// DELETAR PRODUTO
if (isset($_GET['deletar'])) {
    $id = intval($_GET['deletar']);
    
    // Buscar imagem para deletar
    $produto = $conn->query("SELECT imagem FROM produtos WHERE id = $id")->fetch_assoc();
    
    if ($conn->query("DELETE FROM produtos WHERE id = $id")) {
        // Deletar imagem
        if ($produto['imagem'] && file_exists($produto['imagem'])) {
            unlink($produto['imagem']);
        }
        $mensagem = "Produto deletado com sucesso!";
    } else {
        $erro = "Erro ao deletar produto: " . $conn->error;
    }
}

// ===== BUSCAR PRODUTOS =====
$busca = $_GET['busca'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';

$where_conditions = [];
$params = [];
$types = "";

if (!empty($busca)) {
    $where_conditions[] = "(nome LIKE ? OR descricao LIKE ?)";
    $busca_param = "%{$busca}%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $types .= "ss";
}

if (!empty($categoria_filtro)) {
    $where_conditions[] = "categoria = ?";
    $params[] = $categoria_filtro;
    $types .= "s";
}

$where_sql = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$sql = "SELECT * FROM produtos $where_sql ORDER BY nome ASC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$produtos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar categorias únicas
$categorias = $conn->query("SELECT DISTINCT categoria FROM produtos ORDER BY categoria ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - Burger House</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            color: #333;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-danger {
            background: #ff6b6b;
            color: white;
        }

        .btn-danger:hover {
            background: #ee5a52;
        }

        .btn-success {
            background: #51cf66;
            color: white;
        }

        .btn-success:hover {
            background: #40c057;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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
            transition: border 0.3s;
        }

        .filters input:focus,
        .filters select:focus {
            border-color: #667eea;
        }

        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .produto-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .produto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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

        .produto-nome {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .produto-descricao {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .produto-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .produto-preco {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .produto-categoria {
            display: inline-block;
            padding: 5px 12px;
            background: #f8f9fa;
            border-radius: 20px;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .produto-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-disponivel {
            background: #d4edda;
            color: #155724;
        }

        .status-indisponivel {
            background: #f8d7da;
            color: #721c24;
        }

        .produto-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .produto-actions button,
        .produto-actions a {
            flex: 1;
            padding: 10px;
            text-align: center;
            font-size: 13px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h2 {
            color: #333;
        }

        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border 0.3s;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .produtos-grid {
                grid-template-columns: 1fr;
            }

            .filters {
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
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1><i class="fas fa-hamburger"></i> Gerenciar Produtos</h1>
            <div style="display: flex; gap: 10px;">
                <button onclick="openAddModal()" class="btn btn-success">
                    <i class="fas fa-plus"></i> Adicionar Produto
                </button>
                <a href="painel_dono.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- MENSAGENS -->
        <?php if ($mensagem): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $erro ?>
            </div>
        <?php endif; ?>

        <!-- FILTROS -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <input type="text" name="busca" placeholder="Buscar produto..." value="<?= htmlspecialchars($busca) ?>">
                
                <select name="categoria">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['categoria'] ?>" <?= $categoria_filtro == $cat['categoria'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                
                <a href="gerenciar_produtos.php" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Limpar
                </a>
            </form>
        </div>

        <!-- GRID DE PRODUTOS -->
        <div class="produtos-grid">
            <?php if (empty($produtos)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-box-open"></i>
                    <h3>Nenhum produto encontrado</h3>
                    <p>Clique em "Adicionar Produto" para começar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($produtos as $produto): ?>
                    <div class="produto-card">
                        <?php if ($produto['imagem'] && file_exists($produto['imagem'])): ?>
                            <img src="<?= $produto['imagem'] ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" class="produto-imagem">
                        <?php else: ?>
                            <div class="produto-imagem" style="display: flex; align-items: center; justify-content: center; background: #f0f0f0;">
                                <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="produto-info">
                            <span class="produto-categoria">
                                <i class="fas fa-tag"></i> <?= htmlspecialchars($produto['categoria']) ?>
                            </span>
                            
                            <div class="produto-nome"><?= htmlspecialchars($produto['nome']) ?></div>
                            <div class="produto-descricao"><?= htmlspecialchars($produto['descricao']) ?></div>
                            
                            <div class="produto-footer">
                                <div class="produto-preco">R$ <?= number_format($produto['preco'], 2, ',', '.') ?></div>
                                <span class="produto-status <?= $produto['disponivel'] ? 'status-disponivel' : 'status-indisponivel' ?>">
                                    <?= $produto['disponivel'] ? 'Disponível' : 'Indisponível' ?>
                                </span>
                            </div>
                            
                            <div class="produto-actions">
                                <button onclick='openEditModal(<?= json_encode($produto) ?>)' class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                                <a href="?deletar=<?= $produto['id'] ?>" 
                                   onclick="return confirm('Tem certeza que deseja deletar este produto?')" 
                                   class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Deletar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL ADICIONAR -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Adicionar Produto</h2>
                <span class="close-modal" onclick="closeAddModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nome do Produto *</label>
                    <input type="text" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Preço (R$) *</label>
                    <input type="number" name="preco" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Categoria</label>
                    <input type="text" name="categoria" placeholder="Ex: Hambúrguer, Bebida, Sobremesa...">
                </div>
                
                <div class="form-group">
                    <label>Imagem</label>
                    <input type="file" name="imagem" accept="image/*">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="disponivel" id="add_disponivel" checked>
                        <label for="add_disponivel" style="margin: 0;">Produto disponível</label>
                    </div>
                </div>
                
                <button type="submit" name="adicionar" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-save"></i> Salvar Produto
                </button>
            </form>
        </div>
    </div>

    <!-- MODAL EDITAR -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Produto</h2>
                <span class="close-modal" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Nome do Produto *</label>
                    <input type="text" name="nome" id="edit_nome" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" id="edit_descricao"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Preço (R$) *</label>
                    <input type="number" name="preco" id="edit_preco" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Categoria</label>
                    <input type="text" name="categoria" id="edit_categoria">
                </div>
                
                <div class="form-group">
                    <label>Nova Imagem (deixe em branco para manter a atual)</label>
                    <input type="file" name="imagem" accept="image/*">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="disponivel" id="edit_disponivel">
                        <label for="edit_disponivel" style="margin: 0;">Produto disponível</label>
                    </div>
                </div>
                
                <button type="submit" name="editar" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(produto) {
            document.getElementById('edit_id').value = produto.id;
            document.getElementById('edit_nome').value = produto.nome;
            document.getElementById('edit_descricao').value = produto.descricao;
            document.getElementById('edit_preco').value = produto.preco;
            document.getElementById('edit_categoria').value = produto.categoria;
            document.getElementById('edit_disponivel').checked = produto.disponivel == 1;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Fechar modal clicando fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
