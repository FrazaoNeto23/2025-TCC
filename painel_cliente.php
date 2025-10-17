<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$msg = "";
$id_cliente = $_SESSION['id_usuario'];

// ===== VERIFICAR E ADICIONAR COLUNA OBSERVACOES SE NÃO EXISTIR =====
$check_column = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'observacoes'");
if ($check_column->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE pedidos ADD COLUMN observacoes TEXT AFTER metodo_pagamento");
    } catch (Exception $e) {
        // Ignorar se der erro
    }
}

// ===== SISTEMA DE RESET AUTOMÁTICO =====
if (!isset($_SESSION['reset_verificado_hoje'])) {
    $hoje = date('Y-m-d');

    // Verificar se já resetou hoje
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM system_logs 
        WHERE tipo = 'reset_diario' 
        AND DATE(created_at) = ?
    ");
    $stmt->bind_param("s", $hoje);
    $stmt->execute();
    $reset_hoje = $stmt->get_result()->fetch_assoc()['total'];

    if ($reset_hoje == 0) {
        try {
            $conn->begin_transaction();

            // 1. Criar tabela de backup para hoje
            $backup_table = 'pedidos_backup_' . date('Y_m_d');
            $conn->query("CREATE TABLE IF NOT EXISTS `$backup_table` LIKE pedidos");

            // 2. Fazer backup dos pedidos finalizados
            $conn->query("
                INSERT INTO `$backup_table` 
                SELECT * FROM pedidos 
                WHERE status = 'Entregue' AND status_pagamento = 'Pago'
            ");

            // 3. Limpar pedidos finalizados (mais de 2 horas)
            $stmt = $conn->prepare("
                DELETE FROM pedidos 
                WHERE status = 'Entregue' 
                AND status_pagamento = 'Pago' 
                AND data < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ");
            $stmt->execute();
            $pedidos_removidos = $stmt->affected_rows;

            // 4. Resetar auto increment para começar do 1
            $conn->query("ALTER TABLE pedidos AUTO_INCREMENT = 1");

            // 5. Registrar o reset no log
            $stmt = $conn->prepare("
                INSERT INTO system_logs (tipo, nivel, status, mensagem, dados) 
                VALUES ('reset_diario', 'INFO', 'sucesso', 'Reset automático executado', ?)
            ");
            $dados_log = json_encode([
                'cliente_id' => $id_cliente,
                'pedidos_removidos' => $pedidos_removidos,
                'backup_table' => $backup_table,
                'timestamp' => time()
            ]);
            $stmt->bind_param("s", $dados_log);
            $stmt->execute();

            $conn->commit();

            // Marcar como verificado para esta sessão
            $_SESSION['reset_verificado_hoje'] = true;

            if ($pedidos_removidos > 0) {
                $msg = "🔄 Numeração de pedidos reiniciada! $pedidos_removidos pedidos anteriores foram arquivados.";
            } else {
                $msg = "🔄 Sistema pronto! Os próximos pedidos começarão do #1.";
            }

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Erro no reset automático: " . $e->getMessage());
        }
    } else {
        $_SESSION['reset_verificado_hoje'] = true;
    }
}

// Adicionar ao carrinho
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);
    $tipo_produto = $_POST['tipo_produto'];

    // Verificar se já existe no carrinho
    $stmt = $conn->prepare("
        SELECT id, quantidade FROM carrinho 
        WHERE id_cliente=? AND id_produto=? AND tipo_produto=?
    ");
    $stmt->bind_param("iis", $id_cliente, $id_produto, $tipo_produto);
    $stmt->execute();
    $verifica = $stmt->get_result();

    if ($verifica->num_rows > 0) {
        // Atualizar quantidade
        $item = $verifica->fetch_assoc();
        $nova_qtd = $item['quantidade'] + $quantidade;
        $stmt = $conn->prepare("UPDATE carrinho SET quantidade=? WHERE id=?");
        $stmt->bind_param("ii", $nova_qtd, $item['id']);
        $stmt->execute();
    } else {
        // Adicionar novo item
        $stmt = $conn->prepare("
            INSERT INTO carrinho (id_cliente, id_produto, quantidade, tipo_produto) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiis", $id_cliente, $id_produto, $quantidade, $tipo_produto);
        $stmt->execute();
    }

    $msg = "✅ Produto adicionado ao carrinho!";
}

// Cancelar pedido
if (isset($_GET['cancelar'])) {
    $id_pedido = intval($_GET['cancelar']);
    $stmt = $conn->prepare("
        DELETE FROM pedidos 
        WHERE id=? AND id_cliente=? AND status='Pendente'
    ");
    $stmt->bind_param("ii", $id_pedido, $id_cliente);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $msg = "❌ Pedido cancelado com sucesso!";
    }
}

// Mensagem de sucesso do pagamento
if (isset($_SESSION['pagamento_sucesso'])) {
    $msg = $_SESSION['pagamento_sucesso'];
    unset($_SESSION['pagamento_sucesso']);
}

// Buscar produtos normais
$produtos = $conn->query("SELECT * FROM produtos ORDER BY nome");

// Buscar produtos especiais
$produtos_especiais = $conn->query("SELECT * FROM produtos_especiais ORDER BY nome");

// Buscar pedidos do cliente (com verificação da coluna observacoes)
$pedidos_query = "
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
";

$pedidos = $conn->query($pedidos_query);

// Contar itens no carrinho
$count_carrinho = $conn->query("
    SELECT COUNT(*) as total FROM carrinho WHERE id_cliente=$id_cliente
")->fetch_assoc()['total'];
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
        .reset-notification {
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

        .reset-notification i {
            font-size: 24px;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .produtos-especiais {
            margin-bottom: 40px;
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

        .produto.especial h3 {
            color: #ffa500;
        }

        .pedido-numero-novo {
            background: linear-gradient(135deg, #0ff, #00d4d4);
            color: #121212;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 10px;
        }

        .pedido-numero-antigo {
            background: #666;
            color: #fff;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 10px;
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

        .btn-carrinho:hover {
            background: linear-gradient(135deg, #00d4d4, #0ff);
            box-shadow: 0 8px 25px rgba(0, 255, 255, 0.6);
            transform: translateY(-2px);
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

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .btn-add-carrinho {
            background: linear-gradient(135deg, #0ff, #00d4d4);
            color: #121212;
        }

        .btn-add-carrinho:hover {
            background: linear-gradient(135deg, #00d4d4, #0ff);
            box-shadow: 0 0 15px #0ff;
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
            <div class="reset-notification">
                <i class="fa fa-info-circle"></i>
                <span><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

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
                            <button type="submit" name="adicionar_carrinho" class="btn-comprar btn-add-carrinho">
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
                            <button type="submit" name="adicionar_carrinho" class="btn-comprar btn-add-carrinho">
                                <i class="fa fa-cart-plus"></i> Adicionar ao Carrinho
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Nenhum produto disponível no momento.</p>
            <?php endif; ?>
        </div>

        <h2><i class="fa fa-receipt"></i> Meus Pedidos</h2>

        <?php if ($pedidos && $pedidos->num_rows > 0): ?>
            <div class="pedidos-lista">
                <?php while ($pd = $pedidos->fetch_assoc()):
                    $status_class = '';
                    $status_icon = 'fa-clock';
                    $is_new_numbering = !str_starts_with($pd['numero_exibicao'], 'OLD-');

                    if ($pd['status'] == 'Em preparo') {
                        $status_class = 'status-preparo';
                        $status_icon = 'fa-fire';
                    } elseif ($pd['status'] == 'Entregando') {
                        $status_class = 'status-entregando';
                        $status_icon = 'fa-truck';
                    } elseif ($pd['status'] == 'Entregue') {
                        $status_class = 'status-entregue';
                        $status_icon = 'fa-check-circle';
                    }

                    $pagamento_class = $pd['status_pagamento'] == 'Pago' ? 'pago' : 'pendente';
                    ?>
                    <div class="pedido-card <?= $status_class ?>">
                        <div class="pedido-header">
                            <div>
                                <?php if ($is_new_numbering): ?>
                                    <span class="pedido-numero-novo">Pedido #<?= $pd['numero_exibicao'] ?></span>
                                <?php else: ?>
                                    <span class="pedido-numero-antigo">Pedido #<?= $pd['numero_exibicao'] ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="pedido-data">
                                <i class="fa fa-calendar"></i>
                                <?= date('d/m/Y H:i', strtotime($pd['data'])) ?>
                            </span>
                        </div>

                        <div class="pedido-info">
                            <?php if ($pd['imagem'] && file_exists("uploads/" . $pd['imagem'])): ?>
                                <img src="uploads/<?= $pd['imagem'] ?>" alt="<?= $pd['produto_nome'] ?>" class="pedido-img">
                            <?php endif; ?>
                            <div class="pedido-detalhes">
                                <h3><?= htmlspecialchars($pd['produto_nome'] ?? 'Produto não encontrado') ?></h3>
                                <?php if ($pd['numero_mesa']): ?>
                                    <p><i class="fa fa-table"></i> Mesa: <?= $pd['numero_mesa'] ?></p>
                                <?php else: ?>
                                    <p><i class="fa fa-motorcycle"></i> Delivery</p>
                                <?php endif; ?>
                                <p><i class="fa fa-box"></i> Quantidade: <?= $pd['quantidade'] ?></p>
                                <p class="pedido-total">
                                    <i class="fa fa-dollar-sign"></i> Total: R$ <?= number_format($pd['total'], 2, ',', '.') ?>
                                </p>

                                <?php
                                // Verificar se a coluna observacoes existe e tem conteúdo
                                $observacoes = isset($pd['observacoes']) ? $pd['observacoes'] : null;
                                if ($observacoes):
                                    ?>
                                    <p><i class="fa fa-comment"></i> <?= htmlspecialchars($observacoes) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pedido-footer">
                            <span class="status-badge <?= $status_class ?>">
                                <i class="fa <?= $status_icon ?>"></i> <?= $pd['status'] ?>
                            </span>
                            <span class="pagamento-badge <?= $pagamento_class ?>">
                                <i class="fa <?= $pd['status_pagamento'] == 'Pago' ? 'fa-check' : 'fa-clock' ?>"></i>
                                <?= $pd['status_pagamento'] ?>
                            </span>

                            <?php if (!empty($pd['metodo_pagamento'])): ?>
                                <p class="metodo-pagamento">
                                    <i class="fa fa-info-circle"></i> Método: <?= ucfirst($pd['metodo_pagamento']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="pedido-acoes">
                                <?php if ($pd['status'] == 'Pendente' && $pd['status_pagamento'] == 'Aguardando'): ?>
                                    <a href="pagamento.php?pedido_id=<?= $pd['id'] ?>" class="btn-pagar">
                                        <i class="fa fa-credit-card"></i> Pagar
                                    </a>
                                <?php endif; ?>

                                <?php if ($pd['status'] == 'Pendente'): ?>
                                    <a href="?cancelar=<?= $pd['id'] ?>" class="btn-cancelar"
                                        onclick="return confirm('Deseja cancelar este pedido?')">
                                        <i class="fa fa-times"></i> Cancelar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="sem-pedidos">
                <i class="fa fa-shopping-bag"></i>
                <p>Você ainda não fez nenhum pedido</p>
            </div>
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

        // Auto-hide notification after 10 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const notification = document.querySelector('.reset-notification');
            if (notification) {
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateY(-20px)';
                    setTimeout(() => notification.remove(), 500);
                }, 10000);
            }
        });
    </script>
</body>

</html>