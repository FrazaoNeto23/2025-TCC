<?php
// ====================================
// PAINEL DO DONO - BURGER HOUSE
// ====================================

// Definir caminhos base
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URLs base
define('BASE_URL', 'http://localhost/2025-TCC');
define('PUBLIC_URL', BASE_URL . '/public');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Carregar config.php (que já inicia sessão e conecta BD)
require_once CONFIG_PATH . '/config.php';

// Carregar classes necessárias
require_once BASE_PATH . '/includes/notificacoes.php';

// Verificar se arquivos de gestão existem
$gestor_pedidos_file = __DIR__ . '/gestor_pedidos.php';
$fila_impressao_file = __DIR__ . '/fila_impressao.php';

if (file_exists($gestor_pedidos_file)) {
    require_once $gestor_pedidos_file;
}

if (file_exists($fila_impressao_file)) {
    require_once $fila_impressao_file;
}

// ====================================
// VERIFICAÇÕES DE SEGURANÇA
// ====================================

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// Padronizar variáveis de sessão
$user_id = $_SESSION['user_id'] ?? $_SESSION['usuario_id'];
$user_tipo = $_SESSION['tipo'] ?? $_SESSION['usuario_tipo'];

// Verificar se é dono
if ($user_tipo !== 'dono') {
    header('Location: ' . BASE_URL . '/home');
    exit;
}

// ====================================
// INICIALIZAR SISTEMAS
// ====================================

try {
    // Inicializar notificações
    $notificacoes = new Notificacoes($conn);

    // Inicializar gestor de pedidos (se a classe existir)
    $gestor = null;
    if (class_exists('GestorPedidos')) {
        $gestor = new GestorPedidos($conn);
    }

    // Inicializar fila de impressão (se a classe existir)
    $fila = null;
    if (class_exists('FilaImpressao')) {
        $fila = new FilaImpressao($conn);
    }

} catch (Exception $e) {
    error_log("Erro ao inicializar sistemas: " . $e->getMessage());
    die("Erro ao carregar painel. Contate o administrador.");
}

// ====================================
// BUSCAR DADOS PARA O DASHBOARD
// ====================================

// Buscar pedidos pendentes
$pedidos_pendentes = [];
if ($gestor) {
    $pedidos_pendentes = $gestor->buscarPedidos(['status' => 'Pendente']);
}

// Buscar pedidos em preparo
$pedidos_preparando = [];
if ($gestor) {
    $pedidos_preparando = $gestor->buscarPedidos(['status' => 'Preparando']);
}

// Total de pedidos hoje
$total_hoje = 0;
$sql_total = "SELECT COUNT(*) as total FROM pedidos WHERE DATE(data_pedido) = CURDATE()";
$result_total = $conn->query($sql_total);
if ($result_total) {
    $row = $result_total->fetch_assoc();
    $total_hoje = $row['total'];
}

// Status da fila de impressão
$status_fila = [];
$total_fila = 0;
if ($fila) {
    $status_fila = $fila->statusFila();
    $total_fila = array_sum(array_column($status_fila, 'quantidade'));
}

