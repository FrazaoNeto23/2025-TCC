<?php
session_start();
require_once "config.php"; // ✅ Corrigido caminho

// Redirecionamento se já logado
if (isset($_SESSION['usuario'])) {
    switch ($_SESSION['tipo']) {
        case 'dono': header("Location: painel_dono.php"); exit;
        case 'cliente': header("Location: painel_cliente.php"); exit;
        case 'funcionario': header("Location: painel_funcionario.php"); exit;
    }
}



if (isset($_SESSION['usuario'])) {
    switch ($_SESSION['tipo']) {
        case 'dono':
            header("Location: painel_dono.php");  // ← REMOVIDO public/
            exit;
        case 'cliente':
            header("Location: painel_cliente.php");  // ← REMOVIDO public/
            exit;
        case 'funcionario':
            header("Location: painel_funcionario.php");  // ← REMOVIDO public/
            exit;
    }
}

// Processa Login de Cliente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email=? AND tipo='cliente'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && password_verify($senha, $res['senha'])) {
        $_SESSION['usuario'] = $res['nome'];
        $_SESSION['id_usuario'] = $res['id'];
        $_SESSION['tipo'] = 'cliente';
        header("Location: painel_cliente.php");  // ← REMOVIDO public/
        exit;
    } else {
        $erro_login = "Email ou senha incorretos!";
    }
}

// Processa Cadastro de Cliente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastro'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE email=?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        $erro_cadastro = "Email já cadastrado!";
    } else {
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'cliente')");
        $stmt->bind_param("sss", $nome, $email, $senha);
        
        if ($stmt->execute()) {
            $_SESSION['usuario'] = $nome;
            $_SESSION['id_usuario'] = $stmt->insert_id;
            $_SESSION['tipo'] = 'cliente';
            header("Location: painel_cliente.php");  // ← REMOVIDO public/
            exit;
        }
    }
}

// Processa Login do Dono
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_dono'])) {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email=? AND tipo='dono'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && password_verify($senha, $res['senha'])) {
        $_SESSION['usuario'] = $res['nome'];
        $_SESSION['usuario_id'] = $res['id'];
        $_SESSION['usuario_tipo'] = 'dono';
        $_SESSION['tipo'] = 'dono';
        header("Location: painel_dono.php");  // ← REMOVIDO public/
        exit;
    } else {
        $erro_dono = "Email ou senha incorretos!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Burger House - Sistema de Pedidos</title>
    <link rel="stylesheet" href="css/acesso.css?e=<?php echo time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1><i class="fa fa-burger"></i> Burger House</h1>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="mostrarTab('cliente')">Cliente</button>
            <button class="tab-btn" onclick="mostrarTab('dono')">Dono</button>
        </div>

        <!-- Tab Cliente -->
        <div id="tab-cliente" class="tab-content active">
            <div class="form-toggle">
                <button class="toggle-btn active" onclick="mostrarForm('login')">Login</button>
                <button class="toggle-btn" onclick="mostrarForm('cadastro')">Cadastro</button>
            </div>

            <!-- Login Cliente -->
            <form method="POST" id="form-login" class="form active">
                <h2>Login</h2>
                <?php if (isset($erro_login)) echo "<p class='msg'><i class='fa fa-exclamation-circle'></i> $erro_login</p>"; ?>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit" name="login"><i class="fa fa-sign-in-alt"></i> Entrar</button>
            </form>

            <!-- Cadastro Cliente -->
            <form method="POST" id="form-cadastro" class="form">
                <h2>Cadastro</h2>
                <?php if (isset($erro_cadastro)) echo "<p class='msg'><i class='fa fa-exclamation-circle'></i> $erro_cadastro</p>"; ?>
                <input type="text" name="nome" placeholder="Nome completo" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="senha" placeholder="Senha (mín. 6 caracteres)" required minlength="6">
                <button type="submit" name="cadastro"><i class="fa fa-user-plus"></i> Cadastrar</button>
            </form>
        </div>

        <!-- Tab Dono -->
        <div id="tab-dono" class="tab-content">
            <form method="POST" class="form active">
                <h2>Acesso do Dono</h2>
                <?php if (isset($erro_dono)) echo "<p class='msg'><i class='fa fa-exclamation-circle'></i> $erro_dono</p>"; ?>
                <input type="email" name="email" placeholder="Email do dono" required>
                <input type="password" name="senha" placeholder="Senha" required>
                <button type="submit" name="login_dono"><i class="fa fa-crown"></i> Entrar como Dono</button>
            </form>
        </div>

        <p class="link-func">
            <a href="painel_funcionario.php"><i class="fa fa-user-tie"></i> Acesso de Funcionário →</a>
        </p>
    </div>

    <script>
        function mostrarTab(tipo) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById('tab-' + tipo).classList.add('active');
            event.target.classList.add('active');
        }

        function mostrarForm(tipo) {
            document.querySelectorAll('.form').forEach(f => f.classList.remove('active'));
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            
            document.getElementById('form-' + tipo).classList.add('active');
            event.target.classList.add('active');
        }

        // Mostrar erro específico
        <?php if (isset($erro_login)): ?>
            mostrarTab('cliente');
            mostrarForm('login');
        <?php elseif (isset($erro_cadastro)): ?>
            mostrarTab('cliente');
            mostrarForm('cadastro');
        <?php elseif (isset($erro_dono)): ?>
            mostrarTab('dono');
        <?php endif; ?>
    </script>
</body>
</html>