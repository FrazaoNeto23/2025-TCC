<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

$id_cliente = $_SESSION['id_usuario'];

// Remover item do carrinho
if (isset($_GET['remover'])) {
    $id_carrinho = intval($_GET['remover']);
    $stmt = $conn->prepare("DELETE FROM carrinho WHERE id=? AND id_cliente=?");
    $stmt->bind_param("ii", $id_carrinho, $id_cliente);
    $stmt->execute();
    header("Location: carrinho.php");
    exit;
}

// Atualizar quantidade - CORRIGIDO
if (isset($_POST['quantidade']) && isset($_POST['id_carrinho'])) {
    $id_carrinho = intval($_POST['id_carrinho']);
    $quantidade = intval($_POST['quantidade']);

    if ($quantidade > 0 && $quantidade <= 99) {
        $stmt = $conn->prepare("UPDATE carrinho SET quantidade=? WHERE id=? AND id_cliente=?");
        $stmt->bind_param("iii", $quantidade, $id_carrinho, $id_cliente);
        $stmt->execute();
    }

    // Retornar JSON para requisições AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => true]);
        exit;
    }

    header("Location: carrinho.php");
    exit;
}

// Limpar carrinho
if (isset($_GET['limpar'])) {
    $stmt = $conn->prepare("DELETE FROM carrinho WHERE id_cliente=?");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    header("Location: carrinho.php");
    exit;
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
           END as produto_imagem,
           CASE 
               WHEN carrinho.tipo_produto = 'normal' THEN produtos.descricao
               WHEN carrinho.tipo_produto = 'especial' THEN produtos_especiais.descricao
           END as produto_descricao
    FROM carrinho
    LEFT JOIN produtos ON carrinho.id_produto = produtos.id AND carrinho.tipo_produto = 'normal'
    LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id AND carrinho.tipo_produto = 'especial'
    WHERE carrinho.id_cliente = $id_cliente
    ORDER BY carrinho.data_adicao DESC
");

$total_carrinho = 0;
$total_itens = 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Meu Carrinho</title>
    <link rel="stylesheet" href="css/carrinho.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="header-carrinho">
            <a href="painel_cliente.php" class="btn-voltar"><i class="fa fa-arrow-left"></i> Continuar Comprando</a>
            <h1><i class="fa fa-shopping-cart"></i> Meu Carrinho</h1>
            <div class="spacer"></div>
        </div>

        <?php if ($itens_carrinho->num_rows > 0): ?>
            <div class="carrinho-content">
                <div class="itens-carrinho">
                    <?php while ($item = $itens_carrinho->fetch_assoc()):
                        $subtotal = $item['produto_preco'] * $item['quantidade'];
                        $total_carrinho += $subtotal;
                        $total_itens += $item['quantidade'];
                        ?>
                        <div class="item-carrinho <?= $item['tipo_produto'] == 'especial' ? 'item-especial' : '' ?>">
                            <div class="item-imagem">
                                <?php if ($item['produto_imagem']): ?>
                                    <img src="uploads/<?= $item['produto_imagem'] ?>" alt="<?= $item['produto_nome'] ?>">
                                <?php else: ?>
                                    <div class="sem-imagem"><i class="fa fa-image"></i></div>
                                <?php endif; ?>

                                <?php if ($item['tipo_produto'] == 'especial'): ?>
                                    <span class="badge-especial"><i class="fa fa-star"></i> Especial</span>
                                <?php endif; ?>
                            </div>

                            <div class="item-info">
                                <h3><?= $item['produto_nome'] ?></h3>
                                <p class="item-descricao"><?= $item['produto_descricao'] ?></p>
                                <p class="item-preco">R$ <?= number_format($item['produto_preco'], 2, ',', '.') ?></p>
                            </div>

                            <div class="item-acoes">
                                <form method="POST" class="form-quantidade" data-id="<?= $item['id'] ?>">
                                    <input type="hidden" name="id_carrinho" value="<?= $item['id'] ?>">
                                    <div class="qtd-control">
                                        <button type="button" onclick="diminuir(this)"><i class="fa fa-minus"></i></button>
                                        <input type="number" name="quantidade" value="<?= $item['quantidade'] ?>" min="1"
                                            max="99" readonly>
                                        <button type="button" onclick="aumentar(this)"><i class="fa fa-plus"></i></button>
                                    </div>
                                </form>

                                <div class="item-subtotal">
                                    <span>Subtotal:</span>
                                    <strong>R$ <?= number_format($subtotal, 2, ',', '.') ?></strong>
                                </div>

                                <a href="?remover=<?= $item['id'] ?>" class="btn-remover"
                                    onclick="return confirm('Remover este item do carrinho?')">
                                    <i class="fa fa-trash"></i> Remover
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="resumo-carrinho">
                    <h2><i class="fa fa-receipt"></i> Resumo do Pedido</h2>

                    <div class="resumo-linha">
                        <span>Total de itens:</span>
                        <strong><?= $total_itens ?></strong>
                    </div>

                    <div class="resumo-linha">
                        <span>Subtotal:</span>
                        <strong>R$ <?= number_format($total_carrinho, 2, ',', '.') ?></strong>
                    </div>

                    <div class="resumo-linha taxa">
                        <span>Taxa de entrega:</span>
                        <strong class="gratis">GRÁTIS</strong>
                    </div>

                    <div class="resumo-total">
                        <span>Total:</span>
                        <strong>R$ <?= number_format($total_carrinho, 2, ',', '.') ?></strong>
                    </div>

                    <form method="GET" action="finalizar_carrinho.php" class="form-mesa" id="form-finalizar">
                        <label for="numero_mesa"><i class="fa fa-table"></i> Número da Mesa (opcional):</label>
                        <input type="number" name="numero_mesa" id="numero_mesa" placeholder="Digite o número da mesa"
                            min="1" max="999">
                        <small>Deixe em branco para delivery</small>
                    </form>

                    <button type="submit" form="form-finalizar" class="btn-finalizar">
                        <i class="fa fa-check-circle"></i> Finalizar Pedido
                    </button>

                    <a href="?limpar=1" class="btn-limpar-carrinho" onclick="return confirm('Limpar todo o carrinho?')">
                        <i class="fa fa-broom"></i> Limpar Carrinho
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="carrinho-vazio">
                <i class="fa fa-shopping-cart"></i>
                <h2>Seu carrinho está vazio</h2>
                <p>Adicione produtos do cardápio para continuar</p>
                <a href="painel_cliente.php" class="btn-voltar-cardapio">
                    <i class="fa fa-utensils"></i> Ver Cardápio
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let timeoutId;

        function atualizarQuantidade(form) {
            clearTimeout(timeoutId);

            timeoutId = setTimeout(() => {
                // Submeter o formulário
                form.submit();
            }, 800);
        }

        function aumentar(btn) {
            const form = btn.closest('.form-quantidade');
            const input = form.querySelector('input[type="number"]');
            let valor = Number(input.value);

            if (valor < 99) {
                input.value = valor + 1;
                atualizarQuantidade(form);
            }
        }

        function diminuir(btn) {
            const form = btn.closest('.form-quantidade');
            const input = form.querySelector('input[type="number"]');
            let valor = Number(input.value);

            if (valor > 1) {
                input.value = valor - 1;
                atualizarQuantidade(form);
            }
        }

        // Prevenir scroll no input number
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.qtd-control input[type="number"]').forEach(input => {
                // Prevenir scroll do mouse
                input.addEventListener('wheel', function (e) {
                    e.preventDefault();
                });

                // Garantir que o valor seja numérico
                input.addEventListener('input', function () {
                    if (this.value === '') {
                        this.value = 1;
                    }
                    let val = parseInt(this.value);
                    if (isNaN(val) || val < 1) {
                        this.value = 1;
                    } else if (val > 99) {
                        this.value = 99;
                    }

                    const form = this.closest('.form-quantidade');
                    atualizarQuantidade(form);
                });
            });
        });

        // Salvar número da mesa
        const mesaInput = document.getElementById('numero_mesa');
        if (mesaInput) {
            const mesaSalva = localStorage.getItem('numero_mesa');
            if (mesaSalva) {
                mesaInput.value = mesaSalva;
            }

            mesaInput.addEventListener('input', function () {
                if (this.value) {
                    localStorage.setItem('numero_mesa', this.value);
                } else {
                    localStorage.removeItem('numero_mesa');
                }
            });
        }
    </script>
</body>

</html>