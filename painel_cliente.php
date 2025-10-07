<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$msg = "";

// Adicionar pedido
if (isset($_POST['pedido'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);

    $produto = $conn->query("SELECT preco FROM produtos WHERE id=$id_produto")->fetch_assoc();
    $total = $produto['preco'] * $quantidade;

    $stmt = $conn->prepare("INSERT INTO pedidos (id_cliente, id_produto, quantidade, total, status, status_pagamento) VALUES (?, ?, ?, ?, 'Pendente', 'Aguardando')");
    $stmt->bind_param("iiid", $_SESSION['id_usuario'], $id_produto, $quantidade, $total);
    $stmt->execute();

    $id_pedido = $conn->insert_id;

    // Redireciona para pagamento
    header("Location: pagamento.php?pedido_id=$id_pedido");
    exit;
}

// Cancelar pedido
if (isset($_GET['cancelar'])) {
    $id_pedido = intval($_GET['cancelar']);
    $stmt = $conn->prepare("DELETE FROM pedidos WHERE id=? AND id_cliente=? AND status='Pendente'");
    $stmt->bind_param("ii", $id_pedido, $_SESSION['id_usuario']);
    $stmt->execute();
    $msg = "Pedido cancelado!";
}

// Mensagem de sucesso do pagamento
if (isset($_SESSION['pagamento_sucesso'])) {
    $msg = $_SESSION['pagamento_sucesso'];
    unset($_SESSION['pagamento_sucesso']);
}

// Buscar produtos
$produtos = $conn->query("SELECT * FROM produtos");

// Buscar pedidos do cliente com status
$pedidos = $conn->query("SELECT pedidos.*, produtos.nome AS produto_nome, produtos.imagem
                         FROM pedidos 
                         JOIN produtos ON pedidos.id_produto = produtos.id 
                         WHERE pedidos.id_cliente=" . $_SESSION['id_usuario'] . "
                         ORDER BY pedidos.data DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Painel do Cliente</title>
    <link rel="stylesheet" href="css/cliente.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="header-cliente">
            <h1><i class="fa fa-user-circle"></i> Bem-vindo, <?php echo $_SESSION['usuario']; ?></h1>
            <a href="logout.php" class="btn-sair"><i class="fa fa-right-from-bracket"></i> Sair</a>
        </div>

        <?php if ($msg): ?>
            <div class="msg-sucesso">
                <i class="fa fa-check-circle"></i> <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <h2><i class="fa fa-utensils"></i> Cardápio</h2>
        <div class="produtos">
            <?php while ($p = $produtos->fetch_assoc()): ?>
                <div class="produto">
                    <?php if ($p['imagem']): ?>
                        <img src="uploads/<?= $p['imagem'] ?>" alt="<?= $p['nome'] ?>">
                    <?php else: ?>
                        <div class="sem-imagem"><i class="fa fa-image"></i></div>
                    <?php endif; ?>
                    <h3><?= $p['nome'] ?></h3>
                    <p class="descricao"><?= $p['descricao'] ?></p>
                    <p class="preco">R$ <?= number_format($p['preco'], 2, ',', '.') ?></p>
                    <form method="POST">
                        <input type="hidden" name="id_produto" value="<?= $p['id'] ?>">
                        <div class="qtd-control">
                            <button type="button" onclick="diminuir(this)"><i class="fa fa-minus"></i></button>
                            <input type="number" name="quantidade" value="1" min="1" max="99" readonly>
                            <button type="button" onclick="aumentar(this)"><i class="fa fa-plus"></i></button>
                        </div>
                        <button type="submit" name="pedido" class="btn-comprar">
                            <i class="fa fa-shopping-cart"></i> Adicionar ao Pedido
                        </button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>

        <h2><i class="fa fa-receipt"></i> Meus Pedidos</h2>

        <?php if ($pedidos->num_rows > 0): ?>
            <div class="pedidos-lista">
                <?php while ($pd = $pedidos->fetch_assoc()):
                    $status_class = '';
                    $status_icon = 'fa-clock';

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
                            <span class="pedido-id">#<?= $pd['id'] ?></span>
                            <span class="pedido-data"><i class="fa fa-calendar"></i>
                                <?= date('d/m/Y H:i', strtotime($pd['data'])) ?></span>
                        </div>

                        <div class="pedido-info">
                            <?php if ($pd['imagem']): ?>
                                <img src="uploads/<?= $pd['imagem'] ?>" alt="<?= $pd['produto_nome'] ?>" class="pedido-img">
                            <?php endif; ?>
                            <div class="pedido-detalhes">
                                <h3><?= $pd['produto_nome'] ?></h3>
                                <p><i class="fa fa-box"></i> Quantidade: <?= $pd['quantidade'] ?></p>
                                <p class="pedido-total"><i class="fa fa-dollar-sign"></i> Total: R$
                                    <?= number_format($pd['total'], 2, ',', '.') ?></p>
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

                            <?php if ($pd['status'] == 'Pendente' && $pd['status_pagamento'] == 'Aguardando'): ?>
                                <div class="pedido-acoes">
                                    <a href="pagamento.php?pedido_id=<?= $pd['id'] ?>" class="btn-pagar">
                                        <i class="fa fa-credit-card"></i> Pagar
                                    </a>
                                    <a href="?cancelar=<?= $pd['id'] ?>" class="btn-cancelar"
                                        onclick="return confirm('Cancelar este pedido?')">
                                        <i class="fa fa-times"></i> Cancelar
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($pd['metodo_pagamento'])): ?>
                                <p class="metodo-pagamento">
                                    <i class="fa fa-info-circle"></i> Método: <?= ucfirst($pd['metodo_pagamento']) ?>
                                </p>
                            <?php endif; ?>
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
    </script>
</body>

</html>