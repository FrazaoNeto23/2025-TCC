<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
require_once CONFIG_PATH . "/config.php";
include "notificacoes.php";

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Não autorizado']);
    exit;
}

$notif = new Notificacoes($conn);

// Inicializar tabelas se necessário
$notif->inicializar();

$acao = $_GET['acao'] ?? 'buscar';

switch ($acao) {
    case 'buscar':
        $notificacoes = $notif->buscarNaoLidas(
            $_SESSION['id_usuario'] ?? null,
            $_SESSION['tipo'] ?? null
        );
        echo json_encode([
            'sucesso' => true,
            'notificacoes' => $notificacoes,
            'total' => count($notificacoes)
        ]);
        break;

    case 'contar':
        $total = $notif->contarNaoLidas(
            $_SESSION['id_usuario'] ?? null,
            $_SESSION['tipo'] ?? null
        );
        echo json_encode([
            'sucesso' => true,
            'total' => $total
        ]);
        break;

    case 'marcar_lida':
        $id = intval($_POST['id'] ?? 0);
        $resultado = $notif->marcarComoLida($id);
        echo json_encode([
            'sucesso' => $resultado
        ]);
        break;

    default:
        echo json_encode(['sucesso' => false, 'erro' => 'Ação inválida']);
}