<?php
session_start();
include "config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] !== 'dono') {
    header("Location: index.php");
    exit;
}

// CADASTRAR FUNCIONÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == "cadastrar") {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, 'funcionario')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $nome, $email, $senha);
    $stmt->execute();
    header("Location: funcionarios.php");
    exit;
}

// EDITAR FUNCIONÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == "editar") {
    $id = intval($_POST['id']);
    $nome = $_POST['nome'];
    $email = $_POST['email'];

    if (!empty($_POST['senha'])) {
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $sql = "UPDATE usuarios SET nome=?, email=?, senha=? WHERE id=? AND tipo='funcionario'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nome, $email, $senha, $id);
    } else {
        $sql = "UPDATE usuarios SET nome=?, email=? WHERE id=? AND tipo='funcionario'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nome, $email, $id);
    }
    $stmt->execute();
    header("Location: funcionarios.php");
    exit;
}

// EXCLUIR FUNCIONÁRIO
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM usuarios WHERE id=$id AND tipo='funcionario'");
    header("Location: funcionarios.php");
    exit;
}

// LISTAR FUNCIONÁRIOS
$result = $conn->query("SELECT * FROM usuarios WHERE tipo='funcionario'");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Funcionários</title>
    <link rel="stylesheet" href="css/styles.css?e=<?php echo rand(0,10000)?>">
</head>
<body>
    <h1>Gerenciar Funcionários</h1>

    <!-- FORMULÁRIO DE CADASTRO -->
    <form method="POST">
        <h2>Cadastrar Funcionário</h2>
        <input type="hidden" name="acao" value="cadastrar">
        <input type="text" name="nome" placeholder="Nome completo" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit">Cadastrar</button>
    </form>

    <h2>Funcionários Cadastrados</h2>
    <div class="produtos-container">
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="card-produto">
                <div class="card-info">
                    <h3><?= htmlspecialchars($row['nome']) ?></h3>
                    <p><?= htmlspecialchars($row['email']) ?></p>
                </div>
                <div class="card-acoes">
                    <!-- Botão editar abre formulário embutido -->
                    <button onclick="document.getElementById('edit-<?= $row['id'] ?>').style.display='block'">Editar</button>
                    <a href="funcionarios.php?delete=<?= $row['id'] ?>" class="delete" onclick="return confirm('Excluir este funcionário?')">Excluir</a>
                </div>
                <!-- Formulário de edição escondido -->
                <form method="POST" id="edit-<?= $row['id'] ?>" style="display:none; margin-top:10px;">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <input type="text" name="nome" value="<?= htmlspecialchars($row['nome']) ?>" required>
                    <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" required>
                    <input type="password" name="senha" placeholder="Nova senha (opcional)">
                    <button type="submit">Salvar Alterações</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>

    <p><a href="painel.php">⬅ Voltar ao Painel</a></p>
</body>
</html>
