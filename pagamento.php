<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

// Processar pagamento
if (isset($_POST['processar_pagamento'])) {
    $id_pedido = intval($_POST['id_pedido']);
    $metodo = $_POST['metodo_pagamento'];

    // Atualizar pedido com método de pagamento
    $stmt = $conn->prepare("UPDATE pedidos SET metodo_pagamento=?, status_pagamento='Pago' WHERE id=?");
    $stmt->bind_param("si", $metodo, $id_pedido);
    $stmt->execute();

    $_SESSION['pagamento_sucesso'] = "Pagamento realizado com sucesso!";
    header("Location: painel_cliente.php");
    exit;
}

// Verificar se há pedido pendente
if (!isset($_GET['pedido_id'])) {
    header("Location: painel_cliente.php");
    exit;
}

$id_pedido = intval($_GET['pedido_id']);
$pedido = $conn->query("SELECT pedidos.*, produtos.nome AS produto_nome 
                        FROM pedidos 
                        JOIN produtos ON pedidos.id_produto = produtos.id 
                        WHERE pedidos.id=$id_pedido AND pedidos.id_cliente=" . $_SESSION['id_usuario'])->fetch_assoc();

if (!$pedido) {
    header("Location: painel_cliente.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Pagamento</title>
    <link rel="stylesheet" href="css/pagamento.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <a href="painel_cliente.php" class="btn-voltar"><i class="fa fa-arrow-left"></i> Voltar</a>

        <h1><i class="fa fa-credit-card"></i> Finalizar Pagamento</h1>

        <div class="pedido-resumo">
            <h2>Resumo do Pedido</h2>
            <p><strong>Produto:</strong> <?= $pedido['produto_nome'] ?></p>
            <p><strong>Quantidade:</strong> <?= $pedido['quantidade'] ?></p>
            <p class="total"><strong>Total:</strong> R$ <?= number_format($pedido['total'], 2, ',', '.') ?></p>
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
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PIX-BURGUERHOUSE-<?= $id_pedido ?>"
                    alt="QR Code PIX">
            </div>
            <p class="instrucao">Escaneie o QR Code com seu app de pagamento</p>
            <p class="codigo-pix">Código: <strong>PIX-BURGUER-<?= $id_pedido ?></strong></p>
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
            <input type="hidden" name="id_pedido" value="<?= $id_pedido ?>">
            <input type="hidden" name="metodo_pagamento" id="metodo_selecionado">
            <button type="submit" name="processar_pagamento" id="btn-confirmar" disabled>
                <i class="fa fa-check"></i> Confirmar Pagamento
            </button>
        </form>
    </div>

    <script>
        function selecionarMetodo(metodo) {
            // Marca o radio
            document.getElementById(metodo).checked = true;
            document.getElementById('metodo_selecionado').value = metodo;

            // Esconde todas as áreas
            document.querySelectorAll('.area-pagamento').forEach(el => el.style.display = 'none');

            // Remove seleção visual
            document.querySelectorAll('.metodo-card').forEach(el => el.classList.remove('selected'));

            // Adiciona seleção visual
            event.currentTarget.classList.add('selected');

            // Mostra área correspondente
            document.getElementById('area-' + metodo).style.display = 'block';

            // Habilita botão
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