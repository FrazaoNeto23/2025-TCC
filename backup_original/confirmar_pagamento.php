<?php
ob_start();
session_start();
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["processar_pagamento"])) {
    $tipo_entrega = $_POST["tipo_entrega"];
    $tipo_pagamento = $_POST["tipo_pagamento"];
    $numero_mesa = $_POST["numero_mesa"] ?? null;
    $observacao = $_POST["observacao"] ?? '';

    $total = 0;

    // Verifica se há carrinho na sessão
    if (isset($_SESSION['carrinho']) && !empty($_SESSION['carrinho'])) {
        foreach ($_SESSION['carrinho'] as $id_produto => $item) {
            $total += $item['preco'] * $item['quantidade'];
        }
    } else {
        $_SESSION['pagamento_erro'] = "⚠️ Carrinho vazio. Adicione itens antes de finalizar.";
        header("Location: carrinho.php");
        exit;
    }

    // Início da transação
    $conn->begin_transaction();

    try {
        // Insere o pedido
        $sql_pedido = "INSERT INTO pedidos (id_usuario, tipo_entrega, tipo_pagamento, numero_mesa, observacao, total, status, data_pedido) 
                       VALUES (?, ?, ?, ?, ?, ?, 'Pendente', NOW())";
        $stmt_pedido = $conn->prepare($sql_pedido);
        $stmt_pedido->bind_param("issssd", $_SESSION['id_usuario'], $tipo_entrega, $tipo_pagamento, $numero_mesa, $observacao, $total);
        $stmt_pedido->execute();

        $id_pedido = $stmt_pedido->insert_id;

        // Insere os itens do pedido
        $sql_item = "INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($_SESSION['carrinho'] as $id_produto => $item) {
            $stmt_item->bind_param("iiid", $id_pedido, $id_produto, $item['quantidade'], $item['preco']);
            $stmt_item->execute();
        }

        // Commit da transação
        $conn->commit();

        // Limpa o carrinho da sessão
        unset($_SESSION['carrinho']);

        // Mensagem de sucesso personalizada
        if ($numero_mesa) {
            $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$id_pedido confirmado! Em breve será servido na mesa $numero_mesa.";
        } else {
            $_SESSION['pagamento_sucesso'] = "🎉 Pedido #$id_pedido confirmado! Em breve estará a caminho. Obrigado pela preferência!";
        }

        // Redireciona para o painel do cliente
        ob_clean();
        header("Location: painel_cliente.php");
        exit();

    } catch (Exception $e) {
        // Caso haja erro, reverte a transação
        $conn->rollback();
        $_SESSION['pagamento_erro'] = "❌ Erro ao processar o pagamento: " . $e->getMessage();
        header("Location: carrinho.php");
        exit;
    }
}
?>
