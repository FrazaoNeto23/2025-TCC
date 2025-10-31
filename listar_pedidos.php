<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

// ===== VERIFICAR E ADICIONAR COLUNAS FALTANTES =====
$check_numero = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'numero_pedido'");
if ($check_numero->num_rows == 0) {
    $conn->query("ALTER TABLE pedidos ADD COLUMN numero_pedido VARCHAR(20) AFTER id");
}

$check_prioridade = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'prioridade'");
if ($check_prioridade->num_rows == 0) {
    $conn->query("ALTER TABLE pedidos ADD COLUMN prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media' AFTER status");
}

$check_obs = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'observacoes'");
if ($check_obs->num_rows == 0) {
    $conn->query("ALTER TABLE pedidos ADD COLUMN observacoes TEXT AFTER metodo_pagamento");
}

// ===== FILTROS =====
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_busca = $_GET['busca'] ?? '';

$where_conditions = ["p.status != 'cancelado'"];
$params = [];
$types = "";

if ($filtro_status !== 'todos') {
    $where_conditions[] = "p.status = ?";
    $params[] = $filtro_status;
    $types .= "s";
}

if (!empty($filtro_busca)) {
    $where_conditions[] = "(p.numero_pedido LIKE ? OR u.nome LIKE ? OR p.id LIKE ?)";
    $busca_param = "%{$filtro_busca}%";
    $params[] = $busca_param;
    $params[] = $busca_param;
    $params[] = $busca_param;
    $types .= "sss";
}

$where_sql = implode(" AND ", $where_conditions);

// ===== BUSCAR PEDIDOS - CORRIGIDO =====
$sql = "
    SELECT 
        p.id,
        p.numero_pedido,
        p.id_cliente,
        u.nome as nome_cliente,
        u.email as email_cliente,
        p.total,
        p.status,
        p.prioridade,
        p.metodo_pagamento,
        p.numero_mesa,
        p.observacoes,
        p.data_pedido,
        GROUP_CONCAT(
            CONCAT(pr.nome, ' (', ip.quantidade, 'x)')
            SEPARATOR ', '
        ) as itens
    FROM pedidos p
    LEFT JOIN usuarios u ON p.id_cliente = u.id
    LEFT JOIN itens_pedido ip ON p.id = ip.id_pedido
    LEFT JOIN produtos pr ON ip.id_produto = pr.id
    WHERE {$where_sql}
    GROUP BY p.id
    ORDER BY 
        CASE p.prioridade
            WHEN 'alta' THEN 1
            WHEN 'media' THEN 2
            WHEN 'baixa' THEN 3
        END,
        p.data_pedido DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();
$pedidos = $resultado->fetch_all(MYSQLI_ASSOC);

// ===== ESTATÍSTICAS =====
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN status = 'em_preparo' THEN 1 ELSE 0 END) as em_preparo,
        SUM(CASE WHEN status = 'pronto' THEN 1 ELSE 0 END) as prontos,
        SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregues,
        SUM(CASE WHEN DATE(data_pedido) = CURDATE() THEN total ELSE 0 END) as faturamento_hoje
    FROM pedidos
    WHERE status != 'cancelado'
