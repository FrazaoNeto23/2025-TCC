<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "config_seguro.php";
include "verificar_sessao.php";
include_once "helpers.php"; // ✅ ADICIONAR HELPERS

verificarCliente();

$id_cliente = $_SESSION['id_usuario'];
$msg = "";

// ===== LIMPAR CARRINHO DE PRODUTOS INEXISTENTES =====
$removidos = limpar_carrinho_invalido($conn, $id_cliente);
if ($removidos > 0) {
    $msg = "⚠️ $removidos item(ns) inválido(s) foram removidos do seu carrinho.";
}

// ===== ADICIONAR AO CARRINHO - CORRIGIDO COM VALIDAÇÃO =====
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);
    $tipo_produto = $_POST['tipo_produto'] ?? 'normal';

    try {
        // Validar quantidade
        $quantidade = validar_quantidade($quantidade);
        if (!$quantidade) {
            throw new Exception("Quantidade inválida (1-99)");
        }

        // VALIDAR PRODUTO ANTES DE ADICIONAR
        $validacao = validar_produto($conn, $id_produto, $tipo_produto);

        if (!$validacao['exists']) {
            $msg = "❌ " . $validacao['erro'];
            log_erro_integridade($conn, 'produto_invalido', $validacao['erro'], [
                'id_produto' => $id_produto,
                'tipo_produto' => $tipo_produto,
                'id_cliente' => $id_cliente
            ]);
        } else {
            // Produto válido - verificar se já existe no carrinho
            $stmt = $conn->prepare("
                SELECT id, quantidade FROM carrinho 
                WHERE id_cliente=? AND id_produto=? AND tipo_produto=?
            ");
            $stmt->bind_param("iis", $id_cliente, $id_produto, $tipo_produto);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {
                // Atualizar quantidade existente
                $item = $resultado->fetch_assoc();
                $nova_qtd = min($item['quantidade'] + $quantidade, 99);

                $update = $conn->prepare("UPDATE carrinho SET quantidade=? WHERE id=?");
                $update->bind_param("ii", $nova_qtd, $item['id']);
                $update->execute();
                $update->close();

                $msg = "✅ Quantidade atualizada no carrinho!";
            } else {
                // Inserir novo item
                $insert = $conn->prepare("
                    INSERT INTO carrinho (id_cliente, id_produto, quantidade, tipo_produto, data_adicao) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert->bind_param("iiis", $id_cliente, $id_produto, $quantidade, $tipo_produto);
                $insert->execute();
                $insert->close();

                $msg = "✅ Produto adicionado ao carrinho!";
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $msg = "❌ Erro: " . $e->getMessage();
        log_erro_integridade($conn, 'erro_carrinho', $e->getMessage(), [
            'id_cliente' => $id_cliente,
            'id_produto' => $id_produto ?? 0
        ]);
    }
}

// ===== MENSAGEM DE SUCESSO DO PAGAMENTO =====
if (isset($_SESSION['pagamento_sucesso'])) {
    $msg = $_SESSION['pagamento_sucesso'];
    unset($_SESSION['pagamento_sucesso']);
}

// ===== BUSCAR PRODUTOS =====
try {
    $produtos = $conn->query("SELECT * FROM produtos WHERE disponivel = 1 ORDER BY nome");
    $produtos_especiais = $conn->query("SELECT * FROM produtos_especiais ORDER BY nome");

    // Buscar pedidos - CORRIGIDO
    $stmt_pedidos = $conn->prepare("
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
        WHERE pedidos.id_cliente = ?
        ORDER BY pedidos.data DESC
        LIMIT 10
    ");
    $stmt_pedidos->bind_param("i", $id_cliente);
    $stmt_pedidos->execute();
    $pedidos = $stmt_pedidos->get_result();

    // Contar carrinho - APENAS PRODUTOS VÁLIDOS
    $stmt_count = $conn->prepare("
        SELECT COUNT(*) as total FROM carrinho c
        WHERE c.id_cliente = ?
        AND (
            (c.tipo_produto = 'normal' AND EXISTS(SELECT 1 FROM produtos WHERE id = c.id_produto AND disponivel = 1)) OR
            (c.tipo_produto = 'especial' AND EXISTS(SELECT 1 FROM produtos_especiais WHERE id = c.id_produto))
        )
    ");
    $stmt_count->bind_param("i", $id_cliente);
    $stmt_count->execute();
    $count_carrinho = $stmt_count->get_result()->fetch_assoc()['total'];
    $stmt_count->close();

} catch (Exception $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Painel do Cliente - Burger House</title>
    <link rel="stylesheet" href="css/cliente.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="header-cliente">
            <h1><i class="fa fa-user-circle"></i> Bem-vindo, <?php echo sanitizar_texto($_SESSION['usuario']); ?>!</h1>
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
            <div class="msg-sucesso-pedido <?= str_contains($msg, '❌') || str_contains($msg, '⚠️') ? 'msg-error' : '' ?>"
                id="notification">
                <i
                    class="fa <?= str_contains($msg, '❌') || str_contains($msg, '⚠️') ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
                <span>
                    <?php
                    if (preg_match('/#([\d-]+)/', $msg, $matches)) {
                        $numero_pedido = $matches[1];
                        $msg_formatada = str_replace(
                            "#$numero_pedido",
                            "<strong class='pedido-numero-destaque'>#$numero_pedido</strong>",
                            $msg
                        );
                        echo $msg_formatada;
                    } else {
                        echo $msg;
                    }
                    ?>
                </span>
                <i class="fa fa-times close-msg" onclick="fecharNotificacao()"></i>
            </div>

            <script>
                setTimeout(() => fecharNotificacao(), 10000);

                function fecharNotificacao() {
                    const notification = document.getElementById('notification');
                    if (notification) {
                        notification.classList.add('fade-out');
                        setTimeout(() => notification.remove(), 500);
                    }
                }
            </script>
        <?php endif; ?>

        <!-- Produtos Especiais -->
        <?php if ($produtos_especiais && $produtos_especiais->num_rows > 0): ?>
            <h2><i class="fa fa-star"></i> Cardápio Especial</h2>
            <div class="produtos produtos-especiais">
                <?php while ($pe = $produtos_especiais->fetch_assoc()): ?>
                    <div class="produto especial">
                        <?php if ($pe['imagem'] && file_exists("uploads/" . $pe['imagem'])): ?>
                            <img src="uploads/<?= $pe['imagem'] ?>" alt="<?= sanitizar_texto($pe['nome']) ?>">
                        <?php else: ?>
                            <div class="sem-imagem"><i class="fa fa-star"></i></div>
                        <?php endif; ?>
                        <h3><?= sanitizar_texto($pe['nome']) ?></h3>
                        <p class="descricao"><?= sanitizar_texto($pe['descricao'] ?? '') ?></p>
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
                            <img src="uploads/<?= $p['imagem'] ?>" alt="<?= sanitizar_texto($p['nome']) ?>">
                        <?php else: ?>
                            <div class="sem-imagem"><i class="fa fa-image"></i></div>
                        <?php endif; ?>
                        <h3><?= sanitizar_texto($p['nome']) ?></h3>
                        <p class="descricao"><?= sanitizar_texto($p['descricao'] ?? '') ?></p>
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
        <h2><i class="fa fa-receipt"></i> Meus Pedidos Recentes</h2>
        <?php if ($pedidos && $pedidos->num_rows > 0): ?>
            <div class="pedidos-lista">
                <?php while ($pd = $pedidos->fetch_assoc()): ?>
                    <div class="pedido-card">
                        <div class="pedido-header">
                            <span class="pedido-id">Pedido #<?= $pd['numero_exibicao'] ?></span>
                            <span class="pedido-data">
                                <i class="fa fa-clock"></i> <?= date('d/m/Y H:i', strtotime($pd['data'])) ?>
                            </span>
                        </div>
                        <div class="pedido-info">
                            <h3><?= sanitizar_texto($pd['produto_nome'] ?? 'Produto') ?></h3>
                            <p><i class="fa fa-box"></i> Quantidade: <?= $pd['quantidade'] ?></p>
                            <p class="pedido-total"><i class="fa fa-dollar-sign"></i> Total: R$
                                <?= number_format($pd['total'], 2, ',', '.') ?>
                            </p>
                        </div>
                        <div class="pedido-footer">
                            <span class="status-badge"><?= $pd['status'] ?></span>
                            <span
                                class="pagamento-badge <?= strtolower($pd['status_pagamento']) ?>"><?= $pd['status_pagamento'] ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="sem-pedidos">
                <i class="fa fa-inbox"></i>
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