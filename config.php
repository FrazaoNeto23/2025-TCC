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

// Configurações de Pagamento
define('MERCADOPAGO_PUBLIC_KEY', ''); // Adicionar chave real
define('MERCADOPAGO_ACCESS_TOKEN', ''); // Adicionar token real
define('PAGAMENTO_AMBIENTE', 'sandbox'); // sandbox ou production

// Configurações de Email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', ''); // Adicionar email
define('SMTP_PASS', ''); // Adicionar senha
define('EMAIL_FROM', 'noreply@burgerhouse.com');
define('EMAIL_FROM_NAME', 'Burger House');

// Iniciar sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Apenas HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Regenerar ID de sessão periodicamente
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
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

// Função para gerar token CSRF
function gerarTokenCSRF() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Função para validar token CSRF
function validarTokenCSRF($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Função para limpar entrada
function limparEntrada($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Função para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para log de segurança
function logSeguranca($tipo, $mensagem, $dados = []) {
    global $conn;
    
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
        
    } catch (Exception $e) {
        error_log("Erro ao registrar log de segurança: " . $e->getMessage());
    }
}

// Rate Limiting - Controle de tentativas de login
function verificarRateLimit($identificador, $max_tentativas = MAX_LOGIN_ATTEMPTS, $tempo = LOGIN_TIMEOUT) {
    $key = 'rate_limit_' . $identificador;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'tentativas' => 0,
            'primeiro_acesso' => time()
        ];
    }
    
    $dados = $_SESSION[$key];
    
    // Reset se passou o tempo
    if (time() - $dados['primeiro_acesso'] > $tempo) {
        $_SESSION[$key] = [
            'tentativas' => 1,
            'primeiro_acesso' => time()
        ];
        return true;
    }
    
    // Verificar se excedeu
    if ($dados['tentativas'] >= $max_tentativas) {
        $tempo_restante = $tempo - (time() - $dados['primeiro_acesso']);
        return [
            'bloqueado' => true,
            'tempo_restante' => ceil($tempo_restante / 60)
        ];
    }
    
    // Incrementar tentativa
    $_SESSION[$key]['tentativas']++;
    return true;
}

// Limpar rate limit após sucesso
function limparRateLimit($identificador) {
    $key = 'rate_limit_' . $identificador;
    unset($_SESSION[$key]);
}

// Validar upload de arquivo
function validarUpload($arquivo) {
    // Verificar erro de upload
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'erro' => 'Erro ao fazer upload do arquivo'];
    }
    
    // Verificar tamanho
    if ($arquivo['size'] > MAX_FILE_SIZE) {
        return ['sucesso' => false, 'erro' => 'Arquivo muito grande. Máximo: 5MB'];
    }
    
    // Verificar extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, ALLOWED_EXTENSIONS)) {
        return ['sucesso' => false, 'erro' => 'Tipo de arquivo não permitido'];
    }
    
    // Verificar MIME type real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime, ALLOWED_MIME_TYPES)) {
        return ['sucesso' => false, 'erro' => 'Tipo MIME inválido'];
    }
    
    // Gerar nome único
    $nome_novo = uniqid() . '_' . time() . '.' . $extensao;
    
    return [
        'sucesso' => true,
        'nome_arquivo' => $nome_novo,
        'caminho' => UPLOAD_DIR . $nome_novo
    ];
}

// Handler de erros global
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[$errno] $errstr em $errfile:$errline");
    
    // Não mostrar detalhes ao usuário em produção
    if (PAGAMENTO_AMBIENTE === 'production') {
        return true;
    }
    
    return false;
});

// Handler de exceções
set_exception_handler(function($exception) {
    error_log("Exceção não tratada: " . $exception->getMessage());
    error_log("Trace: " . $exception->getTraceAsString());
    
    if (PAGAMENTO_AMBIENTE === 'production') {
        die("Ocorreu um erro. Por favor, tente novamente mais tarde.");
    } else {
        die("Erro: " . $exception->getMessage());
    }
});