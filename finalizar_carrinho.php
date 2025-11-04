<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "config_seguro.php";
include "verificar_sessao.php";
include_once "helpers.php"; // ✅ INCLUIR HELPERS

verificarCliente();

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

// ===== GERAR NÚMERO SEQUENCIAL COM LOCK =====
function gerarNumeroSequencial($conn)
{
    $conn->query("LOCK TABLES pedidos WRITE");

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
    $stmt->close();

    $proximo_numero = ($resultado['ultimo_numero'] ?? 0) + 1;
    $numero_pedido = $prefixo . '-' . str_pad($proximo_numero, 3, '0', STR_PAD_LEFT);

    $conn->query("UNLOCK TABLES");

    return $numero_pedido;
}

// ===== PROCESSAR PAGAMENTO - CORRIGIDO =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['metodo_pagamento'])) {
    $metodo = $_POST['metodo_pagamento'] ?? '';
    $numero_mesa = validar_numero_mesa($_POST['numero_mesa'] ?? '');

    // Validar método de pagamento
    if (!validar_metodo_pagamento($metodo)) {
        $_SESSION['erro_pagamento'] = "❌ Método de pagamento inválido!";
        header("Location: carrinho.php");
        exit;
    }

    // ===== BUSCAR ITENS DO CARRINHO - APENAS PRODUTOS VÁLIDOS =====
    $stmt_itens = $conn->prepare("
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
        LEFT JOIN produtos ON carrinho.id_produto = produtos.id 
            AND carrinho.tipo_produto = 'normal' 
            AND produtos.disponivel = 1
        LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id 
            AND carrinho.tipo_produto = 'especial'
        WHERE carrinho.id_cliente = ?
            AND (
                (carrinho.tipo_produto = 'normal' AND produtos.id IS NOT NULL) OR
                (carrinho.tipo_produto = 'especial' AND produtos_especiais.id IS NOT NULL)
            )
    ");

    $stmt_itens->bind_param("i", $id_cliente);
    $stmt_itens->execute();
    $itens = $stmt_itens->get_result();

    if ($itens->num_rows == 0) {
        $_SESSION['erro_pagamento'] = "❌ Carrinho vazio ou contém produtos inválidos!";
        header("Location: carrinho.php");
        exit;
    }

    // Transformar em array para validação
    $itens_array = [];
    while ($item = $itens->fetch_assoc()) {
        $itens_array[] = $item;
    }

    // ===== VALIDAR INTEGRIDADE COMPLETA =====
    $verificacao = verificar_integridade_pedido($conn, $id_cliente, $itens_array);

    if (!$verificacao['valido']) {
        $_SESSION['erro_pagamento'] = "⚠️ Erro: " . implode(", ", $verificacao['erros']);
        header("Location: carrinho.php");
        exit;
    }

    try {
        $conn->begin_transaction();

        // Gerar número do pedido
        $numero_pedido = gerarNumeroSequencial($conn);
        $total_geral = 0;

        // Calcular total
        foreach ($itens_array as $item) {
            $subtotal = $item['produto_preco'] * $item['quantidade'];
            $total_geral += $subtotal;
        }

        // Criar observações
        $observacoes = "Pagamento via " . ucfirst($metodo);
        if ($numero_mesa) {
            $observacoes .= " | Mesa: " . $numero_mesa;
        }

        // Criar pedido principal
        $stmt_pedido = $conn->prepare("
            INSERT INTO pedidos 
            (numero_pedido, id_cliente, numero_mesa, total, status, status_pagamento, metodo_pagamento, observacoes, data) 
            VALUES (?, ?, ?, ?, 'Pendente', 'Pago', ?, ?, NOW())
        ");

        $stmt_pedido->bind_param("siidss", $numero_pedido, $id_cliente, $numero_mesa, $total_geral, $metodo, $observacoes);

        if (!$stmt_pedido->execute()) {
            throw new Exception("Erro ao criar pedido: " . $conn->error);
        }

        $id_pedido = $stmt_pedido->insert_id;
        $stmt_pedido->close();

        // Criar tabela itens_pedido se não existir
        $conn->query("
            CREATE TABLE IF NOT EXISTS itens_pedido (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_pedido INT NOT NULL,
                id_produto INT NOT NULL,
                tipo_produto ENUM('normal', 'especial') DEFAULT 'normal',
                quantidade INT NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                observacoes TEXT,
                FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
                INDEX idx_pedido (id_pedido)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Inserir itens do pedido
        $stmt_item = $conn->prepare("
            INSERT INTO itens_pedido 
            (id_pedido, id_produto, tipo_produto, quantidade, preco_unitario, subtotal) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($itens_array as $item) {
            $subtotal = $item['produto_preco'] * $item['quantidade'];
            $stmt_item->bind_param(
                "iisidd",
                $id_pedido,
                $item['id_produto'],
                $item['tipo_produto'],
                $item['quantidade'],
                $item['produto_preco'],
                $subtotal
            );

            if (!$stmt_item->execute()) {
                throw new Exception("Erro ao inserir item: " . $conn->error);
            }
        }
        $stmt_item->close();

        // Limpar carrinho
        $stmt_limpar = $conn->prepare("DELETE FROM carrinho WHERE id_cliente = ?");
        $stmt_limpar->bind_param("i", $id_cliente);
        $stmt_limpar->execute();
        $stmt_limpar->close();

        $conn->commit();

        // Mensagem de sucesso
        if ($numero_mesa) {
            $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$numero_pedido confirmado! Mesa $numero_mesa. Total: R$ " . number_format($total_geral, 2, ',', '.');
        } else {
            $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$numero_pedido confirmado! Total: R$ " . number_format($total_geral, 2, ',', '.');
        }

        ob_end_clean();
        header("Location: painel_cliente.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['erro_pagamento'] = "❌ Erro ao processar pedido: " . $e->getMessage();

        log_erro_integridade($conn, 'erro_finalizar_pedido', $e->getMessage(), [
            'id_cliente' => $id_cliente,
            'metodo' => $metodo,
            'total_itens' => count($itens_array)
        ]);

        header("Location: carrinho.php");
        exit;
    }
}

// ===== BUSCAR ITENS PARA EXIBIÇÃO =====
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
    LEFT JOIN produtos ON carrinho.id_produto = produtos.id 
        AND carrinho.tipo_produto = 'normal'
        AND produtos.disponivel = 1
    LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id 
        AND carrinho.tipo_produto = 'especial'
    WHERE carrinho.id_cliente = $id_cliente
        AND (
            (carrinho.tipo_produto = 'normal' AND produtos.id IS NOT NULL) OR
            (carrinho.tipo_produto = 'especial' AND produtos_especiais.id IS NOT NULL)
        )
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

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Finalizar Pedido - Burger House</title>
    <link rel="stylesheet" href="css/pagamento.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <a href="carrinho.php" class="btn-voltar"><i class="fa fa-arrow-left"></i> Voltar ao Carrinho</a>

        <h1><i class="fa fa-credit-card"></i> Finalizar Pedido</h1>

        <?php if (isset($_SESSION['erro_pagamento'])): ?>
            <div style="background:#ff4c4c;color:#fff;padding:15px;border-radius:8px;margin-bottom:20px;">
                <i class="fa fa-exclamation-triangle"></i>
                <?= $_SESSION['erro_pagamento'];
                unset($_SESSION['erro_pagamento']); ?>
            </div>
        <?php endif; ?>

        <div class="pedido-resumo">
            <h2><i class="fa fa-receipt"></i> Resumo do Pedido</h2>
            <?php foreach ($itens_array as $item): ?>
                <p>
                    <strong><?= sanitizar_texto($item['produto_nome']) ?></strong><br>
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