<?php
/**
 * SCRIPT DE LIMPEZA DE CARRINHO
 * Execute este arquivo UMA VEZ para limpar produtos inválidos
 * Acesse: http://seusite.com/limpar_carrinho_invalido.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "config.php";

// Proteção: apenas admin pode executar
if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != 'dono') {
    die("❌ Acesso negado! Apenas administradores podem executar este script.");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpeza de Carrinho - Burger House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #0f0;
            padding: 40px;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #000;
            padding: 30px;
            border: 2px solid #0f0;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.3);
        }

        h1 {
            color: #0f0;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 0 10px #0f0;
        }

        .section {
            margin: 20px 0;
            padding: 20px;
            background: #0a0a0a;
            border-left: 4px solid #0f0;
        }

        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .success {
            background: #004400;
            color: #0f0;
            border: 1px solid #0f0;
        }

        .error {
            background: #440000;
            color: #f00;
            border: 1px solid #f00;
        }

        .warning {
            background: #444400;
            color: #ff0;
            border: 1px solid #ff0;
        }

        .info {
            background: #000044;
            color: #0ff;
            border: 1px solid #0ff;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px 5px;
            background: #0f0;
            color: #000;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #0c0;
            box-shadow: 0 0 15px #0f0;
        }

        .btn-danger {
            background: #f00;
            color: #fff;
        }

        .btn-danger:hover {
            background: #c00;
            box-shadow: 0 0 15px #f00;
        }

        pre {
            background: #0a0a0a;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            color: #0ff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border: 1px solid #0f0;
        }

        th {
            background: #003300;
            color: #0f0;
        }

        td {
            background: #001100;
        }

        .progress {
            width: 100%;
            height: 30px;
            background: #000;
            border: 2px solid #0f0;
            border-radius: 5px;
            overflow: hidden;
            margin: 20px 0;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #0f0, #0c0);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>
            <i class="fa fa-broom"></i> LIMPEZA DE CARRINHOS INVÁLIDOS
        </h1>

        <?php
        $executar = isset($_GET['executar']) && $_GET['executar'] === 'sim';

        if (!$executar):
            ?>
            <!-- MODO PRÉ-VISUALIZAÇÃO -->
            <div class="section">
                <h2><i class="fa fa-info-circle"></i> MODO: PRÉ-VISUALIZAÇÃO</h2>
                <div class="status info">
                    <strong>ℹ️ Informação:</strong> Este é o modo de pré-visualização. Nenhuma alteração será feita no banco
                    de dados.
                </div>
            </div>

            <?php
            // Análise: Produtos normais inválidos
            $sql_produtos_invalidos = "
                SELECT 
                    c.id,
                    c.id_cliente,
                    c.id_produto,
                    c.tipo_produto,
                    c.quantidade,
                    u.nome as cliente_nome,
                    c.data_adicao
                FROM carrinho c
                LEFT JOIN produtos p ON c.id_produto = p.id AND c.tipo_produto = 'normal'
                LEFT JOIN produtos_especiais pe ON c.id_produto = pe.id AND c.tipo_produto = 'especial'
                LEFT JOIN usuarios u ON c.id_cliente = u.id
                WHERE (c.tipo_produto = 'normal' AND p.id IS NULL)
                   OR (c.tipo_produto = 'especial' AND pe.id IS NULL)
            ";

            $resultado = $conn->query($sql_produtos_invalidos);
            $total_invalidos = $resultado->num_rows;
            ?>

            <div class="section">
                <h2><i class="fa fa-search"></i> ANÁLISE DO CARRINHO</h2>

                <div class="status <?= $total_invalidos > 0 ? 'warning' : 'success' ?>">
                    <strong><?= $total_invalidos > 0 ? '⚠️ Atenção' : '✅ Sucesso' ?>:</strong>
                    Encontrados <?= $total_invalidos ?> item(ns) inválido(s) no carrinho
                </div>

                <?php if ($total_invalidos > 0): ?>
                    <h3>Itens que serão removidos:</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Produto ID</th>
                                <th>Tipo</th>
                                <th>Qtd</th>
                                <th>Data Adição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $item['id'] ?></td>
                                    <td><?= $item['cliente_nome'] ?? 'Cliente Desconhecido' ?></td>
                                    <td><?= $item['id_produto'] ?></td>
                                    <td><?= $item['tipo_produto'] ?></td>
                                    <td><?= $item['quantidade'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($item['data_adicao'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php
            // Estatísticas do carrinho
            $stats = $conn->query("
                SELECT 
                    COUNT(*) as total_itens,
                    COUNT(DISTINCT id_cliente) as total_clientes,
                    SUM(quantidade) as total_produtos
                FROM carrinho
            ")->fetch_assoc();
            ?>

            <div class="section">
                <h2><i class="fa fa-chart-bar"></i> ESTATÍSTICAS GERAIS</h2>
                <table>
                    <tr>
                        <td><strong>Total de Itens no Carrinho:</strong></td>
                        <td><?= $stats['total_itens'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Clientes com Itens no Carrinho:</strong></td>
                        <td><?= $stats['total_clientes'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total de Produtos:</strong></td>
                        <td><?= $stats['total_produtos'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Itens Inválidos:</strong></td>
                        <td style="color: <?= $total_invalidos > 0 ? '#ff0' : '#0f0' ?>">
                            <?= $total_invalidos ?>
                            (<?= $stats['total_itens'] > 0 ? round(($total_invalidos / $stats['total_itens']) * 100, 2) : 0 ?>%)
                        </td>
                    </tr>
                </table>
            </div>

            <?php if ($total_invalidos > 0): ?>
                <div class="section">
                    <h2><i class="fa fa-exclamation-triangle"></i> AÇÃO NECESSÁRIA</h2>
                    <div class="status warning">
                        <strong>⚠️ Atenção:</strong> Foram encontrados itens inválidos que precisam ser removidos.
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="?executar=sim" class="btn btn-danger"
                            onclick="return confirm('Tem certeza que deseja REMOVER todos os itens inválidos? Esta ação é IRREVERSÍVEL!')">
                            <i class="fa fa-trash"></i> EXECUTAR LIMPEZA AGORA
                        </a>
                        <a href="painel_dono.php" class="btn">
                            <i class="fa fa-arrow-left"></i> Cancelar e Voltar
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="section">
                    <h2><i class="fa fa-check-circle"></i> CARRINHO SAUDÁVEL</h2>
                    <div class="status success">
                        <strong>✅ Sucesso:</strong> Nenhum item inválido encontrado! O carrinho está limpo.
                    </div>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="painel_dono.php" class="btn">
                            <i class="fa fa-arrow-left"></i> Voltar ao Painel
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- MODO EXECUÇÃO -->
            <div class="section">
                <h2><i class="fa fa-cog fa-spin"></i> MODO: EXECUÇÃO</h2>
                <div class="status warning">
                    <strong>⚙️ Processando:</strong> Executando limpeza do banco de dados...
                </div>
            </div>

            <?php
            $inicio = microtime(true);

            // Executar limpeza
            $sql_delete = "
                DELETE carrinho FROM carrinho
                LEFT JOIN produtos ON carrinho.id_produto = produtos.id 
                    AND carrinho.tipo_produto = 'normal'
                LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id 
                    AND carrinho.tipo_produto = 'especial'
                WHERE (carrinho.tipo_produto = 'normal' AND produtos.id IS NULL)
                   OR (carrinho.tipo_produto = 'especial' AND produtos_especiais.id IS NULL)
            ";

            $conn->query($sql_delete);
            $removidos = $conn->affected_rows;

            $tempo_execucao = round((microtime(true) - $inicio) * 1000, 2);

            // Log da operação
            if ($removidos > 0) {
                $conn->query("
                    INSERT INTO system_logs (tipo, nivel, status, mensagem, dados) 
                    VALUES (
                        'limpeza_carrinho', 
                        'INFO', 
                        'sucesso', 
                        'Limpeza de carrinho executada',
                        '{\"itens_removidos\": $removidos, \"tempo_ms\": $tempo_execucao, \"usuario\": \"{$_SESSION['usuario']}\"}'
                    )
                ");
            }
            ?>

            <div class="section">
                <h2><i class="fa fa-check-circle"></i> RESULTADO DA EXECUÇÃO</h2>

                <div class="progress">
                    <div class="progress-bar" style="width: 100%">
                        100% CONCLUÍDO
                    </div>
                </div>

                <div class="status <?= $removidos > 0 ? 'success' : 'info' ?>">
                    <strong><?= $removidos > 0 ? '✅ Limpeza Concluída' : 'ℹ️ Sem Alterações' ?>:</strong>
                    <?= $removidos ?> item(ns) foi(ram) removido(s) do carrinho
                </div>

                <table>
                    <tr>
                        <td><strong>Itens Removidos:</strong></td>
                        <td><?= $removidos ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tempo de Execução:</strong></td>
                        <td><?= $tempo_execucao ?> ms</td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td style="color: #0f0">✅ Concluído com Sucesso</td>
                    </tr>
                    <tr>
                        <td><strong>Data/Hora:</strong></td>
                        <td><?= date('d/m/Y H:i:s') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Executado por:</strong></td>
                        <td><?= $_SESSION['usuario'] ?></td>
                    </tr>
                </table>
            </div>

            <?php
            // Verificar estado atual
            $stats_final = $conn->query("
                SELECT 
                    COUNT(*) as total_itens,
                    COUNT(DISTINCT id_cliente) as total_clientes
                FROM carrinho
            ")->fetch_assoc();
            ?>

            <div class="section">
                <h2><i class="fa fa-database"></i> ESTADO ATUAL DO CARRINHO</h2>
                <div class="status success">
                    <strong>✅ Status:</strong> Carrinho limpo e otimizado
                </div>

                <table>
                    <tr>
                        <td><strong>Itens Válidos Restantes:</strong></td>
                        <td><?= $stats_final['total_itens'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Clientes Ativos:</strong></td>
                        <td><?= $stats_final['total_clientes'] ?></td>
                    </tr>
                </table>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="painel_dono.php" class="btn">
                    <i class="fa fa-home"></i> Voltar ao Painel
                </a>
                <a href="limpar_carrinho_invalido.php" class="btn">
                    <i class="fa fa-redo"></i> Executar Nova Análise
                </a>
            </div>

        <?php endif; ?>

        <div class="section" style="margin-top: 40px; border-color: #0ff;">
            <h2><i class="fa fa-info-circle"></i> INFORMAÇÕES</h2>
            <div class="status info">
                <p><strong>ℹ️ Sobre este Script:</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Remove itens do carrinho que apontam para produtos que não existem mais</li>
                    <li>Seguro: não afeta produtos válidos nem pedidos finalizados</li>
                    <li>Recomendado executar periodicamente (1x por semana)</li>
                    <li>Todas as operações são registradas no log do sistema</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Animação de progresso
        window.addEventListener('load', function () {
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = '100%';
                }, 100);
            }
        });
    </script>
</body>

</html>