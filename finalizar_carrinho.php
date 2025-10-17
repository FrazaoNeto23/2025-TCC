<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$id_cliente = $_SESSION['id_usuario'];

// ===== VERIFICAR E ADICIONAR COLUNAS SE NÃO EXISTIREM =====
$check_numero = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'numero_pedido'");
if ($check_numero->num_rows == 0) {
    $conn->query("ALTER TABLE pedidos ADD COLUMN numero_pedido VARCHAR(20) AFTER id");
    $conn->query("ALTER TABLE pedidos ADD INDEX idx_numero_pedido (numero_pedido)");
}

$check_obs = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'observacoes'");
if ($check_obs->num_rows == 0) {
    $conn->query("ALTER TABLE pedidos ADD COLUMN observacoes TEXT AFTER metodo_pagamento");
}

// ===== FUNÇÃO PARA GERAR NUMERAÇÃO SEQUENCIAL =====
function gerarNumeroSequencial($conn)
{
    $prefixo = date('Ymd'); // Ex: 20251017

    $stmt = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(numero_pedido, '-', -1) AS UNSIGNED)) as ultimo_numero
        FROM pedidos 
        WHERE numero_pedido LIKE ?
    ");

    $like_pattern = $prefixo . '-%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();

    $proximo_numero = ($resultado['ultimo_numero'] ?? 0) + 1;

    return $prefixo . '-' . str_pad($proximo_numero, 3, '0', STR_PAD_LEFT);
}

