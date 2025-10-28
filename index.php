<?php
require_once __DIR__ . '/config/paths.php';
session_start();
require_once CONFIG_PATH . '/config.php';

if (isset($_SESSION['usuario']) && isset($_SESSION['tipo'])) {
    switch ($_SESSION['tipo']) {
        case 'dono':
            header("Location: public/pedidos.php");
            exit;
        case 'cliente':
            header("Location: public/painel_cliente.php");
            exit;
        case 'funcionario':
            header("Location: public/painel_funcionario.php");
            exit;
    }
}

// Processar login
$erro = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        if (empty($email) || empty($senha)) {
            $erro = "Preencha todos os campos!";
        } else {
            // Login do dono
            if ($email === "dono@burgerhouse.com" && $senha === "dono123") {
                $_SESSION['usuario'] = "Dono";
                $_SESSION['id_usuario'] = 1;
                $_SESSION['tipo'] = "dono";
                $_SESSION['usuario_id'] = 1;
                $_SESSION['usuario_tipo'] = "dono";
                header("Location: public/painel_dono.php");
                exit;
            }

            // Login de cliente
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email=? AND tipo='cliente'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($senha, $user['senha'])) {
                    $_SESSION['usuario'] = $user['nome'];
                    $_SESSION['id_usuario'] = $user['id'];
                    $_SESSION['tipo'] = "cliente";
                    header("Location: public/painel_cliente.php");
                    exit;
                } else {
                    $erro = "Senha incorreta!";
                }
            } else {
                $erro = "Usuário não encontrado!";
            }
        }
    } elseif (isset($_POST['cadastro'])) {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];

        if (empty($nome) || empty($email) || empty($senha)) {
            $erro = "Preencha todos os campos!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Email inválido!";
        } elseif (strlen($senha) < 6) {
            $erro = "A senha deve ter no mínimo 6 caracteres!";
        } else {
            // Verificar se email já existe
            $check = $conn->prepare("SELECT id FROM usuarios WHERE email=?");
            $check->bind_param("s", $email);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                $erro = "Email já cadastrado!";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'cliente')");
                $stmt->bind_param("sss", $nome, $email, $senha_hash);

                if ($stmt->execute()) {
                    $_SESSION['usuario'] = $nome;
                    $_SESSION['id_usuario'] = $stmt->insert_id;
                    $_SESSION['tipo'] = "cliente";
                    header("Location: public/painel_cliente.php");
                    exit;
                } else {
                    $erro = "Erro ao cadastrar. Tente novamente.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Burger House - Login</title>
    <link rel="stylesheet" href="assets/css/acesso.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <div class="logo">
            <i class="fa fa-burger"></i>
            <h1>Burger House</h1>
        </div>

        <?php if ($erro): ?>
            <div class="msg erro">
                <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <div class="forms">
            <!-- Formulário de Login -->
            <form method="POST" class="active" id="loginForm">
                <h2><i class="fa fa-sign-in-alt"></i> Entrar</h2>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit" name="login"><i class="fa fa-arrow-right"></i> Entrar</button>
                <p class="switch">
                    Não tem conta? <a href="#" onclick="toggleForm(); return false;">Cadastre-se</a>
                </p>
                <p class="switch">
                    <a href="public/painel_funcionario.php" style="color:#0ff;">
                        <i class="fa fa-user-tie"></i> Acesso de Funcionário
                    </a>
                </p>
            </form>

            <!-- Formulário de Cadastro -->
            <form method="POST" id="cadastroForm">
                <h2><i class="fa fa-user-plus"></i> Cadastrar</h2>
                <input type="text" name="nome" placeholder="Nome completo" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha (mín. 6 caracteres)" required minlength="6">
                <button type="submit" name="cadastro"><i class="fa fa-check"></i> Cadastrar</button>
                <p class="switch">
                    Já tem conta? <a href="#" onclick="toggleForm(); return false;">Entrar</a>
                </p>
            </form>
        </div>

        <div class="demo-info">
            <h3><i class="fa fa-info-circle"></i> Acesso de Demonstração</h3>
            <p><strong>Dono:</strong> dono@burgerhouse.com | senha: dono123</p>
        </div>
    </div>

    <script>
        function toggleForm() {
            const loginForm = document.getElementById('loginForm');
            const cadastroForm = document.getElementById('cadastroForm');

            loginForm.classList.toggle('active');
            cadastroForm.classList.toggle('active');
        }
    </script>
</body>

</html>
