<?php
require_once 'notificacoes.php';
require_once 'fila_impressao.php';

class GestorPedidos
{
    private $conn;
    private $notificacoes;
    private $fila_impressao;

    public function __construct($conexao)
    {
        $this->conn = $conexao;
        $this->notificacoes = new Notificacoes($conexao);
        $this->fila_impressao = new FilaImpressao($conexao);
    }

    /**
     * Inicializar melhorias na tabela de pedidos
     */
    public function inicializar()
    {
        $this->notificacoes->inicializar();
        $this->fila_impressao->inicializar();

        // Adicionar colunas extras se não existirem
        $this->adicionarColunasMelhorias();

        // Criar tabela de tempos de preparo por produto
        $this->criarTabelaTemposPreparo();

        // Criar tabela de cancelamentos
        $this->criarTabelaCancelamentos();
    }

    /**
     * Adicionar colunas de melhorias
     */
    private function adicionarColunasMelhorias()
    {
        $colunas = [
            "tempo_estimado_entrega DATETIME COMMENT 'Previsão de entrega'",
            "tempo_real_preparo INT COMMENT 'Tempo real de preparo em minutos'",
            "prioridade_numerica INT DEFAULT 0 COMMENT 'Prioridade numérica para ordenação'",
            "cliente_confirmou BOOLEAN DEFAULT FALSE",
            "data_confirmacao_cliente TIMESTAMP NULL",
            "editado_cliente BOOLEAN DEFAULT FALSE",
            "data_ultima_edicao TIMESTAMP NULL",
            "permite_edicao BOOLEAN DEFAULT TRUE",
            "motivo_nao_edicao VARCHAR(255)",
            "avaliacao_cliente INT CHECK (avaliacao_cliente >= 1 AND avaliacao_cliente <= 5)",
            "comentario_avaliacao TEXT",
            "data_avaliacao TIMESTAMP NULL"
        ];

        foreach ($colunas as $coluna) {
            $nome_coluna = explode(' ', $coluna)[0];
            $check = $this->conn->query("SHOW COLUMNS FROM pedidos LIKE '$nome_coluna'");
            if ($check->num_rows == 0) {
                $this->conn->query("ALTER TABLE pedidos ADD COLUMN $coluna");
            }
        }
    }

    /**
     * Criar tabela de tempos de preparo
     */
    private function criarTabelaTemposPreparo()
    {
        $sql = "CREATE TABLE IF NOT EXISTS tempos_preparo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_produto INT NOT NULL,
            tempo_base INT NOT NULL COMMENT 'Tempo base em minutos',
            tempo_adicional_qty INT DEFAULT 2 COMMENT 'Minutos adicionais por unidade extra',
            periodo_dia ENUM('manha', 'tarde', 'noite', 'madrugada') DEFAULT 'tarde',
            dia_semana ENUM('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo') DEFAULT 'segunda',
            fator_multiplicador DECIMAL(3,2) DEFAULT 1.0,
            ativo BOOLEAN DEFAULT TRUE,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_produto) REFERENCES produtos(id),
            INDEX idx_produto (id_produto),
            INDEX idx_periodo (periodo_dia, dia_semana)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($sql);

