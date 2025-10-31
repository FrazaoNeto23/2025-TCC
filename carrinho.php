<?php
session_start();
include "config.php";

// Verificar se usuário está logado
if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: login.php?redirect=index.php");
    exit;
}

$id_cliente = $_SESSION['id_usuario'];

// ===== ADICIONAR AO CARRINHO (CORRIGIDO) =====
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = isset($_POST['quantidade']) ? intval($_POST['quantidade']) : 1;
    
    // Validar quantidade
    if ($quantidade < 1) {
        $quantidade = 1;
    }
    
    // ===== VERIFICAR SE O PRODUTO EXISTE E ESTÁ DISPONÍVEL =====
    $stmt_verifica = $conn->prepare("
        SELECT id, nome, preco, disponivel 
        FROM produtos 
        WHERE id = ? AND disponivel = 1
    ");
    $stmt_verifica->bind_param("i", $id_produto);
    $stmt_verifica->execute();
    $produto = $stmt_verifica->get_result()->fetch_assoc();
    
    if (!$produto) {
        $_SESSION['erro'] = "Produto não encontrado ou indisponível!";
        header("Location: index.php");
        exit;
    }
    
    // ===== VERIFICAR SE JÁ EXISTE NO CARRINHO =====
    $stmt_check = $conn->prepare("
        SELECT id, quantidade 
        FROM carrinho 
        WHERE id_cliente = ? AND id_produto = ?
    ");
    $stmt_check->bind_param("ii", $id_cliente, $id_produto);
    $stmt_check->execute();
    $item_existente = $stmt_check->get_result()->fetch_assoc();
    
    if ($item_existente) {
        // Atualizar quantidade
        $nova_quantidade = $item_existente['quantidade'] + $quantidade;
        $stmt_update = $conn->prepare("
            UPDATE carrinho 
            SET quantidade = ? 
            WHERE id = ?
        ");
        $stmt_update->bind_param("ii", $nova_quantidade, $item_existente['id']);
        
        if ($stmt_update->execute()) {
            $_SESSION['sucesso'] = "Quantidade atualizada no carrinho!";
        } else {
            $_SESSION['erro'] = "Erro ao atualizar carrinho: " . $conn->error;
        }
    } else {
        // Inserir novo item
        $stmt_insert = $conn->prepare("
            INSERT INTO carrinho (id_cliente, id_produto, quantidade) 
            VALUES (?, ?, ?)
        ");
        $stmt_insert->bind_param("iii", $id_cliente, $id_produto, $quantidade);
        
        if ($stmt_insert->execute()) {
            $_SESSION['sucesso'] = "Produto adicionado ao carrinho!";
        } else {
            $_SESSION['erro'] = "Erro ao adicionar ao carrinho: " . $conn->error;
        }
    }
    
    // Redirecionar de volta
    $redirect = $_POST['redirect'] ?? 'index.php';
    header("Location: " . $redirect);
    exit;
}

// ===== REMOVER DO CARRINHO =====
if (isset($_GET['remover'])) {
    $id_item = intval($_GET['remover']);
    
    $stmt_remove = $conn->prepare("
        DELETE FROM carrinho 
        WHERE id = ? AND id_cliente = ?
    ");
    $stmt_remove->bind_param("ii", $id_item, $id_cliente);
    
    if ($stmt_remove->execute()) {
        $_SESSION['sucesso'] = "Item removido do carrinho!";
    } else {
        $_SESSION['erro'] = "Erro ao remover item: " . $conn->error;
    }
    
    header("Location: carrinho.php");
    exit;
}

// ===== ATUALIZAR QUANTIDADE =====
if (isset($_POST['atualizar_quantidade'])) {
    $id_item = intval($_POST['id_item']);
    $nova_quantidade = intval($_POST['quantidade']);
    
    if ($nova_quantidade < 1) {
        // Se quantidade for 0 ou menor, remover o item
        $stmt_remove = $conn->prepare("
            DELETE FROM carrinho 
            WHERE id = ? AND id_cliente = ?
        ");
        $stmt_remove->bind_param("ii", $id_item, $id_cliente);
        $stmt_remove->execute();
        $_SESSION['sucesso'] = "Item removido do carrinho!";
    } else {
        // Atualizar quantidade
        $stmt_update = $conn->prepare("
            UPDATE carrinho 
            SET quantidade = ? 
            WHERE id = ? AND id_cliente = ?
        ");
        $stmt_update->bind_param("iii", $nova_quantidade, $id_item, $id_cliente);
        
        if ($stmt_update->execute()) {
            $_SESSION['sucesso'] = "Quantidade atualizada!";
        } else {
            $_SESSION['erro'] = "Erro ao atualizar quantidade: " . $conn->error;
        }
    }
    
    header("Location: carrinho.php");
    exit;
}

// ===== LIMPAR CARRINHO =====
if (isset($_GET['limpar'])) {
    $stmt_limpar = $conn->prepare("DELETE FROM carrinho WHERE id_cliente = ?");
    $stmt_limpar->bind_param("i", $id_cliente);
    
    if ($stmt_limpar->execute()) {
        $_SESSION['sucesso'] = "Carrinho esvaziado!";
    } else {
        $_SESSION['erro'] = "Erro ao limpar carrinho: " . $conn->error;
    }
    
    header("Location: carrinho.php");
    exit;
}

// ===== BUSCAR ITENS DO CARRINHO =====
$sql_carrinho = "
    SELECT 
        c.id,
        c.id_produto,
        c.quantidade,
        p.nome,
        p.descricao,
        p.preco,
        p.imagem,
        (c.quantidade * p.preco) as subtotal
    FROM carrinho c
    INNER JOIN produtos p ON c.id_produto = p.id
    WHERE c.id_cliente = ?
    ORDER BY c.data_adicao DESC
";

$stmt = $conn->prepare($sql_carrinho);
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$itens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calcular total
$total = 0;
foreach ($itens as $item) {
    $total += $item['subtotal'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho - Burger House</title>
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
            max-width: 1200px;
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
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .carrinho-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 30px;
        }

        .item-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .item-imagem {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            background: #f0f0f0;
        }

        .item-info h3 {
            color: #333;
            margin-bottom: 5px;
        }

        .item-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .item-preco {
            color: #667eea;
            font-size: 18px;
            font-weight: bold;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }

        .quantidade-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantidade-control input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-weight: bold;
        }

        .quantidade-control button {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 5px;
            background: #667eea;
            color: white;
            cursor: pointer;
            font-size: 18px;
        }

        .quantidade-control button:hover {
            background: #5568d3;
        }

        .resumo {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .resumo h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .resumo-linha {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .resumo-total {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .empty-cart {
            background: white;
            padding: 60px;
            border-radius: 15px;
            text-align: center;
        }

        .empty-cart i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .item-card {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .item-actions {
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shopping-cart"></i> Meu Carrinho</h1>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Continuar Comprando
            </a>
        </div>

        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['erro']; unset($_SESSION['erro']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($itens)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Seu carrinho está vazio</h2>
                <p>Adicione alguns produtos deliciosos!</p>
                <a href="index.php" class="btn btn-success" style="margin-top: 20px;">
                    <i class="fas fa-hamburger"></i> Ver Cardápio
                </a>
            </div>
        <?php else: ?>
            <div class="carrinho-grid">
                <?php foreach ($itens as $item): ?>
                    <div class="item-card">
                        <div>
                            <?php if ($item['imagem'] && file_exists($item['imagem'])): ?>
                                <img src="<?= $item['imagem'] ?>" alt="<?= htmlspecialchars($item['nome']) ?>" class="item-imagem">
                            <?php else: ?>
                                <div class="item-imagem" style="display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-hamburger" style="font-size: 32px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="item-info">
                            <h3><?= htmlspecialchars($item['nome']) ?></h3>
                            <p><?= htmlspecialchars($item['descricao']) ?></p>
                            <div class="item-preco">
                                R$ <?= number_format($item['preco'], 2, ',', '.') ?> x <?= $item['quantidade'] ?> = 
                                R$ <?= number_format($item['subtotal'], 2, ',', '.') ?>
                            </div>
                        </div>

                        <div class="item-actions">
                            <form method="POST" class="quantidade-control">
                                <input type="hidden" name="id_item" value="<?= $item['id'] ?>">
                                <button type="button" onclick="diminuir(<?= $item['id'] ?>)">-</button>
                                <input type="number" 
                                       id="qtd_<?= $item['id'] ?>" 
                                       name="quantidade" 
                                       value="<?= $item['quantidade'] ?>" 
                                       min="1" 
                                       readonly>
                                <button type="button" onclick="aumentar(<?= $item['id'] ?>)">+</button>
                                <button type="submit" name="atualizar_quantidade" style="display: none;" id="btn_<?= $item['id'] ?>">Atualizar</button>
                            </form>

                            <a href="?remover=<?= $item['id'] ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Remover este item do carrinho?')" 
                               style="font-size: 12px;">
                                <i class="fas fa-trash"></i> Remover
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="resumo">
                <h2>Resumo do Pedido</h2>
                
                <div class="resumo-linha">
                    <span>Subtotal:</span>
                    <span>R$ <?= number_format($total, 2, ',', '.') ?></span>
                </div>

                <div class="resumo-total">
                    <span>Total:</span>
                    <span>R$ <?= number_format($total, 2, ',', '.') ?></span>
                </div>

                <a href="finalizar_carrinho.php" class="btn btn-success" style="width: 100%; justify-content: center; font-size: 18px; padding: 18px;">
                    <i class="fas fa-check-circle"></i> Finalizar Pedido
                </a>

                <a href="?limpar=1" 
                   class="btn btn-danger" 
                   onclick="return confirm('Limpar todo o carrinho?')" 
                   style="width: 100%; justify-content: center; margin-top: 10px;">
                    <i class="fas fa-trash"></i> Limpar Carrinho
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function aumentar(id) {
            const input = document.getElementById('qtd_' + id);
            input.value = parseInt(input.value) + 1;
            document.getElementById('btn_' + id).click();
        }

        function diminuir(id) {
            const input = document.getElementById('qtd_' + id);
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                document.getElementById('btn_' + id).click();
            }
        }
    </script>
</body>
</html>
