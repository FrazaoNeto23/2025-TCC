<?php
session_start();
include "config_seguro.php";
include "Pagamento.php";

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_transacao'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID de transação não fornecido']);
    exit;
}

$pagamento = new Pagamento($conn);
$resultado = $pagamento->confirmarPagamento($input['id_transacao'], 'PIX-AUTO-' . time());

if ($resultado['sucesso']) {
    logSeguranca('pix_confirmado', 'Pagamento PIX confirmado automaticamente', [
        'id_transacao' => $input['id_transacao']
    ]);
}

echo json_encode($resultado);
?>