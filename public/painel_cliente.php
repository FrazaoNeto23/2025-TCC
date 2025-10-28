<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
require_once CONFIG_PATH . '/config.php';

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$msg = "";
$id_cliente = $_SESSION['id_usuario'];

// Reconectar se necessário
if (!$conn->ping()) {
    $conn = new mysqli("localhost", "root", "", "burger_house", 3307);
    $conn->set_charset("utf8mb4");
}

// Adicionar ao carrinho
if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);
    $tipo_produto = $_POST['tipo_produto'];

    try {
        $produto_valido = false;

        if ($tipo_produto == 'normal') {
            $check = $conn->prepare("SELECT id FROM produtos WHERE id = ?");
            $check->bind_param("i", $id_produto);
            $check->execute();
            $produto_valido = $check->get_result()->num_rows > 0;
        } elseif ($tipo_produto == 'especial') {
            $check = $conn->prepare("SELECT id FROM produtos_especiais WHERE id = ?");
            $check->bind_param("i", $id_produto);
            $check->execute();
            $produto_valido = $check->get_result()->num_rows > 0;
        }

        if (!$produto_valido) {
            $msg = "❌ Produto não encontrado!";
        } else {
            $stmt = $conn->prepare("
                SELECT id, quantidade FROM carrinho 
                WHERE id_cliente=? AND id_produto=? AND tipo_produto=?
            ");
            $stmt->bind_param("iis", $id_cliente, $id_produto, $tipo_produto);
            $stmt->execute();
            $resultado = $stmt->get_result();

            if ($resultado->num_rows > 0) {
                $item = $resultado->fetch_assoc();
                $nova_qtd = $item['quantidade'] + $quantidade;

                $update = $conn->prepare("UPDATE carrinho SET quantidade=? WHERE id=?");
                $update->bind_param("ii", $nova_qtd, $item['id']);
                $update->execute();

                $msg = "✅ Quantidade atualizada no carrinho!";
            } else {
                $insert = $conn->prepare("
                    INSERT INTO carrinho (id_cliente, id_produto, quantidade, tipo_produto, data_adicao) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert->bind_param("iiis", $id_cliente, $id_produto, $quantidade, $tipo_produto);
                $insert->execute();

                $msg = "✅ Produto adicionado ao carrinho!";
            }
        }
    } catch (Exception $e) {
        $msg = "❌ Erro: " . $e->getMessage();
    }
}

// Mensagem de sucesso do pagamento
if (isset($_SESSION['pagamento_sucesso'])) {
    $msg = $_SESSION['pagamento_sucesso'];
    unset($_SESSION['pagamento_sucesso']);
}

// Buscar produtos
try {
    $produtos = $conn->query("SELECT * FROM produtos ORDER BY nome");
    $produtos_especiais = $conn->query("SELECT * FROM produtos_especiais ORDER BY nome");

    // Buscar pedidos recentes (últimos 10)
    $pedidos = $conn->query("
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
        LIMIT 10
    ");

    // Contar carrinho
    $count_result = $conn->query("SELECT COUNT(*) as total FROM carrinho WHERE id_cliente=$id_cliente");
    $count_carrinho = $count_result ? $count_result->fetch_assoc()['total'] : 0;

} catch (Exception $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

// Verificar se há pedidos novos (últimos 5 minutos)
$pedidos_novos = [];
if ($pedidos && $pedidos->num_rows > 0) {
    $pedidos->data_seek(0);
    $agora = time();
    while ($p = $pedidos->fetch_assoc()) {
        $tempo_pedido = strtotime($p['data']);
        if (($agora - $tempo_pedido) < 300) { // 5 minutos
            $pedidos_novos[] = $p['id'];
        }
    }
    $pedidos->data_seek(0);
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
        <!-- Header -->
        <div class="header-cliente">
            <h1><i class="fa fa-user-circle"></i> Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h1>
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="carrinho.php" class="btn-carrinho">
                    <i class="fa fa-shopping-cart"></i>
                    <span>Carrinho</span>
                    <?php if ($count_carrinho > 0): ?>
                        <span class="carrinho-badge"><?= $count_carrinho ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="btn-sair"><i class="fa fa-right-from-bracket"></i> Sair</a>
            </div>
        </div>

        <!-- Mensagem de Notificação -->
        <?php if ($msg): ?>
            <div class="msg-sucesso-pedido <?= str_contains($msg, '❌') ? 'msg-error' : '' ?>" id="notification">
                <i class="fa <?= str_contains($msg, '❌') ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
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
        <?php endif; ?>

        <!-- Produtos Especiais -->
        <?php if ($produtos_especiais && $produtos_especiais->num_rows > 0): ?>
            <h2><i class="fa fa-star"></i> Cardápio Especial</h2>
            <div class="produtos">
                <?php while ($pe = $produtos_especiais->fetch_assoc()): ?>
                    <div class="produto" style="border: 2px solid #ffa500; box-shadow: 0 0 20px rgba(255, 165, 0, 0.3);">
                        <?php if ($pe['imagem'] && file_exists("uploads/" . $pe['imagem'])): ?>
                            <img src="uploads/<?= htmlspecialchars($pe['imagem']) ?>" alt="<?= htmlspecialchars($pe['nome']) ?>">
                        <?php else: ?>
                            <div class="sem-imagem"><i class="fa fa-star"></i></div>
                        <?php endif; ?>
                        <h3 style="color: #ffa500;"><i class="fa fa-star"></i> <?= htmlspecialchars($pe['nome']) ?></h3>
                        <p class="descricao"><?= htmlspecialchars($pe['descricao'] ?? '') ?></p>
                        <p class="preco" style="color: #ffa500;">R$ <?= number_format($pe['preco'], 2, ',', '.') ?></p>

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
                            <img src="uploads/<?= htmlspecialchars($p['imagem']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>">
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
                            <button type="submit" name="adicionar_carrinho" class="btn-comprar">
                                <i class="fa fa-cart-plus"></i> Adicionar ao Carrinho
                            </button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Meus Pedidos -->
        <h2 id="meus-pedidos"><i class="fa fa-receipt"></i> Meus Pedidos Recentes</h2>
        <?php if ($pedidos && $pedidos->num_rows > 0): ?>
            <div class="pedidos-lista">
                <?php while ($pd = $pedidos->fetch_assoc()):
                    $is_novo = in_array($pd['id'], $pedidos_novos);
                    ?>
                    <div class="pedido-card <?= $is_novo ? 'pedido-novo' : '' ?>">
                        <div class="pedido-header">
                            <span class="pedido-id"><?= htmlspecialchars($pd['numero_exibicao']) ?></span>
                            <span class="pedido-data">
                                <i class="fa fa-clock"></i> <?= date('d/m/Y H:i', strtotime($pd['data'])) ?>
                            </span>
                        </div>

                        <div class="pedido-info">
                            <?php if ($pd['imagem'] && file_exists("uploads/" . $pd['imagem'])): ?>
                                <img src="uploads/<?= htmlspecialchars($pd['imagem']) ?>"
                                    alt="<?= htmlspecialchars($pd['produto_nome']) ?>" class="pedido-img">
                            <?php endif; ?>

                            <div class="pedido-detalhes">
                                <h3><?= htmlspecialchars($pd['produto_nome'] ?? 'Produto') ?></h3>
                                <p><i class="fa fa-box"></i> Quantidade: <?= $pd['quantidade'] ?></p>
                                <?php if ($pd['numero_mesa']): ?>
                                    <p><i class="fa fa-table"></i> Mesa: <?= $pd['numero_mesa'] ?></p>
                                <?php else: ?>
                                    <p><i class="fa fa-motorcycle"></i> Delivery</p>
                                <?php endif; ?>
                                <p class="pedido-total">Total: R$ <?= number_format($pd['total'], 2, ',', '.') ?></p>
                            </div>
                        </div>

                        <div class="pedido-footer">
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $pd['status'])) ?>">
                                <i
                                    class="fa fa-<?= $pd['status'] == 'Pendente' ? 'clock' : ($pd['status'] == 'Em preparo' ? 'fire' : ($pd['status'] == 'Entregando' ? 'truck' : 'check-circle')) ?>"></i>
                                <?= $pd['status'] ?>
                            </span>

                            <span class="pagamento-badge <?= strtolower($pd['status_pagamento']) ?>">
                                <i class="fa fa-<?= $pd['status_pagamento'] == 'Pago' ? 'check-circle' : 'clock' ?>"></i>
                                <?= $pd['status_pagamento'] ?>
                            </span>

                            <?php if ($pd['metodo_pagamento']): ?>
                                <span class="metodo-pagamento">
                                    <i class="fa fa-credit-card"></i> <?= ucfirst($pd['metodo_pagamento']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Barra de Progresso -->
                        <div class="progresso-container">
                            <div class="progresso-marcos">
                                <span
                                    class="<?= in_array($pd['status'], ['Pendente', 'Em preparo', 'Entregando', 'Entregue']) ? 'completo' : '' ?> <?= $pd['status'] == 'Pendente' ? 'ativo' : '' ?>">
                                    <i class="fa fa-clock"></i>
                                    <small>Pendente</small>
                                </span>
                                <span
                                    class="<?= in_array($pd['status'], ['Em preparo', 'Entregando', 'Entregue']) ? 'completo' : '' ?> <?= $pd['status'] == 'Em preparo' ? 'ativo' : '' ?>">
                                    <i class="fa fa-fire"></i>
                                    <small>Preparo</small>
                                </span>
                                <span
                                    class="<?= in_array($pd['status'], ['Entregando', 'Entregue']) ? 'completo' : '' ?> <?= $pd['status'] == 'Entregando' ? 'ativo' : '' ?>">
                                    <i class="fa fa-truck"></i>
                                    <small>Saiu</small>
                                </span>
                                <span class="<?= $pd['status'] == 'Entregue' ? 'completo ativo' : '' ?>">
                                    <i class="fa fa-check-circle"></i>
                                    <small>Entregue</small>
                                </span>
                            </div>
                            <div class="barra-progresso">
                                <div class="barra-progresso-fill" style="width: <?=
                                    $pd['status'] == 'Pendente' ? '25%' :
                                    ($pd['status'] == 'Em preparo' ? '50%' :
                                        ($pd['status'] == 'Entregando' ? '75%' : '100%'))
                                    ?>"></div>
                            </div>
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

        function fecharNotificacao() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.classList.add('fade-out');
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }
        }

        // Auto-fechar notificação após 10 segundos
        <?php if ($msg): ?>
            setTimeout(() => {
                fecharNotificacao();
            }, 10000);
        <?php endif; ?>

        // Prevenir scroll no input number
        document.querySelectorAll('.qtd-control input[type="number"]').forEach(input => {
            input.addEventListener('wheel', function (e) {
                e.preventDefault();
            });
        });
    </script>
</body>

</html>