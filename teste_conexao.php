<?php
require_once __DIR__ . '/config/paths.php';
require_once CONFIG_PATH . '/config.php';

echo "<h1>Teste de Conexão</h1>";

if ($conn->ping()) {
    echo "✅ CONEXÃO OK!<br>";
    echo "Versão MySQL: " . $conn->server_info . "<br>";
    echo "Banco: " . DB_NAME . "<br>";
    echo "Host: " . DB_HOST . "<br>";
} else {
    echo "❌ FALHA NA CONEXÃO<br>";
    echo "Erro: " . $conn->connect_error;
}
?>