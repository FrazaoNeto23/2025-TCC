<?php
// /config/config.php - ARQUIVO ÚNICO DE CONFIGURAÇÃO

// Configurações do Banco de Dados
define('DB_HOST', 'localhost:3307');  // ✅ PORTA INCLUÍDA NO HOST
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'burger_house');

// Configurações de Segurança
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900);

// Configurações de Upload
define('UPLOAD_DIR', UPLOADS_PATH . '/');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
]);

// Configurações de Pagamento
define('PAGAMENTO_AMBIENTE', 'sandbox');

// Sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Conexão com banco - SIMPLIFICADA
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log("Erro de conexão: " . $conn->connect_error);
        die("❌ Erro ao conectar ao banco de dados: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    echo "<!-- ✅ Conectado ao MySQL -->";

} catch (Exception $e) {
    error_log("Exceção MySQL: " . $e->getMessage());
    die("❌ Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>