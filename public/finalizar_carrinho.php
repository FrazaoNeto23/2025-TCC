<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
require_once CONFIG_PATH . '/config.php';  // ✅ CORRETO

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$id_cliente = $_SESSION['id_usuario'];

// ===== VERIFICAR E ADICIONAR COLUNAS =====
$check_numero = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'numero_pedido'");
if ($check_numero->num_rows == 0) {
    $conn->query("ALTER TABLE pedidos ADD COLUMN numero_pedido VARCHAR(20) AFTER id");
    $conn->query("ALTER TABLE pedidos ADD INDEX idx_numero_pedido (numero_pedido)");
}

$check_obs = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'observacoes'");
if ($check_obs->num_rows == 0) {
    $conn->query("ALTER TABLE pedidos ADD COLUMN observacoes TEXT AFTER metodo_pagamento");
}

// ===== GERAR NÚMERO SEQUENCIAL =====
function gerarNumeroSequencial($conn)
{
    $prefixo = date('Ymd');

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['metodo_pagamento'])) {
    $metodo = $_POST['metodo_pagamento'] ?? '';
    $numero_mesa = isset($_POST['numero_mesa']) && $_POST['numero_mesa'] ? intval($_POST['numero_mesa']) : null;

    if (empty($metodo)) {
        $erro = "Por favor, selecione um método de pagamento!";
    } else {
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
            try {
                $conn->begin_transaction();

                $numero_pedido = gerarNumeroSequencial($conn);
                $total_geral = 0;

                while ($item = $itens->fetch_assoc()) {
                    $total = $item['produto_preco'] * $item['quantidade'];
                    $total_geral += $total;

                    $observacoes = "Pagamento via " . $metodo;

                    $stmt = $conn->prepare("
                        INSERT INTO pedidos 
                        (numero_pedido, id_cliente, numero_mesa, id_produto, tipo_produto, quantidade, total, status, status_pagamento, metodo_pagamento, observacoes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente', 'Pago', ?, ?)
                    ");

                    $stmt->bind_param(
                        "siiisidss",
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

                    $stmt->execute();
                }

                $conn->query("DELETE FROM carrinho WHERE id_cliente = $id_cliente");
                $conn->commit();

                if ($numero_mesa) {
                    $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$numero_pedido confirmado! Mesa $numero_mesa.";
                } else {
                    $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$numero_pedido confirmado!";
                }

                // LIMPAR BUFFER E REDIRECIONAR IMEDIATAMENTE
                ob_end_clean();
                header("Location: painel_cliente.php");
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $erro = "Erro: " . $e->getMessage();
            }
        } else {
            $erro = "Carrinho vazio!";
        }
    }
}

// Buscar itens do carrinho
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

$proximo_numero = gerarNumeroSequencial($conn);

ob_end_flush(); // Libera o buffer para mostrar a página
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Finalizar Pedido - Burger House</title>
    <link rel="stylesheet" href="css/pagamento.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            console.log('DOM carregado - Script iniciado');

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

            window.selecionarMetodo = function (metodo) {
                console.log('Método selecionado:', metodo);

                document.getElementById(metodo).checked = true;

                document.querySelectorAll('.area-pagamento').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.metodo-card').forEach(el => el.classList.remove('selected'));

                event.currentTarget.classList.add('selected');
                document.getElementById('area-' + metodo).style.display = 'block';
                document.getElementById('btn-confirmar').disabled = false;

                console.log('Botão habilitado');
            }

            document.getElementById('payment-form').addEventListener('submit', function (e) {
                console.log('FORMULÁRIO ENVIADO!');

                const metodo = document.querySelector('input[name="metodo_pagamento"]:checked');
                console.log('Método marcado:', metodo ? metodo.value : 'NENHUM');

                if (!metodo) {
                    e.preventDefault();
                    alert('Por favor, selecione um método de pagamento!');
                    console.log('BLOQUEADO - Nenhum método selecionado');
                    return false;
                }

                const btn = document.getElementById('btn-confirmar');
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';
                btn.disabled = true;

                console.log('Enviando formulário para o servidor...');
                return true;
            });

            console.log('Script configurado com sucesso!');
        });
    </script>
</head>

<body>
    <div class="container">
        <a href="carrinho.php" class="btn-voltar"><i class="fa fa-arrow-left"></i> Voltar ao Carrinho</a>

        <h1><i class="fa fa-credit-card"></i> Finalizar Pedido</h1>

        <?php if (isset($erro)): ?>
            <div style="background:#ff4c4c;color:#fff;padding:15px;border-radius:8px;margin-bottom:20px;">
                <i class="fa fa-exclamation-triangle"></i> <?= $erro ?>
            </div>
        <?php endif; ?>

        <div class="pedido-resumo">
            <h2><i class="fa fa-receipt"></i> Resumo do Pedido</h2>
            <?php foreach ($itens_array as $item): ?>
                <p>
                    <strong><?= htmlspecialchars($item['produto_nome']) ?></strong><br>
                    <span style="color:#aaa;font-size:14px;">
                        Qtd: <?= $item['quantidade'] ?> × R$ <?= number_format($item['produto_preco'], 2, ',', '.') ?> =
                        <strong style="color:#0ff;">R$
                            <?= number_format($item['produto_preco'] * $item['quantidade'], 2, ',', '.') ?></strong>
                    </span>
                </p>
            <?php endforeach; ?>
            <p class="total">Total: R$ <?= number_format($total_carrinho, 2, ',', '.') ?></p>
        </div>

        <div class="metodos-pagamento">
            <h2><i class="fa fa-credit-card"></i> Escolha o método de pagamento</h2>

            <form method="POST" id="payment-form">
                <div class="form-group">
                    <label for="numero_mesa">
                        <i class="fa fa-table"></i> Número da Mesa (opcional)
                    </label>
                    <input type="number" name="numero_mesa" id="numero_mesa" placeholder="Digite o número da mesa"
                        min="1" max="999">
                    <small><i class="fa fa-info-circle"></i> Deixe em branco para delivery</small>
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
                        <i class="fa fa-money-bill-wave"></i>
                        <span>Dinheiro</span>
                    </label>
                </div>

                <div id="area-pix" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-qrcode"></i> Pagamento via PIX</h3>
                    <p style="text-align:center;color:#aaa;">Sistema demonstrativo - pedido será criado como pago</p>
                </div>

                <div id="area-cartao" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-credit-card"></i> Dados do Cartão</h3>
                    <p style="text-align:center;color:#aaa;">Sistema demonstrativo - pedido será criado como pago</p>
                </div>

                <div id="area-dinheiro" class="area-pagamento" style="display:none;">
                    <h3><i class="fa fa-money-bill-wave"></i> Pagamento em Dinheiro</h3>
                    <p style="text-align:center;color:#aaa;"><i class="fa fa-motorcycle"></i> Você pagará na entrega</p>
                </div>

                <button type="submit" name="processar_pagamento" id="btn-confirmar" disabled>
                    <i class="fa fa-check-circle"></i> Confirmar Pagamento - R$
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

        document.getElementById('payment-form').addEventListener('submit', function (e) {
            const metodo = document.querySelector('input[name="metodo_pagamento"]:checked');

            if (!metodo) {
                e.preventDefault();
                alert('Por favor, selecione um método de pagamento!');
                return false;
            }

            const btn = document.getElementById('btn-confirmar');
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';
            btn.disabled = true;

            return true;
        });
    </script>
</body>

</html>