<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$msg = "";
$id_cliente = $_SESSION['id_usuario'];

// ===== ADICIONAR AO CARRINHO - CORREÇÃO ESPECÍFICA =====
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);
    $tipo_produto = $_POST['tipo_produto']; // 'normal' ou 'especial'

    try {
        // 1. VALIDAR SE O PRODUTO EXISTE
        $produto_valido = false;

        if ($tipo_produto == 'normal') {
            $check = $conn->prepare("SELECT id FROM produtos WHERE id = ?");
            $check->bind_param("i", $id_produto);
            $check->execute();
            $produto_valido = $check->get_result()->num_rows > 0;
        } elseif ($tipo_produto == 'especial') {
            $check = $conn->prepare("SELECT id FROM produtos_especiais WHERE id = ?");
            $check->bind_param("i", $id_produto);
            $check->execute();
            $produto_valido = $check->get_result()->num_rows > 0;
        }

        if (!$produto_valido) {
            $msg = "❌ Produto não encontrado!";
        } else {
            // 2. VERIFICAR SE JÁ EXISTE NO CARRINHO
            $stmt = $conn->prepare("
                SELECT id, quantidade FROM carrinho 
                WHERE id_cliente=? AND id_produto=? AND tipo_produto=?
            ");
            $stmt->bind_param("iis", $id_cliente, $id_produto, $tipo_produto);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {
                // 3. ATUALIZAR QUANTIDADE EXISTENTE
                $item_existente = $resultado->fetch_assoc();
                $nova_quantidade = $item_existente['quantidade'] + $quantidade;

                $update_stmt = $conn->prepare("UPDATE carrinho SET quantidade=? WHERE id=?");
                $update_stmt->bind_param("ii", $nova_quantidade, $item_existente['id']);

                if ($update_stmt->execute()) {
                    $msg = "✅ Quantidade atualizada no carrinho!";
                } else {
                    $msg = "❌ Erro ao atualizar carrinho: " . $conn->error;
                }
            } else {
                // 4. INSERIR NOVO ITEM NO CARRINHO
                $insert_stmt = $conn->prepare("
                    INSERT INTO carrinho (id_cliente, id_produto, quantidade, tipo_produto, data_adicao) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert_stmt->bind_param("iiis", $id_cliente, $id_produto, $quantidade, $tipo_produto);

                if ($insert_stmt->execute()) {
                    $nome_produto = "";
                    if ($tipo_produto == 'normal') {
                        $nome_result = $conn->query("SELECT nome FROM produtos WHERE id = $id_produto");
                        $nome_produto = $nome_result->fetch_assoc()['nome'];
                    } else {
                        $nome_result = $conn->query("SELECT nome FROM produtos_especiais WHERE id = $id_produto");
                        $nome_produto = $nome_result->fetch_assoc()['nome'];
                    }

                    $msg = "✅ $nome_produto adicionado ao carrinho!";
                } else {
                    $msg = "❌ Erro ao adicionar ao carrinho: " . $conn->error;
                }
            }
        }

    } catch (Exception $e) {
        $msg = "❌ Erro no sistema: " . $e->getMessage();

        // Log do erro para debug
        error_log("Erro no carrinho - Cliente: $id_cliente, Produto: $id_produto, Tipo: $tipo_produto, Erro: " . $e->getMessage());
    }
}

// ===== SISTEMA DE RESET AUTOMÁTICO (simplificado) =====
if (!isset($_SESSION['reset_verificado_hoje'])) {
    $hoje = date('Y-m-d');

    // Verificar se existe a tabela system_logs
    $table_check = $conn->query("SHOW TABLES LIKE 'system_logs'");
    if ($table_check->num_rows == 0) {
        // Criar tabela se não existir
        $conn->query("
            CREATE TABLE system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(50),
                nivel VARCHAR(20) DEFAULT 'INFO',
                status VARCHAR(20),
                mensagem TEXT,
                dados JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // Verificar se já resetou hoje
    $reset_check = $conn->query("
        SELECT COUNT(*) as total 
        FROM system_logs 
        WHERE tipo = 'reset_diario' 
        AND DATE(created_at) = '$hoje'
    ");

    $reset_hoje = $reset_check ? $reset_check->fetch_assoc()['total'] : 0;

    if ($reset_hoje == 0) {
        try {
            // Fazer backup e reset simples
            $backup_table = 'pedidos_backup_' . date('Y_m_d');
            $conn->query("CREATE TABLE IF NOT EXISTS `$backup_table` LIKE pedidos");
            $conn->query("INSERT INTO `$backup_table` SELECT * FROM pedidos WHERE status = 'Entregue'");
            $conn->query("DELETE FROM pedidos WHERE status = 'Entregue' AND data < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
            $conn->query("ALTER TABLE pedidos AUTO_INCREMENT = 1");
            $conn->query("INSERT INTO system_logs (tipo, status, mensagem) VALUES ('reset_diario', 'sucesso', 'Reset automático')");

            $_SESSION['reset_verificado_hoje'] = true;
            $msg = "🔄 Sistema reiniciado para o dia!";

        } catch (Exception $e) {
            // Ignorar erros de reset para não quebrar o sistema
            $_SESSION['reset_verificado_hoje'] = true;
        }
    } else {
        $_SESSION['reset_verificado_hoje'] = true;
    }
}

// Cancelar pedido
if (isset($_GET['cancelar'])) {
    $id_pedido = intval($_GET['cancelar']);
    $stmt = $conn->prepare("DELETE FROM pedidos WHERE id=? AND id_cliente=? AND status='Pendente'");
    $stmt->bind_param("ii", $id_pedido, $id_cliente);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $msg = "❌ Pedido cancelado com sucesso!";
    }
}

// Mensagem de sucesso do pagamento
if (isset($_SESSION['pagamento_sucesso'])) {
    $msg = $_SESSION['pagamento_sucesso'];
    unset($_SESSION['pagamento_sucesso']);
}

// Buscar produtos
$produtos = $conn->query("SELECT * FROM produtos ORDER BY nome");
$produtos_especiais = $conn->query("SELECT * FROM produtos_especiais ORDER BY nome");

// Buscar pedidos
$pedidos = $conn->query("
    SELECT pedidos.*, 
           CASE 
               WHEN pedidos.tipo_produto = 'normal' THEN produtos.nome
               WHEN pedidos.tipo_produto = 'especial' THEN produtos_especiais.nome
           END as produto_nome,
           CASE 
               WHEN pedidos.tipo_produto = 'normal' THEN produtos.imagem
               WHEN pedidos.tipo_produto = 'especial' THEN produtos_especiais.imagem
           END as imagem,
           COALESCE(pedidos.numero_pedido, CONCAT('OLD-', pedidos.id)) as numero_exibicao
    FROM pedidos 
    LEFT JOIN produtos ON pedidos.id_produto = produtos.id AND pedidos.tipo_produto = 'normal'
    LEFT JOIN produtos_especiais ON pedidos.id_produto = produtos_especiais.id AND pedidos.tipo_produto = 'especial'
    WHERE pedidos.id_cliente = $id_cliente
    ORDER BY pedidos.data DESC
");

// Contar itens no carrinho
$count_carrinho = $conn->query("SELECT COUNT(*) as total FROM carrinho WHERE id_cliente=$id_cliente")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Painel do Cliente - Burger House</title>
    <link rel="stylesheet" href="css/cliente.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .msg-notification {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #2e7d32;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            animation: slideInDown 0.5s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .msg-error {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            border-left-color: #c62828;
            box-shadow: 0 4px 15px rgba(244, 67, 54, 0.3);
        }

        .btn-carrinho {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #0ff, #00d4d4);
            color: #121212;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: 0.3s;
            position: relative;
            box-shadow: 0 5px 15px rgba(0, 255, 255, 0.4);
        }

        .carrinho-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4c4c;
            color: #fff;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        .produto.especial {
            border: 2px solid #ffa500;
            box-shadow: 0 0 20px rgba(255, 165, 0, 0.3);
            position: relative;
        }

        .produto.especial::before {
            content: "⭐ ESPECIAL";
            position: absolute;
            top: -10px;
            right: 10px;
            background: #ffa500;
            color: #121212;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }

        .debug-info {
            background: #1e1e1e;
            border: 2px solid #ff9800;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header-cliente">
            <h1><i class="fa fa-user-circle"></i> Bem-vindo, <?php echo $_SESSION['usuario']; ?>!</h1>
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="carrinho.php" class="btn-carrinho">
                    <i class="fa fa-shopping-cart"></i> Carrinho
                    <?php if ($count_carrinho > 0): ?>
                        <span class="carrinho-badge"><?= $count_carrinho ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="btn-sair"><i class="fa fa-right-from-bracket"></i> Sair</a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="msg-notification <?= str_contains($msg, '❌') ? 'msg-error' : '' ?>">
                <i class="fa <?= str_contains($msg, '❌') ? 'fa-exclamation-triangle' : 'fa-info-circle' ?>"></i>
                <span><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Debug removido - sistema funcionando! -->

        <!-- Produtos Especiais -->
        <?php if ($produtos_especiais && $produtos_especiais->num_rows > 0): ?>
            <h2><i class="fa fa-star"></i> Cardápio Especial</h2>
            <div class="produtos produtos-especiais">
                <?php while ($pe = $produtos_especiais->fetch_assoc()): ?>
                    <div class="produto especial">
                        <?php if ($pe['imagem'] && file_exists("uploads/" . $pe['imagem'])): ?>
                            <img src="uploads/<?= $pe['imagem'] ?>" alt="<?= $pe['nome'] ?>">
                        <?php else: ?>
                            <div class="sem-imagem"><i class="fa fa-star"></i></div>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($pe['nome']) ?></h3>
                        <p class="descricao"><?= htmlspecialchars($pe['descricao'] ?? '') ?></p>
                        <p class="preco">R$ <?= number_format($pe['preco'], 2, ',', '.') ?></p>

                        <form method="POST">
                            <input type="hidden" name="id_produto" value="<?= $pe['id'] ?>">
                            <input type="hidden" name="tipo_produto" value="especial">
                            <div class="qtd-control">
                                <button type="button" onclick="diminuir(this)"><i class="fa fa-minus"></i></button>
                                <input type="number" name="quantidade" value="1" min="1" max="99" readonly>
                                <button type="button" onclick="aumentar(this)"><i class="fa fa-plus"></i></button>
                            </div>
                            <button type="submit" name="adicionar_carrinho" class="btn-comprar">
                                <i class="fa fa-cart-plus"></i> Adicionar ao Carrinho
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- Produtos Normais -->
        <h2><i class="fa fa-utensils"></i> Cardápio</h2>
        <div class="produtos">
            <?php if ($produtos && $produtos->num_rows > 0): ?>
                <?php while ($p = $produtos->fetch_assoc()): ?>
                    <div class="produto">
                        <?php if ($p['imagem'] && file_exists("uploads/" . $p['imagem'])): ?>
                            <img src="uploads/<?= $p['imagem'] ?>" alt="<?= $p['nome'] ?>">
                        <?php else: ?>
                            <div class="sem-imagem"><i class="fa fa-image"></i></div>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($p['nome']) ?></h3>
                        <p class="descricao"><?= htmlspecialchars($p['descricao'] ?? '') ?></p>
                        <p class="preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>

                        <form method="POST">
                            <input type="hidden" name="id_produto" value="<?= $p['id'] ?>">
                            <input type="hidden" name="tipo_produto" value="normal">
                            <div class="qtd-control">
                                <button type="button" onclick="diminuir(this)"><i class="fa fa-minus"></i></button>
                                <input type="number" name="quantidade" value="1" min="1" max="99" readonly>
                                <button type="button" onclick="aumentar(this)"><i class="fa fa-plus"></i></button>
                            </div>
                            <button type="submit" name="adicionar_carrinho" class="btn-comprar">
                                <i class="fa fa-cart-plus"></i> Adicionar ao Carrinho
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Meus Pedidos -->
        <h2><i class="fa fa-receipt"></i> Meus Pedidos</h2>
        <?php if ($pedidos && $pedidos->num_rows > 0): ?>
            <div class="pedidos-lista">
                <?php while ($pd = $pedidos->fetch_assoc()): ?>
                    <div class="pedido-card">
                        <div class="pedido-header">
                            <span class="pedido-id">Pedido #<?= $pd['numero_exibicao'] ?></span>
                            <span class="pedido-data"><?= date('d/m/Y H:i', strtotime($pd['data'])) ?></span>
                        </div>
                        <div class="pedido-info">
                            <h3><?= htmlspecialchars($pd['produto_nome'] ?? 'Produto') ?></h3>
                            <p>Quantidade: <?= $pd['quantidade'] ?></p>
                            <p>Total: R$ <?= number_format($pd['total'], 2, ',', '.') ?></p>
                            <p>Status: <?= $pd['status'] ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>Nenhum pedido encontrado.</p>
        <?php endif; ?>
    </div>

    <script>
        function aumentar(btn) {
            const input = btn.parentElement.querySelector('input[type="number"]');
            if (input.value < 99) {
                input.value = parseInt(input.value) + 1;
            }
        }

        function diminuir(btn) {
            const input = btn.parentElement.querySelector('input[type="number"]');
            if (input.value > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }

        // Auto-hide notifications
        document.addEventListener('DOMContentLoaded', function () {
            const notifications = document.querySelectorAll('.msg-notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateY(-20px)';
                    setTimeout(() => notification.remove(), 500);
                }, 5000);
            });
        });
    </script>
</body>

</html>