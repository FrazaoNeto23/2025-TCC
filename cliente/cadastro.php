<?php
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = md5($_POST['senha']);
    $endereco = $_POST['endereco'];
    $telefone = $_POST['telefone'];

    $sql = "INSERT INTO clientes (nome, email, senha, endereco, telefone) 
            VALUES ('$nome','$email','$senha','$endereco','$telefone')";

    if ($conn->query($sql) === TRUE) {
        echo "<p style='color:lime;text-align:center;'>Cadastro realizado! <a href='cliente/login.php'>Fazer login</a></p>";
    } else {
        echo "<p style='color:red;text-align:center;'>Erro: " . $conn->error . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastro Cliente</title>
    <link rel="stylesheet" href="css/cliente.css">
</head>
<body>
    <div class="login-container">
        <h2>Cadastro Cliente</h2>
        <form method="post">
            <label>Nome:</label>
            <input type="text" name="nome" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Senha:</label>
            <input type="password" name="senha" required>

            <label>Endereço:</label>
            <input type="text" name="endereco">

            <label>Telefone:</label>
            <input type="text" name="telefone">

            <button type="submit">Cadastrar</button>
        </form>
        <p>Já tem conta? <a href="login.php">Login</a></p>
    </div>
</body>
</html>
