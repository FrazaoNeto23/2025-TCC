<?php
include "config_seguro.php";

// Se já estiver logado, redirecionar
if (isset($_SESSION['usuario']) && isset($_SESSION['tipo'])) {
    switch ($_SESSION['tipo']) {
        case 'cliente':
            header("Location: painel_cliente.php");
            break;
        case 'dono':
            header("Location: painel_dono.php");
            break;
        case 'funcionario':
            header("Location: painel_funcionario.php");
            break;
    }
    exit;
}

$msg = "";
$msg_tipo = "erro";

// Mensagens de erro da URL
if (isset($_GET['erro'])) {
    switch ($_GET['erro']) {
        case 'sessao_invalida':
            $msg = "Sua sessão é inválida. Faça login novamente.";
            break;
        case 'sessao_expirada':
            $msg = "Sua sessão expirou. Faça login novamente.";
            break;
        case 'tipo_invalido':
            $msg = "Você não tem permissão para acessar essa área.";
            break;
    }
}

if (isset($_GET['logout'])) {
    $msg = "Logout realizado com sucesso!";
    $msg_tipo = "sucesso";
}

// --- Login ---
if (isset($_POST['acao']) && $_POST['acao'] == "login") {
    if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
        $msg = "Token de segurança inválido!";
        logSeguranca('csrf_invalido', 'Tentativa de login com token inválido', ['post' => $_POST]);
    } else {
        $email = limparEntrada($_POST['email']);
        $senha = $_POST['senha'];

        if (!validarEmail($email)) {
            $msg = "Email inválido!";
        } else {
            $rate_check = verificarRateLimit($email);

            if (is_array($rate_check) && isset($rate_check['bloqueado'])) {
                $msg = "Muitas tentativas. Tente novamente em " . $rate_check['tempo_restante'] . " minutos.";
                $msg_tipo = "erro";

                logSeguranca('rate_limit', 'Login bloqueado por rate limit', [
                    'email' => $email,
                    'tempo_restante' => $rate_check['tempo_restante']
                ]);
            } else {
                $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email=?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();

                if ($res && password_verify($senha, $res['senha'])) {
                    limparRateLimit($email);
                    session_regenerate_id(true);

                    $_SESSION['usuario'] = $res['nome'];
                    $_SESSION['id_usuario'] = $res['id'];
                    $_SESSION['tipo'] = $res['tipo'];
                    $_SESSION['login_time'] = time();

                    logSeguranca('login_sucesso', 'Login realizado', [
                        'usuario_id' => $res['id'],
                        'tipo' => $res['tipo']
                    ]);

                    if ($res['tipo'] == "dono") {
                        header("Location: painel_dono.php");
                    } else {
                        header("Location: painel_cliente.php");
                    }
                    exit;
                } else {
                    $msg = "Email ou senha incorretos!";
                    $msg_tipo = "erro";

                    logSeguranca('login_falhou', 'Tentativa de login falhou', [
                        'email' => $email
                    ]);
                }
            }
        }
    }
}

// --- Cadastro ---
if (isset($_POST['acao']) && $_POST['acao'] == "cadastro") {
    if (!isset($_POST['csrf_token']) || !validarTokenCSRF($_POST['csrf_token'])) {
        $msg = "Token de segurança inválido!";
        logSeguranca('csrf_invalido', 'Tentativa de cadastro com token inválido');
    } else {
        $nome = limparEntrada($_POST['nome']);
        $email = limparEntrada($_POST['email']);
        $senha = $_POST['senha'];
        $tipo = $_POST['tipo'];

        if (empty($nome) || empty($email) || empty($senha)) {
            $msg = "Todos os campos são obrigatórios!";
        } elseif (!validarEmail($email)) {
            $msg = "Email inválido!";
        } elseif (strlen($senha) < 6) {
            $msg = "Senha deve ter no mínimo 6 caracteres!";
        } elseif (!in_array($tipo, ['cliente', 'dono'])) {
            $msg = "Tipo de usuário inválido!";
        } else {
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $msg = "Email já cadastrado!";
                $msg_tipo = "erro";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nome, $email, $senha_hash, $tipo);

                if ($stmt->execute()) {
                    $msg = "Cadastro realizado com sucesso! Faça login.";
                    $msg_tipo = "sucesso";

                    logSeguranca('cadastro_sucesso', 'Novo usuário cadastrado', [
                        'email' => $email,
                        'tipo' => $tipo
                    ]);
                } else {
                    $msg = "Erro ao cadastrar. Tente novamente.";
                }
            }
        }
    }
}

$csrf_token = gerarTokenCSRF();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Acesso - Burger House</title>
    <link rel="stylesheet" href="css/acesso.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
    <div class="container">
        <h1>Sistema de Acesso</h1>
        <?php if ($msg): ?>
            <p class='msg <?= $msg_tipo == "sucesso" ? "msg-sucesso" : "" ?>'><?= $msg ?></p>
        <?php endif; ?>

        <div class="forms">
            <!-- LOGIN -->
            <form method="POST" class="form-login active">
                <h2>Login</h2>
                <input type="hidden" name="acao" value="login">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha" required minlength="6">
                <button type="submit">Entrar</button>
                <p class="switch" onclick="toggleForms()">Não tem conta? Cadastre-se</p>
            </form>

            <!-- CADASTRO -->
            <form method="POST" class="form-cadastro">
                <h2>Cadastro</h2>
                <input type="hidden" name="acao" value="cadastro">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="text" name="nome" placeholder="Nome completo" required maxlength="100">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha (mín. 6 caracteres)" required minlength="6">
                <select name="tipo" required>
                    <option value="">Selecione o tipo</option>
                    <option value="cliente">Cliente</option>
                    <option value="dono">Dono</option>
                </select>
                <button type="submit">Cadastrar</button>
                <p class="switch" onclick="toggleForms()">Já tem conta? Faça login</p>
            </form>
        </div>
    </div>

    <script>
        function toggleForms() {
            const loginForm = document.querySelector('.form-login');
            const cadastroForm = document.querySelector('.form-cadastro');

            if (loginForm.classList.contains('active')) {
                loginForm.classList.remove('active');
                loginForm.classList.add('slide-out-left');
                cadastroForm.classList.remove('slide-out-right');
                cadastroForm.classList.add('active');
            } else {
                cadastroForm.classList.remove('active');
                cadastroForm.classList.add('slide-out-right');
                loginForm.classList.remove('slide-out-left');
                loginForm.classList.add('active');
            }
        }
    </script>
</body>

</html>