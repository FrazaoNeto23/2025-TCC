<?php
// =====================================================
// HELPERS.PHP - Funções auxiliares para validação
// Crie este arquivo na raiz do projeto
// =====================================================

/**
 * Valida se um produto existe antes de adicionar ao carrinho/pedido
 * 
 * @param mysqli $conn Conexão com o banco
 * @param int $id_produto ID do produto
 * @param string $tipo_produto 'normal' ou 'especial'
 * @return array ['exists' => bool, 'produto' => array|null]
 */
function validar_produto($conn, $id_produto, $tipo_produto)
{
    $id_produto = intval($id_produto);

    if ($tipo_produto === 'normal') {
        $stmt = $conn->prepare("SELECT id, nome, preco, imagem FROM produtos WHERE id = ?");
    } elseif ($tipo_produto === 'especial') {
        $stmt = $conn->prepare("SELECT id, nome, preco, imagem FROM produtos_especiais WHERE id = ?");
    } else {
        return ['exists' => false, 'produto' => null, 'erro' => 'Tipo de produto inválido'];
    }

    $stmt->bind_param("i", $id_produto);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return [
            'exists' => true,
            'produto' => $result->fetch_assoc(),
            'erro' => null
        ];
    }

    return [
        'exists' => false,
        'produto' => null,
        'erro' => "Produto não encontrado (ID: $id_produto, Tipo: $tipo_produto)"
    ];
}

/**
 * Valida método de pagamento
 * 
 * @param string $metodo Método escolhido
 * @return bool
 */
function validar_metodo_pagamento($metodo)
{
    $metodos_validos = ['pix', 'cartao', 'dinheiro'];
    return in_array($metodo, $metodos_validos);
}

/**
 * Limpa e valida número de mesa
 * 
 * @param mixed $numero_mesa Número da mesa
 * @return int|null Número validado ou null
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
 * Registra erro de integridade no log do sistema
 * 
 * @param mysqli $conn Conexão com banco
 * @param string $tipo Tipo do erro
 * @param string $mensagem Mensagem de erro
 * @param array $dados Dados adicionais
 */
function log_erro_integridade($conn, $tipo, $mensagem, $dados = [])
{
    try {
        $stmt = $conn->prepare("
            INSERT INTO system_logs (tipo, nivel, status, mensagem, dados) 
            VALUES (?, 'ERROR', 'erro', ?, ?)
        ");

        $dados_json = json_encode($dados);
        $stmt->bind_param("sss", $tipo, $mensagem, $dados_json);
        $stmt->execute();
    } catch (Exception $e) {
        // Falha silenciosa em log não deve quebrar o sistema
        error_log("Falha ao registrar log: " . $e->getMessage());
    }
}

/**
 * Verifica integridade de um pedido antes de criar
 * 
 * @param mysqli $conn Conexão
 * @param int $id_cliente ID do cliente
 * @param array $itens Array de itens do carrinho
 * @return array ['valido' => bool, 'erros' => array]
 */
function verificar_integridade_pedido($conn, $id_cliente, $itens)
{
    $erros = [];

    // Validar cliente
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ? AND tipo = 'cliente'");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        $erros[] = "Cliente inválido";
    }

    // Validar cada produto
    foreach ($itens as $item) {
        $validacao = validar_produto($conn, $item['id_produto'], $item['tipo_produto']);

        if (!$validacao['exists']) {
            $erros[] = $validacao['erro'];

            // Registrar erro no log
            log_erro_integridade($conn, 'produto_invalido', $validacao['erro'], [
                'id_produto' => $item['id_produto'],
                'tipo_produto' => $item['tipo_produto'],
                'id_cliente' => $id_cliente
            ]);
        }
    }

    return [
        'valido' => empty($erros),
        'erros' => $erros
    ];
}

// =====================================================
// EXEMPLO DE USO
// =====================================================

/*
// No painel_cliente.php, substitua a validação atual por:

include 'helpers.php';

if (isset($_POST['adicionar_carrinho'])) {
    $id_produto = intval($_POST['id_produto']);
    $quantidade = intval($_POST['quantidade']);
    $tipo_produto = $_POST['tipo_produto'];

    // Validar produto
    $validacao = validar_produto($conn, $id_produto, $tipo_produto);

    if (!$validacao['exists']) {
        $msg = "❌ " . $validacao['erro'];
    } else {
        // Produto válido, adicionar ao carrinho
        // ... resto do código
    }
}

// No finalizar_carrinho.php, antes de criar pedidos:

$integridade = verificar_integridade_pedido($conn, $id_cliente, $itens_array);

if (!$integridade['valido']) {
    $erro = "Erro de integridade: " . implode(", ", $integridade['erros']);
    // Mostrar erro ao usuário
} else {
    // Continuar com a criação do pedido
}
*/
?>