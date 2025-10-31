<?php
include "config_seguro.php";
include "verificar_sessao.php";

verificarDono(); // Ou verificarFuncionario() se funcionários também podem acessar

// ... resto do código
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}

// Atualizar status via GET - CORRIGIDO
if (isset($_GET['update_status'], $_GET['id'])) {
    $novo_status = $_GET['update_status'];
    $id_pedido = intval($_GET['id']);
    
    // Validar status permitido
    $status_validos = ['Pendente', 'Em preparo', 'Entregando', 'Entregue', 'Cancelado'];
    if (!in_array($novo_status, $status_validos)) {
        die("Status inválido");
    }
    
    $stmt = $conn->prepare("UPDATE pedidos SET status=? WHERE id=?");
    $stmt->bind_param("si", $novo_status, $id_pedido);
    $stmt->execute();
    header("Location: pedidos.php");
    exit;
}

// Marcar como entregue
if (isset($_GET['entregue'])) {
    $id_pedido = intval($_GET['entregue']);
    $stmt = $conn->prepare("UPDATE pedidos SET status='Entregue' WHERE id=?");
    $stmt->bind_param("i", $id_pedido);
    $stmt->execute();
    header("Location: pedidos.php");
    exit;
}

// Filtros - CORRIGIDO COM PREPARED STATEMENTS
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'todos';
$filtro_pagamento = isset($_GET['pagamento']) ? $_GET['pagamento'] : 'todos';

$where_clauses = [];
$params = [];
$types = "";

if ($filtro_status != 'todos') {
    $where_clauses[] = "pedidos.status = ?";
    $params[] = $filtro_status;
    $types .= "s";
}

if ($filtro_pagamento != 'todos') {
    $where_clauses[] = "pedidos.status_pagamento = ?";
    $params[] = $filtro_pagamento;
    $types .= "s";
}

// Condição para esconder pedidos finalizados
if (empty($where_clauses)) {
    $where_sql = "WHERE NOT (pedidos.status = 'Entregue' AND pedidos.status_pagamento = 'Pago')";
} else {
    if ($filtro_status != 'Entregue') {
        $where_clauses[] = "NOT (pedidos.status = 'Entregue' AND pedidos.status_pagamento = 'Pago')";
    }
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Busca pedidos - CORRIGIDO
$sql = "
    SELECT pedidos.*, 
           usuarios.nome AS cliente_nome,
           usuarios.email AS cliente_email,
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
    $where_sql
    ORDER BY 
        CASE 
            WHEN pedidos.status = 'Pendente' THEN 1
            WHEN pedidos.status = 'Em preparo' THEN 2
            WHEN pedidos.status = 'Entregando' THEN 3
            ELSE 4
        END,
        pedidos.data DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $pedidos = $stmt->get_result();
} else {
    $pedidos = $conn->query($sql);
}

// Estatísticas - sem parâmetros, pode ficar sem prepared statement
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN status = 'Em preparo' THEN 1 ELSE 0 END) as preparo,
        SUM(CASE WHEN status = 'Entregando' THEN 1 ELSE 0 END) as entregando,
        SUM(CASE WHEN status_pagamento = 'Aguardando' THEN 1 ELSE 0 END) as aguardando_pag,
        SUM(total) as valor_total
    FROM pedidos
    WHERE data >= CURDATE()
