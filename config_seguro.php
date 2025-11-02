<?php
// ========================================
// CONFIGURAÇÃO SEGURA DO SISTEMA
// ========================================

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'burger_house');
define('DB_PORT', 3306);

// Configurações de Segurança
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200); // 2 horas
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutos

// Configurações de Upload
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
]);

// Iniciar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Mudar para 1 em produção com HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Regenerar ID de sessão periodicamente
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Conectar ao banco com tratamento de erro
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        error_log("Erro de conexão: " . $conn->connect_error);
        die("Erro ao conectar ao banco de dados. Tente novamente mais tarde.");
    }

    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    error_log("Exceção na conexão: " . $e->getMessage());
    die("Erro ao conectar ao banco de dados. Tente novamente mais tarde.");
}

// ========================================
// FUNÇÕES DE SEGURANÇA
// ========================================

function gerarTokenCSRF()
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validarTokenCSRF($token)
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function limparEntrada($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validarEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function logSeguranca($tipo, $mensagem, $dados = [])
{
    global $conn;

    // Verificar se tabela existe
    $table_check = $conn->query("SHOW TABLES LIKE 'system_logs'");
    if ($table_check && $table_check->num_rows == 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tipo VARCHAR(50),
                nivel VARCHAR(20) DEFAULT 'info',
                status VARCHAR(20),
                mensagem TEXT,
                dados JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tipo (tipo),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO system_logs (tipo, nivel, status, mensagem, dados, created_at) 
            VALUES (?, 'SECURITY', 'info', ?, ?, NOW())
        ");

        $dados['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $dados['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $dados_json = json_encode($dados);

        $stmt->bind_param("sss", $tipo, $mensagem, $dados_json);
        $stmt->execute();
        $stmt->close();

    } catch (Exception $e) {
        error_log("Erro ao registrar log de segurança: " . $e->getMessage());
    }
}

function verificarRateLimit($identificador, $max_tentativas = MAX_LOGIN_ATTEMPTS, $tempo = LOGIN_TIMEOUT)
{
    $key = 'rate_limit_' . $identificador;

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'tentativas' => 0,
            'primeiro_acesso' => time()
        ];
    }

    $dados = $_SESSION[$key];

    if (time() - $dados['primeiro_acesso'] > $tempo) {
        $_SESSION[$key] = [
            'tentativas' => 1,
            'primeiro_acesso' => time()
        ];
        return true;
    }

    if ($dados['tentativas'] >= $max_tentativas) {
        $tempo_restante = $tempo - (time() - $dados['primeiro_acesso']);
        return [
            'bloqueado' => true,
            'tempo_restante' => ceil($tempo_restante / 60)
        ];
    }

    $_SESSION[$key]['tentativas']++;
    return true;
}

function limparRateLimit($identificador)
{
    $key = 'rate_limit_' . $identificador;
    unset($_SESSION[$key]);
}

function validarUpload($arquivo)
{
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'erro' => 'Erro ao fazer upload do arquivo'];
    }

    if ($arquivo['size'] > MAX_FILE_SIZE) {
        return ['sucesso' => false, 'erro' => 'Arquivo muito grande. Máximo: 5MB'];
    }

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, ALLOWED_EXTENSIONS)) {
        return ['sucesso' => false, 'erro' => 'Tipo de arquivo não permitido'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_MIME_TYPES)) {
        return ['sucesso' => false, 'erro' => 'Tipo MIME inválido'];
    }

    $nome_novo = uniqid() . '_' . time() . '.' . $extensao;

    return [
        'sucesso' => true,
        'nome_arquivo' => $nome_novo,
        'caminho' => UPLOAD_DIR . $nome_novo
    ];
}
?>