<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
require_once CONFIG_PATH . "/config.php";

// Login de funcionário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_func'])) {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $stmt = $conn->prepare("SELECT * FROM funcionarios WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && password_verify($senha, $res['senha'])) {
        $_SESSION['funcionario'] = $res['nome'];
        $_SESSION['id_funcionario'] = $res['id'];
        $_SESSION['tipo'] = 'funcionario';
        header("Location: painel_funcionario.php");
        exit;
    } else {
        $erro = "Email ou senha incorretos!";
    }
}

// Verificar se é funcionário
if (!isset($_SESSION['funcionario'])) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="UTF-8">
        <title>Login Funcionário</title>
        <link rel="stylesheet" href="css/acesso.css?e=<?php
require_once __DIR__ . '/../config/paths.php'; echo rand(0, 10000) ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>

    <body>
        <div class="container">
            <h1>Acesso de Funcionário</h1>
            <?php
require_once __DIR__ . '/../config/paths.php'; if (isset($erro))
                echo "<p class='msg'>$erro</p>"; ?>
            <div class="forms">
                <form method="POST" class="active">
                    <h2>Login</h2>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="password" name="senha" placeholder="Senha" required>
                    <button type="submit" name="login_func">Entrar</button>
                    <p class="switch"><a href="index.php" style="color:#0ff;text-decoration:none;">Voltar à página
                            inicial</a></p>
                </form>
            </div>
        </div>
    </body>

    </html>
    <?php
require_once __DIR__ . '/../config/paths.php';
    exit;
}

