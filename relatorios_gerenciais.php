<?php
// ========================================
// SISTEMA COMPLETO DE RELATÓRIOS GERENCIAIS  
// FASE 2 - ITENS 26-34
// ========================================

class RelatoriosGerenciais {
    private $conn;
    
    public function __construct($conexao) {
        $this->conn = $conexao;
    }
    
    /**
     * ITEM 27: Gráfico de faturamento por período
     */
    public function faturamentoPorPeriodo($data_inicio, $data_fim, $agrupamento = 'dia') {
        $format_sql = [
            'dia' => "DATE(p.data_pedido)",
            'semana' => "YEARWEEK(p.data_pedido, 1)",
            'mes' => "DATE_FORMAT(p.data_pedido, '%Y-%m')",
            'ano' => "YEAR(p.data_pedido)"
        ];
        
        $format_php = [
            'dia' => 'Y-m-d',
            'semana' => 'Y-\WW',
            'mes' => 'Y-m',
            'ano' => 'Y'
        ];
        
        if (!isset($format_sql[$agrupamento])) {
            $agrupamento = 'dia';
        }
        
        $sql = "SELECT 
                    {$format_sql[$agrupamento]} as periodo,
                    COUNT(p.id) as total_pedidos,
                    SUM(p.valor_total) as faturamento_bruto,
                    SUM(p.valor_total - COALESCE(p.desconto, 0)) as faturamento_liquido,
                    AVG(p.valor_total) as ticket_medio,
                    SUM(CASE WHEN p.status = 'Cancelado' THEN 1 ELSE 0 END) as cancelamentos,
                    SUM(CASE WHEN p.status = 'Entregue' THEN p.valor_total ELSE 0 END) as faturamento_confirmado
                FROM pedidos p
                WHERE p.data_pedido BETWEEN ? AND ?
                GROUP BY {$format_sql[$agrupamento]}
                ORDER BY periodo";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $data_inicio, $data_fim);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $dados = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Formatar dados para gráfico
        foreach ($dados as &$item) {
            $item['periodo_formatado'] = $this->formatarPeriodo($item['periodo'], $agrupamento);
            $item['faturamento_bruto'] = floatval($item['faturamento_bruto']);
            $item['faturamento_liquido'] = floatval($item['faturamento_liquido']);
            $item['ticket_medio'] = floatval($item['ticket_medio']);
            $item['taxa_cancelamento'] = $item['total_pedidos'] > 0 ? 
                round(($item['cancelamentos'] / $item['total_pedidos']) * 100, 2) : 0;
        }
        
        return $dados;
    }
    
    /**
     * ITEM 28: Relatório de produtos mais vendidos
     */
    public function produtosMaisVendidos($data_inicio, $data_fim, $limite = 20) {
        $sql = "SELECT 
                    pr.id,
                    pr.nome,
                    pr.categoria,
                    pr.preco,
                    SUM(ip.quantidade) as total_vendido,
                    SUM(ip.quantidade * ip.preco_unitario) as receita_total,
                    COUNT(DISTINCT ip.id_pedido) as pedidos_distintos,
                    AVG(ip.preco_unitario) as preco_medio,
                    (SUM(ip.quantidade * ip.preco_unitario) / 
                     (SELECT SUM(valor_total) FROM pedidos WHERE data_pedido BETWEEN ? AND ? AND status != 'Cancelado')
                    ) * 100 as participacao_receita
                FROM produtos pr
                JOIN itens_pedido ip ON pr.id = ip.id_produto
                JOIN pedidos p ON ip.id_pedido = p.id
                WHERE p.data_pedido BETWEEN ? AND ?
                  AND p.status != 'Cancelado'
                GROUP BY pr.id, pr.nome, pr.categoria, pr.preco
                ORDER BY total_vendido DESC
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssssi", $data_inicio, $data_fim, $data_inicio, $data_fim, $limite);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $produtos = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Calcular posição no ranking
        foreach ($produtos as $index => &$produto) {
            $produto['posicao'] = $index + 1;
            $produto['receita_total'] = floatval($produto['receita_total']);
            $produto['preco_medio'] = floatval($produto['preco_medio']);
            $produto['participacao_receita'] = floatval($produto['participacao_receita']);
        }
        
        return $produtos;
    }
    
