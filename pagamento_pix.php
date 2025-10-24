<?php
session_start();
include "config_seguro.php";
include "Pagamento.php";

if (!isset($_SESSION['usuario']) || $_SESSION['tipo'] != "cliente") {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['dados_pix']) || !isset($_SESSION['id_transacao'])) {
    header("Location: painel_cliente.php");
    exit;
}

$dados_pix = $_SESSION['dados_pix'];
$id_transacao = $_SESSION['id_transacao'];

// Limpar dados da sessão após capturar
unset($_SESSION['dados_pix']);
unset($_SESSION['id_transacao']);

$pagamento = new Pagamento($conn);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Pagamento PIX - Burger House</title>
    <link rel="stylesheet" href="css/pagamento.css?e=<?php echo rand(0, 10000) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .pix-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #1e1e1e;
            border-radius: 15px;
            border: 2px solid #0ff;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.4);
            text-align: center;
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pix-container h1 {
            color: #0ff;
            margin-bottom: 20px;
            text-shadow: 0 0 15px #0ff;
        }

        .qr-code-box {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            margin: 30px auto;
            display: inline-block;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.3);
        }

        .qr-code-box img {
            max-width: 250px;
            border-radius: 8px;
        }

        .chave-pix {
            background: #121212;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            border: 2px solid #0ff;
            position: relative;
        }

        .chave-pix strong {
            color: #0ff;
            font-size: 16px;
            display: block;
            margin-bottom: 10px;
        }

        .chave-pix code {
            color: #fff;
            font-size: 14px;
            word-break: break-all;
        }

        .btn-copiar {
            margin-top: 10px;
            padding: 10px 20px;
            background: #0ff;
            color: #121212;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-copiar:hover {
            background: #00d4d4;
            box-shadow: 0 0 15px #0ff;
        }

        .instrucoes {
            text-align: left;
            margin: 30px 0;
            color: #ccc;
        }

        .instrucoes ol {
            padding-left: 20px;
        }

        .instrucoes li {
            margin: 10px 0;
            line-height: 1.6;
        }

        .status-pagamento {
            background: #ffa500;
            color: #fff;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }
        }

        .btn-voltar-pedidos {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #00cc55;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            transition: 0.3s;
        }

        .btn-voltar-pedidos:hover {
            background: #009944;
            box-shadow: 0 0 15px #00cc55;
        }
    </style>
</head>

<body>
    <div class="pix-container">
        <h1><i class="fa fa-qrcode"></i> Pagamento via PIX</h1>

        <div class="status-pagamento">
            <i class="fa fa-clock"></i>
            <span>Aguardando pagamento...</span>
        </div>

        <div class="qr-code-box">
            <img src="<?= htmlspecialchars($dados_pix['qr_code']) ?>" alt="QR Code PIX">
        </div>

        <div class="chave-pix">
            <strong><i class="fa fa-key"></i> Chave PIX (Copia e Cola):</strong>
            <code id="chave-pix-texto"><?= htmlspecialchars($dados_pix['chave_pix']) ?></code>
            <button class="btn-copiar" onclick="copiarChave()">
                <i class="fa fa-copy"></i> Copiar Chave
            </button>
        </div>

        <div class="instrucoes">
            <strong style="color:#0ff;font-size:18px;display:block;margin-bottom:15px;">
                <i class="fa fa-info-circle"></i> Como pagar:
            </strong>
            <ol>
                <li>Abra o app do seu banco</li>
                <li>Acesse a área PIX</li>
                <li>Escolha "Pagar com QR Code" ou "PIX Copia e Cola"</li>
                <li>Escaneie o QR Code acima OU copie e cole a chave</li>
                <li>Confirme o pagamento</li>
            </ol>
        </div>

        <p style="color:#aaa;font-size:14px;margin:20px 0;">
            <i class="fa fa-shield-alt"></i> Este é um ambiente de demonstração.
            O pagamento será confirmado automaticamente em instantes.
        </p>

        <a href="painel_cliente.php" class="btn-voltar-pedidos">
            <i class="fa fa-arrow-left"></i> Voltar para Meus Pedidos
        </a>
    </div>

    <script>
        function copiarChave() {
            const chave = document.getElementById('chave-pix-texto').textContent;
            navigator.clipboard.writeText(chave).then(() => {
                const btn = event.target.closest('.btn-copiar');
                const textoOriginal = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-check"></i> Copiado!';
                btn.style.background = '#00cc55';

                setTimeout(() => {
                    btn.innerHTML = textoOriginal;
                    btn.style.background = '#0ff';
                }, 2000);
            });
        }

        // Simular confirmação automática após 5 segundos (apenas demonstração)
        setTimeout(() => {
            fetch('confirmar_pix.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_transacao: <?= $id_transacao ?>
                })
            }).then(response => response.json())
                .then(data => {
                    if (data.sucesso) {
                        document.querySelector('.status-pagamento').innerHTML = `
                          <i class="fa fa-check-circle"></i>
                          <span style="color:#00cc55;">Pagamento confirmado!</span>
                      `;
                        document.querySelector('.status-pagamento').style.background = '#00cc55';

                        setTimeout(() => {
                            window.location.href = 'painel_cliente.php';
                        }, 2000);
                    }
                });
        }, 5000);
    </script>
</body>

</html>