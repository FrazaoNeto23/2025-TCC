<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
require_once CONFIG_PATH . "/config.php";
// Autoload carrega automaticamente;

header('Content-Type: application/json');

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['codigo']) || !isset($input['valor_pedido'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos']);
    exit;
}

$pagamento = new Pagamento($conn);
$resultado = $pagamento->aplicarCupom(
    $input['codigo'],
    $_SESSION['id_usuario'],
    floatval($input['valor_pedido'])
);

echo json_encode($resultado);
?>