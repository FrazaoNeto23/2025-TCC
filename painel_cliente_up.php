<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$msg = "";
$id_cliente = $_SESSION['id_usuario'];

// Adicionar ao carrinho
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);
    $tipo_produto = $_POST['tipo_produto']; // 'normal' ou 'especial'

    // Verificar se já existe no carrinho
    $verifica = $conn->query("SELECT * FROM carrinho WHERE id_cliente=$id_cliente AND id_produto=$id_produto AND tipo_produto='$tipo_produto'");

    if ($verifica->num_rows > 0) {
        // Atualizar quantidade
        $item = $verifica->fetch_assoc();
        $nova_qtd = $item['quantidade'] + $quantidade;
        $stmt = $conn->prepare("UPDATE carrinho SET quantidade=? WHERE id=?");
        $stmt->bind_param("ii", $nova_qtd, $item['id']);
        $stmt->execute();
    } else {
        // Adicionar novo item
        $stmt = $conn->prepare("INSERT INTO carrinho (id_cliente, id_produto, quantidade, tipo_produto) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $id_cliente, $id_produto, $quantidade, $tipo_produto);
        $stmt->execute();
    }

    $msg = "Produto adicionado ao carrinho!";
}

// Cancelar pedido
if (isset($_GET['cancelar'])) {
    $id_pedido = intval($_GET['cancelar']);
    $stmt = $conn->prepare("DELETE FROM pedidos WHERE id=? AND id_cliente=? AND status='Pendente'");
    $stmt->bind_param("ii", $id_pedido, $id_cliente);
    $stmt->execute();
    $msg = "Pedido cancelado!";
}

// Mensagem de sucesso do pagamento
if (isset($_SESSION['pagamento_sucesso'])) {
    $msg = $_SESSION['pagamento_sucesso'];
    unset($_SESSION['pagamento_sucesso']);
}

// Buscar produtos normais
$produtos = $conn->query("SELECT * FROM produtos");

// Buscar pedidos do cliente
$pedidos = $conn->query("SELECT pedidos.*, 
                                CASE 
                                    WHEN pedidos.tipo_produto = 'normal' THEN produtos.nome
                                    WHEN pedidos.tipo_produto = 'especial' THEN produtos_especiais.nome
                                END as produto_nome,
                                CASE 
                                    WHEN pedidos.tipo_produto = 'normal' THEN produtos.imagem
                                    WHEN pedidos.tipo_produto = 'especial' THEN produtos_especiais.imagem
                                END as imagem
                         FROM pedidos 
                         LEFT JOIN produtos ON pedidos.id_produto = produtos.id AND pedidos.tipo_produto = 'normal'
                         LEFT JOIN produtos_especiais ON pedidos.id_produto = produtos_especiais.id AND pedidos.tipo_produto = 'especial'
                         WHERE pedidos.id_cliente=$id_cliente
                         ORDER BY pedidos.data DESC");

// Contar itens no carrinho
$count_carrinho = $conn->query("SELECT COUNT(*) as total FROM carrinho WHERE id_cliente=$id_cliente")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Painel do Cliente</title>
    <link rel="stylesheet" href="css/cliente.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
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
            <h1><i class="fa fa-user-circle"></i> Bem-vindo, <?php echo $_SESSION['usuario']; ?></h1>
            <div style="display: flex; gap: 15px;">
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
                                <?php if ($pd['numero_mesa']): ?>
                                    <p><i class="fa fa-table"></i> Mesa: <?= $pd['numero_mesa'] ?></p>
                                <?php else: ?>
                                    <p><i class="fa fa-motorcycle"></i> Delivery</p>
                                <?php endif; ?>
                                <p><i class="fa fa-box"></i> Quantidade: <?= $pd['quantidade'] ?></p>
                                <p class="pedido-total"><i class="fa fa-dollar-sign"></i> Total: R$
                                    <?= number_format($pd['total'], 2, ',', '.') ?>
                                </p>
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