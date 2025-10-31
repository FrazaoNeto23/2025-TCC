<?php
session_start();

echo "<h1>TESTE DE FORMULÁRIO</h1>";
echo "<pre>";
echo "POST recebido: ";
print_r($_POST);
echo "\n\nSESSION: ";
print_r($_SESSION);
echo "</pre>";

if (isset($_POST['teste'])) {
    echo "<h2 style='color:green;'>✅ FORMULÁRIO FUNCIONOU!</h2>";
    $_SESSION['msg'] = "Redirecionamento OK!";
    header("Location: painel_cliente.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Teste</title>
</head>

<body style="padding:50px;background:#121212;color:#fff;">
    <h1>Teste de Formulário</h1>
    <form method="POST">
        <button type="submit" name="teste" value="1" style="padding:20px;font-size:20px;">
            CLIQUE AQUI PARA TESTAR
        </button>
    </form>
</body>

</html>