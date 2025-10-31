<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pedido'], $_POST['novo_status'])) {
    $id_pedido = intval($_POST['id_pedido']);
    $novo_status = $_POST['novo_status'];

    // Validar status
    $status_validos = ['pendente', 'em_preparo', 'pronto', 'entregue', 'cancelado'];

    if (!in_array($novo_status, $status_validos)) {
        $_SESSION['erro'] = "Status inválido!";
        header("Location: listar_pedidos.php");
        exit;
    }

    // ===== ATUALIZAR STATUS =====
    $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $novo_status, $id_pedido);

    if ($stmt->execute()) {
        $_SESSION['sucesso'] = "Status alterado com sucesso para " . strtoupper(str_replace('_', ' ', $novo_status)) . "!";

        // Registrar no log
        $log_stmt = $conn->prepare("
            INSERT INTO system_logs (tipo, mensagem, id_usuario) 
            VALUES ('status', ?, ?)
        ");
        $mensagem_log = "Pedido #{$id_pedido} - Status alterado para {$novo_status}";
        $log_stmt->bind_param("si", $mensagem_log, $_SESSION['id_usuario']);
        $log_stmt->execute();

        // Se o pedido foi marcado como pronto, adicionar à fila de impressão
        if ($novo_status == 'pronto') {
            $check_fila = $conn->query("SHOW TABLES LIKE 'fila_impressao'");

            if ($check_fila->num_rows > 0) {
                $insert_fila = $conn->prepare("
                    INSERT INTO fila_impressao (id_pedido, status) 
                    VALUES (?, 'pendente')
                    ON DUPLICATE KEY UPDATE status = 'pendente'
                ");
                $insert_fila->bind_param("i", $id_pedido);
                $insert_fila->execute();
            }
        }
    } else {
        $_SESSION['erro'] = "Erro ao alterar status: " . $conn->error;
    }

    $stmt->close();
} else {
    $_SESSION['erro'] = "Requisição inválida!";
}

header("Location: listar_pedidos.php");
exit;
?>