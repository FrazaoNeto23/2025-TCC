<?php
require_once __DIR__ . '/../config/paths.php';
// ========================================
// EXEMPLO DE INTEGRAÇÃO COMPLETA - FASE 2
// Painel do Dono com todas as funcionalidades
// ========================================

session_start();
require_once CONFIG_PATH . "/config.php";
// Autoload carrega automaticamente;
// Autoload carrega automaticamente;
// Autoload carrega automaticamente;

// Verificar se é dono
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 'dono') {
    header('Location: index.php');
    exit;
}

// Inicializar sistemas
$notificacoes = new Notificacoes($conn);
$gestor = new GestorPedidos($conn);
$fila = new FilaImpressao($conn);

// Buscar dados para o dashboard
$pedidos_pendentes = $gestor->buscarPedidos(['status' => 'Pendente']);
$pedidos_preparando = $gestor->buscarPedidos(['status' => 'Preparando']);
$total_hoje = $conn->query("SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_pedido) = CURDATE()")->fetch_assoc()['total'];

// Buscar resumo da fila de impressão
$status_fila = $fila->statusFila();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Dono - Burger House</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-color: #ff6b35;
            --secondary-color: #f7931e;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border-bottom: 2px solid var(--primary-color);
            border-radius: 15px 15px 0 0 !important;
            font-weight: bold;
        }

        .metric-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-align: center;
            padding: 20px;
            border-radius: 15px;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: bold;
        }

        .pedido-card {
            transition: transform 0.3s ease;
        }

        .pedido-card:hover {
            transform: translateY(-5px);
        }

        .status-badge {
            border-radius: 25px;
            padding: 8px 16px;
        }

        .btn-action {
            margin: 2px;
            border-radius: 20px;
        }
    </style>
</head>