// Buscar pedidos com número de mesa
$pedidos = $conn->query("
    SELECT pedidos.*, 
           usuarios.nome AS cliente_nome,
           CASE 
               WHEN pedidos.tipo_produto = 'normal' THEN produtos.nome
               WHEN pedidos.tipo_produto = 'especial' THEN produtos_especiais.nome
           END as produto_nome,
           CASE 
               WHEN pedidos.tipo_produto = 'normal' THEN produtos.imagem
               WHEN pedidos.tipo_produto = 'especial' THEN produtos_especiais.imagem
           END as produto_imagem
    FROM pedidos
    JOIN usuarios ON pedidos.id_cliente = usuarios.id
    LEFT JOIN produtos ON pedidos.id_produto = produtos.id AND pedidos.tipo_produto = 'normal'
    LEFT JOIN produtos_especiais ON pedidos.id_produto = produtos_especiais.id AND pedidos.tipo_produto = 'especial'
    WHERE pedidos.status != 'Entregue'
    ORDER BY pedidos.numero_mesa ASC, pedidos.data DESC
");

// Buscar produtos especiais
$produtos_especiais = $conn->query("SELECT * FROM produtos_especiais");
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Painel do Funcionário</title>
    <link rel="stylesheet" href="css/funcionario_painel.css?e=<?php
require_once __DIR__ . '/../config/paths.php'; echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="header-func">
            <h1><i class="fa fa-user-tie"></i> Painel do Funcionário</h1>
            <div class="user-info">
                <span><i class="fa fa-user-circle"></i> <?= $_SESSION['funcionario'] ?></span>
                <a href="logout.php" class="btn-sair"><i class="fa fa-right-from-bracket"></i> Sair</a>
            </div>
        </div>

        <div class="dashboard-menu">
            <button class="menu-btn active" onclick="mostrarSecao('pedidos')">
                <i class="fa fa-receipt"></i> Pedidos por Mesa
            </button>
            <button class="menu-btn" onclick="mostrarSecao('cardapio')">
                <i class="fa fa-star"></i> Cardápio Especial
            </button>
        </div>

        <!-- Seção de Pedidos por Mesa -->
        <div id="secao-pedidos" class="secao active">
            <h2><i class="fa fa-table"></i> Pedidos por Mesa</h2>

            <?php
require_once __DIR__ . '/../config/paths.php'; if ($pedidos->num_rows > 0): ?>
                <div class="mesas-container">
                    <?php
require_once __DIR__ . '/../config/paths.php';
                    $pedidos_por_mesa = [];
                    $pedidos->data_seek(0);
                    while ($p = $pedidos->fetch_assoc()) {
                        $mesa = $p['numero_mesa'] ?? 'Sem mesa';
                        if (!isset($pedidos_por_mesa[$mesa])) {
                            $pedidos_por_mesa[$mesa] = [];
                        }
                        $pedidos_por_mesa[$mesa][] = $p;
                    }

                    foreach ($pedidos_por_mesa as $mesa => $pedidos_mesa):
                        ?>
                        <div class="mesa-card">
                            <div class="mesa-header">
                                <h3><i class="fa fa-table"></i> <?= $mesa == 'Sem mesa' ? 'Delivery' : "Mesa $mesa" ?></h3>
                                <span class="mesa-badge"><?= count($pedidos_mesa) ?> pedido(s)</span>
                            </div>

                            <div class="pedidos-mesa">
                                <?php
require_once __DIR__ . '/../config/paths.php'; foreach ($pedidos_mesa as $p):
                                    $status_class = strtolower(str_replace(' ', '-', $p['status']));
                                    ?>
                                    <div class="pedido-item status-<?= $status_class ?>">
                                        <div class="pedido-info-mini">
                                            <span class="pedido-num">#<?= $p['id'] ?></span>
                                            <span class="pedido-cliente"><?= $p['cliente_nome'] ?></span>
                                        </div>

                                        <?php
require_once __DIR__ . '/../config/paths.php'; if ($p['produto_imagem']): ?>
                                            <img src="uploads/<?= $p['produto_imagem'] ?>" alt="<?= $p['produto_nome'] ?>"
                                                class="pedido-img-mini">
                                        <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

                                        <div class="pedido-detalhes-mini">
                                            <strong><?= $p['produto_nome'] ?></strong>
                                            <p>Qtd: <?= $p['quantidade'] ?> | R$ <?= number_format($p['total'], 2, ',', '.') ?></p>
                                            <span class="badge-status-mini"><?= $p['status'] ?></span>
                                        </div>
                                    </div>
                                <?php
require_once __DIR__ . '/../config/paths.php'; endforeach; ?>
                            </div>
                        </div>
                    <?php
require_once __DIR__ . '/../config/paths.php'; endforeach; ?>
                </div>
            <?php
require_once __DIR__ . '/../config/paths.php'; else: ?>
                <div class="sem-pedidos">
                    <i class="fa fa-inbox"></i>
                    <p>Nenhum pedido ativo no momento</p>
                </div>
            <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>
        </div>

        <!-- Seção do Cardápio Especial -->
        <div id="secao-cardapio" class="secao">
            <h2><i class="fa fa-star"></i> Cardápio Especial</h2>

            <?php
require_once __DIR__ . '/../config/paths.php'; if ($produtos_especiais->num_rows > 0): ?>
                <div class="produtos-especiais">
                    <?php
require_once __DIR__ . '/../config/paths.php'; while ($pe = $produtos_especiais->fetch_assoc()): ?>
                        <div class="produto-especial-card">
                            <?php
require_once __DIR__ . '/../config/paths.php'; if ($pe['imagem']): ?>
                                <img src="uploads/<?= $pe['imagem'] ?>" alt="<?= $pe['nome'] ?>">
                            <?php
require_once __DIR__ . '/../config/paths.php'; else: ?>
                                <div class="sem-imagem-especial"><i class="fa fa-image"></i></div>
                            <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

                            <div class="produto-info-especial">
                                <h3><i class="fa fa-star"></i> <?= $pe['nome'] ?></h3>
                                <p class="descricao"><?= $pe['descricao'] ?></p>
                                <p class="preco-especial">R$ <?= number_format($pe['preco'], 2, ',', '.') ?></p>
                            </div>
                        </div>
                    <?php
require_once __DIR__ . '/../config/paths.php'; endwhile; ?>
                </div>
            <?php
require_once __DIR__ . '/../config/paths.php'; else: ?>
                <div class="sem-produtos">
                    <i class="fa fa-utensils"></i>
                    <p>Nenhum produto especial cadastrado ainda</p>
                </div>
            <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>
        </div>
    </div>

    <script>
        function mostrarSecao(secao) {
            document.querySelectorAll('.secao').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.menu-btn').forEach(b => b.classList.remove('active'));

            document.getElementById('secao-' + secao).classList.add('active');
            event.target.closest('.menu-btn').classList.add('active');
        }

        // Auto-refresh a cada 30 segundos
        setTimeout(() => location.reload(), 30000);
    </script>
</body>

</html>