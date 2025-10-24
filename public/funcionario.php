<?php
require_once __DIR__ . '/../config/paths.php';
session_start();
require_once CONFIG_PATH . "/config.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] !== 'dono') {
    header("Location: index.php");
    exit;
}

// CADASTRAR FUNCIONÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == "cadastrar") {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = "Todos os campos são obrigatórios!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido!";
    } elseif (strlen($senha) < 6) {
        $erro = "Senha deve ter no mínimo 6 caracteres!";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM funcionarios WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();

        if ($stmt_check->get_result()->num_rows > 0) {
            $erro = "Email já cadastrado!";
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $foto = null;

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                if (in_array($_FILES['foto']['type'], $allowed)) {
                    $foto = time() . "_" . basename($_FILES['foto']['name']);
                    move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $foto);
                }
            }

            $stmt = $conn->prepare("INSERT INTO funcionarios (nome, email, senha, foto) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $email, $senha_hash, $foto);

            if ($stmt->execute()) {
                header("Location: funcionario.php?success=1");
                exit;
            } else {
                $erro = "Erro ao cadastrar funcionário!";
            }
        }
    }
}

// EDITAR FUNCIONÁRIO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['acao']) && $_POST['acao'] == "editar") {
    $id = intval($_POST['id']);
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);

    if (empty($nome) || empty($email)) {
        $erro = "Nome e email são obrigatórios!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido!";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM funcionarios WHERE email = ? AND id != ?");
        $stmt_check->bind_param("si", $email, $id);
        $stmt_check->execute();

        if ($stmt_check->get_result()->num_rows > 0) {
            $erro = "Email já cadastrado para outro funcionário!";
        } else {
            $stmt_foto = $conn->prepare("SELECT foto FROM funcionarios WHERE id = ?");
            $stmt_foto->bind_param("i", $id);
            $stmt_foto->execute();
            $result = $stmt_foto->get_result();

            $foto = null;
            if ($result->num_rows > 0) {
                $foto = $result->fetch_assoc()['foto'];
            }

            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
                if (in_array($_FILES['foto']['type'], $allowed)) {
                    $novaFoto = time() . "_" . basename($_FILES['foto']['name']);
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], "uploads/" . $novaFoto)) {
                        if ($foto && file_exists("uploads/" . $foto)) {
                            unlink("uploads/" . $foto);
                        }
                        $foto = $novaFoto;
                    }
                }
            }

            if (!empty($_POST['senha'])) {
                if (strlen($_POST['senha']) < 6) {
                    $erro = "Nova senha deve ter no mínimo 6 caracteres!";
                } else {
                    $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE funcionarios SET nome = ?, email = ?, senha = ?, foto = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $nome, $email, $senha_hash, $foto, $id);
                }
            } else {
                $stmt = $conn->prepare("UPDATE funcionarios SET nome = ?, email = ?, foto = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nome, $email, $foto, $id);
            }

            if (isset($stmt) && !isset($erro)) {
                if ($stmt->execute()) {
                    header("Location: funcionario.php?updated=1");
                    exit;
                } else {
                    $erro = "Erro ao atualizar funcionário!";
                }
            }
        }
    }
}

// EXCLUIR FUNCIONÁRIO
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("SELECT foto FROM funcionarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $foto = $result->fetch_assoc()['foto'];
        if ($foto && file_exists("uploads/" . $foto)) {
            unlink("uploads/" . $foto);
        }
    }

    $stmt_delete = $conn->prepare("DELETE FROM funcionarios WHERE id = ?");
    $stmt_delete->bind_param("i", $id);
    $stmt_delete->execute();

    header("Location: funcionario.php?deleted=1");
    exit;
}

$result = $conn->query("SELECT * FROM funcionarios ORDER BY nome ASC");
$funcionarios = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funcionarios[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Funcionários</title>
    <link rel="stylesheet" href="css/style_funcionario.css?e=<?php
require_once __DIR__ . '/../config/paths.php'; echo time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.92);
            z-index: 999999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(8px);
        }

        .modal.show {
            display: flex !important;
        }

        .modal-content {
            position: relative;
            z-index: 1000000;
            background: #1c1c1c;
            padding: 30px;
            border-radius: 15px;
            width: min(95vw, 450px);
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 0 50px rgba(0, 255, 255, 0.8);
            border: 3px solid #0ff;
        }

        .modal-content * {
            position: relative;
            z-index: 1000001;
        }

        .close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 32px;
            cursor: pointer;
            color: #ff4c4c;
            font-weight: bold;
            z-index: 1000002;
            background: rgba(0, 0, 0, 0.8);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close:hover {
            background: rgba(255, 76, 76, 0.3);
            transform: rotate(90deg) scale(1.3);
        }

        body.modal-open {
            overflow: hidden;
        }
    </style>
</head>

<body>
    <div class="header-page">
        <a href="painel_dono.php" class="btn-voltar-top"><i class="fa fa-arrow-left"></i> Voltar ao Painel</a>
        <h1><i class="fa fa-users"></i> Gerenciar Funcionários</h1>
        <div class="spacer"></div>
    </div>

    <?php
require_once __DIR__ . '/../config/paths.php'; if (isset($_GET['success'])): ?>
        <div
            style="background: #00cc55; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            ✅ Funcionário cadastrado com sucesso!
        </div>
    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

    <?php
require_once __DIR__ . '/../config/paths.php'; if (isset($_GET['updated'])): ?>
        <div
            style="background: #0ff; color: #121212; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            ✅ Funcionário atualizado com sucesso!
        </div>
    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

    <?php