// Nome do usuário
$user_name = $_SESSION['nome'] ?? $_SESSION['usuario_nome'] ?? 'Dono';
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

        body {
            background-color: #f8f9fa;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
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
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .pedido-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .pedido-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .status-badge {
            border-radius: 25px;
            padding: 8px 16px;
            font-weight: 500;
        }

        .btn-action {
            margin: 5px;
            border-radius: 20px;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(255, 107, 53, 0.05);
        }
    </style>
</head>

<body data-usuario-logado="true">
    <!-- Navegação -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>/painel_dono">
                <i class="fas fa-crown"></i> Painel do Dono - Burger House
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/relatorios">
                            <i class="fas fa-chart-line"></i> Relatórios
                        </a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($user_name); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/logout">
                            <i class="fas fa-sign-out-alt"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active">Painel do Dono</li>
            </ol>
        </nav>

        <!-- Métricas Rápidas -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="metric-card">
                    <div class="metric-value"><?= count($pedidos_pendentes) ?></div>
                    <div class="metric-label">Pedidos Pendentes</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="metric-value"><?= count($pedidos_preparando) ?></div>
                    <div class="metric-label">Em Preparo</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #17a2b8, #20c997);">
                    <div class="metric-value"><?= $total_hoje ?></div>
                    <div class="metric-label">Pedidos Hoje</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="metric-card" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="metric-value" id="filaImpressao">
                        <?= $total_fila ?>
                    </div>
                    <div class="metric-label">Fila Impressão</div>
                </div>
            </div>
        </div>

        <!-- Ações Rápidas -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i> Ações Rápidas
                    </div>
                    <div class="card-body text-center">
                        <?php if ($fila): ?>
                            <button class="btn btn-primary btn-action" onclick="processarFilaImpressao()">
                                <i class="fas fa-print"></i> Processar Fila de Impressão
                            </button>
                        <?php endif; ?>

                        <button class="btn btn-success btn-action"
                            onclick="window.location.href='<?php echo BASE_URL; ?>/relatorios'">
                            <i class="fas fa-chart-line"></i> Ver Relatórios
                        </button>

                        <button class="btn btn-info btn-action" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Atualizar Painel
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

        <!-- Conteúdo das Tabs -->
        <div class="tab-content p-4 bg-white rounded-bottom shadow-sm" id="pedidosTabsContent">
            <!-- Tab Pendentes -->
            <div class="tab-pane fade show active" id="pendentes" role="tabpanel">
                <div class="row">
                    <?php if (count($pedidos_pendentes) > 0): ?>
                        <?php foreach ($pedidos_pendentes as $pedido): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card pedido-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Pedido #<?= $pedido['id'] ?></h5>
                                        <p class="card-text">
                                            <strong>Cliente:</strong>
                                            <?= htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') ?><br>
                                            <strong>Valor:</strong> R$
                                            <?= number_format($pedido['valor_total'] ?? 0, 2, ',', '.') ?><br>
                                            <strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
                                        </p>
                                        <div class="btn-group w-100" role="group">
                                            <button class="btn btn-success btn-sm"
                                                onclick="confirmarPedido(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-check"></i> Confirmar
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick="verDetalhes(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-eye"></i> Detalhes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center text-muted py-5">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <p>Nenhum pedido pendente no momento!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Preparando -->
            <div class="tab-pane fade" id="preparando" role="tabpanel">
                <div class="row">
                    <?php if (count($pedidos_preparando) > 0): ?>
                        <?php foreach ($pedidos_preparando as $pedido): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card pedido-card border-warning">
                                    <div class="card-body">
                                        <h5 class="card-title">Pedido #<?= $pedido['id'] ?></h5>
                                        <p class="card-text">
                                            <strong>Cliente:</strong>
                                            <?= htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') ?><br>
                                            <strong>Valor:</strong> R$
                                            <?= number_format($pedido['valor_total'] ?? 0, 2, ',', '.') ?><br>
                                            <strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?>
                                        </p>
                                        <div class="btn-group w-100" role="group">
                                            <button class="btn btn-primary btn-sm" onclick="marcarPronto(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-check-double"></i> Marcar Pronto
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick="verDetalhes(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-eye"></i> Detalhes
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center text-muted py-5">
                            <i class="fas fa-fire fa-3x mb-3"></i>
                            <p>Nenhum pedido em preparo no momento!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Todos -->
            <div class="tab-pane fade" id="todos" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <input type="date" class="form-control" id="filtroData">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filtroStatus">
                            <option value="">Todos os Status</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Confirmado">Confirmado</option>
                            <option value="Preparando">Preparando</option>
                            <option value="Pronto">Pronto</option>
                            <option value="Entregue">Entregue</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="filtroCliente" placeholder="Nome do cliente">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" onclick="filtrarPedidos()">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="tabelaPedidos">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Valor</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Clique em "Filtrar" para carregar os pedidos
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoDetalhes">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Histórico -->
    <div class="modal fade" id="modalHistorico" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Histórico do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoHistorico">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
            $.post('<?php echo BASE_URL; ?>/gestor_pedidos', {
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

        function imprimirPedido(id) {
            window.open('<?php echo BASE_URL; ?>/imprimir_pedido?id=' + id, '_blank');
        }

        function verDetalhes(id) {
            $('#modalDetalhes').modal('show');
            $('#conteudoDetalhes').html('<div class="text-center"><div class="spinner-border"></div></div>');

            $.get('<?php echo BASE_URL; ?>/api/pedido_detalhes', {
                id: id
            }).done(function (data) {
                let html = '<div class="p-3">';
                html += '<h6>Pedido #' + id + '</h6>';
                html += '<p><strong>Cliente:</strong> ' + (data.cliente_nome || 'N/A') + '</p>';
                html += '<p><strong>Valor Total:</strong> R$ ' + (parseFloat(data.valor_total || 0).toFixed(2)) + '</p>';
                html += '<p><strong>Status:</strong> ' + (data.status || 'N/A') + '</p>';
                html += '</div>';
                $('#conteudoDetalhes').html(html);
            }).fail(function () {
                $('#conteudoDetalhes').html('<div class="alert alert-danger">Erro ao carregar detalhes</div>');
            });
        }

        function verHistorico(id) {
            $('#modalHistorico').modal('show');
            $('#conteudoHistorico').html('<div class="text-center"><div class="spinner-border"></div></div>');

            // Implementar busca de histórico
            setTimeout(function () {
                $('#conteudoHistorico').html('<p class="text-muted">Histórico não disponível</p>');
            }, 1000);
        }

        // ====================================
        // FUNÇÕES DE AÇÕES RÁPIDAS
        // ====================================

        function processarFilaImpressao() {
            $.get('<?php echo BASE_URL; ?>/fila_impressao?processar=1').done(function (response) {
                alert('Fila de impressão processada!');
                location.reload();
            }).fail(function () {
                alert('Erro ao processar fila');
            });
        }

        function exportarRelatorioRapido() {
            const hoje = new Date().toISOString().split('T')[0];
            window.open('<?php echo BASE_URL; ?>/relatorios/exportar?data=' + hoje, '_blank');
        }

        function filtrarPedidos() {
            const data = $('#filtroData').val();
            const status = $('#filtroStatus').val();
            const cliente = $('#filtroCliente').val();

            $('#tabelaPedidos tbody').html('<tr><td colspan="6" class="text-center"><div class="spinner-border spinner-border-sm"></div> Carregando...</td></tr>');

            $.get('<?php echo BASE_URL; ?>/api/pedidos', {
                data: data,
                status: status,
                cliente: cliente
            }).done(function (pedidos) {
                if (pedidos.length === 0) {
                    $('#tabelaPedidos tbody').html('<tr><td colspan="6" class="text-center text-muted">Nenhum pedido encontrado</td></tr>');
                    return;
                }

                let html = '';
                pedidos.forEach(function (pedido) {
                    html += `
                        <tr>
                            <td>#${pedido.id}</td>
                            <td>${pedido.cliente_nome || 'N/A'}</td>
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
            }).fail(function () {
                $('#tabelaPedidos tbody').html('<tr><td colspan="6" class="text-center text-danger">Erro ao carregar pedidos</td></tr>');
            });
        }

        // ====================================
        // INICIALIZAÇÃO
        // ====================================

        $(document).ready(function () {
            // Atualizar dados a cada 30 segundos
            setInterval(function () {
                location.reload();
            }, 30000);
        });
    </script>
</body>

</html>