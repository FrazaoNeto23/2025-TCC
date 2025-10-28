<?php
/**
 * TESTE DE CONEXÃO COM BANCO DE DADOS
 * Execute este arquivo para diagnosticar o problema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Diagnóstico de Conexão MySQL</h1>";
echo "<style>
    body { font-family: Arial; padding: 20px; background: #f5f5f5; }
    .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
    code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
</style>";

// Teste 1: Verificar se a extensão mysqli está habilitada
echo "<h2>Teste 1: Extensão MySQLi</h2>";
if (extension_loaded('mysqli')) {
    echo "<div class='success'>✅ Extensão MySQLi está habilitada</div>";
} else {
    echo "<div class='error'>❌ Extensão MySQLi NÃO está habilitada<br>";
    echo "Solução: Ative no php.ini removendo o ; de: <code>extension=mysqli</code></div>";
    exit;
}

// Teste 2: Tentar conectar em diferentes portas
echo "<h2>Teste 2: Testando Portas MySQL</h2>";

$portas = [3306, 3307, 3308];
$host = 'localhost';
$user = 'root';
$pass = '';
$db_name = 'burger_house';

$porta_correta = null;
$conexao_sucesso = null;

foreach ($portas as $porta) {
    echo "<div class='info'>🔌 Tentando conectar em <code>$host:$porta</code>...</div>";

    $conn = @new mysqli($host, $user, $pass, '', $porta);

    if ($conn->connect_error) {
        echo "<div class='error'>❌ Porta $porta: " . $conn->connect_error . "</div>";
    } else {
        echo "<div class='success'>✅ Porta $porta: Conexão bem-sucedida!</div>";
        $porta_correta = $porta;
        $conexao_sucesso = $conn;
        break;
    }
}

if (!$conexao_sucesso) {
    echo "<div class='error'><h3>❌ ERRO CRÍTICO</h3>";
    echo "Não foi possível conectar ao MySQL em nenhuma porta.<br><br>";
    echo "<strong>Soluções:</strong><br>";
    echo "1. Verifique se o MySQL/XAMPP está rodando<br>";
    echo "2. Abra o XAMPP Control Panel e inicie o MySQL<br>";
    echo "3. Verifique se a senha do root não foi alterada<br>";
    echo "4. Tente reiniciar o MySQL no XAMPP</div>";
    exit;
}

// Teste 3: Verificar se o banco de dados existe
echo "<h2>Teste 3: Banco de Dados</h2>";

$result = $conexao_sucesso->query("SHOW DATABASES LIKE '$db_name'");

if ($result->num_rows > 0) {
    echo "<div class='success'>✅ Banco de dados <code>$db_name</code> existe</div>";
} else {
    echo "<div class='warning'>⚠️ Banco de dados <code>$db_name</code> NÃO existe<br>";
    echo "Criando banco de dados...</div>";

    if ($conexao_sucesso->query("CREATE DATABASE $db_name")) {
        echo "<div class='success'>✅ Banco de dados criado com sucesso!</div>";
    } else {
        echo "<div class='error'>❌ Erro ao criar banco de dados: " . $conexao_sucesso->error . "</div>";
        exit;
    }
}

// Selecionar o banco
$conexao_sucesso->select_db($db_name);

// Teste 4: Verificar tabelas
echo "<h2>Teste 4: Tabelas do Sistema</h2>";

$tabelas_necessarias = ['usuarios', 'produtos', 'produtos_especiais', 'pedidos', 'carrinho'];
$tabelas_faltando = [];

foreach ($tabelas_necessarias as $tabela) {
    $result = $conexao_sucesso->query("SHOW TABLES LIKE '$tabela'");

    if ($result->num_rows > 0) {
        echo "<div class='success'>✅ Tabela <code>$tabela</code> existe</div>";
    } else {
        echo "<div class='warning'>⚠️ Tabela <code>$tabela</code> NÃO existe</div>";
        $tabelas_faltando[] = $tabela;
    }
}

// Teste 5: Criar configuração correta
echo "<h2>Teste 5: Arquivo de Configuração</h2>";

$config_content = "<?php
// ========================================
// CONFIGURAÇÃO SEGURA DO SISTEMA
// ========================================

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'burger_house');
define('DB_PORT', $porta_correta); // ← PORTA CORRETA DETECTADA

// Configurações de Segurança
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900);

// Configurações de Upload
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880);
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
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Conectar ao banco com tratamento de erro
try {
    \$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if (\$conn->connect_error) {
        error_log(\"Erro de conexão: \" . \$conn->connect_error);
        die(\"Erro ao conectar ao banco de dados. Tente novamente mais tarde.\");
    }
    
    \$conn->set_charset(\"utf8mb4\");
    
} catch (Exception \$e) {
    error_log(\"Exceção na conexão: \" . \$e->getMessage());
    die(\"Erro ao conectar ao banco de dados. Tente novamente mais tarde.\");
}
?>";

echo "<div class='info'>📝 Configuração correta gerada:<br><br>";
echo "<strong>Porta detectada:</strong> <code>$porta_correta</code><br><br>";
echo "Copie o código abaixo e substitua em <code>config/config.php</code>:<br>";
echo "<textarea style='width:100%; height:300px; font-family:monospace; padding:10px;'>$config_content</textarea>";
echo "</div>";

// Teste 6: SQL para criar tabelas se faltarem
if (!empty($tabelas_faltando)) {
    echo "<h2>Teste 6: SQL para Criar Tabelas Faltando</h2>";

    $sql_file = "-- ========================================
-- CRIAR TABELAS DO BURGER HOUSE
-- Execute este SQL no phpMyAdmin
-- ========================================

USE burger_house;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('cliente', 'dono', 'funcionario') DEFAULT 'cliente',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir usuário dono padrão
INSERT IGNORE INTO usuarios (nome, email, senha, tipo) VALUES 
('Administrador', 'dono@burgerhouse.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dono');
-- Senha: dono123

-- Tabela de produtos
CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    descricao TEXT,
    imagem VARCHAR(255),
    categoria VARCHAR(50),
    ativo BOOLEAN DEFAULT TRUE,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de produtos especiais
CREATE TABLE IF NOT EXISTS produtos_especiais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    preco DECIMAL(10,2) NOT NULL,
    descricao TEXT,
    imagem VARCHAR(255),
    ativo BOOLEAN DEFAULT TRUE,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de carrinho
CREATE TABLE IF NOT EXISTS carrinho (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT DEFAULT 1,
    tipo_produto ENUM('normal', 'especial') DEFAULT 'normal',
    data_adicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_pedido VARCHAR(20),
    id_cliente INT NOT NULL,
    numero_mesa INT,
    id_produto INT NOT NULL,
    tipo_produto ENUM('normal', 'especial') DEFAULT 'normal',
    quantidade INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('Pendente', 'Em preparo', 'Entregando', 'Entregue', 'Cancelado') DEFAULT 'Pendente',
    status_pagamento ENUM('Aguardando', 'Pago') DEFAULT 'Aguardando',
    metodo_pagamento VARCHAR(50),
    observacoes TEXT,
    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_numero_pedido (numero_pedido),
    INDEX idx_status (status),
    INDEX idx_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de funcionários
CREATE TABLE IF NOT EXISTS funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    cargo VARCHAR(50),
    foto VARCHAR(255),
    ativo BOOLEAN DEFAULT TRUE,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de logs do sistema
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50),
    nivel VARCHAR(20) DEFAULT 'INFO',
    status VARCHAR(20),
    mensagem TEXT,
    dados JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_data (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

    echo "<div class='warning'>⚠️ Faltam tabelas no banco de dados<br><br>";
    echo "<strong>Soluções:</strong><br>";
    echo "1. Copie o SQL abaixo<br>";
    echo "2. Abra o phpMyAdmin (http://localhost/phpmyadmin)<br>";
    echo "3. Selecione o banco 'burger_house'<br>";
    echo "4. Cole e execute o SQL<br><br>";
    echo "<textarea style='width:100%; height:400px; font-family:monospace; padding:10px;'>$sql_file</textarea>";
    echo "</div>";
}

// Resumo Final
echo "<h2>📋 Resumo</h2>";
echo "<div class='success'>";
echo "<strong>Porta MySQL correta:</strong> <code>$porta_correta</code><br>";
echo "<strong>Próximos passos:</strong><br>";
echo "1. Atualize o arquivo <code>config/config.php</code> com a porta correta<br>";
if (!empty($tabelas_faltando)) {
    echo "2. Execute o SQL no phpMyAdmin para criar as tabelas<br>";
    echo "3. Acesse <code>http://localhost/2025-TCC/</code> novamente<br>";
} else {
    echo "2. Todas as tabelas estão criadas! ✅<br>";
    echo "3. Acesse <code>http://localhost/2025-TCC/</code> e faça login!<br>";
}
echo "</div>";

$conexao_sucesso->close();
?>