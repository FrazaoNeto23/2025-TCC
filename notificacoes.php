<?php
// ========================================
// SISTEMA DE NOTIFICAÇÕES EM TEMPO REAL
// FASE 2 - ITEM 14
// ========================================

class Notificacoes {
    private $conn;
    
    public function __construct($conexao) {
        $this->conn = $conexao;
    }
    
    /**
     * Inicializar tabelas de notificações
     */
    public function inicializar() {
        // Tabela de notificações
        $sql = "CREATE TABLE IF NOT EXISTS notificacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(50) NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            mensagem TEXT NOT NULL,
            usuario_id INT,
            usuario_tipo ENUM('cliente', 'dono', 'funcionario', 'todos') DEFAULT 'todos',
            id_referencia INT,
            tabela_referencia VARCHAR(50),
            lida BOOLEAN DEFAULT FALSE,
            prioridade ENUM('baixa', 'media', 'alta', 'urgente') DEFAULT 'media',
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_leitura TIMESTAMP NULL,
            icone VARCHAR(50) DEFAULT 'bell',
            cor VARCHAR(20) DEFAULT '#007bff',
            link VARCHAR(255),
            INDEX idx_usuario (usuario_id, lida),
            INDEX idx_tipo (usuario_tipo, lida),
            INDEX idx_data (data_criacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->conn->query($sql);
        
        // Tabela de histórico de status de pedidos
        $sql_historico = "CREATE TABLE IF NOT EXISTS pedidos_historico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            status_anterior VARCHAR(50),
            status_novo VARCHAR(50) NOT NULL,
            alterado_por INT,
            tipo_usuario VARCHAR(20),
            observacao TEXT,
            tempo_gasto INT COMMENT 'Tempo em minutos',
            data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
            INDEX idx_pedido (id_pedido),
            INDEX idx_data (data_alteracao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->conn->query($sql_historico);
        
        // Adicionar colunas na tabela pedidos se não existirem
        $this->adicionarColunasPedidos();
    }
    
    /**
     * Adicionar novas colunas na tabela pedidos
     */
    private function adicionarColunasPedidos() {
        $colunas = [
            "tempo_estimado INT DEFAULT 30 COMMENT 'Tempo estimado em minutos'",
            "tempo_preparo INT COMMENT 'Tempo real de preparo em minutos'",
            "prioridade ENUM('baixa', 'media', 'alta', 'urgente') DEFAULT 'media'",
            "observacoes_cliente TEXT",
            "observacoes_cozinha TEXT",
            "avaliacao_cliente INT CHECK (avaliacao_cliente >= 1 AND avaliacao_cliente <= 5)",
            "comentario_avaliacao TEXT",
            "data_avaliacao TIMESTAMP NULL",
            "cancelado_por INT",
            "motivo_cancelamento TEXT",
            "data_cancelamento TIMESTAMP NULL"
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
     * Criar notificação
     */
    public function criar($tipo, $titulo, $mensagem, $usuario_id = null, $usuario_tipo = 'todos', $dados = []) {
        $stmt = $this->conn->prepare("INSERT INTO notificacoes 
            (tipo, titulo, mensagem, usuario_id, usuario_tipo, id_referencia, 
             tabela_referencia, prioridade, icone, cor, link) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $prioridade = $dados['prioridade'] ?? 'media';
        $icone = $dados['icone'] ?? 'bell';
        $cor = $dados['cor'] ?? '#007bff';
        $link = $dados['link'] ?? null;
        $id_referencia = $dados['id_referencia'] ?? null;
        $tabela_referencia = $dados['tabela_referencia'] ?? null;
        
        $stmt->bind_param("sssisssssss", 
            $tipo, $titulo, $mensagem, $usuario_id, $usuario_tipo, 
            $id_referencia, $tabela_referencia, $prioridade, $icone, $cor, $link
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Notificação para novo pedido
     */
    public function novoPedido($id_pedido, $cliente_nome) {
        $this->criar(
            'novo_pedido',
            'Novo Pedido!',
            "Novo pedido #$id_pedido de $cliente_nome",
            null,
            'dono',
            [
                'id_referencia' => $id_pedido,
                'tabela_referencia' => 'pedidos',
                'prioridade' => 'alta',
                'icone' => 'shopping-cart',
                'cor' => '#28a745',
                'link' => "painel_dono.php?pedido=$id_pedido"
            ]
        );
        
        // Notificar funcionários também
        $this->criar(
            'novo_pedido',
            'Novo Pedido para Preparar',
            "Pedido #$id_pedido aguardando preparo",
            null,
            'funcionario',
            [
                'id_referencia' => $id_pedido,
                'tabela_referencia' => 'pedidos',
                'prioridade' => 'alta',
                'icone' => 'chef-hat',
                'cor' => '#fd7e14'
            ]
        );
    }
    
    /**
     * Notificação de mudança de status
     */
    public function mudancaStatus($id_pedido, $status_novo, $cliente_id) {
        $mensagens = [
            'Confirmado' => 'Seu pedido foi confirmado e está sendo preparado!',
            'Preparando' => 'Seu pedido está sendo preparado na cozinha',
            'Pronto' => 'Seu pedido está pronto para entrega!',
            'Saiu para Entrega' => 'Seu pedido saiu para entrega',
            'Entregue' => 'Seu pedido foi entregue. Que tal avaliar?',
            'Cancelado' => 'Seu pedido foi cancelado'
        ];
        
        $cores = [
            'Confirmado' => '#17a2b8',
            'Preparando' => '#fd7e14',
            'Pronto' => '#28a745',
            'Saiu para Entrega' => '#6f42c1',
            'Entregue' => '#28a745',
            'Cancelado' => '#dc3545'
        ];
        
        $icones = [
            'Confirmado' => 'check-circle',
            'Preparando' => 'chef-hat',
            'Pronto' => 'check-square',
            'Saiu para Entrega' => 'truck',
            'Entregue' => 'thumbs-up',
            'Cancelado' => 'x-circle'
        ];
        
        if (isset($mensagens[$status_novo])) {
            $this->criar(
                'status_pedido',
                "Pedido #$id_pedido - $status_novo",
                $mensagens[$status_novo],
                $cliente_id,
                'cliente',
                [
                    'id_referencia' => $id_pedido,
                    'tabela_referencia' => 'pedidos',
                    'prioridade' => 'media',
                    'icone' => $icones[$status_novo],
                    'cor' => $cores[$status_novo],
                    'link' => "meus_pedidos.php?pedido=$id_pedido"
                ]
            );
        }
    }
    
    /**
     * Buscar notificações do usuário
     */
    public function buscarPorUsuario($usuario_id, $usuario_tipo, $limite = 10, $apenas_nao_lidas = false) {
        $sql = "SELECT * FROM notificacoes 
                WHERE (usuario_id = ? OR usuario_tipo = 'todos' OR usuario_tipo = ?)";
        
        if ($apenas_nao_lidas) {
            $sql .= " AND lida = FALSE";
        }
        
        $sql .= " ORDER BY data_criacao DESC LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isi", $usuario_id, $usuario_tipo, $limite);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $notificacoes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $notificacoes;
    }
    
    /**
     * Marcar como lida
     */
    public function marcarLida($id_notificacao) {
        $stmt = $this->conn->prepare("UPDATE notificacoes SET lida = TRUE, data_leitura = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id_notificacao);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Contar não lidas
     */
    public function contarNaoLidas($usuario_id, $usuario_tipo) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM notificacoes 
                                     WHERE (usuario_id = ? OR usuario_tipo = 'todos' OR usuario_tipo = ?) 
                                     AND lida = FALSE");
        $stmt->bind_param("is", $usuario_id, $usuario_tipo);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['total'];
    }
    
    /**
     * Adicionar ao histórico de pedido
     */
    public function adicionarHistorico($id_pedido, $status_anterior, $status_novo, $alterado_por, $tipo_usuario, $observacao = null) {
        $stmt = $this->conn->prepare("INSERT INTO pedidos_historico 
            (id_pedido, status_anterior, status_novo, alterado_por, tipo_usuario, observacao) 
            VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ississ", $id_pedido, $status_anterior, $status_novo, $alterado_por, $tipo_usuario, $observacao);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Buscar histórico de um pedido
     */
    public function buscarHistorico($id_pedido) {
        $stmt = $this->conn->prepare("SELECT h.*, 
                                            CASE 
                                                WHEN h.tipo_usuario = 'cliente' THEN c.nome
                                                WHEN h.tipo_usuario = 'funcionario' THEN f.nome
                                                WHEN h.tipo_usuario = 'dono' THEN d.nome
                                                ELSE 'Sistema'
                                            END as nome_usuario
                                     FROM pedidos_historico h
                                     LEFT JOIN clientes c ON h.alterado_por = c.id AND h.tipo_usuario = 'cliente'
                                     LEFT JOIN funcionarios f ON h.alterado_por = f.id AND h.tipo_usuario = 'funcionario' 
                                     LEFT JOIN dono d ON h.alterado_por = d.id AND h.tipo_usuario = 'dono'
                                     WHERE h.id_pedido = ?
                                     ORDER BY h.data_alteracao ASC");
        
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $historico = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $historico;
    }
}

// ========================================
// API PARA NOTIFICAÇÕES EM TEMPO REAL
// ========================================

// Este arquivo pode ser chamado via AJAX para buscar novas notificações
if (isset($_GET['action']) && $_GET['action'] == 'buscar_notificacoes') {
    header('Content-Type: application/json');
    
    session_start();
    
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['erro' => 'Usuário não autenticado']);
        exit;
    }
    
    require_once 'config.php';
    
    $notif = new Notificacoes($conn);
    
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'cliente';
    
    $notificacoes = $notif->buscarPorUsuario($usuario_id, $usuario_tipo, 5, true);
    $total_nao_lidas = $notif->contarNaoLidas($usuario_id, $usuario_tipo);
    
    echo json_encode([
        'notificacoes' => $notificacoes,
        'total_nao_lidas' => $total_nao_lidas
    ]);
    exit;
}

// Marcar notificação como lida
if (isset($_GET['action']) && $_GET['action'] == 'marcar_lida') {
    header('Content-Type: application/json');
    
    session_start();
    
    if (!isset($_SESSION['usuario_id'])) {
        echo json_encode(['erro' => 'Usuário não autenticado']);
        exit;
    }
    
    $id_notificacao = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    
    if ($id_notificacao) {
        require_once 'config.php';
        $notif = new Notificacoes($conn);
        
        $sucesso = $notif->marcarLida($id_notificacao);
        echo json_encode(['sucesso' => $sucesso]);
    } else {
        echo json_encode(['erro' => 'ID inválido']);
    }
    exit;
}
?>