    /**
     * ITEM 29: Análise de horários de pico
     */
    public function horariosPico($data_inicio, $data_fim) {
        $sql = "SELECT 
                    HOUR(p.data_pedido) as hora,
                    COUNT(p.id) as total_pedidos,
                    SUM(p.valor_total) as faturamento,
                    AVG(p.valor_total) as ticket_medio,
                    AVG(CASE WHEN p.tempo_real_preparo IS NOT NULL 
                             THEN p.tempo_real_preparo 
                             ELSE p.tempo_estimado END) as tempo_medio_preparo
                FROM pedidos p
                WHERE p.data_pedido BETWEEN ? AND ?
                  AND p.status != 'Cancelado'
                GROUP BY HOUR(p.data_pedido)
                ORDER BY hora";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $data_inicio, $data_fim);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $horarios = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Identificar picos
        $media_pedidos = array_sum(array_column($horarios, 'total_pedidos')) / count($horarios);
        
        foreach ($horarios as &$horario) {
            $horario['hora_formatada'] = sprintf('%02d:00', $horario['hora']);
            $horario['eh_pico'] = $horario['total_pedidos'] > ($media_pedidos * 1.5);
            $horario['faturamento'] = floatval($horario['faturamento']);
            $horario['ticket_medio'] = floatval($horario['ticket_medio']);
            $horario['tempo_medio_preparo'] = floatval($horario['tempo_medio_preparo']);
            
            // Classificar período
            if ($horario['hora'] >= 6 && $horario['hora'] < 12) {
                $horario['periodo'] = 'Manhã';
            } elseif ($horario['hora'] >= 12 && $horario['hora'] < 18) {
                $horario['periodo'] = 'Tarde';
            } elseif ($horario['hora'] >= 18 && $horario['hora'] < 24) {
                $horario['periodo'] = 'Noite';
            } else {
                $horario['periodo'] = 'Madrugada';
            }
        }
        
        return $horarios;
    }
    
    /**
     * ITEM 30: Taxa de conversão (visitantes → pedidos)
     */
    public function taxaConversao($data_inicio, $data_fim) {
        // Primeiro, criar tabela de visitantes se não existir
        $this->criarTabelaVisitantes();
        
        $sql = "SELECT 
                    DATE(data) as data,
                    SUM(visitantes_unicos) as total_visitantes,
                    (SELECT COUNT(*) FROM pedidos 
                     WHERE DATE(data_pedido) = DATE(v.data) 
                     AND status != 'Cancelado') as total_pedidos,
                    (SELECT COUNT(DISTINCT id_cliente) FROM pedidos 
                     WHERE DATE(data_pedido) = DATE(v.data) 
                     AND status != 'Cancelado') as clientes_convertidos
                FROM visitantes_diarios v
                WHERE v.data BETWEEN ? AND ?
                GROUP BY DATE(data)
                ORDER BY data";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $data_inicio, $data_fim);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $conversoes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($conversoes as &$conversao) {
            $conversao['taxa_conversao_pedidos'] = $conversao['total_visitantes'] > 0 ? 
                round(($conversao['total_pedidos'] / $conversao['total_visitantes']) * 100, 2) : 0;
            
            $conversao['taxa_conversao_clientes'] = $conversao['total_visitantes'] > 0 ? 
                round(($conversao['clientes_convertidos'] / $conversao['total_visitantes']) * 100, 2) : 0;
        }
        
