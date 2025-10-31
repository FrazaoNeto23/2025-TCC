<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    die("Acesso negado!");
}

$id_pedido = intval($_GET['id'] ?? 0);

if ($id_pedido <= 0) {
    die("Pedido inválido!");
}

// ===== BUSCAR DADOS DO PEDIDO =====
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
        p.data_pedido
    FROM pedidos p
    LEFT JOIN usuarios u ON p.id_cliente = u.id
    WHERE p.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();

if (!$pedido) {
    die("Pedido não encontrado!");
}

// ===== BUSCAR ITENS DO PEDIDO =====
$sql_itens = "
    SELECT 
        pr.nome,
        ip.quantidade,
        ip.preco_unitario,
        (ip.quantidade * ip.preco_unitario) as subtotal
    FROM itens_pedido ip
    JOIN produtos pr ON ip.id_produto = pr.id
    WHERE ip.id_pedido = ?
";

$stmt_itens = $conn->prepare($sql_itens);
$stmt_itens->bind_param("i", $id_pedido);
$stmt_itens->execute();
$itens = $stmt_itens->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?= $pedido['numero_pedido'] ?? $pedido['id'] ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        .ticket {
            border: 2px dashed #333;
            padding: 20px;
            background: white;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            color: #666;
        }

        .info-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #999;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .info-row strong {
            font-weight: bold;
        }

        .items-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .items-table th {
            background: #f0f0f0;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #333;
        }

        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }

        .items-table .qty {
            text-align: center;
            width: 60px;
        }

        .items-table .price {
            text-align: right;
            width: 100px;
        }

        .total-section {
            text-align: right;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #333;
        }

        .observacoes {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin-top: 20px;
        }

        .observacoes strong {
            display: block;
            margin-bottom: 8px;
            color: #856404;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px dashed #333;
            font-size: 12px;
            color: #666;
        }

        .prioridade {
            display: inline-block;
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

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <!-- CABEÇALHO -->
        <div class="header">
            <h1>🍔 BURGER HOUSE</h1>
            <p>Os melhores hambúrgueres da cidade!</p>
            <p>Tel: (12) 98765-4321</p>
        </div>

        <!-- INFORMAÇÕES DO PEDIDO -->
        <div class="info-section">
            <div class="info-row">
                <strong>Pedido:</strong>
                <span>#<?= $pedido['numero_pedido'] ?? $pedido['id'] ?></span>
            </div>
            <div class="info-row">
                <strong>Data/Hora:</strong>
                <span><?= date('d/m/Y H:i:s', strtotime($pedido['data_pedido'])) ?></span>
            </div>
            <div class="info-row">
                <strong>Cliente:</strong>
                <span><?= htmlspecialchars($pedido['nome_cliente']) ?></span>
            </div>
            <?php if ($pedido['numero_mesa']): ?>
            <div class="info-row">
                <strong>Mesa:</strong>
                <span>#<?= $pedido['numero_mesa'] ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <strong>Pagamento:</strong>
                <span><?= ucfirst($pedido['metodo_pagamento']) ?></span>
            </div>
            <div class="info-row">
                <strong>Prioridade:</strong>
                <span class="prioridade prioridade-<?= $pedido['prioridade'] ?>">
                    <?= strtoupper($pedido['prioridade']) ?>
                </span>
            </div>
            <div class="info-row">
                <strong>Status:</strong>
                <span><?= ucwords(str_replace('_', ' ', $pedido['status'])) ?></span>
            </div>
        </div>

        <!-- ITENS DO PEDIDO -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="qty">Qtd</th>
                    <th class="price">Preço Unit.</th>
                    <th class="price">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nome']) ?></td>
                    <td class="qty"><?= $item['quantidade'] ?>x</td>
                    <td class="price">R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                    <td class="price">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTAL -->
        <div class="total-section">
            TOTAL: R$ <?= number_format($pedido['total'], 2, ',', '.') ?>
        </div>

        <!-- OBSERVAÇÕES -->
        <?php if (!empty($pedido['observacoes'])): ?>
        <div class="observacoes">
            <strong>⚠️ OBSERVAÇÕES:</strong>
            <?= nl2br(htmlspecialchars($pedido['observacoes'])) ?>
        </div>
        <?php endif; ?>

        <!-- RODAPÉ -->
        <div class="footer">
            <p>Obrigado pela preferência!</p>
            <p>Volte sempre! 😊</p>
        </div>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 15px 30px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir
        </button>
        <button onclick="window.close()" style="padding: 15px 30px; background: #ff6b6b; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; font-weight: bold; margin-left: 10px;">
            ❌ Fechar
        </button>
    </div>

    <script>
        // Auto-imprimir ao carregar
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
