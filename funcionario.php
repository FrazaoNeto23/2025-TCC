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

    $sql = "INSERT INTO funcionarios (nome, email, senha) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $nome, $email, $senha);
    $stmt->execute();
    header("Location: funcionario.php");
    exit;
}

// EDITAR FUNCIONÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == "editar") {
    $id = intval($_POST['id']);
    $nome = $_POST['nome'];
    $email = $_POST['email'];

    if (!empty($_POST['senha'])) {
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $sql = "UPDATE funcionarios SET nome=?, email=?, senha=? WHERE id=? AND tipo='funcionario'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nome, $email, $senha, $id);
    } else {
        $sql = "UPDATE funcionarios SET nome=?, email=? WHERE id=? AND tipo='funcionario'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nome, $email, $id);
    }
    $stmt->execute();
    header("Location: funcionario.php");
    exit;
}

// EXCLUIR FUNCIONÁRIO
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM funcionarios");
    header("Location: funcionario.php");
    exit;
}

// LISTAR FUNCIONÁRIOS
$result = $conn->query("SELECT * FROM funcionarios");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Funcionários</title>
    <link rel="stylesheet" href="css/style_funcionario.css?e=<?php echo rand(0,10000)?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <h1><i class="fa fa-users"></i> Gerenciar Funcionários</h1>

    <!-- FORMULÁRIO DE CADASTRO -->
    <form method="POST">
        <h2><i class="fa fa-user-plus"></i> Cadastrar Funcionário</h2>
        <input type="hidden" name="acao" value="cadastrar">
        <input type="text" name="nome" placeholder="Nome completo" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit"><i class="fa fa-save"></i> Cadastrar</button>
    </form>

    <h2><i class="fa fa-list"></i> Funcionários Cadastrados</h2>
    <div class="produtos-container">
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="card-produto">
                <div class="card-info">
                    <h3><i class="fa fa-id-card"></i> <?= htmlspecialchars($row['nome']) ?></h3>
                    <p><i class="fa fa-envelope"></i> <?= htmlspecialchars($row['email']) ?></p>
                </div>
                <div class="card-acoes">
                    <!-- Botão abre modal -->
                    <button onclick="abrirModal(<?= $row['id'] ?>)"><i class="fa fa-edit"></i> Editar</button>
                    <a href="funcionario.php?delete=<?= $row['id'] ?>" class="delete" onclick="return confirm('Excluir este funcionário?')"><i class="fa fa-trash"></i> Excluir</a>
                </div>
            </div>

            <!-- Modal -->
            <div class="modal" id="modal-<?= $row['id'] ?>">
                <div class="modal-content">
                    <span class="close" onclick="fecharModal(<?= $row['id'] ?>)">&times;</span>
                    <h2><i class="fa fa-pen"></i> Editar Funcionário</h2>
                    <form method="POST">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <input type="text" name="nome" value="<?= htmlspecialchars($row['nome']) ?>" required>
                        <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" required>
                        <input type="password" name="senha" placeholder="Nova senha (opcional)">
                        <button type="submit"><i class="fa fa-save"></i> Salvar Alterações</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <p><a href="painel_dono.php"><i class="fa fa-arrow-left"></i> Voltar ao Painel</a></p>

    <!-- Sons -->
    <!-- <audio id="sound-open" src="assets/sounds/popup.wav" preload="auto"></audio> -->
    <!-- <audio id="sound-close" src="assets/sounds/close.wav" preload="auto"></audio> -->

    <script>
        function abrirModal(id) {
            let modal = document.getElementById("modal-" + id);
            modal.style.display = "flex";
            modal.classList.remove("fade-out");
            modal.classList.add("fade-in");
            document.getElementById("sound-open").play();
        }
        function fecharModal(id) {
            let modal = document.getElementById("modal-" + id);
            modal.classList.remove("fade-in");
            modal.classList.add("fade-out");
            document.getElementById("sound-close").play();
            setTimeout(() => { modal.style.display = "none"; }, 400);
        }
    </script>
</body>
</html>