        // Inserir tempos padrão se a tabela estiver vazia
        $this->inserirTemposPadrao();
    }

    /**
     * Criar tabela de cancelamentos
     */
    private function criarTabelaCancelamentos()
    {
        $sql = "CREATE TABLE IF NOT EXISTS pedidos_cancelamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            cancelado_por INT NOT NULL,
            tipo_usuario ENUM('cliente', 'funcionario', 'dono', 'sistema') NOT NULL,
            motivo ENUM('cliente_desistiu', 'erro_pedido', 'produto_indisponivel', 'demora_preparo', 'problema_pagamento', 'outro') NOT NULL,
            descricao TEXT,
            permite_refazer BOOLEAN DEFAULT TRUE,
            valor_estorno DECIMAL(10,2),
            status_estorno ENUM('pendente', 'processado', 'recusado') DEFAULT 'pendente',
            data_cancelamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_pedido) REFERENCES pedidos(id),
            INDEX idx_pedido (id_pedido),
            INDEX idx_data (data_cancelamento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($sql);
    }

    /**
     * Inserir tempos de preparo padrão
     */
    private function inserirTemposPadrao()
    {
        $check = $this->conn->query("SELECT COUNT(*) as total FROM tempos_preparo");
        $row = $check->fetch_assoc();

        if ($row['total'] == 0) {
            // Buscar produtos e inserir tempos padrão
            $produtos = $this->conn->query("SELECT id, nome, categoria FROM produtos WHERE ativo = 1");

            while ($produto = $produtos->fetch_assoc()) {
                // Definir tempo base por categoria
                $tempo_base = 15; // Padrão

                switch (strtolower($produto['categoria'])) {
                    case 'hamburguer':
                    case 'sanduiche':
                        $tempo_base = 12;
                        break;
                    case 'bebida':
                        $tempo_base = 2;
                        break;
                    case 'acompanhamento':
                        $tempo_base = 8;
                        break;
                    case 'sobremesa':
                        $tempo_base = 5;
                        break;
                    case 'prato':
                        $tempo_base = 20;
                        break;
                }

                $stmt = $this->conn->prepare("INSERT INTO tempos_preparo (id_produto, tempo_base) VALUES (?, ?)");
                $stmt->bind_param("ii", $produto['id'], $tempo_base);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    /**
     * Calcular tempo estimado do pedido
     */
    public function calcularTempoEstimado($id_pedido)
    {
        $stmt = $this->conn->prepare("
            SELECT i.id_produto, i.quantidade, p.nome, 
                   COALESCE(tp.tempo_base, 15) as tempo_base,
                   COALESCE(tp.tempo_adicional_qty, 2) as tempo_adicional,
                   COALESCE(tp.fator_multiplicador, 1.0) as fator_multiplicador
            FROM itens_pedido i
            JOIN produtos p ON i.id_produto = p.id
            LEFT JOIN tempos_preparo tp ON i.id_produto = tp.id_produto 
                AND tp.ativo = TRUE
            WHERE i.id_pedido = ?
        ");

        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $result = $stmt->get_result();

        $tempo_total = 0;
        $maior_tempo_item = 0;

        while ($item = $result->fetch_assoc()) {
            // Calcular tempo do item
            $tempo_item = $item['tempo_base'];

            // Adicionar tempo por quantidade extra (primeira unidade já está no tempo base)
            if ($item['quantidade'] > 1) {
                $tempo_item += ($item['quantidade'] - 1) * $item['tempo_adicional'];
            }

            // Aplicar fator multiplicador (rush hour, falta de funcionários, etc.)
            $tempo_item *= $item['fator_multiplicador'];

            // O tempo total não é a soma, mas sim o maior tempo de preparo
            // pois itens podem ser feitos em paralelo
            if ($tempo_item > $maior_tempo_item) {
                $maior_tempo_item = $tempo_item;
            }
        }

        $stmt->close();

        // Adicionar buffer de 5 minutos + 20% do tempo total
        $tempo_total = $maior_tempo_item + 5 + ($maior_tempo_item * 0.2);

        // Verificar pedidos na fila (adicionar tempo baseado na fila)
        $fila = $this->verificarTamanhoDaFila();
        $tempo_total += ($fila * 3); // 3 minutos por pedido na fila

        return round($tempo_total);
    }

    /**
     * Verificar tamanho da fila de pedidos
     */
    private function verificarTamanhoDaFila()
    {
        $result = $this->conn->query("
            SELECT COUNT(*) as total 
            FROM pedidos 
            WHERE status IN ('Pendente', 'Confirmado', 'Preparando') 
            AND data_pedido >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ");

        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }

    /**
     * Atualizar status do pedido com histórico
     */
    public function atualizarStatus($id_pedido, $novo_status, $usuario_id, $tipo_usuario, $observacao = null)
    {
        // Buscar status atual
        $stmt = $this->conn->prepare("SELECT status, id_cliente, prioridade FROM pedidos WHERE id = ?");
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $result = $stmt->get_result();
        $pedido = $result->fetch_assoc();
        $stmt->close();

        if (!$pedido) {
            return false;
        }

        $status_anterior = $pedido['status'];

        // Registrar no histórico
        $this->notificacoes->adicionarHistorico(
            $id_pedido,
            $status_anterior,
            $novo_status,
            $usuario_id,
            $tipo_usuario,
            $observacao
        );

        // Atualizar status
        $stmt = $this->conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $novo_status, $id_pedido);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Criar notificação
            $this->notificacoes->mudancaStatus($id_pedido, $novo_status, $pedido['id_cliente']);

            // Adicionar à fila de impressão para status específicos
            if (in_array($novo_status, ['Confirmado', 'Preparando'])) {
                $prioridade = $this->definirPrioridadeImpressao($pedido['prioridade']);
                $this->fila_impressao->adicionarPedido($id_pedido, 'pedido', $prioridade);
            }

            // Atualizar tempo real de preparo se entregue
            if ($novo_status == 'Entregue') {
                $this->atualizarTempoRealPreparo($id_pedido);
            }
        }

        return $result;
    }

    /**
     * Definir prioridade para impressão
     */
    private function definirPrioridadeImpressao($prioridade_pedido)
    {
        $mapeamento = [
            'baixa' => 'baixa',
            'media' => 'media',
            'alta' => 'alta',
            'urgente' => 'urgente'
        ];

        return $mapeamento[$prioridade_pedido] ?? 'media';
    }

    /**
     * Atualizar tempo real de preparo
     */
    private function atualizarTempoRealPreparo($id_pedido)
    {
        $stmt = $this->conn->prepare("
            SELECT data_pedido,
                   (SELECT MIN(data_alteracao) FROM pedidos_historico 
                    WHERE id_pedido = ? AND status_novo = 'Preparando') as inicio_preparo,
                   NOW() as fim_preparo
            FROM pedidos WHERE id = ?
        ");

        $stmt->bind_param("ii", $id_pedido, $id_pedido);
        $stmt->execute();
        $result = $stmt->get_result();
        $dados = $result->fetch_assoc();
        $stmt->close();

        if ($dados['inicio_preparo']) {
            $tempo_preparo = (strtotime($dados['fim_preparo']) - strtotime($dados['inicio_preparo'])) / 60;

            $update = $this->conn->prepare("UPDATE pedidos SET tempo_real_preparo = ? WHERE id = ?");
            $update->bind_param("ii", round($tempo_preparo), $id_pedido);
            $update->execute();
            $update->close();
        }
    }

    /**
     * Permitir cancelamento pelo cliente
     */
    public function cancelarPorCliente($id_pedido, $id_cliente, $motivo)
    {
        // Verificar se o pedido pode ser cancelado
        $stmt = $this->conn->prepare("
            SELECT status, id_cliente, valor_total 
            FROM pedidos 
            WHERE id = ? AND id_cliente = ?
        ");

        $stmt->bind_param("ii", $id_pedido, $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result();
        $pedido = $result->fetch_assoc();
        $stmt->close();

        if (!$pedido) {
            return ['sucesso' => false, 'erro' => 'Pedido não encontrado'];
        }

        // Apenas pedidos pendentes podem ser cancelados pelo cliente
        if ($pedido['status'] != 'Pendente') {
            return ['sucesso' => false, 'erro' => 'Pedido não pode mais ser cancelado'];
        }

        // Registrar cancelamento
        $stmt = $this->conn->prepare("
            INSERT INTO pedidos_cancelamentos 
            (id_pedido, cancelado_por, tipo_usuario, motivo, descricao, valor_estorno, status_estorno)
            VALUES (?, ?, 'cliente', 'cliente_desistiu', ?, ?, 'pendente')
        ");

        $stmt->bind_param("iisd", $id_pedido, $id_cliente, $motivo, $pedido['valor_total']);
        $stmt->execute();
        $stmt->close();

        // Atualizar status do pedido
        $this->atualizarStatus($id_pedido, 'Cancelado', $id_cliente, 'cliente', 'Cancelado pelo cliente: ' . $motivo);

        return ['sucesso' => true, 'mensagem' => 'Pedido cancelado com sucesso'];
    }

    /**
     * Permitir edição pelo cliente
     */
    public function podeEditarPedido($id_pedido, $id_cliente)
    {
        $stmt = $this->conn->prepare("
            SELECT status, permite_edicao, motivo_nao_edicao 
            FROM pedidos 
            WHERE id = ? AND id_cliente = ?
        ");

        $stmt->bind_param("ii", $id_pedido, $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result();
        $pedido = $result->fetch_assoc();
        $stmt->close();

        if (!$pedido) {
            return ['pode' => false, 'motivo' => 'Pedido não encontrado'];
        }

        if ($pedido['status'] != 'Pendente') {
            return ['pode' => false, 'motivo' => 'Pedido já está sendo preparado'];
        }

        if (!$pedido['permite_edicao']) {
            return ['pode' => false, 'motivo' => $pedido['motivo_nao_edicao'] ?? 'Edição não permitida'];
        }

        return ['pode' => true];
    }

    /**
     * Confirmar recebimento pelo cliente
     */
    public function confirmarRecebimento($id_pedido, $id_cliente)
    {
        $stmt = $this->conn->prepare("
            UPDATE pedidos 
            SET cliente_confirmou = TRUE, data_confirmacao_cliente = NOW()
            WHERE id = ? AND id_cliente = ? AND status = 'Entregue'
        ");

        $stmt->bind_param("ii", $id_pedido, $id_cliente);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Criar notificação para o dono
            $this->notificacoes->criar(
                'confirmacao_recebimento',
                'Cliente Confirmou Recebimento',
                "Cliente confirmou o recebimento do pedido #$id_pedido",
                null,
                'dono',
                [
                    'id_referencia' => $id_pedido,
                    'tabela_referencia' => 'pedidos',
                    'prioridade' => 'baixa',
                    'icone' => 'check-circle',
                    'cor' => '#28a745'
                ]
            );
        }

        return $result;
    }

    /**
     * Avaliar pedido
     */
    public function avaliarPedido($id_pedido, $id_cliente, $avaliacao, $comentario = '')
    {
        // Validar avaliação
        if ($avaliacao < 1 || $avaliacao > 5) {
            return ['sucesso' => false, 'erro' => 'Avaliação deve ser entre 1 e 5'];
        }

        $stmt = $this->conn->prepare("
            UPDATE pedidos 
            SET avaliacao_cliente = ?, comentario_avaliacao = ?, data_avaliacao = NOW()
            WHERE id = ? AND id_cliente = ? AND status = 'Entregue'
        ");

        $stmt->bind_param("isii", $avaliacao, $comentario, $id_pedido, $id_cliente);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Criar notificação
            $estrelas = str_repeat('⭐', $avaliacao);
            $this->notificacoes->criar(
                'nova_avaliacao',
                'Nova Avaliação',
                "Pedido #$id_pedido foi avaliado com $estrelas",
                null,
                'dono',
                [
                    'id_referencia' => $id_pedido,
                    'tabela_referencia' => 'pedidos',
                    'prioridade' => 'baixa',
                    'icone' => 'star',
                    'cor' => '#ffc107'
                ]
            );
        }

        return ['sucesso' => $result];
    }

    /**
     * Definir prioridade do pedido
     */
    public function definirPrioridade($id_pedido, $prioridade, $usuario_id, $tipo_usuario)
    {
        $prioridades_validas = ['baixa', 'media', 'alta', 'urgente'];

        if (!in_array($prioridade, $prioridades_validas)) {
            return false;
        }

        // Mapear prioridade para número (para ordenação)
        $prioridade_numerica = [
            'baixa' => 1,
            'media' => 2,
            'alta' => 3,
            'urgente' => 4
        ][$prioridade];

        $stmt = $this->conn->prepare("
            UPDATE pedidos 
            SET prioridade = ?, prioridade_numerica = ?
            WHERE id = ?
        ");

        $stmt->bind_param("sii", $prioridade, $prioridade_numerica, $id_pedido);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Registrar no histórico
            $this->notificacoes->adicionarHistorico(
                $id_pedido,
                null,
                "Prioridade alterada para: $prioridade",
                $usuario_id,
                $tipo_usuario
            );
        }

        return $result;
    }

    /**
     * Buscar pedidos com filtros avançados
     */
    public function buscarPedidos($filtros = [])
    {
        $where = ["1=1"];
        $params = [];
        $types = "";

        // Filtro por status
        if (!empty($filtros['status'])) {
            $where[] = "p.status = ?";
            $params[] = $filtros['status'];
            $types .= "s";
        }

        // Filtro por cliente
        if (!empty($filtros['cliente_id'])) {
            $where[] = "p.id_cliente = ?";
            $params[] = $filtros['cliente_id'];
            $types .= "i";
        }

        // Filtro por data
        if (!empty($filtros['data_inicio'])) {
            $where[] = "DATE(p.data_pedido) >= ?";
            $params[] = $filtros['data_inicio'];
            $types .= "s";
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "DATE(p.data_pedido) <= ?";
            $params[] = $filtros['data_fim'];
            $types .= "s";
        }

        // Filtro por prioridade
        if (!empty($filtros['prioridade'])) {
            $where[] = "p.prioridade = ?";
            $params[] = $filtros['prioridade'];
            $types .= "s";
        }

        // Filtro por avaliação
        if (!empty($filtros['avaliacao_min'])) {
            $where[] = "p.avaliacao_cliente >= ?";
            $params[] = $filtros['avaliacao_min'];
            $types .= "i";
        }

        $sql = "SELECT p.*, c.nome as cliente_nome, c.telefone,
                       COUNT(i.id) as total_itens
                FROM pedidos p
                JOIN clientes c ON p.id_cliente = c.id
                LEFT JOIN itens_pedido i ON p.id = i.id_pedido
                WHERE " . implode(" AND ", $where) . "
                GROUP BY p.id
                ORDER BY p.prioridade_numerica DESC, p.data_pedido DESC";

        $stmt = $this->conn->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $pedidos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $pedidos;
    }

    /**
     * Buscar histórico completo de um pedido
     */
    public function buscarHistoricoCompleto($id_pedido)
    {
        return $this->notificacoes->buscarHistorico($id_pedido);
    }

    /**
     * Atualizar tempo estimado de entrega
     */
    public function atualizarTempoEstimadoEntrega($id_pedido)
    {
        $tempo_estimado = $this->calcularTempoEstimado($id_pedido);

        $data_estimada = date('Y-m-d H:i:s', strtotime("+$tempo_estimado minutes"));

        $stmt = $this->conn->prepare("
            UPDATE pedidos 
            SET tempo_estimado = ?, tempo_estimado_entrega = ?
            WHERE id = ?
        ");

        $stmt->bind_param("isi", $tempo_estimado, $data_estimada, $id_pedido);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}

// ========================================
// API PARA GESTÃO DE PEDIDOS
// ========================================

// Processar requisições AJAX
if (isset($_POST['action'])) {
    session_start();

    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['erro' => 'Usuário não autenticado']);
        exit;
    }

    require_once 'config.php';
    $gestor = new GestorPedidos($conn);

    $action = $_POST['action'];
    $response = ['sucesso' => false];

    switch ($action) {
        case 'atualizar_status':
            if (isset($_POST['id_pedido'], $_POST['novo_status'])) {
                $result = $gestor->atualizarStatus(
                    $_POST['id_pedido'],
                    $_POST['novo_status'],
                    $_SESSION['usuario_id'],
                    $_SESSION['usuario_tipo'],
                    $_POST['observacao'] ?? null
                );
                $response['sucesso'] = $result;
            }
            break;

        case 'cancelar_pedido':
            if (isset($_POST['id_pedido'], $_POST['motivo'])) {
                $response = $gestor->cancelarPorCliente(
                    $_POST['id_pedido'],
                    $_SESSION['usuario_id'],
                    $_POST['motivo']
                );
            }
            break;

        case 'confirmar_recebimento':
            if (isset($_POST['id_pedido'])) {
                $result = $gestor->confirmarRecebimento(
                    $_POST['id_pedido'],
                    $_SESSION['usuario_id']
                );
                $response['sucesso'] = $result;
            }
            break;

        case 'avaliar_pedido':
            if (isset($_POST['id_pedido'], $_POST['avaliacao'])) {
                $response = $gestor->avaliarPedido(
                    $_POST['id_pedido'],
                    $_SESSION['usuario_id'],
                    $_POST['avaliacao'],
                    $_POST['comentario'] ?? ''
                );
            }
            break;

        case 'definir_prioridade':
            if (isset($_POST['id_pedido'], $_POST['prioridade'])) {
                $result = $gestor->definirPrioridade(
                    $_POST['id_pedido'],
                    $_POST['prioridade'],
                    $_SESSION['usuario_id'],
                    $_SESSION['usuario_tipo']
                );
                $response['sucesso'] = $result;
            }
            break;
    }

    echo json_encode($response);
    exit;
}
?>