<?php
session_start();
include "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $senha = md5($_POST['senha']); 

    $sql = "SELECT * FROM usuarios WHERE email='$email' AND senha='$senha'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['usuario'] = $user['nome'];
        $_SESSION['tipo'] = $user['tipo'];

        if ($user['tipo'] == "dono") {
            header("Location: painel.php");
            exit;
        } else {
            echo "<p style='color:red;text-align:center;'>Acesso negado!</p>";
        }
    } else {
        echo "<p style='color:red;text-align:center;'>Usuário ou senha incorretos!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login Dono</title>
    <link rel="stylesheet" href="css/styles.css?e=<?php echo rand(0,10000)?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <h2>Login do Dono</h2>
        <form method="post">
            <label>Email:</label>
            <input type="text" name="email" required>
            
            <label>Senha:</label>
            <input type="password" name="senha" required>
            
            <button type="submit">
                <i class="fa fa-right-to-bracket"></i> Entrar
            </button>
        </form>
    </div>
</body>
</html>
