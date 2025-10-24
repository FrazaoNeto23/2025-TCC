<?php
// ========================================
// CLASSE DE GERENCIAMENTO DE PAGAMENTOS
// ========================================

class Pagamento
{
    private $conn;

    public function __construct($conexao)
    {
        $this->conn = $conexao;
    }

    /**
     * Criar nova transação
     */
    public function criarTransacao($id_pedido, $metodo, $valor, $dados_extras = [])
    {
        try {
            $numero_transacao = $this->gerarNumeroTransacao();
            $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $dados_json = json_encode($dados_extras);

            $stmt = $this->conn->prepare("
                INSERT INTO transacoes 
                (numero_transacao, id_pedido, metodo, valor, ip_cliente, dados_pagamento, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pendente')
            ");

            $stmt->bind_param(
                "sisdss",
                $numero_transacao,
                $id_pedido,
                $metodo,
                $valor,
                $ip_cliente,
                $dados_json
            );

            if ($stmt->execute()) {
                $id_transacao = $this->conn->insert_id;

                // Atualizar pedido com id da transação
                $this->conn->query("
                    UPDATE pedidos 
                    SET id_transacao = $id_transacao, valor_final = $valor 
                    WHERE id = $id_pedido
                ");

                logSeguranca('pagamento', "Transação criada: $numero_transacao", [
                    'id_transacao' => $id_transacao,
                    'metodo' => $metodo,
                    'valor' => $valor
                ]);

                return [
                    'sucesso' => true,
                    'id_transacao' => $id_transacao,
                    'numero_transacao' => $numero_transacao
                ];
            }

            return ['sucesso' => false, 'erro' => 'Erro ao criar transação'];

        } catch (Exception $e) {
            error_log("Erro ao criar transação: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao processar pagamento'];
        }
    }

    /**
     * Processar pagamento PIX
     */
    public function processarPIX($id_transacao)
    {
        try {
            // Obter dados da transação
            $stmt = $this->conn->prepare("SELECT * FROM transacoes WHERE id = ?");
            $stmt->bind_param("i", $id_transacao);
            $stmt->execute();
            $transacao = $stmt->get_result()->fetch_assoc();

            if (!$transacao) {
                return ['sucesso' => false, 'erro' => 'Transação não encontrada'];
            }

            // Gerar QR Code e chave PIX (simulado)
            $chave_pix = $this->gerarChavePIX($transacao);
            $qr_code_url = $this->gerarQRCodePIX($chave_pix);

            // Atualizar transação
            $stmt = $this->conn->prepare("
                UPDATE transacoes 
                SET status = 'processando',
                    dados_pagamento = JSON_SET(
                        COALESCE(dados_pagamento, '{}'),
                        '$.chave_pix', ?,
                        '$.qr_code', ?
                    )
                WHERE id = ?
            ");

            $stmt->bind_param("ssi", $chave_pix, $qr_code_url, $id_transacao);
            $stmt->execute();

            return [
                'sucesso' => true,
                'chave_pix' => $chave_pix,
                'qr_code' => $qr_code_url
            ];

        } catch (Exception $e) {
            error_log("Erro ao processar PIX: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao gerar PIX'];
        }
    }

    /**
     * Processar pagamento com Cartão
     */
    public function processarCartao($id_transacao, $dados_cartao)
    {
        try {
            // Validar dados do cartão
            $validacao = $this->validarDadosCartao($dados_cartao);
            if (!$validacao['valido']) {
                return ['sucesso' => false, 'erro' => $validacao['erro']];
            }

            // Obter transação
            $stmt = $this->conn->prepare("SELECT * FROM transacoes WHERE id = ?");
            $stmt->bind_param("i", $id_transacao);
            $stmt->execute();
            $transacao = $stmt->get_result()->fetch_assoc();

            if (!$transacao) {
                return ['sucesso' => false, 'erro' => 'Transação não encontrada'];
            }

            // Integração com gateway (simulado)
            $resultado_gateway = $this->processarGatewayCartao($transacao, $dados_cartao);

            if ($resultado_gateway['aprovado']) {
                // Confirmar pagamento
                $this->confirmarPagamento($id_transacao, $resultado_gateway['codigo_autorizacao']);

                return [
                    'sucesso' => true,
                    'codigo_autorizacao' => $resultado_gateway['codigo_autorizacao']
                ];
            } else {
                // Pagamento recusado
                $this->recusarPagamento($id_transacao, $resultado_gateway['motivo']);

                return [
                    'sucesso' => false,
                    'erro' => $resultado_gateway['motivo']
                ];
            }

        } catch (Exception $e) {
            error_log("Erro ao processar cartão: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao processar cartão'];
        }
    }

    /**
     * Confirmar pagamento
     */
    public function confirmarPagamento($id_transacao, $codigo_autorizacao = null)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE transacoes 
                SET status = 'confirmado',
                    codigo_autorizacao = ?,
                    data_confirmacao = NOW()
                WHERE id = ?
            ");

            $stmt->bind_param("si", $codigo_autorizacao, $id_transacao);
            $stmt->execute();

            // Atualizar pedido
            $this->conn->query("
                UPDATE pedidos p
                JOIN transacoes t ON p.id_transacao = t.id
                SET p.status_pagamento = 'Pago'
                WHERE t.id = $id_transacao
            ");

            logSeguranca('pagamento', "Pagamento confirmado", [
                'id_transacao' => $id_transacao,
                'codigo' => $codigo_autorizacao
            ]);

            return ['sucesso' => true];

        } catch (Exception $e) {
            error_log("Erro ao confirmar pagamento: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao confirmar'];
        }
    }

    /**
     * Cancelar/Estornar pagamento
     */
    public function estornarPagamento($id_transacao, $motivo)
    {
        try {
            $stmt = $this->conn->prepare("
                UPDATE transacoes 
                SET status = 'estornado',
                    observacoes = CONCAT(COALESCE(observacoes, ''), ' | Estorno: ', ?)
                WHERE id = ?
            ");

            $stmt->bind_param("si", $motivo, $id_transacao);
            $stmt->execute();

            // Atualizar pedido
            $this->conn->query("
                UPDATE pedidos p
                JOIN transacoes t ON p.id_transacao = t.id
                SET p.status_pagamento = 'Estornado'
                WHERE t.id = $id_transacao
            ");

            logSeguranca('pagamento', "Pagamento estornado", [
                'id_transacao' => $id_transacao,
                'motivo' => $motivo
            ]);

            return ['sucesso' => true];

        } catch (Exception $e) {
            error_log("Erro ao estornar: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao estornar'];
        }
    }

    /**
     * Aplicar cupom de desconto
     */
    public function aplicarCupom($codigo, $id_usuario, $valor_pedido)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM cupons 
                WHERE codigo = ? 
                AND ativo = TRUE
                AND (quantidade_total IS NULL OR quantidade_usada < quantidade_total)
                AND (data_inicio IS NULL OR data_inicio <= CURDATE())
                AND (data_fim IS NULL OR data_fim >= CURDATE())
            ");

            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            $cupom = $stmt->get_result()->fetch_assoc();

            if (!$cupom) {
                return ['sucesso' => false, 'erro' => 'Cupom inválido ou expirado'];
            }

            // Verificar valor mínimo
            if ($valor_pedido < $cupom['valor_minimo']) {
                return [
                    'sucesso' => false,
                    'erro' => 'Valor mínimo do pedido: R$ ' . number_format($cupom['valor_minimo'], 2, ',', '.')
                ];
            }

            // Calcular desconto
            if ($cupom['tipo'] === 'percentual') {
                $desconto = ($valor_pedido * $cupom['valor']) / 100;
            } else {
                $desconto = $cupom['valor'];
            }

            // Não pode ser maior que o valor do pedido
            $desconto = min($desconto, $valor_pedido);

            return [
                'sucesso' => true,
                'id_cupom' => $cupom['id'],
                'desconto' => $desconto,
                'valor_final' => $valor_pedido - $desconto
            ];

        } catch (Exception $e) {
            error_log("Erro ao aplicar cupom: " . $e->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao validar cupom'];
        }
    }

    /**
     * Registrar uso de cupom
     */
    public function registrarUsoCupom($id_cupom, $id_usuario, $id_pedido, $valor_desconto)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO cupons_usados (id_cupom, id_usuario, id_pedido, valor_desconto) 
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param("iiid", $id_cupom, $id_usuario, $id_pedido, $valor_desconto);
            $stmt->execute();

            return ['sucesso' => true];

        } catch (Exception $e) {
            error_log("Erro ao registrar cupom: " . $e->getMessage());
            return ['sucesso' => false];
        }
    }

    /**
     * Obter histórico de transações
     */
    public function obterHistorico($filtros = [])
    {
        $where = [];
        $params = [];
        $types = "";

        if (!empty($filtros['id_pedido'])) {
            $where[] = "t.id_pedido = ?";
            $params[] = $filtros['id_pedido'];
            $types .= "i";
        }

        if (!empty($filtros['status'])) {
            $where[] = "t.status = ?";
            $params[] = $filtros['status'];
            $types .= "s";
        }

        if (!empty($filtros['metodo'])) {
            $where[] = "t.metodo = ?";
            $params[] = $filtros['metodo'];
            $types .= "s";
        }

        if (!empty($filtros['data_inicio'])) {
            $where[] = "DATE(t.data_criacao) >= ?";
            $params[] = $filtros['data_inicio'];
            $types .= "s";
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = "DATE(t.data_criacao) <= ?";
            $params[] = $filtros['data_fim'];
            $types .= "s";
        }

        $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "
            SELECT t.*, 
                   p.numero_pedido,
                   u.nome as cliente_nome
            FROM transacoes t
            JOIN pedidos p ON t.id_pedido = p.id
            JOIN usuarios u ON p.id_cliente = u.id
            $where_sql
            ORDER BY t.data_criacao DESC
        ";

        $stmt = $this->conn->prepare($sql);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ========================================
    // MÉTODOS AUXILIARES PRIVADOS
    // ========================================

    private function gerarNumeroTransacao()
    {
        return 'TXN' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));
    }

    private function gerarChavePIX($transacao)
    {
        return 'PIX-BH-' . $transacao['id'] . '-' . time();
    }

    private function gerarQRCodePIX($chave)
    {
        return "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($chave);
    }

    private function validarDadosCartao($dados)
    {
        if (empty($dados['numero']) || empty($dados['cvv']) || empty($dados['validade'])) {
            return ['valido' => false, 'erro' => 'Dados do cartão incompletos'];
        }

        // Validar número do cartão (Luhn)
        $numero = preg_replace('/\s+/', '', $dados['numero']);
        if (!$this->validarLuhn($numero)) {
            return ['valido' => false, 'erro' => 'Número do cartão inválido'];
        }

        return ['valido' => true];
    }

    private function validarLuhn($numero)
    {
        $soma = 0;
        $tamanho = strlen($numero);
        $paridade = $tamanho % 2;

        for ($i = 0; $i < $tamanho; $i++) {
            $digito = intval($numero[$i]);

            if ($i % 2 == $paridade) {
                $digito *= 2;
            }

            if ($digito > 9) {
                $digito -= 9;
            }

            $soma += $digito;
        }

        return ($soma % 10) == 0;
    }

    private function processarGatewayCartao($transacao, $dados_cartao)
    {
        // SIMULAÇÃO - Em produção, integrar com gateway real (Mercado Pago, PagSeguro, etc)

        // Simular aprovação (80% de chance)
        $aprovado = (rand(1, 100) <= 80);

        if ($aprovado) {
            return [
                'aprovado' => true,
                'codigo_autorizacao' => 'AUTH-' . strtoupper(uniqid())
            ];
        } else {
            $motivos = [
                'Saldo insuficiente',
                'Cartão bloqueado',
                'Dados inválidos',
                'Limite excedido'
            ];

            return [
                'aprovado' => false,
                'motivo' => $motivos[array_rand($motivos)]
            ];
        }
    }

    private function recusarPagamento($id_transacao, $motivo)
    {
        $stmt = $this->conn->prepare("
            UPDATE transacoes 
            SET status = 'cancelado',
                observacoes = ?
            WHERE id = ?
        ");

        $stmt->bind_param("si", $motivo, $id_transacao);
        $stmt->execute();
    }
}