        return $conversoes;
    }
    
    /**
     * ITEM 31: Cálculo de ticket médio
     */
    public function ticketMedio($data_inicio, $data_fim, $agrupamento = 'total') {
        switch ($agrupamento) {
            case 'cliente':
                $sql = "SELECT 
                            c.id,
                            c.nome,
                            COUNT(p.id) as total_pedidos,
                            SUM(p.valor_total) as valor_total_gasto,
                            AVG(p.valor_total) as ticket_medio,
                            MIN(p.data_pedido) as primeira_compra,
                            MAX(p.data_pedido) as ultima_compra
                        FROM clientes c
                        JOIN pedidos p ON c.id = p.id_cliente
                        WHERE p.data_pedido BETWEEN ? AND ?
                          AND p.status != 'Cancelado'
                        GROUP BY c.id, c.nome
                        ORDER BY ticket_medio DESC";
                break;
                
            case 'produto':
                $sql = "SELECT 
                            pr.categoria,
                            COUNT(DISTINCT p.id) as pedidos_com_produto,
                            SUM(ip.quantidade * ip.preco_unitario) as receita_categoria,
                            AVG(ip.quantidade * ip.preco_unitario) as ticket_medio_categoria
                        FROM produtos pr
                        JOIN itens_pedido ip ON pr.id = ip.id_produto
                        JOIN pedidos p ON ip.id_pedido = p.id
                        WHERE p.data_pedido BETWEEN ? AND ?
                          AND p.status != 'Cancelado'
                        GROUP BY pr.categoria
                        ORDER BY ticket_medio_categoria DESC";
                break;
                
            case 'periodo':
                $sql = "SELECT 
                            DATE(p.data_pedido) as data,
                            COUNT(p.id) as total_pedidos,
                            SUM(p.valor_total) as faturamento_dia,
                            AVG(p.valor_total) as ticket_medio_dia,
                            MIN(p.valor_total) as menor_pedido,
                            MAX(p.valor_total) as maior_pedido
                        FROM pedidos p
                        WHERE p.data_pedido BETWEEN ? AND ?
                          AND p.status != 'Cancelado'
                        GROUP BY DATE(p.data_pedido)
                        ORDER BY data";
                break;
                
            default: // total
                $sql = "SELECT 
                            COUNT(p.id) as total_pedidos,
                            SUM(p.valor_total) as faturamento_total,
                            AVG(p.valor_total) as ticket_medio_geral,
                            STDDEV(p.valor_total) as desvio_padrao,
                            MIN(p.valor_total) as menor_pedido,
                            MAX(p.valor_total) as maior_pedido,
                            PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY p.valor_total) as mediana
                        FROM pedidos p
                        WHERE p.data_pedido BETWEEN ? AND ?
                          AND p.status != 'Cancelado'";
                break;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $data_inicio, $data_fim);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $dados = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Formatar valores monetários
        foreach ($dados as &$item) {
            foreach ($item as $key => &$value) {
                if (strpos($key, 'ticket') !== false || 
                    strpos($key, 'valor') !== false || 
                    strpos($key, 'faturamento') !== false ||
                    strpos($key, 'receita') !== false ||
                    strpos($key, 'pedido') !== false && is_numeric($value)) {
                    $value = floatval($value);
                }
            }
        }
        
        return $dados;
    }
    
    /**
     * ITEM 32: Relatório de desempenho por funcionário
     */
    public function desempenhoFuncionarios($data_inicio, $data_fim) {
        $sql = "SELECT 
                    f.id,
                    f.nome,
                    f.cargo,
                    COUNT(DISTINCT CASE WHEN ph.status_novo = 'Confirmado' THEN ph.id_pedido END) as pedidos_confirmados,
                    COUNT(DISTINCT CASE WHEN ph.status_novo = 'Preparando' THEN ph.id_pedido END) as pedidos_iniciados,
                    COUNT(DISTINCT CASE WHEN ph.status_novo = 'Pronto' THEN ph.id_pedido END) as pedidos_finalizados,
                    COUNT(DISTINCT CASE WHEN ph.status_novo = 'Entregue' THEN ph.id_pedido END) as pedidos_entregues,
                    AVG(CASE WHEN p.tempo_real_preparo IS NOT NULL 
                             THEN p.tempo_real_preparo END) as tempo_medio_preparo,
                    COUNT(DISTINCT ph.id_pedido) as total_interacoes,
                    SUM(CASE WHEN ph.status_novo = 'Entregue' THEN p.valor_total ELSE 0 END) as faturamento_gerado
                FROM funcionarios f
                LEFT JOIN pedidos_historico ph ON f.id = ph.alterado_por AND ph.tipo_usuario = 'funcionario'
                LEFT JOIN pedidos p ON ph.id_pedido = p.id
                WHERE ph.data_alteracao BETWEEN ? AND ?
                GROUP BY f.id, f.nome, f.cargo
                ORDER BY pedidos_entregues DESC, faturamento_gerado DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $data_inicio, $data_fim);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $funcionarios = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($funcionarios as &$funcionario) {
            $funcionario['eficiencia'] = $funcionario['pedidos_iniciados'] > 0 ? 
                round(($funcionario['pedidos_finalizados'] / $funcionario['pedidos_iniciados']) * 100, 2) : 0;
            
            $funcionario['faturamento_gerado'] = floatval($funcionario['faturamento_gerado']);
            $funcionario['tempo_medio_preparo'] = floatval($funcionario['tempo_medio_preparo']);
        }
        
        return $funcionarios;
    }
    
    /**
     * ITEM 33: Exportação de relatórios em Excel/PDF
     */
    public function exportarRelatorio($tipo_relatorio, $formato, $parametros) {
        // Buscar dados do relatório
        switch ($tipo_relatorio) {
            case 'faturamento':
                $dados = $this->faturamentoPorPeriodo(
                    $parametros['data_inicio'], 
                    $parametros['data_fim'], 
                    $parametros['agrupamento'] ?? 'dia'
                );
                $titulo = "Relatório de Faturamento";
                break;
                
            case 'produtos':
                $dados = $this->produtosMaisVendidos(
                    $parametros['data_inicio'], 
                    $parametros['data_fim'], 
                    $parametros['limite'] ?? 20
                );
                $titulo = "Produtos Mais Vendidos";
                break;
                
            case 'funcionarios':
                $dados = $this->desempenhoFuncionarios(
                    $parametros['data_inicio'], 
                    $parametros['data_fim']
                );
                $titulo = "Desempenho dos Funcionários";
                break;
                
            default:
                return false;
        }
        
        if ($formato == 'excel') {
            return $this->exportarExcel($dados, $titulo, $tipo_relatorio);
        } elseif ($formato == 'pdf') {
            return $this->exportarPDF($dados, $titulo, $tipo_relatorio);
        }
        
        return false;
    }
    
    /**
     * Exportar para Excel (CSV)
     */
    private function exportarExcel($dados, $titulo, $tipo) {
        $filename = "relatorio_" . $tipo . "_" . date('Y-m-d_H-i-s') . ".csv";
        $filepath = "relatorios/" . $filename;
        
        // Criar diretório se não existir
        if (!is_dir('relatorios')) {
            mkdir('relatorios', 0755, true);
        }
        
        $file = fopen($filepath, 'w');
        
        // BOM para UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Cabeçalho
        fputcsv($file, [$titulo . " - Gerado em: " . date('d/m/Y H:i:s')], ';');
        fputcsv($file, [], ';'); // Linha vazia
        
        if (!empty($dados)) {
            // Cabeçalhos das colunas
            fputcsv($file, array_keys($dados[0]), ';');
            
            // Dados
            foreach ($dados as $linha) {
                fputcsv($file, $linha, ';');
            }
        }
        
        fclose($file);
        
        return $filepath;
    }
    
    /**
     * Exportar para PDF (HTML simples)
     */
    private function exportarPDF($dados, $titulo, $tipo) {
        $filename = "relatorio_" . $tipo . "_" . date('Y-m-d_H-i-s') . ".html";
        $filepath = "relatorios/" . $filename;
        
        // Criar diretório se não existir
        if (!is_dir('relatorios')) {
            mkdir('relatorios', 0755, true);
        }
        
        $html = "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>$titulo</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; text-align: center; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .header-info { text-align: center; margin-bottom: 20px; color: #666; }
                .monetary { text-align: right; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <h1>$titulo</h1>
            <div class='header-info'>
                Gerado em: " . date('d/m/Y H:i:s') . "<br>
                Burger House - Sistema de Gestão
            </div>";
        
        if (!empty($dados)) {
            $html .= "<table>";
            
            // Cabeçalhos
            $html .= "<thead><tr>";
            foreach (array_keys($dados[0]) as $coluna) {
                $html .= "<th>" . ucfirst(str_replace('_', ' ', $coluna)) . "</th>";
            }
            $html .= "</tr></thead>";
            
            // Dados
            $html .= "<tbody>";
            foreach ($dados as $linha) {
                $html .= "<tr>";
                foreach ($linha as $key => $valor) {
                    $class = (strpos($key, 'valor') !== false || 
                             strpos($key, 'faturamento') !== false ||
                             strpos($key, 'receita') !== false ||
                             strpos($key, 'ticket') !== false) ? 'monetary' : '';
                    
                    if (is_numeric($valor) && $class == 'monetary') {
                        $valor = 'R$ ' . number_format($valor, 2, ',', '.');
                    }
                    
                    $html .= "<td class='$class'>$valor</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</tbody>";
            
            $html .= "</table>";
        } else {
            $html .= "<p>Nenhum dado encontrado para o período selecionado.</p>";
        }
        
        $html .= "
            <div class='no-print' style='margin-top: 30px; text-align: center;'>
                <button onclick='window.print()'>Imprimir</button>
            </div>
        </body>
        </html>";
        
        file_put_contents($filepath, $html);
        
        return $filepath;
    }
    
    /**
     * ITEM 34: Dashboard visual com gráficos interativos
     */
    public function gerarDashboard($data_inicio, $data_fim) {
        $dashboard = [
            'resumo' => $this->resumoGeral($data_inicio, $data_fim),
            'faturamento_diario' => $this->faturamentoPorPeriodo($data_inicio, $data_fim, 'dia'),
            'produtos_top' => $this->produtosMaisVendidos($data_inicio, $data_fim, 10),
            'horarios_pico' => $this->horariosPico($data_inicio, $data_fim),
            'ticket_medio' => $this->ticketMedio($data_inicio, $data_fim, 'total'),
            'conversao' => $this->taxaConversao($data_inicio, $data_fim),
            'funcionarios' => $this->desempenhoFuncionarios($data_inicio, $data_fim)
        ];
        
        return $dashboard;
    }
    
    /**
     * Resumo geral para dashboard
     */
    private function resumoGeral($data_inicio, $data_fim) {
        $sql = "SELECT 
                    COUNT(*) as total_pedidos,
                    SUM(CASE WHEN status = 'Entregue' THEN 1 ELSE 0 END) as pedidos_entregues,
                    SUM(CASE WHEN status = 'Cancelado' THEN 1 ELSE 0 END) as pedidos_cancelados,
                    SUM(valor_total) as faturamento_bruto,
                    SUM(CASE WHEN status = 'Entregue' THEN valor_total ELSE 0 END) as faturamento_liquido,
                    AVG(valor_total) as ticket_medio,
                    COUNT(DISTINCT id_cliente) as clientes_unicos,
                    AVG(CASE WHEN tempo_real_preparo IS NOT NULL 
                             THEN tempo_real_preparo 
                             ELSE tempo_estimado END) as tempo_medio_preparo
                FROM pedidos
                WHERE data_pedido BETWEEN ? AND ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $data_inicio, $data_fim);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $resumo = $result->fetch_assoc();
        $stmt->close();
        
        // Calcular métricas adicionais
        $resumo['taxa_entrega'] = $resumo['total_pedidos'] > 0 ? 
            round(($resumo['pedidos_entregues'] / $resumo['total_pedidos']) * 100, 2) : 0;
        
        $resumo['taxa_cancelamento'] = $resumo['total_pedidos'] > 0 ? 
            round(($resumo['pedidos_cancelados'] / $resumo['total_pedidos']) * 100, 2) : 0;
        
        // Formatar valores
        foreach (['faturamento_bruto', 'faturamento_liquido', 'ticket_medio', 'tempo_medio_preparo'] as $campo) {
            $resumo[$campo] = floatval($resumo[$campo]);
        }
        
        return $resumo;
    }
    
    /**
     * Criar tabela de visitantes (para taxa de conversão)
     */
    private function criarTabelaVisitantes() {
        $sql = "CREATE TABLE IF NOT EXISTS visitantes_diarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL,
            visitantes_unicos INT DEFAULT 0,
            paginas_vistas INT DEFAULT 0,
            tempo_medio_sessao INT DEFAULT 0,
            taxa_rejeicao DECIMAL(5,2) DEFAULT 0.00,
            data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_data (data)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->conn->query($sql);
        
        // Inserir dados simulados se a tabela estiver vazia
        $this->inserirDadosVisitantesSimulados();
    }
    
    /**
     * Inserir dados simulados de visitantes (para demonstração)
     */
    private function inserirDadosVisitantesSimulados() {
        $check = $this->conn->query("SELECT COUNT(*) as total FROM visitantes_diarios");
        $row = $check->fetch_assoc();
        
        if ($row['total'] == 0) {
            // Inserir dados dos últimos 30 dias
            for ($i = 30; $i >= 0; $i--) {
                $data = date('Y-m-d', strtotime("-$i days"));
                $visitantes = rand(50, 200);
                $paginas = $visitantes * rand(2, 8);
                $tempo_sessao = rand(120, 1800); // 2 a 30 minutos
                $taxa_rejeicao = rand(20, 80);
                
                $stmt = $this->conn->prepare("INSERT INTO visitantes_diarios 
                    (data, visitantes_unicos, paginas_vistas, tempo_medio_sessao, taxa_rejeicao) 
                    VALUES (?, ?, ?, ?, ?)");
                
                $stmt->bind_param("siiid", $data, $visitantes, $paginas, $tempo_sessao, $taxa_rejeicao);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
    /**
     * Formatar período para exibição
     */
    private function formatarPeriodo($periodo, $tipo) {
        switch ($tipo) {
            case 'dia':
                return date('d/m/Y', strtotime($periodo));
            case 'semana':
                return "Semana $periodo";
            case 'mes':
                return date('m/Y', strtotime($periodo . '-01'));
            case 'ano':
                return $periodo;
            default:
                return $periodo;
        }
    }
}

// ========================================
// API PARA RELATÓRIOS
// ========================================

if (isset($_GET['action'])) {
    session_start();
    
    if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 'dono') {
        echo json_encode(['erro' => 'Acesso negado']);
        exit;
    }
    
    require_once 'config.php';
    $relatorios = new RelatoriosGerenciais($conn);
    
    $action = $_GET['action'];
    
    switch ($action) {
        case 'dashboard':
            $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
            $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
            
            $dashboard = $relatorios->gerarDashboard($data_inicio, $data_fim);
            
            header('Content-Type: application/json');
            echo json_encode($dashboard);
            break;
            
        case 'faturamento':
            $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
            $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
            $agrupamento = $_GET['agrupamento'] ?? 'dia';
            
            $dados = $relatorios->faturamentoPorPeriodo($data_inicio, $data_fim, $agrupamento);
            
            header('Content-Type: application/json');
            echo json_encode($dados);
            break;
            
        case 'produtos':
            $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
            $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
            $limite = $_GET['limite'] ?? 20;
            
            $dados = $relatorios->produtosMaisVendidos($data_inicio, $data_fim, $limite);
            
            header('Content-Type: application/json');
            echo json_encode($dados);
            break;
            
        case 'exportar':
            $tipo = $_GET['tipo'] ?? 'faturamento';
            $formato = $_GET['formato'] ?? 'excel';
            $parametros = [
                'data_inicio' => $_GET['data_inicio'] ?? date('Y-m-01'),
                'data_fim' => $_GET['data_fim'] ?? date('Y-m-d'),
                'agrupamento' => $_GET['agrupamento'] ?? 'dia',
                'limite' => $_GET['limite'] ?? 20
            ];
            
            $arquivo = $relatorios->exportarRelatorio($tipo, $formato, $parametros);
            
            if ($arquivo) {
                header('Content-Type: application/json');
                echo json_encode(['sucesso' => true, 'arquivo' => $arquivo]);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['sucesso' => false, 'erro' => 'Erro ao gerar relatório']);
            }
            break;
    }
    
    exit;
}
?>