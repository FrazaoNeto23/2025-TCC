<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

// ===== ESTATÍSTICAS DO DASHBOARD =====
$stats = [];

// Total de pedidos hoje
$stats['pedidos_hoje'] = $conn->query("
    SELECT COUNT(*) as total 
    FROM pedidos 
    WHERE DATE(data_pedido) = CURDATE() AND status != 'cancelado'
")->fetch_assoc()['total'];

// Faturamento hoje
$stats['faturamento_hoje'] = $conn->query("
    SELECT COALESCE(SUM(total), 0) as total 
    FROM pedidos 
    WHERE DATE(data_pedido) = CURDATE() AND status != 'cancelado'
")->fetch_assoc()['total'];

// Pedidos pendentes
$stats['pedidos_pendentes'] = $conn->query("
    SELECT COUNT(*) as total 
    FROM pedidos 
    WHERE status = 'pendente'
")->fetch_assoc()['total'];

// Pedidos em preparo
$stats['pedidos_preparo'] = $conn->query("
    SELECT COUNT(*) as total 
    FROM pedidos 
    WHERE status = 'em_preparo'
")->fetch_assoc()['total'];

// Total de produtos
$stats['total_produtos'] = $conn->query("
    SELECT COUNT(*) as total 
    FROM produtos
")->fetch_assoc()['total'];

// Total de clientes
$stats['total_clientes'] = $conn->query("
    SELECT COUNT(*) as total 
    FROM usuarios 
    WHERE tipo = 'cliente'
")->fetch_assoc()['total'];

// Ticket médio
$stats['ticket_medio'] = $conn->query("
    SELECT COALESCE(AVG(total), 0) as media 
    FROM pedidos 
    WHERE status != 'cancelado'
")->fetch_assoc()['media'];

// Produto mais vendido
$produto_top = $conn->query("
    SELECT pr.nome, SUM(ip.quantidade) as total_vendido
    FROM itens_pedido ip
    JOIN produtos pr ON ip.id_produto = pr.id
    GROUP BY ip.id_produto
    ORDER BY total_vendido DESC
    LIMIT 1
")->fetch_assoc();

// Faturamento da semana
$faturamento_semana = $conn->query("
    SELECT COALESCE(SUM(total), 0) as total
    FROM pedidos
    WHERE WEEK(data_pedido) = WEEK(CURDATE()) 
    AND YEAR(data_pedido) = YEAR(CURDATE())
    AND status != 'cancelado'
")->fetch_assoc()['total'];

// Faturamento do mês
$faturamento_mes = $conn->query("
    SELECT COALESCE(SUM(total), 0) as total
    FROM pedidos
    WHERE MONTH(data_pedido) = MONTH(CURDATE()) 
    AND YEAR(data_pedido) = YEAR(CURDATE())
    AND status != 'cancelado'
")->fetch_assoc()['total'];

// Últimos 5 pedidos
$ultimos_pedidos = $conn->query("
    SELECT 
        p.id,
        p.numero_pedido,
        u.nome as cliente,
        p.total,
        p.status,
        p.data_pedido
    FROM pedidos p
    LEFT JOIN usuarios u ON p.id_cliente = u.id
    ORDER BY p.data_pedido DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Dono - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
        }

        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header .user-info i {
            font-size: 32px;
            color: #667eea;
        }

        .header .user-name {
            font-size: 18px;
            color: #666;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout {
            background: #ff6b6b;
            color: white;
        }

        .btn-logout:hover {
            background: #ee5a52;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .stat-card.pedidos .icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.faturamento .icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .stat-card.pendentes .icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .stat-card.preparo .icon {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
        }

        .stat-card.produtos .icon {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #333;
        }

        .stat-card.clientes .icon {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #333;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #999;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #333;
        }

        .stat-card .description {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .action-card i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #667eea;
        }

        .action-card h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .action-card p {
            color: #666;
            font-size: 14px;
        }

        .recent-orders {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .recent-orders h2 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s;
        }

        .order-item:hover {
            background: #f8f9fa;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-info {
            flex: 1;
        }

        .order-number {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .order-client {
            font-size: 14px;
            color: #666;
        }

        .order-value {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            margin: 0 20px;
        }

        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pendente {
            background: #ffd43b;
            color: #333;
        }

        .status-em_preparo {
            background: #74c0fc;
            color: #1864ab;
        }

        .status-pronto {
            background: #51cf66;
            color: white;
        }

        .status-entregue {
            background: #868e96;
            color: white;
        }

        @media (max-width: 768px) {
            .header {
                text-align: center;
                flex-direction: column;
            }

            .stats-grid,
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .order-item {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .order-value {
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div>
                <h1><i class="fas fa-burger"></i> Painel do Dono</h1>
            </div>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div class="user-name">Olá, <?= htmlspecialchars($_SESSION['usuario']) ?>!</div>
                    <a href="logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>

        <!-- ESTATÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card pedidos">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <h3>Pedidos Hoje</h3>
                <div class="number"><?= $stats['pedidos_hoje'] ?></div>
            </div>

            <div class="stat-card faturamento">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <h3>Faturamento Hoje</h3>
                <div class="number">R$ <?= number_format($stats['faturamento_hoje'], 2, ',', '.') ?></div>
            </div>

            <div class="stat-card pendentes">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <h3>Pedidos Pendentes</h3>
                <div class="number"><?= $stats['pedidos_pendentes'] ?></div>
            </div>

            <div class="stat-card preparo">
                <div class="icon"><i class="fas fa-fire"></i></div>
                <h3>Em Preparo</h3>
                <div class="number"><?= $stats['pedidos_preparo'] ?></div>
            </div>

            <div class="stat-card produtos">
                <div class="icon"><i class="fas fa-hamburger"></i></div>
                <h3>Total de Produtos</h3>
                <div class="number"><?= $stats['total_produtos'] ?></div>
            </div>

            <div class="stat-card clientes">
                <div class="icon"><i class="fas fa-users"></i></div>
                <h3>Total de Clientes</h3>
                <div class="number"><?= $stats['total_clientes'] ?></div>
            </div>
        </div>

        <!-- CARDS DE INFORMAÇÕES ADICIONAIS -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Ticket Médio</h3>
                <div class="number">R$ <?= number_format($stats['ticket_medio'], 2, ',', '.') ?></div>
                <div class="description">Valor médio por pedido</div>
            </div>

            <div class="stat-card">
                <h3>Produto Mais Vendido</h3>
                <div class="number" style="font-size: 20px;">
                    <?= $produto_top['nome'] ?? 'N/A' ?>
                </div>
                <div class="description">
                    <?= $produto_top ? $produto_top['total_vendido'] . ' vendidos' : 'Sem vendas' ?>
                </div>
            </div>

            <div class="stat-card">
                <h3>Faturamento Semana</h3>
                <div class="number">R$ <?= number_format($faturamento_semana, 2, ',', '.') ?></div>
            </div>

            <div class="stat-card">
                <h3>Faturamento Mês</h3>
                <div class="number">R$ <?= number_format($faturamento_mes, 2, ',', '.') ?></div>
            </div>
        </div>

        <!-- AÇÕES RÁPIDAS -->
        <div class="actions-grid">
            <a href="listar_pedidos.php" class="action-card" style="text-decoration: none;">
                <i class="fas fa-list-alt"></i>
                <h3>Gerenciar Pedidos</h3>
                <p>Visualize e gerencie todos os pedidos</p>
            </a>

            <a href="gerenciar_produtos.php" class="action-card" style="text-decoration: none;">
                <i class="fas fa-hamburger"></i>
                <h3>Gerenciar Produtos</h3>
                <p>Adicione, edite ou remova produtos</p>
            </a>

            <a href="relatorios.php" class="action-card" style="text-decoration: none;">
                <i class="fas fa-chart-bar"></i>
                <h3>Relatórios</h3>
                <p>Visualize relatórios e estatísticas</p>
            </a>

            <a href="configuracoes.php" class="action-card" style="text-decoration: none;">
                <i class="fas fa-cog"></i>
                <h3>Configurações</h3>
                <p>Ajuste as configurações do sistema</p>
            </a>
        </div>

        <!-- ÚLTIMOS PEDIDOS -->
        <div class="recent-orders">
            <h2><i class="fas fa-receipt"></i> Últimos Pedidos</h2>
            <?php if (empty($ultimos_pedidos)): ?>
                <p style="text-align: center; color: #999; padding: 40px;">Nenhum pedido realizado ainda.</p>
            <?php else: ?>
                <?php foreach ($ultimos_pedidos as $pedido): ?>
                    <div class="order-item">
                        <div class="order-info">
                            <div class="order-number">
                                Pedido #<?= $pedido['numero_pedido'] ?? $pedido['id'] ?>
                            </div>
                            <div class="order-client">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($pedido['cliente']) ?>
                                • <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
                            </div>
                        </div>
                        <div class="order-value">
                            R$ <?= number_format($pedido['total'], 2, ',', '.') ?>
                        </div>
                        <span class="order-status status-<?= $pedido['status'] ?>">
                            <?= ucwords(str_replace('_', ' ', $pedido['status'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
