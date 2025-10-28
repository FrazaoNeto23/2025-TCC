<?php
/**
 * LOGOUT.PHP - Sistema de Logout Completo
 * Burger House Management System
 * 
 * Este arquivo garante logout correto de todos os tipos de usuário
 */

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Armazenar tipo de usuário antes de destruir sessão (para debug)
$tipo_usuario = isset($_SESSION['tipo']) ? $_SESSION['tipo'] : 'desconhecido';

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Se houver cookie de sessão, deletar
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir a sessão completamente
session_destroy();

// Limpar qualquer output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Prevenir cache do navegador
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Redirecionar para página de login
header("Location: index.php");
exit();
?>