// ===== PROCESSAR PAGAMENTO =====
if (isset($_POST['processar_pagamento'])) {
    $metodo = $_POST['metodo_pagamento'] ?? '';
    $numero_mesa = isset($_POST['numero_mesa']) && $_POST['numero_mesa'] ? intval($_POST['numero_mesa']) : null;

    if (empty($metodo)) {
        $erro = "Por favor, selecione um método de pagamento!";
    } else {
        // Buscar itens do carrinho
        $itens = $conn->query("
            SELECT carrinho.*, 
                   CASE 
                       WHEN carrinho.tipo_produto = 'normal' THEN produtos.preco
                       WHEN carrinho.tipo_produto = 'especial' THEN produtos_especiais.preco
                   END as produto_preco,
                   CASE 
                       WHEN carrinho.tipo_produto = 'normal' THEN produtos.nome
                       WHEN carrinho.tipo_produto = 'especial' THEN produtos_especiais.nome
                   END as produto_nome
            FROM carrinho
            LEFT JOIN produtos ON carrinho.id_produto = produtos.id AND carrinho.tipo_produto = 'normal'
            LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id AND carrinho.tipo_produto = 'especial'
            WHERE carrinho.id_cliente = $id_cliente
        ");

        if ($itens->num_rows > 0) {
            $pedidos_criados = [];
            $total_geral = 0;

            try {
                $conn->begin_transaction();

                // Gerar um único número para todos os itens deste pedido
                $numero_pedido = gerarNumeroSequencial($conn);

                // Criar pedidos para cada item do carrinho
                while ($item = $itens->fetch_assoc()) {
                    $total = $item['produto_preco'] * $item['quantidade'];
                    $total_geral += $total;

                    $observacoes = "Pagamento via " . $metodo . " - Produto: " . $item['produto_nome'];

                    $stmt = $conn->prepare("
                        INSERT INTO pedidos 
                        (numero_pedido, id_cliente, numero_mesa, id_produto, tipo_produto, quantidade, total, status, status_pagamento, metodo_pagamento, observacoes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', 'Pago', ?, ?)
                    ");

                    $stmt->bind_param(
                        "siisisdsss",
                        $numero_pedido,
                        $id_cliente,
                        $numero_mesa,
                        $item['id_produto'],
                        $item['tipo_produto'],
                        $item['quantidade'],
                        $total,
                        $metodo,
                        $observacoes
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Erro ao criar pedido: " . $stmt->error);
                    }

                    $pedidos_criados[] = $conn->insert_id;
                }

                // Limpar carrinho
                $conn->query("DELETE FROM carrinho WHERE id_cliente = $id_cliente");

                // Registrar transação no log
                $transacao_id = 'TXN_' . time() . '_' . $id_cliente;
                $stmt = $conn->prepare("
                    INSERT INTO system_logs (tipo, nivel, status, mensagem, dados) 
                    VALUES ('pagamento', 'INFO', 'sucesso', 'Pagamento processado', ?)
                ");

                $dados_transacao = json_encode([
                    'transacao_id' => $transacao_id,
                    'cliente_id' => $id_cliente,
                    'metodo' => $metodo,
                    'total' => $total_geral,
                    'numero_pedido' => $numero_pedido,
                    'pedidos_ids' => $pedidos_criados,
                    'numero_mesa' => $numero_mesa,
                    'timestamp' => time()
                ]);

                $stmt->bind_param("s", $dados_transacao);
                $stmt->execute();

                $conn->commit();

                // Mensagem de sucesso personalizada
                if ($numero_mesa) {
                    $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$numero_pedido confirmado! Em breve será servido na mesa $numero_mesa.";
                } else {
                    $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$numero_pedido confirmado! Em breve estará na sua casa. Obrigado pela preferência!";
                }

                header("Location: painel_cliente.php");
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $erro = "Erro ao processar pagamento: " . $e->getMessage();
                error_log("Erro finalizar_carrinho.php: " . $e->getMessage());
            }
        } else {
            $erro = "Carrinho vazio!";
        }
    }
}

// Buscar itens do carrinho para exibir
$itens_carrinho = $conn->query("
    SELECT carrinho.*, 
           CASE 
               WHEN carrinho.tipo_produto = 'normal' THEN produtos.nome
               WHEN carrinho.tipo_produto = 'especial' THEN produtos_especiais.nome
           END as produto_nome,
           CASE 
               WHEN carrinho.tipo_produto = 'normal' THEN produtos.preco
               WHEN carrinho.tipo_produto = 'especial' THEN produtos_especiais.preco
           END as produto_preco,
           CASE 
               WHEN carrinho.tipo_produto = 'normal' THEN produtos.imagem
               WHEN carrinho.tipo_produto = 'especial' THEN produtos_especiais.imagem
           END as produto_imagem
    FROM carrinho
    LEFT JOIN produtos ON carrinho.id_produto = produtos.id AND carrinho.tipo_produto = 'normal'
    LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id AND carrinho.tipo_produto = 'especial'
    WHERE carrinho.id_cliente = $id_cliente
");

if ($itens_carrinho->num_rows == 0) {
    header("Location: carrinho.php");
    exit;
}

$total_carrinho = 0;
$itens_array = [];
while ($item = $itens_carrinho->fetch_assoc()) {
    $subtotal = $item['produto_preco'] * $item['quantidade'];
    $total_carrinho += $subtotal;
    $itens_array[] = $item;
}

// Verificar próximo número de pedido para preview
$proximo_numero = gerarNumeroSequencial($conn);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Finalizar Pedido - Burger House</title>
    <link rel="stylesheet" href="css/pagamento.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .preview-numero {
            background: linear-gradient(135deg, #0ff, #00d4d4);
            color: #121212;
            padding: 10px 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
            border: 2px solid #0ff;
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            }

            to {
                box-shadow: 0 0 20px rgba(0, 255, 255, 0.8);
            }
        }

        .erro-msg {
            background: #ff4c4c;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .item-resumo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="carrinho.php" class="btn-voltar"><i class="fa fa-arrow-left"></i> Voltar ao Carrinho</a>

        <h1><i class="fa fa-credit-card"></i> Finalizar Pedido</h1>

        <div class="preview-numero">
            <i class="fa fa-hashtag"></i> Seu pedido será: <strong>#<?= $proximo_numero ?></strong>
        </div>

        <?php if (isset($erro)): ?>
            <div class="erro-msg">
                <i class="fa fa-exclamation-triangle"></i>
                <span><?= htmlspecialchars($erro) ?></span>
            </div>
        <?php endif; ?>

        <div class="pedido-resumo">
            <h2><i class="fa fa-receipt"></i> Resumo do Pedido</h2>
            <?php foreach ($itens_array as $item): ?>
                <div class="item-resumo">
                    <?php if ($item['produto_imagem'] && file_exists("uploads/" . $item['produto_imagem'])): ?>
                        <img src="uploads/<?= $item['produto_imagem'] ?>" alt="<?= $item['produto_nome'] ?>"
                            style="width: 50px; height: 50px; border-radius: 8px;">
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <strong><?= htmlspecialchars($item['produto_nome']) ?></strong>
                        <?php if ($item['tipo_produto'] == 'especial'): ?>
                            <span
                                style="background: #ffa500; color: #121212; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px;">⭐
                                ESPECIAL</span>
                        <?php endif; ?>
                        <br>
                        <span style="color: #aaa;">Qtd: <?= $item['quantidade'] ?> × R$
                            <?= number_format($item['produto_preco'], 2, ',', '.') ?></span>
                        <br>
                        <strong style="color: #0ff;">Subtotal: R$
                            <?= number_format($item['produto_preco'] * $item['quantidade'], 2, ',', '.') ?></strong>
                    </div>
                </div>
                <hr style="border: none; border-top: 1px solid #333; margin: 10px 0;">
            <?php endforeach; ?>
            <p class="total"><strong>Total Geral: R$ <?= number_format($total_carrinho, 2, ',', '.') ?></strong></p>
        </div>

        <div class="metodos-pagamento">
            <h2><i class="fa fa-payment"></i> Escolha o método de pagamento</h2>

            <form method="POST" id="payment-form">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="numero_mesa"
                        style="display: block; color: #0ff; font-weight: bold; margin-bottom: 10px;">
                        <i class="fa fa-table"></i> Número da Mesa (opcional)
                    </label>
                    <input type="number" name="numero_mesa" id="numero_mesa" placeholder="Digite o número da mesa"
                        min="1" max="999"
                        style="width: 100%; padding: 12px; background: #121212; border: 2px solid #0ff; border-radius: 8px; color: #fff;">
                    <small style="color: #aaa; display: block; margin-top: 5px;">Deixe em branco para delivery</small>
                </div>

                <div class="metodo-card" onclick="selecionarMetodo('pix')">
                    <input type="radio" name="metodo_pagamento" id="pix" value="pix" required>
                    <label for="pix">
                        <i class="fa fa-qrcode"></i>
                        <span>PIX</span>
                    </label>
                </div>

                <div class="metodo-card" onclick="selecionarMetodo('cartao')">
                    <input type="radio" name="metodo_pagamento" id="cartao" value="cartao" required>
                    <label for="cartao">
                        <i class="fa fa-credit-card"></i>
                        <span>Cartão de Crédito</span>
                    </label>
                </div>

                <div class="metodo-card" onclick="selecionarMetodo('dinheiro')">
                    <input type="radio" name="metodo_pagamento" id="dinheiro" value="dinheiro" required>
                    <label for="dinheiro">
                        <i class="fa fa-money-bill"></i>
                        <span>Dinheiro</span>
                    </label>
                </div>

                <div id="area-pix" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-qrcode"></i> Pagamento via PIX</h3>
                    <div class="pix-info">
                        <p><i class="fa fa-info-circle"></i> Sistema demonstrativo - pedido será criado como pago</p>
                    </div>
                </div>

                <div id="area-cartao" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-credit-card"></i> Dados do Cartão</h3>
                    <div style="background: #2a2a2a; padding: 20px; border-radius: 10px;">
                        <p><i class="fa fa-info-circle"></i> Sistema demonstrativo - pedido será criado como pago</p>
                    </div>
                </div>

                <div id="area-dinheiro" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-money-bill"></i> Pagamento em Dinheiro</h3>
                    <div style="background: #2a2a2a; padding: 20px; border-radius: 10px;">
                        <p><i class="fa fa-motorcycle"></i> Você pagará na entrega</p>
                    </div>
                </div>

                <button href="painel_cliente.php" type="submit" name="processar_pagamento" id="btn-confirmar" disabled>
                    <i class="fa fa-check"></i> Confirmar Pagamento - R$
                    <?= number_format($total_carrinho, 2, ',', '.') ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        const mesaSalva = localStorage.getItem('numero_mesa');
        if (mesaSalva) {
            document.getElementById('numero_mesa').value = mesaSalva;
        }

        document.getElementById('numero_mesa').addEventListener('input', function () {
            if (this.value) {
                localStorage.setItem('numero_mesa', this.value);
            } else {
                localStorage.removeItem('numero_mesa');
            }
        });

        function selecionarMetodo(metodo) {
            document.getElementById(metodo).checked = true;

            document.querySelectorAll('.area-pagamento').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.metodo-card').forEach(el => el.classList.remove('selected'));

            event.currentTarget.classList.add('selected');
            document.getElementById('area-' + metodo).style.display = 'block';
            document.getElementById('btn-confirmar').disabled = false;
        }

        document.getElementById('payment-form').addEventListener('submit', function () {
            const btn = document.getElementById('btn-confirmar');
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';
            btn.disabled = true;
        });
    </script>
</body>

</html>