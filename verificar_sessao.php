<?php
// ========================================
// FUNÇÕES DE VERIFICAÇÃO DE SESSÃO
// ========================================

function verificarSessaoAtiva() {
    if (!isset($_SESSION['usuario']) || !isset($_SESSION['id_usuario'])) {
        header("Location: index.php?erro=sessao_invalida");
        exit;
    }
    
    if (isset($_SESSION['login_time'])) {
        $tempo_sessao = time() - $_SESSION['login_time'];
        
        if ($tempo_sessao > SESSION_LIFETIME) {
            session_destroy();
            header("Location: index.php?erro=sessao_expirada");
            exit;
        }
    } else {
        $_SESSION['login_time'] = time();
    }
    
    $_SESSION['ultimo_acesso'] = time();
}

function verificarTipoUsuario($tipo_requerido) {
    verificarSessaoAtiva();
    
    if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] != $tipo_requerido) {
        switch ($_SESSION['tipo'] ?? '') {
            case 'cliente':
                header("Location: painel_cliente.php");
                break;
            case 'dono':
                header("Location: painel_dono.php");
                break;
            case 'funcionario':
                header("Location: painel_funcionario.php");
                break;
            default:
                header("Location: index.php?erro=tipo_invalido");
                break;
        }
        exit;
    }
}

function verificarCliente() {
    verificarTipoUsuario('cliente');
}

function verificarDono() {
    verificarTipoUsuario('dono');
}

function verificarFuncionario() {
    verificarTipoUsuario('funcionario');
}

function obterUsuarioLogado() {
    verificarSessaoAtiva();
    
    return [
        'id' => $_SESSION['id_usuario'],
        'nome' => $_SESSION['usuario'],
        'tipo' => $_SESSION['tipo']
    ];
}

function estaLogado() {
    return isset($_SESSION['usuario']) && isset($_SESSION['id_usuario']);
}

function encerrarSessao() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    
    session_destroy();
    
    header("Location: index.php?logout=sucesso");
    exit;
}
?>