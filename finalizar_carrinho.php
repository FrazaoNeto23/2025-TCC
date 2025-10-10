<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$id_cliente = $_SESSION['id_usuario'];

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
               END as produto_preco
        FROM carrinho
        LEFT JOIN produtos ON carrinho.id_produto = produtos.id AND carrinho.tipo_produto = 'normal'
        LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id AND carrinho.tipo_produto = 'especial'
        WHERE carrinho.id_cliente = $id_cliente
    ");

    if ($itens->num_rows > 0) {
        // Criar pedidos para cada item do carrinho
        while ($item = $itens->fetch_assoc()) {
            $total = $item['produto_preco'] * $item['quantidade'];

            $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, numero_mesa, id_produto, tipo_produto, quantidade, total, status, status_pagamento, metodo_pagamento) VALUES (?, ?, ?, ?, ?, ?, 'Pendente', 'Pago', ?)");
            $stmt->bind_param("iiisids", $id_cliente, $numero_mesa, $item['id_produto'], $item['tipo_produto'], $item['quantidade'], $total, $metodo);
            $stmt->execute();
        }

        // Limpar carrinho
        $conn->query("DELETE FROM carrinho WHERE id_cliente = $id_cliente");

        $_SESSION['pagamento_sucesso'] = "Pagamento realizado com sucesso! Seus pedidos foram enviados para a cozinha.";
        header("Location: painel_cliente.php");
        exit;
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
           END as produto_preco
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
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Finalizar Pedido</title>
    <link rel="stylesheet" href="css/pagamento.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <a href="carrinho.php" class="btn-voltar"><i class="fa fa-arrow-left"></i> Voltar ao Carrinho</a>

        <h1><i class="fa fa-credit-card"></i> Finalizar Pedido</h1>

        <div class="pedido-resumo">
            <h2>Resumo do Pedido</h2>
            <?php foreach ($itens_array as $item): ?>
                <p><strong><?= $item['produto_nome'] ?></strong> x<?= $item['quantidade'] ?> - R$
                    <?= number_format($item['produto_preco'] * $item['quantidade'], 2, ',', '.') ?></p>
            <?php endforeach; ?>
            <p class="total"><strong>Total:</strong> R$ <?= number_format($total_carrinho, 2, ',', '.') ?></p>
        </div>

        <div class="metodos-pagamento">
            <h2>Escolha o método de pagamento</h2>

            <div class="metodo-card" onclick="selecionarMetodo('pix')">
                <input type="radio" name="metodo" id="pix" value="pix">
                <label for="pix">
                    <i class="fa fa-qrcode"></i>
                    <span>PIX</span>
                </label>
            </div>

            <div class="metodo-card" onclick="selecionarMetodo('cartao')">
                <input type="radio" name="metodo" id="cartao" value="cartao">
                <label for="cartao">
                    <i class="fa fa-credit-card"></i>
                    <span>Cartão de Crédito</span>
                </label>
            </div>

            <div class="metodo-card" onclick="selecionarMetodo('dinheiro')">
                <input type="radio" name="metodo" id="dinheiro" value="dinheiro">
                <label for="dinheiro">
                    <i class="fa fa-money-bill"></i>
                    <span>Dinheiro</span>
                </label>
            </div>
        </div>

        <!-- Área PIX -->
        <div id="area-pix" class="area-pagamento" style="display:none;">
            <h3>Pagamento via PIX</h3>
            <div class="qrcode-container">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PIX-BURGUERHOUSE-CARRINHO-<?= $id_cliente ?>-<?= time() ?>"
                    alt="QR Code PIX">
            </div>
            <p class="instrucao">Escaneie o QR Code com seu app de pagamento</p>
            <p class="codigo-pix">Código: <strong>PIX-CARRINHO-<?= $id_cliente ?></strong></p>
        </div>

        <!-- Área Cartão -->
        <div id="area-cartao" class="area-pagamento" style="display:none;">
            <h3>Dados do Cartão</h3>
            <form id="form-cartao">
                <input type="text" placeholder="Número do Cartão" maxlength="19" id="numero-cartao" required>
                <input type="text" placeholder="Nome no Cartão" required>
                <div class="inline-inputs">
                    <input type="text" placeholder="Validade (MM/AA)" maxlength="5" required>
                    <input type="text" placeholder="CVV" maxlength="3" required>
                </div>
            </form>
        </div>

        <!-- Área Dinheiro -->
        <div id="area-dinheiro" class="area-pagamento" style="display:none;">
            <h3>Pagamento em Dinheiro</h3>
            <p class="instrucao">Você pagará na entrega</p>
            <label>Precisa de troco?</label>
            <input type="number" step="0.01" placeholder="Troco para quanto?" id="troco">
        </div>

        <form method="POST" id="form-final">
            <input type="hidden" name="metodo_pagamento" id="metodo_selecionado">
            <input type="hidden" name="numero_mesa" id="numero_mesa_hidden">
            <button type="submit" name="processar_pagamento" id="btn-confirmar" disabled>
                <i class="fa fa-check"></i> Confirmar Pagamento
            </button>
        </form>
    </div>

    <script>
        // Carregar número da mesa do localStorage
        const mesaSalva = localStorage.getItem('numero_mesa');
        if (mesaSalva) {
            document.getElementById('numero_mesa_hidden').value = mesaSalva;
        }

        function selecionarMetodo(metodo) {
            document.getElementById(metodo).checked = true;
            document.getElementById('metodo_selecionado').value = metodo;

            document.querySelectorAll('.area-pagamento').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.metodo-card').forEach(el => el.classList.remove('selected'));

            event.currentTarget.classList.add('selected');
            document.getElementById('area-' + metodo).style.display = 'block';
            document.getElementById('btn-confirmar').disabled = false;
        }

        // Formatação do número do cartão
        document.getElementById('numero-cartao')?.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });
    </script>
</body>

</html>