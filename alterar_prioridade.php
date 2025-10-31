<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "dono") {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pedido'], $_POST['nova_prioridade'])) {
    $id_pedido = intval($_POST['id_pedido']);
    $nova_prioridade = $_POST['nova_prioridade'];

    // Validar prioridade
    $prioridades_validas = ['baixa', 'media', 'alta'];

    if (!in_array($nova_prioridade, $prioridades_validas)) {
        $_SESSION['erro'] = "Prioridade inválida!";
        header("Location: listar_pedidos.php");
        exit;
    }

    // ===== VERIFICAR SE COLUNA EXISTE =====
    $check_column = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'prioridade'");

    if ($check_column->num_rows == 0) {
        // Criar coluna se não existir
        $conn->query("ALTER TABLE pedidos ADD COLUMN prioridade ENUM('baixa', 'media', 'alta') DEFAULT 'media' AFTER status");
    }

    // ===== ATUALIZAR PRIORIDADE =====
    $stmt = $conn->prepare("UPDATE pedidos SET prioridade = ? WHERE id = ?");
    $stmt->bind_param("si", $nova_prioridade, $id_pedido);

    if ($stmt->execute()) {
        $_SESSION['sucesso'] = "Prioridade alterada com sucesso para " . strtoupper($nova_prioridade) . "!";

        // Registrar no log
        $log_stmt = $conn->prepare("
            INSERT INTO system_logs (tipo, mensagem, id_usuario) 
            VALUES ('prioridade', ?, ?)
        ");
        $mensagem_log = "Pedido #{$id_pedido} - Prioridade alterada para {$nova_prioridade}";
        $log_stmt->bind_param("si", $mensagem_log, $_SESSION['id_usuario']);
        $log_stmt->execute();
    } else {
        $_SESSION['erro'] = "Erro ao alterar prioridade: " . $conn->error;
    }

    $stmt->close();
} else {
    $_SESSION['erro'] = "Requisição inválida!";
}

header("Location: listar_pedidos.php");
exit;
?>