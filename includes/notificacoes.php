<?php
// includes/notificacoes.php

class Notificacoes
{

    private $conn;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
    }

    /**
     * Buscar todas as notificações não lidas
     */
    public function buscarNaoLidas($usuario_id = null)
    {
        $sql = "SELECT * FROM notificacoes WHERE lida = 0";

        if ($usuario_id) {
            $sql .= " AND usuario_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->conn->query($sql);
        }

        $notificacoes = [];
        while ($row = $result->fetch_assoc()) {
            $notificacoes[] = $row;
        }

        return $notificacoes;
    }

    /**
     * Marcar notificação como lida
     */
    public function marcarComoLida($notificacao_id)
    {
        $sql = "UPDATE notificacoes SET lida = 1 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $notificacao_id);
        return $stmt->execute();
    }

    /**
     * Criar nova notificação
     */
    public function criar($dados)
    {
        $sql = "INSERT INTO notificacoes (usuario_id, tipo, mensagem, data_criacao) 
                VALUES (?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param(
            "iss",
            $dados['usuario_id'],
            $dados['tipo'],
            $dados['mensagem']
        );

        return $stmt->execute();
    }

    /**
     * Contar notificações não lidas
     */
    public function contarNaoLidas($usuario_id = null)
    {
        $sql = "SELECT COUNT(*) as total FROM notificacoes WHERE lida = 0";

        if ($usuario_id) {
            $sql .= " AND usuario_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->conn->query($sql);
        }

        $row = $result->fetch_assoc();
        return $row['total'];
    }
}
?>