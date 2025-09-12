<?php
session_start();
include "config.php";

$msg = "";

// --- Login ---
if(isset($_POST['acao']) && $_POST['acao'] == "login"){
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if($res && password_verify($senha, $res['senha'])){
        $_SESSION['usuario'] = $res['nome'];
        $_SESSION['id_usuario'] = $res['id'];
        $_SESSION['tipo'] = $res['tipo'];

        if($res['tipo'] == "dono"){
            header("Location: painel_dono.php");
        } else {
            header("Location: painel_cliente.php");
        }
        exit;
    } else {
        $msg = "Email ou senha incorretos!";
    }
}

// --- Cadastro ---
if(isset($_POST['acao']) && $_POST['acao'] == "cadastro"){
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $tipo = $_POST['tipo'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if($res->num_rows > 0){
        $msg = "Email já cadastrado!";
    } else {
        $stmt = $conn->prepare("INSERT INTO usuarios (nome,email,senha,tipo) VALUES (?,?,?,?)");
        $stmt->bind_param("ssss", $nome,$email,$senha,$tipo);
        $stmt->execute();
        $msg = "Cadastro realizado com sucesso! Faça login.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Acesso</title>
<link rel="stylesheet" href="css/acesso.css?e=<?php echo rand(0,10000)?>">
</head>
<body>
<div class="container">
    <h1>Sistema de Acesso</h1>
    <?php if($msg) echo "<p class='msg'>$msg</p>"; ?>

    <div class="forms">
        <!-- LOGIN -->
        <form method="POST" class="form-login active">
            <h2>Login</h2>
            <input type="hidden" name="acao" value="login">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="senha" placeholder="Senha" required>
            <button type="submit">Entrar</button>
            <p class="switch" onclick="toggleForms()">Não tem conta? Cadastre-se</p>
        </form>

        <!-- CADASTRO -->
        <form method="POST" class="form-cadastro">
            <h2>Cadastro</h2>
            <input type="hidden" name="acao" value="cadastro">
            <input type="text" name="nome" placeholder="Nome" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="senha" placeholder="Senha" required>
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
        // anima o login saindo para a esquerda
        loginForm.classList.remove('active');
        loginForm.classList.add('slide-out-left');

        // anima o cadastro entrando
        cadastroForm.classList.remove('slide-out-right');
        cadastroForm.classList.add('active');
    } else {
        // anima o cadastro saindo para a direita
        cadastroForm.classList.remove('active');
        cadastroForm.classList.add('slide-out-right');

        // anima o login entrando
        loginForm.classList.remove('slide-out-left');
        loginForm.classList.add('active');
    }
}
</script>
</body>
</html>
