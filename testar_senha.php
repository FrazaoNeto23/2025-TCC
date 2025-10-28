<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Teste de Senha MySQL</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #333;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        button {
            width: 100%;
            padding: 15px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #45a049;
        }

        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        .suggestions {
            margin-top: 20px;
        }

        .suggestions h3 {
            color: #555;
            font-size: 16px;
        }

        .suggestions ul {
            list-style: none;
            padding: 0;
        }

        .suggestions li {
            padding: 8px;
            background: #f9f9f9;
            margin: 5px 0;
            border-radius: 5px;
            cursor: pointer;
        }

        .suggestions li:hover {
            background: #e9e9e9;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔐 Teste de Senha MySQL</h1>

        <div class="info">
            <strong>ℹ️ Seu MySQL exige senha!</strong><br>
            Digite possíveis senhas abaixo para testar.
        </div>

        <form method="POST" action="">
            <div class="form-group">
                <label for="senha">Digite a senha do MySQL:</label>
                <input type="password" id="senha" name="senha" placeholder="Digite a senha..." required>
            </div>

            <div class="form-group">
                <label for="porta">Porta MySQL:</label>
                <input type="text" id="porta" name="porta" value="3306" required>
            </div>

            <button type="submit" name="testar">🔍 Testar Conexão</button>
        </form>

        <div class="suggestions">
            <h3>💡 Senhas Comuns (clique para testar):</h3>
            <ul>
                <li onclick="testarSenha('')">Sem senha (vazio)</li>
                <li onclick="testarSenha('root')">root</li>
                <li onclick="testarSenha('mysql')">mysql</li>
                <li onclick="testarSenha('admin')">admin</li>
                <li onclick="testarSenha('password')">password</li>
                <li onclick="testarSenha('123456')">123456</li>
                <li onclick="testarSenha('xampp')">xampp</li>
            </ul>
        </div>

        <?php
        if (isset($_POST['testar'])) {
            $senha = $_POST['senha'];
            $porta = intval($_POST['porta']);
            $host = 'localhost';
            $user = 'root';

            echo "<div style='margin-top: 20px;'>";
            echo "<h3>🔍 Testando conexão...</h3>";
            echo "<p><strong>Host:</strong> $host</p>";
            echo "<p><strong>Usuário:</strong> $user</p>";
            echo "<p><strong>Senha:</strong> " . (empty($senha) ? '(vazia)' : str_repeat('*', strlen($senha))) . "</p>";
            echo "<p><strong>Porta:</strong> $porta</p>";

            try {
                $conn = new mysqli($host, $user, $senha, '', $porta);

                if ($conn->connect_error) {
                    echo "<div class='error'>";
                    echo "<strong>❌ Falha na conexão!</strong><br>";
                    echo "Erro: " . $conn->connect_error;
                    echo "</div>";
                } else {
                    echo "<div class='success'>";
                    echo "<strong>✅ CONEXÃO BEM-SUCEDIDA!</strong><br><br>";
                    echo "🎉 A senha correta é: <code>" . ($senha ?: '(sem senha)') . "</code><br><br>";

                    // Gerar config.php
                    $senha_escapada = addslashes($senha);
                    $config_code = "define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '$senha_escapada');
define('DB_NAME', 'burger_house');
define('DB_PORT', $porta);";

                    echo "<strong>📝 Atualize seu config/config.php com:</strong><br>";
                    echo "<textarea readonly style='width:100%; height:150px; margin-top:10px; padding:10px; font-family:monospace;'>$config_code</textarea>";

                    // Verificar banco
                    $result = $conn->query("SHOW DATABASES LIKE 'burger_house'");
                    if ($result->num_rows > 0) {
                        echo "<br><br>✅ Banco de dados 'burger_house' já existe!";
                    } else {
                        echo "<br><br>⚠️ Banco 'burger_house' não existe. Criando...";
                        if ($conn->query("CREATE DATABASE burger_house")) {
                            echo "<br>✅ Banco criado com sucesso!";
                        }
                    }

                    echo "</div>";
                    $conn->close();
                }
            } catch (Exception $e) {
                echo "<div class='error'>";
                echo "<strong>❌ Erro:</strong> " . $e->getMessage();
                echo "</div>";
            }

            echo "</div>";
        }
        ?>

        <div class="info" style="margin-top: 30px;">
            <h3>🔍 Como descobrir a senha?</h3>
            <ol>
                <li>Tente as senhas comuns acima primeiro</li>
                <li>Verifique se você anotou a senha em algum lugar</li>
                <li>Ou resete a senha (próxima opção)</li>
            </ol>
        </div>
    </div>

    <script>
        function testarSenha(senha) {
            document.getElementById('senha').value = senha;
            document.querySelector('form').submit();
        }
    </script>
</body>

</html>