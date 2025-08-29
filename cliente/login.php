<?php
session_start();
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = md5($_POST['senha']);

    $sql = "SELECT * FROM clientes WHERE email='$email' AND senha='$senha'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $cliente = $result->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nome'] = $cliente['nome'];
        header("Location: painel.php");
        exit;
    } else {
        echo "<p style='color:red;text-align:center;'>Email ou senha incorretos!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login Cliente</title>
    <link rel="stylesheet" href="css/cliente.css">
</head>
<body>
    <div class="login-container">
        <h2>Login Cliente</h2>
        <form method="post">
            <label>Email:</label>
            <input type="email" name="email" required>
            
            <label>Senha:</label>
            <input type="password" name="senha" required>
            
            <button type="submit">Entrar</button>
        </form>
        <p>Não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
    </div>
</body>
</html>
