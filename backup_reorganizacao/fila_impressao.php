<?php
// ========================================
// SISTEMA DE FILA DE IMPRESSÃO AUTOMÁTICA
// FASE 2 - ITEM 15
// ========================================

class FilaImpressao {
    private $conn;
    
    public function __construct($conexao) {
        $this->conn = $conexao;
    }
    
    /**
     * Inicializar tabela de fila de impressão
     */
    public function inicializar() {
        $sql = "CREATE TABLE IF NOT EXISTS fila_impressao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            tipo ENUM('pedido', 'comanda', 'recibo') DEFAULT 'pedido',
            prioridade ENUM('baixa', 'media', 'alta', 'urgente') DEFAULT 'media',
            status ENUM('pendente', 'imprimindo', 'impresso', 'erro') DEFAULT 'pendente',
            dados_impressao LONGTEXT NOT NULL,
            tentativas INT DEFAULT 0,
            max_tentativas INT DEFAULT 3,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            data_impressao TIMESTAMP NULL,
            erro_mensagem TEXT,
            impressora VARCHAR(100) DEFAULT 'cozinha_principal',
            FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
            INDEX idx_status (status, prioridade),
            INDEX idx_pedido (id_pedido),
            INDEX idx_data (data_criacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->conn->query($sql);
        
        // Tabela de configurações de impressoras
        $sql_impressoras = "CREATE TABLE IF NOT EXISTS impressoras (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL UNIQUE,
            tipo ENUM('termica', 'matricial', 'jato', 'laser') DEFAULT 'termica',
            endereco_ip VARCHAR(45),
            porta INT DEFAULT 9100,
            status ENUM('ativa', 'inativa', 'manutencao') DEFAULT 'ativa',
            papel_largura INT DEFAULT 80 COMMENT 'Largura em mm',
            papel_altura INT DEFAULT 0 COMMENT 'Altura em mm, 0 = contínuo',
            configuracoes JSON,
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultima_conexao TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->conn->query($sql_impressoras);
        
        // Inserir impressora padrão se não existir
        $this->criarImpressorasPadrao();
    }
    
    /**
     * Criar impressoras padrão
     */
    private function criarImpressorasPadrao() {
        $impressoras_padrao = [
            [
                'nome' => 'cozinha_principal',
                'tipo' => 'termica',
                'endereco_ip' => '192.168.1.100',
                'papel_largura' => 80,
                'configuracoes' => json_encode([
                    'charset' => 'utf-8',
                    'densidade' => 'alta',
                    'corte_automatico' => true,
                    'som_alerta' => true
                ])
            ],
            [
                'nome' => 'cozinha_bebidas',
                'tipo' => 'termica', 
                'endereco_ip' => '192.168.1.101',
                'papel_largura' => 58,
                'configuracoes' => json_encode([
                    'charset' => 'utf-8',
                    'densidade' => 'media',
                    'corte_automatico' => true
                ])
            ],
            [
                'nome' => 'balcao_atendimento',
                'tipo' => 'termica',
                'endereco_ip' => '192.168.1.102', 
                'papel_largura' => 80,
                'configuracoes' => json_encode([
                    'charset' => 'utf-8',
                    'densidade' => 'alta',
                    'corte_automatico' => false
                ])
            ]
        ];
        
        foreach ($impressoras_padrao as $impressora) {
            $check = $this->conn->prepare("SELECT id FROM impressoras WHERE nome = ?");
            $check->bind_param("s", $impressora['nome']);
            $check->execute();
            
            if ($check->get_result()->num_rows == 0) {
                $stmt = $this->conn->prepare("INSERT INTO impressoras 
                    (nome, tipo, endereco_ip, papel_largura, configuracoes) 
                    VALUES (?, ?, ?, ?, ?)");
                
                $stmt->bind_param("sssis", 
                    $impressora['nome'], 
                    $impressora['tipo'],
                    $impressora['endereco_ip'],
                    $impressora['papel_largura'],
                    $impressora['configuracoes']
                );
                
                $stmt->execute();
                $stmt->close();
            }
            $check->close();
        }
    }
    
    /**
     * Adicionar pedido na fila de impressão
     */
    public function adicionarPedido($id_pedido, $tipo = 'pedido', $prioridade = 'media', $impressora = 'cozinha_principal') {
        // Buscar dados do pedido
        $dados_pedido = $this->buscarDadosPedido($id_pedido);
        
        if (!$dados_pedido) {
            return false;
        }
        
        // Gerar conteúdo para impressão
        $conteudo_impressao = $this->gerarConteudoImpressao($dados_pedido, $tipo);
        
        // Adicionar na fila
        $stmt = $this->conn->prepare("INSERT INTO fila_impressao 
            (id_pedido, tipo, prioridade, dados_impressao, impressora) 
            VALUES (?, ?, ?, ?, ?)");
        
        $stmt->bind_param("issss", $id_pedido, $tipo, $prioridade, $conteudo_impressao, $impressora);
        $result = $stmt->execute();
        $stmt->close();
        
        // Processar fila automaticamente
        if ($result) {
            $this->processarFila();
        }
        
        return $result;
    }
    
    /**
     * Buscar dados completos do pedido
     */
    private function buscarDadosPedido($id_pedido) {
        $stmt = $this->conn->prepare("
            SELECT p.*, c.nome as cliente_nome, c.telefone, c.endereco,
                   GROUP_CONCAT(
                       CONCAT(i.quantidade, 'x ', pr.nome, 
                              CASE WHEN i.observacoes IS NOT NULL 
                                   THEN CONCAT(' (', i.observacoes, ')') 
                                   ELSE '' END
                       ) SEPARATOR '\n'
                   ) as itens_detalhes,
                   SUM(i.quantidade * i.preco_unitario) as total_itens
            FROM pedidos p
            JOIN clientes c ON p.id_cliente = c.id
            JOIN itens_pedido i ON p.id = i.id_pedido
            JOIN produtos pr ON i.id_produto = pr.id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $dados = $result->fetch_assoc();
        $stmt->close();
        
        return $dados;
    }
    
    /**
     * Gerar conteúdo formatado para impressão
     */
    private function gerarConteudoImpressao($dados, $tipo) {
        $largura = 40; // Caracteres por linha
        
        $conteudo = "";
        
        // Cabeçalho
        $conteudo .= $this->centralizarTexto("BURGER HOUSE", $largura) . "\n";
        $conteudo .= $this->centralizarTexto("Rua das Delícias, 123", $largura) . "\n";
        $conteudo .= $this->centralizarTexto("Tel: (11) 99999-9999", $largura) . "\n";
        $conteudo .= str_repeat("=", $largura) . "\n\n";
        
        // Tipo de impressão
        if ($tipo == 'pedido') {
            $conteudo .= $this->centralizarTexto("PEDIDO COZINHA", $largura) . "\n";
        } elseif ($tipo == 'comanda') {
            $conteudo .= $this->centralizarTexto("COMANDA", $largura) . "\n";
        } else {
            $conteudo .= $this->centralizarTexto("RECIBO", $largura) . "\n";
        }
        
        $conteudo .= str_repeat("-", $largura) . "\n";
        
        // Informações do pedido
        $conteudo .= "PEDIDO: #" . $dados['id'] . "\n";
        $conteudo .= "DATA: " . date('d/m/Y H:i:s', strtotime($dados['data_pedido'])) . "\n";
        $conteudo .= "CLIENTE: " . $dados['cliente_nome'] . "\n";
        
        if ($dados['telefone']) {
            $conteudo .= "TELEFONE: " . $dados['telefone'] . "\n";
        }
        
        // Tipo de entrega
        if ($dados['tipo_entrega'] == 'delivery') {
            $conteudo .= "ENTREGA: DELIVERY\n";
            if ($dados['endereco']) {
                $conteudo .= "ENDEREÇO:\n" . $this->quebrarLinha($dados['endereco'], $largura - 2) . "\n";
            }
        } else {
            $conteudo .= "ENTREGA: RETIRADA\n";
        }
        
        $conteudo .= "\n" . str_repeat("-", $largura) . "\n";
        $conteudo .= "ITENS:\n";
        $conteudo .= str_repeat("-", $largura) . "\n";
        
        // Itens do pedido
        $itens = explode("\n", $dados['itens_detalhes']);
        foreach ($itens as $item) {
            if (trim($item)) {
                $conteudo .= $this->quebrarLinha($item, $largura) . "\n";
            }
        }
        
        $conteudo .= str_repeat("-", $largura) . "\n";
        
        // Observações
        if ($dados['observacoes_cliente']) {
            $conteudo .= "OBS. CLIENTE:\n";
            $conteudo .= $this->quebrarLinha($dados['observacoes_cliente'], $largura) . "\n\n";
        }
        
        if ($dados['observacoes_cozinha']) {
            $conteudo .= "OBS. COZINHA:\n";
            $conteudo .= $this->quebrarLinha($dados['observacoes_cozinha'], $largura) . "\n\n";
        }
        
        // Total (apenas para recibos)
        if ($tipo == 'recibo') {
            $conteudo .= str_repeat("=", $largura) . "\n";
            $conteudo .= "TOTAL: R$ " . number_format($dados['valor_total'], 2, ',', '.') . "\n";
            
            if ($dados['desconto'] > 0) {
                $conteudo .= "DESCONTO: R$ " . number_format($dados['desconto'], 2, ',', '.') . "\n";
            }
            
            $conteudo .= str_repeat("=", $largura) . "\n";
        }
        
        // Prioridade e tempo estimado
        if ($tipo == 'pedido') {
            $conteudo .= "\nPRIORIDADE: " . strtoupper($dados['prioridade'] ?? 'MEDIA') . "\n";
            $conteudo .= "TEMPO ESTIMADO: " . ($dados['tempo_estimado'] ?? 30) . " min\n";
        }
        
        // Rodapé
        $conteudo .= "\n" . str_repeat("=", $largura) . "\n";
        $conteudo .= $this->centralizarTexto("Impresso em: " . date('d/m/Y H:i:s'), $largura) . "\n";
        
        // Comando de corte de papel (ESC/POS)
        $conteudo .= "\x1B\x69"; // Corte parcial
        
        return $conteudo;
    }
    
    /**
     * Centralizar texto
     */
    private function centralizarTexto($texto, $largura) {
        $len = strlen($texto);
        if ($len >= $largura) return $texto;
        
        $espacos = floor(($largura - $len) / 2);
        return str_repeat(" ", $espacos) . $texto;
    }
    
    /**
     * Quebrar linha respeitando largura
     */
    private function quebrarLinha($texto, $largura, $prefixo = "") {
        return $prefixo . wordwrap($texto, $largura - strlen($prefixo), "\n" . $prefixo);
    }
    
    /**
     * Processar fila de impressão
     */
    public function processarFila() {
        // Buscar itens pendentes ordenados por prioridade
        $stmt = $this->conn->prepare("
            SELECT f.*, i.status as impressora_status, i.endereco_ip, i.porta
            FROM fila_impressao f
            JOIN impressoras i ON f.impressora = i.nome
            WHERE f.status = 'pendente' 
            AND f.tentativas < f.max_tentativas
            AND i.status = 'ativa'
            ORDER BY 
                CASE f.prioridade 
                    WHEN 'urgente' THEN 4
                    WHEN 'alta' THEN 3
                    WHEN 'media' THEN 2
                    WHEN 'baixa' THEN 1
                END DESC,
                f.data_criacao ASC
            LIMIT 10
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($item = $result->fetch_assoc()) {
            $this->imprimirItem($item);
        }
        
        $stmt->close();
    }
    
    /**
     * Imprimir item específico
     */
    private function imprimirItem($item) {
        // Atualizar status para "imprimindo"
        $this->atualizarStatus($item['id'], 'imprimindo');
        
        try {
            // Simular impressão (aqui seria a integração real com a impressora)
            $sucesso = $this->enviarParaImpressora($item);
            
            if ($sucesso) {
                $this->atualizarStatus($item['id'], 'impresso');
                $this->conn->query("UPDATE fila_impressao SET data_impressao = NOW() WHERE id = " . $item['id']);
            } else {
                throw new Exception("Falha na comunicação com a impressora");
            }
            
        } catch (Exception $e) {
            // Incrementar tentativas
            $tentativas = $item['tentativas'] + 1;
            $status = ($tentativas >= $item['max_tentativas']) ? 'erro' : 'pendente';
            
            $stmt = $this->conn->prepare("UPDATE fila_impressao 
                                        SET status = ?, tentativas = ?, erro_mensagem = ? 
                                        WHERE id = ?");
            $stmt->bind_param("sisi", $status, $tentativas, $e->getMessage(), $item['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Enviar dados para impressora
     */
    private function enviarParaImpressora($item) {
        // Em um ambiente real, aqui seria feita a conexão com a impressora
        // Por exemplo, usando socket para impressoras de rede:
        
        /*
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if ($socket === false) {
            return false;
        }
        
        $conectou = socket_connect($socket, $item['endereco_ip'], $item['porta']);
        
        if ($conectou === false) {
            socket_close($socket);
            return false;
        }
        
        $enviou = socket_write($socket, $item['dados_impressao'], strlen($item['dados_impressao']));
        socket_close($socket);
        
        return $enviou !== false;
        */
        
        // Para demonstração, vamos simular sucesso
        usleep(500000); // Simular tempo de impressão (0.5 segundos)
        
        // Log da impressão para debug
        error_log("IMPRESSÃO SIMULADA - Pedido #{$item['id_pedido']} - Impressora: {$item['impressora']}");
        
        return true; // Simular sucesso
    }
    
    /**
     * Atualizar status do item
     */
    private function atualizarStatus($id, $status) {
        $stmt = $this->conn->prepare("UPDATE fila_impressao SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Reprocessar itens com erro
     */
    public function reprocessarErros() {
        $this->conn->query("UPDATE fila_impressao SET status = 'pendente', tentativas = 0, erro_mensagem = NULL WHERE status = 'erro'");
        $this->processarFila();
    }
    
    /**
     * Buscar status da fila
     */
    public function statusFila() {
        $stmt = $this->conn->prepare("
            SELECT status, COUNT(*) as quantidade
            FROM fila_impressao
            WHERE data_criacao >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        $status = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $status;
    }
    
    /**
     * Limpar fila antiga (itens processados há mais de 7 dias)
     */
    public function limparFilaAntiga() {
        $this->conn->query("DELETE FROM fila_impressao 
                          WHERE status IN ('impresso', 'erro') 
                          AND data_criacao < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
}

// ========================================
// PROCESSADOR AUTOMÁTICO DA FILA
// ========================================

// Este script pode ser executado via cron job a cada minuto
if (php_sapi_name() === 'cli' || isset($_GET['processar_fila'])) {
    require_once 'config.php';
    
    $fila = new FilaImpressao($conn);
    $fila->inicializar();
    $fila->processarFila();
    
    // Limpar fila antiga uma vez por dia
    if (date('H:i') == '03:00') {
        $fila->limparFilaAntiga();
    }
    
    if (isset($_GET['processar_fila'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'processado', 'timestamp' => date('Y-m-d H:i:s')]);
    }
}
?>