<body data-usuario-logado="true">
    <!-- Navegação -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-crown"></i> Painel do Dono
            </a>

            <div class="navbar-nav ms-auto">
                <!-- O sino de notificações será criado automaticamente aqui -->
                <li class="nav-item">
                    <a class="nav-link" href="relatorios.html">
                        <i class="fas fa-chart-line"></i> Relatórios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </li>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Painel</li>
            </ol>
        </nav>

        <!-- Métricas Rápidas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value"><?= count($pedidos_pendentes) ?></div>
                    <div>Pedidos Pendentes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="metric-value"><?= count($pedidos_preparando) ?></div>
                    <div>Em Preparo</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #17a2b8, #20c997);">
                    <div class="metric-value"><?= $total_hoje ?></div>
                    <div>Pedidos Hoje</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="metric-value" id="filaImpressao">
                        <?= array_sum(array_column($status_fila, 'quantidade')) ?>
                    </div>
                    <div>Fila Impressão</div>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-lightning-bolt"></i> Ações Rápidas
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary btn-action" onclick="processarFilaImpressao()">
                            <i class="fas fa-print"></i> Processar Fila de Impressão
                        </button>
                        <button class="btn btn-success btn-action" onclick="window.open('relatorios.html', '_blank')">
                            <i class="fas fa-chart-line"></i> Ver Relatórios
                        </button>
                        <button class="btn btn-info btn-action" onclick="verificarNotificacoes()">
                            <i class="fas fa-refresh"></i> Atualizar Notificações
                        </button>
                        <button class="btn btn-warning btn-action" onclick="exportarRelatorioRapido()">
                            <i class="fas fa-download"></i> Exportar Vendas Hoje
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs de Pedidos -->
        <ul class="nav nav-tabs" id="pedidosTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pendentes-tab" data-bs-toggle="tab" data-bs-target="#pendentes">
                    <i class="fas fa-clock"></i> Pendentes (<?= count($pedidos_pendentes) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preparando-tab" data-bs-toggle="tab" data-bs-target="#preparando">
                    <i class="fas fa-fire"></i> Preparando (<?= count($pedidos_preparando) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="todos-tab" data-bs-toggle="tab" data-bs-target="#todos">
                    <i class="fas fa-list"></i> Todos os Pedidos
                </button>
            </li>
        </ul>

        <div class="tab-content" id="pedidosTabContent">
            <!-- Pedidos Pendentes -->
            <div class="tab-pane fade show active" id="pendentes" role="tabpanel">
                <div class="row mt-3">
                    <?php
require_once __DIR__ . '/../config/paths.php'; foreach ($pedidos_pendentes as $pedido): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card pedido-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><strong>Pedido #<?= $pedido['id'] ?></strong></span>
                                    <span class="badge bg-warning status-badge">Pendente</span>
                                </div>
                                <div class="card-body">
                                    <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                                    <p><strong>Total:</strong> R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?>
                                    </p>
                                    <p><strong>Horário:</strong> <?= date('H:i', strtotime($pedido['data_pedido'])) ?></p>

                                    <?php
require_once __DIR__ . '/../config/paths.php'; if ($pedido['observacoes_cliente']): ?>
                                        <p><strong>Obs:</strong>
                                            <em><?= htmlspecialchars($pedido['observacoes_cliente']) ?></em></p>
                                    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

                                    <!-- Ações -->
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-success btn-sm"
                                            onclick="confirmarPedido(<?= $pedido['id'] ?>)">
                                            <i class="fas fa-check"></i> Confirmar
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="verDetalhes(<?= $pedido['id'] ?>)">
                                            <i class="fas fa-eye"></i> Detalhes
                                        </button>
                                        <button class="btn btn-secondary btn-sm"
                                            onclick="imprimirPedido(<?= $pedido['id'] ?>)">
                                            <i class="fas fa-print"></i> Imprimir
                                        </button>
                                    </div>

                                    <!-- Prioridade -->
                                    <div class="mt-2">
                                        <select class="form-select form-select-sm"
                                            onchange="alterarPrioridade(<?= $pedido['id'] ?>, this.value)">
                                            <option value="baixa" <?= ($pedido['prioridade'] ?? 'media') == 'baixa' ? 'selected' : '' ?>>Baixa</option>
                                            <option value="media" <?= ($pedido['prioridade'] ?? 'media') == 'media' ? 'selected' : '' ?>>Média</option>
                                            <option value="alta" <?= ($pedido['prioridade'] ?? 'media') == 'alta' ? 'selected' : '' ?>>Alta</option>
                                            <option value="urgente" <?= ($pedido['prioridade'] ?? 'media') == 'urgente' ? 'selected' : '' ?>>Urgente</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
require_once __DIR__ . '/../config/paths.php'; endforeach; ?>

                    <?php
require_once __DIR__ . '/../config/paths.php'; if (empty($pedidos_pendentes)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> Nenhum pedido pendente no momento
                            </div>
                        </div>
                    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>
                </div>
            </div>

            <!-- Pedidos Preparando -->
            <div class="tab-pane fade" id="preparando" role="tabpanel">
                <div class="row mt-3">
                    <?php
require_once __DIR__ . '/../config/paths.php'; foreach ($pedidos_preparando as $pedido): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card pedido-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span><strong>Pedido #<?= $pedido['id'] ?></strong></span>
                                    <span class="badge bg-warning status-badge">Preparando</span>
                                </div>
                                <div class="card-body">
                                    <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                                    <p><strong>Tempo estimado:</strong> <?= $pedido['tempo_estimado'] ?? 30 ?> min</p>

                                    <!-- Barra de progresso baseada no tempo -->
                                    <?php
require_once __DIR__ . '/../config/paths.php';
                                    $inicio = strtotime($pedido['data_pedido']);
                                    $agora = time();
                                    $tempo_decorrido = ($agora - $inicio) / 60; // em minutos
                                    $progresso = min(100, ($tempo_decorrido / ($pedido['tempo_estimado'] ?? 30)) * 100);
                                    ?>
                                    <div class="progress mb-2">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $progresso ?>%">
                                            <?= round($progresso) ?>%
                                        </div>
                                    </div>

                                    <!-- Ações -->
                                    <div class="btn-group w-100" role="group">
                                        <button class="btn btn-success btn-sm" onclick="marcarPronto(<?= $pedido['id'] ?>)">
                                            <i class="fas fa-check-circle"></i> Pronto
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="verHistorico(<?= $pedido['id'] ?>)">
                                            <i class="fas fa-history"></i> Histórico
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
require_once __DIR__ . '/../config/paths.php'; endforeach; ?>

                    <?php
require_once __DIR__ . '/../config/paths.php'; if (empty($pedidos_preparando)): ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> Nenhum pedido em preparo no momento
                            </div>
                        </div>
                    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>
                </div>
            </div>

            <!-- Todos os Pedidos -->
            <div class="tab-pane fade" id="todos" role="tabpanel">
                <div class="mt-3">
                    <!-- Filtros -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <input type="date" class="form-control" id="filtroData"
                                        value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="filtroStatus">
                                        <option value="">Todos os Status</option>
                                        <option value="Pendente">Pendente</option>
                                        <option value="Confirmado">Confirmado</option>
                                        <option value="Preparando">Preparando</option>
                                        <option value="Pronto">Pronto</option>
                                        <option value="Entregue">Entregue</option>
                                        <option value="Cancelado">Cancelado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" id="filtroCliente"
                                        placeholder="Buscar cliente...">
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary" onclick="filtrarPedidos()">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela de pedidos -->
                    <div class="table-responsive">
                        <table class="table table-striped" id="tabelaPedidos">
                            <thead class="table-primary">
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Data/Hora</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Será preenchido via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modais -->

    <!-- Modal de Detalhes do Pedido -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoDetalhes">
                    <!-- Conteúdo carregado dinamicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Histórico -->
    <div class="modal fade" id="modalHistorico" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Histórico do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoHistorico">
                    <!-- Conteúdo carregado dinamicamente -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/notificacoes-realtime.js"></script>

    <script>
        // ====================================
        // FUNÇÕES DE GESTÃO DE PEDIDOS
        // ====================================

        function confirmarPedido(id) {
            if (confirm('Confirmar este pedido?')) {
                atualizarStatus(id, 'Confirmado', 'Pedido confirmado pelo dono');
            }
        }

        function marcarPronto(id) {
            if (confirm('Marcar pedido como pronto?')) {
                atualizarStatus(id, 'Pronto', 'Pedido finalizado na cozinha');
            }
        }

        function atualizarStatus(id, status, observacao = '') {
            $.post('gestor_pedidos.php', {
                action: 'atualizar_status',
                id_pedido: id,
                novo_status: status,
                observacao: observacao
            }).done(function (response) {
                if (response.sucesso) {
                    location.reload();
                } else {
                    alert('Erro ao atualizar status');
                }
            }).fail(function () {
                alert('Erro na comunicação com o servidor');
            });
        }

        function alterarPrioridade(id, prioridade) {
            $.post('gestor_pedidos.php', {
                action: 'definir_prioridade',
                id_pedido: id,
                prioridade: prioridade
            }).done(function (response) {
                if (response.sucesso) {
                    // Feedback visual
                    const card = $(`.card:has([onclick*="${id}"])`);
                    card.effect("highlight", { color: "#28a745" }, 1000);
                } else {
                    alert('Erro ao alterar prioridade');
                }
            });
        }

        function imprimirPedido(id) {
            $.get('fila_impressao.php', {
                action: 'adicionar_pedido',
                id_pedido: id,
                tipo: 'pedido',
                prioridade: 'alta'
            }).done(function (response) {
                if (response.sucesso) {
                    alert('Pedido adicionado à fila de impressão');
                } else {
                    alert('Erro ao adicionar à fila');
                }
            });
        }

        function verDetalhes(id) {
            $('#conteudoDetalhes').html('<div class="text-center"><div class="spinner-border"></div></div>');
            $('#modalDetalhes').modal('show');

            // Carregar detalhes via AJAX
            $.get('gestor_pedidos.php', {
                action: 'buscar_detalhes',
                id_pedido: id
            }).done(function (data) {
                $('#conteudoDetalhes').html(data);
            });
        }

        function verHistorico(id) {
            $('#conteudoHistorico').html('<div class="text-center"><div class="spinner-border"></div></div>');
            $('#modalHistorico').modal('show');

            // Carregar histórico via AJAX
            $.get('gestor_pedidos.php', {
                action: 'buscar_historico',
                id_pedido: id
            }).done(function (data) {
                let html = '<div class="timeline">';
                data.forEach(function (item) {
                    html += `
                        <div class="timeline-item mb-3">
                            <div class="d-flex">
                                <div class="badge bg-primary me-3">${item.data_alteracao}</div>
                                <div>
                                    <strong>${item.status_novo}</strong><br>
                                    <small class="text-muted">Por: ${item.nome_usuario || 'Sistema'}</small>
                                    ${item.observacao ? `<br><em>${item.observacao}</em>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                $('#conteudoHistorico').html(html);
            });
        }

        // ====================================
        // FUNÇÕES DE AÇÕES RÁPIDAS
        // ====================================

        function processarFilaImpressao() {
            $.get('fila_impressao.php?processar_fila=1').done(function (response) {
                if (response.status === 'processado') {
                    alert('Fila de impressão processada!');
                    // Atualizar contador
                    atualizarContadorFila();
                }
            });
        }

        function exportarRelatorioRapido() {
            const hoje = new Date().toISOString().split('T')[0];
            window.open(`relatorios_gerenciais.php?action=exportar&tipo=faturamento&formato=excel&data_inicio=${hoje}&data_fim=${hoje}`, '_blank');
        }

        function atualizarContadorFila() {
            $.get('fila_impressao.php?action=status_fila').done(function (data) {
                $('#filaImpressao').text(data.total || 0);
            });
        }

        function filtrarPedidos() {
            const data = $('#filtroData').val();
            const status = $('#filtroStatus').val();
            const cliente = $('#filtroCliente').val();

            // Implementar filtro via AJAX
            $.get('gestor_pedidos.php', {
                action: 'buscar_pedidos',
                data_inicio: data,
                data_fim: data,
                status: status,
                cliente: cliente
            }).done(function (pedidos) {
                let html = '';
                pedidos.forEach(function (pedido) {
                    html += `
                        <tr>
                            <td>#${pedido.id}</td>
                            <td>${pedido.cliente_nome}</td>
                            <td>${pedido.data_pedido}</td>
                            <td><span class="badge bg-info">${pedido.status}</span></td>
                            <td>R$ ${parseFloat(pedido.valor_total).toFixed(2)}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(${pedido.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="verHistorico(${pedido.id})">
                                    <i class="fas fa-history"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $('#tabelaPedidos tbody').html(html);
            });
        }

        // ====================================
        // INICIALIZAÇÃO
        // ====================================

        $(document).ready(function () {
            // Carregar tabela de todos os pedidos
            filtrarPedidos();

            // Atualizar dados a cada 30 segundos
            setInterval(function () {
                atualizarContadorFila();
                if (notificacoes) {
                    notificacoes.checkNotifications();
                }
            }, 30000);

            // Inicializar notificações
            if (notificacoes) {
                notificacoes.start();
            }
        });
    </script>
</body>

</html>