";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Pedidos - Burger House</title>
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters select,
        .filters input {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border 0.3s;
        }

        .filters select:focus,
        .filters input:focus {
            border-color: #667eea;
        }

        .filters button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }

        .filters button:hover {
            background: #5568d3;
        }

        .pedidos-grid {
            display: grid;
            gap: 20px;
        }

        .pedido-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .pedido-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .pedido-numero {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .pedido-prioridade {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .prioridade-alta {
            background: #ff4444;
            color: white;
        }

        .prioridade-media {
            background: #ffaa00;
            color: white;
        }

        .prioridade-baixa {
            background: #00cc44;
            color: white;
        }

        .pedido-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item i {
            color: #667eea;
            font-size: 18px;
        }

        .info-item .label {
            font-size: 12px;
            color: #999;
        }

        .info-item .value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }

        .pedido-itens {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .pedido-itens h4 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .pedido-itens p {
            color: #666;
            font-size: 13px;
            line-height: 1.6;
        }

        .pedido-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-status {
            background: #667eea;
            color: white;
        }

        .btn-status:hover {
            background: #5568d3;
        }

        .btn-prioridade {
            background: #ff6b6b;
            color: white;
        }

        .btn-prioridade:hover {
            background: #ee5a52;
        }

        .btn-imprimir {
            background: #51cf66;
            color: white;
        }

        .btn-imprimir:hover {
            background: #40c057;
        }

        .status-badge {
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

        .observacoes {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin-top: 10px;
            border-radius: 5px;
        }

        .observacoes strong {
            color: #856404;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .pedido-info {
                grid-template-columns: 1fr;
            }

            .pedido-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- HEADER COM ESTATÍSTICAS -->
        <div class="header">
            <h1><i class="fas fa-clipboard-list"></i> Gerenciar Pedidos</h1>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Pedidos</h3>
                    <div class="number"><?= $stats['total'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pendentes</h3>
                    <div class="number"><?= $stats['pendentes'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Em Preparo</h3>
                    <div class="number"><?= $stats['em_preparo'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Prontos</h3>
                    <div class="number"><?= $stats['prontos'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Entregues</h3>
                    <div class="number"><?= $stats['entregues'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Faturamento Hoje</h3>
                    <div class="number">R$ <?= number_format($stats['faturamento_hoje'], 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <select name="status">
                    <option value="todos" <?= $filtro_status == 'todos' ? 'selected' : '' ?>>Todos os Status</option>
                    <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    <option value="em_preparo" <?= $filtro_status == 'em_preparo' ? 'selected' : '' ?>>Em Preparo</option>
                    <option value="pronto" <?= $filtro_status == 'pronto' ? 'selected' : '' ?>>Prontos</option>
                    <option value="entregue" <?= $filtro_status == 'entregue' ? 'selected' : '' ?>>Entregues</option>
                </select>

                <input type="text" name="busca" placeholder="Buscar pedido, cliente..."
                    value="<?= htmlspecialchars($filtro_busca) ?>">

                <button type="submit"><i class="fas fa-search"></i> Filtrar</button>
                <a href="listar_pedidos.php" class="btn btn-status" style="text-decoration: none;"><i
                        class="fas fa-sync"></i> Limpar</a>
                <a href="painel_dono.php" class="btn btn-prioridade"
                    style="text-decoration: none; margin-left: auto;"><i class="fas fa-arrow-left"></i> Voltar</a>
            </form>
        </div>

        <!-- LISTA DE PEDIDOS -->
        <div class="pedidos-grid">
            <?php if (empty($pedidos)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Nenhum pedido encontrado</h3>
                    <p>Não há pedidos com os filtros selecionados.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="pedido-card">
                        <!-- CABEÇALHO -->
                        <div class="pedido-header">
                            <div class="pedido-numero">
                                <i class="fas fa-receipt"></i>
                                #<?= $pedido['numero_pedido'] ?? $pedido['id'] ?>
                            </div>
                            <div class="pedido-prioridade prioridade-<?= $pedido['prioridade'] ?>">
                                <?= strtoupper($pedido['prioridade']) ?>
                            </div>
                        </div>

                        <!-- INFORMAÇÕES -->
                        <div class="pedido-info">
                            <div class="info-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <div class="label">Cliente</div>
                                    <div class="value"><?= htmlspecialchars($pedido['nome_cliente']) ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-dollar-sign"></i>
                                <div>
                                    <div class="label">Total</div>
                                    <div class="value">R$ <?= number_format($pedido['total'], 2, ',', '.') ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <i class="fas fa-credit-card"></i>
                                <div>
                                    <div class="label">Pagamento</div>
                                    <div class="value"><?= ucfirst($pedido['metodo_pagamento']) ?></div>
                                </div>
                            </div>

                            <?php if ($pedido['numero_mesa']): ?>
                                <div class="info-item">
                                    <i class="fas fa-chair"></i>
                                    <div>
                                        <div class="label">Mesa</div>
                                        <div class="value">#<?= $pedido['numero_mesa'] ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <div class="label">Data/Hora</div>
                                    <div class="value"><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></div>
                                </div>
                            </div>

                            <div class="info-item">
                                <span class="status-badge status-<?= $pedido['status'] ?>">
                                    <?= ucwords(str_replace('_', ' ', $pedido['status'])) ?>
                                </span>
                            </div>
                        </div>

                        <!-- ITENS DO PEDIDO -->
                        <div class="pedido-itens">
                            <h4><i class="fas fa-hamburger"></i> Itens do Pedido:</h4>
                            <p><?= $pedido['itens'] ?? 'Sem itens' ?></p>
                        </div>

                        <!-- OBSERVAÇÕES -->
                        <?php if (!empty($pedido['observacoes'])): ?>
                            <div class="observacoes">
                                <strong><i class="fas fa-comment"></i> Observações:</strong><br>
                                <?= nl2br(htmlspecialchars($pedido['observacoes'])) ?>
                            </div>
                        <?php endif; ?>

                        <!-- AÇÕES -->
                        <div class="pedido-actions">
                            <form method="POST" action="alterar_status.php" style="display: inline;">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id'] ?>">
                                <select name="novo_status" class="btn btn-status" onchange="this.form.submit()">
                                    <option value="">Alterar Status</option>
                                    <option value="pendente">Pendente</option>
                                    <option value="em_preparo">Em Preparo</option>
                                    <option value="pronto">Pronto</option>
                                    <option value="entregue">Entregue</option>
                                </select>
                            </form>

                            <form method="POST" action="alterar_prioridade.php" style="display: inline;">
                                <input type="hidden" name="id_pedido" value="<?= $pedido['id'] ?>">
                                <select name="nova_prioridade" class="btn btn-prioridade" onchange="this.form.submit()">
                                    <option value="">Alterar Prioridade</option>
                                    <option value="alta">Alta</option>
                                    <option value="media">Média</option>
                                    <option value="baixa">Baixa</option>
                                </select>
                            </form>

                            <button class="btn btn-imprimir" onclick="imprimirPedido(<?= $pedido['id'] ?>)">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function imprimirPedido(id) {
            window.open('imprimir_pedido.php?id=' + id, '_blank', 'width=800,height=600');
        }
    </script>
</body>

</html>