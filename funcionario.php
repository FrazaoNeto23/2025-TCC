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

    $foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $foto = time() . "_" . basename($_FILES['foto']['name']);
        move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $foto);
    }

    $sql = "INSERT INTO funcionarios (nome, email, senha, foto) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $nome, $email, $senha, $foto);
    $stmt->execute();
    header("Location: funcionario.php");
    exit;
}

// EDITAR FUNCIONÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == "editar") {
    $id = intval($_POST['id']);
    $nome = $_POST['nome'];
    $email = $_POST['email'];

    // Recupera foto atual
    $foto = null;
    $res = $conn->query("SELECT foto FROM funcionarios WHERE id=$id");
    if ($res && $res->num_rows > 0) {
        $foto = $res->fetch_assoc()['foto'];
    }

    // Se enviou nova foto
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $novaFoto = time() . "_" . basename($_FILES['foto']['name']);
        move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $novaFoto);
        if ($foto && file_exists("uploads/" . $foto)) {
            unlink("uploads/" . $foto);
        }
        $foto = $novaFoto;
    }

    if (!empty($_POST['senha'])) {
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $sql = "UPDATE funcionarios SET nome=?, email=?, senha=?, foto=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $nome, $email, $senha, $foto, $id);
    } else {
        $sql = "UPDATE funcionarios SET nome=?, email=?, foto=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $nome, $email, $foto, $id);
    }
    $stmt->execute();
    header("Location: funcionario.php");
    exit;
}

// EXCLUIR FUNCIONÁRIO
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT foto FROM funcionarios WHERE id=$id");
    if ($res && $res->num_rows > 0) {
        $foto = $res->fetch_assoc()['foto'];
        if ($foto && file_exists("uploads/" . $foto)) {
            unlink("uploads/" . $foto);
        }
    }
    $conn->query("DELETE FROM funcionarios WHERE id=$id");
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
    <link rel="stylesheet" href="css/style_funcionario.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <h1><i class="fa fa-users"></i> Gerenciar Funcionários</h1>

    <!-- FORMULÁRIO DE CADASTRO -->
    <form method="POST" enctype="multipart/form-data">
        <h2><i class="fa fa-user-plus"></i> Cadastrar Funcionário</h2>
        <input type="hidden" name="acao" value="cadastrar">
        <input type="text" name="nome" placeholder="Nome completo" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="senha" placeholder="Senha" required>

        <input type="file" name="foto" accept="image/*" onchange="previewImagem(event, 'preview-novo')">
        <img id="preview-novo" class="foto-funcionario" style="display:none;">

        <button type="submit"><i class="fa fa-save"></i> Cadastrar</button>
    </form>

    <h2><i class="fa fa-list"></i> Funcionários Cadastrados</h2>
    <div class="produtos-container">
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="card-produto">
                <div class="card-info">
                    <?php if (!empty($row['foto'])): ?>
                        <img src="uploads/<?= htmlspecialchars($row['foto']) ?>" class="foto-funcionario">
                    <?php else: ?>
                        <img src="assets/img/default.png" class="foto-funcionario">
                    <?php endif; ?>
                    <h3><i class="fa fa-id-card"></i> <?= htmlspecialchars($row['nome']) ?></h3>
                    <p><i class="fa fa-envelope"></i> <?= htmlspecialchars($row['email']) ?></p>
                </div>
                <div class="card-acoes">
                    <button onclick="abrirModal(<?= $row['id'] ?>)"><i class="fa fa-edit"></i> Editar</button>
                    <a href="funcionario.php?delete=<?= $row['id'] ?>" class="delete"
                        onclick="return confirm('Excluir este funcionário?')"><i class="fa fa-trash"></i> Excluir</a>
                </div>
            </div>

            <!-- Modal -->
            <div class="modal" id="modal-<?= $row['id'] ?>">
                <div class="modal-content">
                    <span class="close" onclick="fecharModal(<?= $row['id'] ?>)">&times;</span>
                    <h2><i class="fa fa-pen"></i> Editar Funcionário</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="acao" value="editar">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <input type="text" name="nome" value="<?= htmlspecialchars($row['nome']) ?>" required>
                        <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" required>
                        <input type="password" name="senha" placeholder="Nova senha (opcional)">

                        <input type="file" name="foto" accept="image/*"
                            onchange="previewImagem(event, 'preview-<?= $row['id'] ?>')">
                        <?php if (!empty($row['foto'])): ?>
                            <img id="preview-<?= $row['id'] ?>" src="uploads/<?= htmlspecialchars($row['foto']) ?>"
                                class="foto-funcionario">
                        <?php else: ?>
                            <img id="preview-<?= $row['id'] ?>" class="foto-funcionario" style="display:none;">
                        <?php endif; ?>

                        <button type="submit"><i class="fa fa-save"></i> Salvar Alterações</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <p><a href="painel_dono.php"><i class="fa fa-arrow-left"></i> Voltar ao Painel</a></p>

    <script>
        function abrirModal(id) {
            let modal = document.getElementById("modal-" + id);
            modal.style.display = "flex";
            modal.classList.remove("fade-out");
            modal.classList.add("fade-in");
        }
        function fecharModal(id) {
            let modal = document.getElementById("modal-" + id);
            modal.classList.remove("fade-in");
            modal.classList.add("fade-out");
            setTimeout(() => { modal.style.display = "none"; }, 400);
        }
        function previewImagem(event, idPreview) {
            let preview = document.getElementById(idPreview);
            let file = event.target.files[0];
            if (file) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = "block";
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>

</html>