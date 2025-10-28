<?php
// ========================================
// CONFIGURAÇÃO SEGURA DO SISTEMA
// ========================================

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'burger_house');
define('DB_PORT', 3307);

// Configurações de Segurança
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200); // 2 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutos

// Configurações de Upload
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp'
]);

// Configurações de Pagamento
define('PAGAMENTO_AMBIENTE', 'sandbox');

// Sessão segura (ajustada para localhost)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // ⚠️ Corrigido
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Conexão segura ao banco
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die("Erro ao conectar ao banco: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
?>
