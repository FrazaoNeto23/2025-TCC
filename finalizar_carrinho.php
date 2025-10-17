<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$id_cliente = $_SESSION['id_usuario'];

// ===== FUNÇÃO PARA GERAR NUMERAÇÃO SEQUENCIAL =====
function gerarNumeroSequencial($conn)
{
    $prefixo = date('Ymd'); // Ex: 20241017

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
    // Retorna: 20241017-001, 20241017-002, etc.
}

// ===== FUNÇÃO PARA CRIAR PEDIDO COM NUMERAÇÃO =====
function criarPedidoComNumeracao($conn, $dados)
{
    $numero_pedido = gerarNumeroSequencial($conn);

    $stmt = $conn->prepare("
        INSERT INTO pedidos 
        (numero_pedido, id_cliente, numero_mesa, id_produto, tipo_produto, quantidade, total, status, status_pagamento, metodo_pagamento, observacoes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', 'Pago', ?, ?)
    ");

    $stmt->bind_param(
        "siisisdsss",
        $numero_pedido,
        $dados['id_cliente'],
        $dados['numero_mesa'],
        $dados['id_produto'],
        $dados['tipo_produto'],
        $dados['quantidade'],
        $dados['total'],
        $dados['metodo_pagamento'],
        $dados['observacoes']
    );

    if ($stmt->execute()) {
        return $numero_pedido;
    }

    return false;
}

// Processar pagamento
if (isset($_POST['processar_pagamento'])) {
    $metodo = $_POST['metodo_pagamento'];
    $numero_mesa = isset($_POST['numero_mesa']) && $_POST['numero_mesa'] ? intval($_POST['numero_mesa']) : null;

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

            // Criar pedidos para cada item do carrinho
            while ($item = $itens->fetch_assoc()) {
                $total = $item['produto_preco'] * $item['quantidade'];
                $total_geral += $total;

                $observacoes = "Pagamento via " . $metodo . " - Produto: " . $item['produto_nome'];

                $dados_pedido = [
                    'id_cliente' => $id_cliente,
                    'numero_mesa' => $numero_mesa,
                    'id_produto' => $item['id_produto'],
                    'tipo_produto' => $item['tipo_produto'],
                    'quantidade' => $item['quantidade'],
                    'total' => $total,
                    'metodo_pagamento' => $metodo,
                    'observacoes' => $observacoes
                ];

                $numero_pedido = criarPedidoComNumeracao($conn, $dados_pedido);

                if ($numero_pedido) {
                    $pedidos_criados[] = $numero_pedido;
                } else {
                    throw new Exception("Erro ao criar pedido para " . $item['produto_nome']);
                }
            }

            // Limpar carrinho
            $conn->query("DELETE FROM carrinho WHERE id_cliente = $id_cliente");

            // Registrar transação
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
                'pedidos' => $pedidos_criados,
                'numero_mesa' => $numero_mesa
            ]);

            $stmt->bind_param("s", $dados_transacao);
            $stmt->execute();

            $conn->commit();

            // Mensagem de sucesso
            $pedidos_lista = implode(', #', $pedidos_criados);
            $_SESSION['pagamento_sucesso'] = "🎉 Pagamento realizado com sucesso! Pedidos criados: #$pedidos_lista";

            header("Location: painel_cliente.php");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $erro = "Erro ao processar pagamento: " . $e->getMessage();
        }
    } else {
        $erro = "Carrinho vazio!";
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

        .metodo-pix {
            border-color: #00cc55;
        }

        .metodo-cartao {
            border-color: #2196f3;
        }

        .metodo-dinheiro {
            border-color: #ff9800;
        }

        .metodo-pix.selected {
            background: rgba(0, 204, 85, 0.1);
        }

        .metodo-cartao.selected {
            background: rgba(33, 150, 243, 0.1);
        }

        .metodo-dinheiro.selected {
            background: rgba(255, 152, 0, 0.1);
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
    </style>
</head>

<body>
    <div class="container">
        <a href="carrinho.php" class="btn-voltar"><i class="fa fa-arrow-left"></i> Voltar ao Carrinho</a>

        <h1><i class="fa fa-credit-card"></i> Finalizar Pedido</h1>

        <!-- Preview do próximo número -->
        <div class="preview-numero">
            <i class="fa fa-hashtag"></i> Seu próximo pedido será: <strong>#<?= $proximo_numero ?></strong>
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
                            style="width: 50px; height: 50px; border-radius: 8px; margin-right: 10px;">
                    <?php endif; ?>
                    <div>
                        <strong><?= htmlspecialchars($item['produto_nome']) ?></strong>
                        <?php if ($item['tipo_produto'] == 'especial'): ?>
                            <span
                                style="background: #ffa500; color: #121212; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px;">⭐
                                ESPECIAL</span>
                        <?php endif; ?>
                        <br>
                        <span>Qtd: <?= $item['quantidade'] ?> × R$
                            <?= number_format($item['produto_preco'], 2, ',', '.') ?></span>
                        <br>
                        <strong>Subtotal: R$
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
                <!-- Campo mesa -->
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

                <!-- Métodos de pagamento -->
                <div class="metodo-card metodo-pix" onclick="selecionarMetodo('pix')">
                    <input type="radio" name="metodo_pagamento" id="pix" value="pix" required>
                    <label for="pix">
                        <i class="fa fa-qrcode"></i>
                        <span>PIX</span>
                        <small>Aprovação instantânea</small>
                    </label>
                </div>

                <div class="metodo-card metodo-cartao" onclick="selecionarMetodo('cartao')">
                    <input type="radio" name="metodo_pagamento" id="cartao" value="cartao" required>
                    <label for="cartao">
                        <i class="fa fa-credit-card"></i>
                        <span>Cartão de Crédito</span>
                        <small>Seguro e confiável</small>
                    </label>
                </div>

                <div class="metodo-card metodo-dinheiro" onclick="selecionarMetodo('dinheiro')">
                    <input type="radio" name="metodo_pagamento" id="dinheiro" value="dinheiro" required>
                    <label for="dinheiro">
                        <i class="fa fa-money-bill"></i>
                        <span>Dinheiro</span>
                        <small>Pagamento na entrega</small>
                    </label>
                </div>

                <!-- Áreas específicas -->
                <div id="area-pix" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-qrcode"></i> Pagamento via PIX</h3>
                    <div class="pix-info">
                        <p><i class="fa fa-info-circle"></i> Após confirmar, você receberá as instruções de pagamento
                        </p>
                        <div style="display: flex; gap: 15px; margin: 15px 0;">
                            <span style="color: #00cc55;"><i class="fa fa-check"></i> Aprovação instantânea</span>
                            <span style="color: #00cc55;"><i class="fa fa-check"></i> Sem taxas</span>
                            <span style="color: #00cc55;"><i class="fa fa-check"></i> 100% seguro</span>
                        </div>
                    </div>
                </div>

                <div id="area-cartao" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-credit-card"></i> Dados do Cartão</h3>
                    <div style="background: #2a2a2a; padding: 20px; border-radius: 10px;">
                        <p><i class="fa fa-info-circle"></i> Em um sistema real, aqui seria integrado com gateway de
                            pagamento (PagSeguro, Mercado Pago, etc.)</p>
                        <p style="margin-top: 10px; color: #aaa;">Por ora, o pedido será criado como "pago" para
                            demonstração.</p>
                    </div>
                </div>

                <div id="area-dinheiro" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-money-bill"></i> Pagamento em Dinheiro</h3>
                    <div style="background: #2a2a2a; padding: 20px; border-radius: 10px;">
                        <p><i class="fa fa-motorcycle"></i> Você pagará na entrega</p>
                        <p style="margin-top: 10px; color: #aaa;">O entregador levará o troco se necessário.</p>
                    </div>
                </div>

                <button type="submit" name="processar_pagamento" id="btn-confirmar"
                    style="width: 100%; padding: 18px; background: linear-gradient(135deg, #00cc55, #009944); color: #fff; border: none; border-radius: 12px; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 20px; transition: all 0.3s;"
                    disabled>
                    <i class="fa fa-check"></i> Confirmar Pagamento - R$
                    <?= number_format($total_carrinho, 2, ',', '.') ?>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Recuperar mesa salva
        const mesaSalva = localStorage.getItem('numero_mesa');
        if (mesaSalva) {
            document.getElementById('numero_mesa').value = mesaSalva;
        }

        // Salvar mesa quando digitada
        document.getElementById('numero_mesa').addEventListener('input', function () {
            if (this.value) {
                localStorage.setItem('numero_mesa', this.value);
            } else {
                localStorage.removeItem('numero_mesa');
            }
        });

        function selecionarMetodo(metodo) {
            // Marcar radio
            document.getElementById(metodo).checked = true;

            // Esconder todas as áreas
            document.querySelectorAll('.area-pagamento').forEach(el => el.style.display = 'none');

            // Remover seleção visual
            document.querySelectorAll('.metodo-card').forEach(el => el.classList.remove('selected'));

            // Adicionar seleção visual
            event.currentTarget.classList.add('selected');

            // Mostrar área correspondente
            document.getElementById('area-' + metodo).style.display = 'block';

            // Habilitar botão
            document.getElementById('btn-confirmar').disabled = false;

            // Atualizar cor do botão baseado no método
            const btn = document.getElementById('btn-confirmar');
            switch (metodo) {
                case 'pix':
                    btn.style.background = 'linear-gradient(135deg, #00cc55, #009944)';
                    break;
                case 'cartao':
                    btn.style.background = 'linear-gradient(135deg, #2196f3, #1976d2)';
                    break;
                case 'dinheiro':
                    btn.style.background = 'linear-gradient(135deg, #ff9800, #f57c00)';
                    break;
            }
        }

        // Efeito hover no botão
        document.getElementById('btn-confirmar').addEventListener('mouseenter', function () {
            if (!this.disabled) {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0, 204, 85, 0.6)';
            }
        });

        document.getElementById('btn-confirmar').addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });

        // Animação de loading no submit
        document.getElementById('payment-form').addEventListener('submit', function () {
            const btn = document.getElementById('btn-confirmar');
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';
            btn.disabled = true;
        });
    </script>
</body>

</html>