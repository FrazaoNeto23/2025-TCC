<?php
// =====================================================
// HELPERS.PHP - Funções auxiliares MELHORADAS
// =====================================================

/**
 * Valida se um produto existe e está disponível
 */
function validar_produto($conn, $id_produto, $tipo_produto)
{
    $id_produto = intval($id_produto);

    if (!in_array($tipo_produto, ['normal', 'especial'])) {
        return [
            'exists' => false,
            'produto' => null,
            'erro' => 'Tipo de produto inválido'
        ];
    }

    if ($tipo_produto === 'normal') {
        $stmt = $conn->prepare("SELECT id, nome, preco, imagem, disponivel FROM produtos WHERE id = ? AND disponivel = 1");
    } else {
        $stmt = $conn->prepare("SELECT id, nome, preco, imagem FROM produtos_especiais WHERE id = ?");
    }

    $stmt->bind_param("i", $id_produto);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $produto = $result->fetch_assoc();
        $stmt->close();
        return [
            'exists' => true,
            'produto' => $produto,
            'erro' => null
        ];
    }

    $stmt->close();
    return [
        'exists' => false,
        'produto' => null,
        'erro' => "Produto não encontrado ou indisponível (ID: $id_produto, Tipo: $tipo_produto)"
    ];
}

/**
 * Valida método de pagamento
 */
function validar_metodo_pagamento($metodo)
{
    $metodos_validos = ['pix', 'cartao', 'dinheiro'];
    return in_array(strtolower($metodo), $metodos_validos);
}

/**
 * Valida e limpa número de mesa
 */
function validar_numero_mesa($numero_mesa)
{
    if (empty($numero_mesa)) {
        return null;
    }

    $numero = intval($numero_mesa);

    if ($numero < 1 || $numero > 999) {
        return null;
    }

    return $numero;
}

/**
 * Valida quantidade
 */
function validar_quantidade($quantidade)
{
    $qtd = intval($quantidade);
    return ($qtd >= 1 && $qtd <= 99) ? $qtd : false;
}

/**
 * Registra erro de integridade no log
 */
function log_erro_integridade($conn, $tipo, $mensagem, $dados = [])
{
    try {
        // Verificar se tabela existe
        $check = $conn->query("SHOW TABLES LIKE 'system_logs'");
        if ($check->num_rows == 0) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS system_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tipo VARCHAR(50),
                    nivel VARCHAR(20) DEFAULT 'ERROR',
                    status VARCHAR(20) DEFAULT 'erro',
                    mensagem TEXT,
                    dados JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_tipo (tipo),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        $stmt = $conn->prepare("
            INSERT INTO system_logs (tipo, nivel, status, mensagem, dados) 
            VALUES (?, 'ERROR', 'erro', ?, ?)
        ");

        $dados['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $dados['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $dados['timestamp'] = date('Y-m-d H:i:s');

        $dados_json = json_encode($dados);
        $stmt->bind_param("sss", $tipo, $mensagem, $dados_json);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Falha ao registrar log: " . $e->getMessage());
    }
}

/**
 * Verifica integridade completa de um pedido antes de criar
 */
function verificar_integridade_pedido($conn, $id_cliente, $itens)
{
    $erros = [];

    // Validar cliente
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ? AND tipo = 'cliente'");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        $erros[] = "Cliente inválido ou não autorizado";
    }
    $stmt->close();

    // Validar itens vazios
    if (empty($itens)) {
        $erros[] = "Carrinho vazio";
    }

    // Validar cada produto
    foreach ($itens as $item) {
        $validacao = validar_produto($conn, $item['id_produto'], $item['tipo_produto']);

        if (!$validacao['exists']) {
            $erros[] = $validacao['erro'];

            log_erro_integridade($conn, 'produto_invalido_pedido', $validacao['erro'], [
                'id_produto' => $item['id_produto'],
                'tipo_produto' => $item['tipo_produto'],
                'id_cliente' => $id_cliente
            ]);
        }

        // Validar quantidade
        if (!validar_quantidade($item['quantidade'])) {
            $erros[] = "Quantidade inválida para produto ID {$item['id_produto']}";
        }
    }

    return [
        'valido' => empty($erros),
        'erros' => $erros
    ];
}

/**
 * Limpar carrinho de produtos inexistentes para um cliente
 */
function limpar_carrinho_invalido($conn, $id_cliente)
{
    $sql = "
    DELETE carrinho FROM carrinho
    LEFT JOIN produtos ON carrinho.id_produto = produtos.id 
        AND carrinho.tipo_produto = 'normal'
    LEFT JOIN produtos_especiais ON carrinho.id_produto = produtos_especiais.id 
        AND carrinho.tipo_produto = 'especial'
    WHERE carrinho.id_cliente = ?
        AND (
            (carrinho.tipo_produto = 'normal' AND produtos.id IS NULL) OR
            (carrinho.tipo_produto = 'especial' AND produtos_especiais.id IS NULL)
        )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $removidos = $stmt->affected_rows;
    $stmt->close();

    return $removidos;
}

/**
 * Sanitizar entrada de texto
 */
function sanitizar_texto($texto)
{
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function validar_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
?>