require_once __DIR__ . '/../config/paths.php'; if (isset($_GET['deleted'])): ?>
        <div
            style="background: #ff6b6b; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            ✅ Funcionário excluído!
        </div>
    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

    <?php
require_once __DIR__ . '/../config/paths.php'; if (isset($erro)): ?>
        <div
            style="background: #ff4c4c; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            ❌ <?= htmlspecialchars($erro) ?>
        </div>
    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <h2><i class="fa fa-user-plus"></i> Cadastrar Funcionário</h2>
        <input type="hidden" name="acao" value="cadastrar">
        <input type="text" name="nome" placeholder="Nome completo" required maxlength="100">
        <input type="email" name="email" placeholder="Email" required maxlength="100">
        <input type="password" name="senha" placeholder="Senha (mín. 6 caracteres)" required minlength="6">
        <input type="file" name="foto" accept="image/*" onchange="previewImagem(event, 'preview-novo')">
        <img id="preview-novo" class="foto-funcionario" style="display:none;">
        <button type="submit"><i class="fa fa-save"></i> Cadastrar</button>
    </form>

    <h2><i class="fa fa-list"></i> Funcionários Cadastrados</h2>
    <div class="produtos-container">
        <?php
require_once __DIR__ . '/../config/paths.php'; if (!empty($funcionarios)): ?>
            <?php
require_once __DIR__ . '/../config/paths.php'; foreach ($funcionarios as $row): ?>
                <div class="card-produto">
                    <div class="card-info">
                        <?php
require_once __DIR__ . '/../config/paths.php'; if (!empty($row['foto']) && file_exists("uploads/" . $row['foto'])): ?>
                            <img src="uploads/<?= htmlspecialchars($row['foto']) ?>" class="foto-funcionario">
                        <?php
require_once __DIR__ . '/../config/paths.php'; else: ?>
                            <div
                                style="width: 100px; height: 100px; margin: 15px auto; background: #121212; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="fa fa-user" style="font-size: 48px; color: #555;"></i>
                            </div>
                        <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>
                        <h3><i class="fa fa-id-card"></i> <?= htmlspecialchars($row['nome']) ?></h3>
                        <p><i class="fa fa-envelope"></i> <?= htmlspecialchars($row['email']) ?></p>
                    </div>
                    <div class="card-acoes">
                        <button onclick="abrirModal(<?= $row['id'] ?>)"><i class="fa fa-edit"></i> Editar</button>
                        <a href="funcionario.php?delete=<?= $row['id'] ?>" class="delete"
                            onclick="return confirm('Excluir este funcionário?')"><i class="fa fa-trash"></i> Excluir</a>
                    </div>
                </div>
            <?php
require_once __DIR__ . '/../config/paths.php'; endforeach; ?>
        <?php
require_once __DIR__ . '/../config/paths.php'; else: ?>
            <div style="text-align: center; padding: 60px; background: #1e1e1e; border-radius: 12px; grid-column: 1 / -1;">
                <i class="fa fa-inbox" style="font-size: 60px; color: #555; margin-bottom: 20px;"></i>
                <p style="color: #aaa; font-size: 18px;">Nenhum funcionário cadastrado</p>
            </div>
        <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>
    </div>

    <?php
require_once __DIR__ . '/../config/paths.php'; foreach ($funcionarios as $row): ?>
        <div class="modal" id="modal-<?= $row['id'] ?>">
            <div class="modal-content">
                <span class="close" onclick="fecharModal(<?= $row['id'] ?>)">&times;</span>
                <h2><i class="fa fa-pen"></i> Editar Funcionário</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="editar">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <input type="text" name="nome" value="<?= htmlspecialchars($row['nome']) ?>" required maxlength="100">
                    <input type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" required
                        maxlength="100">
                    <input type="password" name="senha" placeholder="Nova senha (deixe em branco para manter)"
                        minlength="6">
                    <input type="file" name="foto" accept="image/*"
                        onchange="previewImagem(event, 'preview-<?= $row['id'] ?>')">

                    <?php
require_once __DIR__ . '/../config/paths.php'; if (!empty($row['foto']) && file_exists("uploads/" . $row['foto'])): ?>
                        <img id="preview-<?= $row['id'] ?>" src="uploads/<?= htmlspecialchars($row['foto']) ?>"
                            class="foto-funcionario">
                    <?php
require_once __DIR__ . '/../config/paths.php'; else: ?>
                        <img id="preview-<?= $row['id'] ?>" class="foto-funcionario" style="display:none;">
                    <?php
require_once __DIR__ . '/../config/paths.php'; endif; ?>

                    <button type="submit"><i class="fa fa-save"></i> Salvar Alterações</button>
                </form>
            </div>
        </div>
    <?php
require_once __DIR__ . '/../config/paths.php'; endforeach; ?>

    <script>
        function abrirModal(id) {
            document.querySelectorAll('.modal').forEach(m => m.classList.remove('show'));

            const modal = document.getElementById("modal-" + id);
            modal.classList.add("show");
            document.body.classList.add("modal-open");

            console.log("Modal aberto:", id);
        }

        function fecharModal(id) {
            const modal = document.getElementById("modal-" + id);
            modal.classList.remove("show");
            document.body.classList.remove("modal-open");

            console.log("Modal fechado:", id);
        }

        function previewImagem(event, idPreview) {
            const preview = document.getElementById(idPreview);
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = "block";
                }
                reader.readAsDataURL(file);
            }
        }

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('modal')) {
                const modalId = e.target.id.replace('modal-', '');
                fecharModal(modalId);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    const modalId = modal.id.replace('modal-', '');
                    fecharModal(modalId);
                });
            }
        });

        console.log("Modais encontrados:", document.querySelectorAll('.modal').length);
    </script>
</body>

</html>