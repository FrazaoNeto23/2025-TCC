<?php
// api/api_notificacoes.php

// Verifica se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui o arquivo de notificações com caminho correto
require_once __DIR__ . '/../includes/notificacoes.php';

// Verifica se a requisição é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica se existe a classe Notificacoes
    if (!class_exists('Notificacoes')) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Classe Notificacoes não encontrada'
        ]);
        exit;
    }
    
    $notificacoes = new Notificacoes();
    
    // Aqui vai o resto da lógica da API
    // ...
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
}
?>