")->fetch_assoc();
?>
<!-- Resto do HTML permanece igual -->
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Pedidos</title>
    <link rel="stylesheet" href="css/pedidos.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <!-- Header com botão voltar -->
        <div class="header-pedidos">
            <a href="painel_dono.php" class="btn-voltar">
                <i class="fa fa-arrow-left"></i> Voltar ao Painel
            </a>
            <h1><i class="fa fa-receipt"></i> Gerenciar Pedidos</h1>
            <div class="header-spacer"></div>
        </div>

        <!-- Estatísticas -->
        <div class="stats-container">
            <div class="stat-card">
                <i class="fa fa-shopping-bag"></i>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total'] ?></span>
                    <span class="stat-label">Total Hoje</span>
                </div>
            </div>
            <div class="stat-card stat-pendente">
                <i class="fa fa-clock"></i>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['pendentes'] ?></span>
                    <span class="stat-label">Pendentes</span>
                </div>
            </div>
            <div class="stat-card stat-preparo">
                <i class="fa fa-fire"></i>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['preparo'] ?></span>
                    <span class="stat-label">Em Preparo</span>
                </div>
            </div>
            <div class="stat-card stat-entregando">
                <i class="fa fa-truck"></i>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['entregando'] ?></span>
                    <span class="stat-label">Entregando</span>
                </div>
            </div>
            <div class="stat-card stat-valor">
                <i class="fa fa-dollar-sign"></i>
                <div class="stat-info">
                    <span class="stat-value">R$ <?= number_format($stats['valor_total'], 2, ',', '.') ?></span>
                    <span class="stat-label">Faturamento</span>
                </div>
            </div>
        </div>

        <!-- Filtros (SEM BOTÃO LIMPAR) -->
        <div class="filtros-container">
            <div class="filtros">
                <label><i class="fa fa-filter"></i> Filtrar por Status:</label>
                <select onchange="window.location.href='?status=' + this.value + '&pagamento=<?= $filtro_pagamento ?>'">
                    <option value="todos" <?= $filtro_status == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="Pendente" <?= $filtro_status == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="Em preparo" <?= $filtro_status == 'Em preparo' ? 'selected' : '' ?>>Em Preparo</option>
                    <option value="Entregando" <?= $filtro_status == 'Entregando' ? 'selected' : '' ?>>Entregando</option>
                    <option value="Entregue" <?= $filtro_status == 'Entregue' ? 'selected' : '' ?>>Entregue (Histórico)
                    </option>
                </select>

                <label><i class="fa fa-credit-card"></i> Pagamento:</label>
                <select onchange="window.location.href='?status=<?= $filtro_status ?>&pagamento=' + this.value">
                    <option value="todos" <?= $filtro_pagamento == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="Aguardando" <?= $filtro_pagamento == 'Aguardando' ? 'selected' : '' ?>>Aguardando
                    </option>
                    <option value="Pago" <?= $filtro_pagamento == 'Pago' ? 'selected' : '' ?>>Pago</option>
                </select>
            </div>
            <!-- BOTÃO "LIMPAR ANTIGOS" FOI REMOVIDO -->
        </div>

        <!-- Info sobre pedidos ocultos -->
        <?php if ($filtro_status != 'Entregue'): ?>
            <div
                style="background: #1e1e1e; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #00cc55; color: #00cc55; font-size: 14px; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-info-circle"></i>
                <span>Pedidos entregues e pagos estão ocultos automaticamente. Para ver histórico completo, selecione
                    "Entregue (Histórico)" no filtro de status.</span>
            </div>
        <?php endif; ?>

        <!-- Grid de Pedidos -->
        <div class="pedidos-grid">
            <?php if ($pedidos->num_rows > 0): ?>
                <?php while ($p = $pedidos->fetch_assoc()):
                    $status_class = strtolower(str_replace(' ', '-', $p['status']));
                    $pag_class = strtolower($p['status_pagamento']);
                    ?>
                    <div class="pedido-card-grid status-<?= $status_class ?>">
                        <div class="pedido-numero">
                            <span class="numero">#<?= $p['id'] ?></span>
                            <?php if ($p['numero_mesa']): ?>
                                <span class="mesa-info"><i class="fa fa-table"></i> Mesa <?= $p['numero_mesa'] ?></span>
                            <?php else: ?>
                                <span class="mesa-info delivery"><i class="fa fa-motorcycle"></i> Delivery</span>
                            <?php endif; ?>
                        </div>

                        <div class="pedido-cliente">
                            <i class="fa fa-user-circle"></i>
                            <div>
                                <strong><?= $p['cliente_nome'] ?></strong>
                                <small><?= $p['cliente_email'] ?></small>
                            </div>
                        </div>

                        <div class="pedido-produto">
                            <?php if ($p['produto_imagem']): ?>
                                <img src="uploads/<?= $p['produto_imagem'] ?>" alt="<?= $p['produto_nome'] ?>">
                            <?php endif; ?>
                            <div class="produto-info">
                                <h3><?= $p['produto_nome'] ?></h3>
                                <p><i class="fa fa-box"></i> Qtd: <?= $p['quantidade'] ?></p>
                                <p class="total"><i class="fa fa-dollar-sign"></i> R$
                                    <?= number_format($p['total'], 2, ',', '.') ?>
                                </p>
                            </div>
                        </div>

                        <div class="pedido-status-info">
                            <span class="badge-status status-<?= $status_class ?>">
                                <?= $p['status'] ?>
                            </span>
                            <span class="badge-pagamento pag-<?= $pag_class ?>">
                                <?= $p['status_pagamento'] ?>
                            </span>
                            <?php if ($p['metodo_pagamento']): ?>
                                <small class="metodo"><i class="fa fa-info-circle"></i>
                                    <?= ucfirst($p['metodo_pagamento']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="pedido-acoes-grid">
                            <?php if ($p['status'] == 'Pendente'): ?>
                                <a href="?update_status=Em preparo&id=<?= $p['id'] ?>" class="btn-acao btn-preparo">
                                    <i class="fa fa-fire"></i> Iniciar Preparo
                                </a>
                            <?php endif; ?>

                            <?php if ($p['status'] == 'Em preparo'): ?>
                                <a href="?update_status=Entregando&id=<?= $p['id'] ?>" class="btn-acao btn-entregar">
                                    <i class="fa fa-truck"></i> Sair p/ Entrega
                                </a>
                            <?php endif; ?>

                            <?php if ($p['status'] == 'Entregando'): ?>
                                <a href="?entregue=<?= $p['id'] ?>" class="btn-acao btn-finalizar">
                                    <i class="fa fa-check-circle"></i> Marcar Entregue
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="sem-pedidos">
                    <i class="fa fa-inbox"></i>
                    <p>Nenhum pedido encontrado</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh a cada 30 segundos
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>

</html>