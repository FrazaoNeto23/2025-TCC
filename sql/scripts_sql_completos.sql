-- ============================================
-- BURGER HOUSE - SCRIPTS SQL DE ATUALIZAÇÃO
-- ============================================
-- Data: 31/10/2025
-- Versão: 2.0
-- 
-- INSTRUÇÕES:
-- 1. Faça BACKUP completo do banco antes de executar
-- 2. Execute os scripts na ordem apresentada
-- 3. Verifique se não há erros após cada execução
-- ============================================
-- ============================================
-- 1. VERIFICAR BANCO DE DADOS
-- ============================================
-- Selecionar o banco de dados
USE burger_house;

-- Listar todas as tabelas existentes
SHOW TABLES;

-- ============================================
-- 2. CRIAR/VERIFICAR TABELA usuarios
-- ============================================
CREATE TABLE
    IF NOT EXISTS usuarios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        senha VARCHAR(255) NOT NULL,
        telefone VARCHAR(20),
        endereco TEXT,
        tipo ENUM ('cliente', 'dono') DEFAULT 'cliente',
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_tipo (tipo)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Adicionar colunas faltantes na tabela usuarios
ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS telefone VARCHAR(20) AFTER email;

ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS endereco TEXT AFTER telefone;

-- Verificar estrutura
SHOW COLUMNS
FROM
    usuarios;

-- ============================================
-- 3. CRIAR/VERIFICAR TABELA produtos
-- ============================================
CREATE TABLE
    IF NOT EXISTS produtos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        preco DECIMAL(10, 2) NOT NULL,
        categoria VARCHAR(50),
        imagem VARCHAR(255),
        disponivel TINYINT (1) DEFAULT 1,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_categoria (categoria),
        INDEX idx_disponivel (disponivel),
        INDEX idx_nome (nome)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verificar estrutura
SHOW COLUMNS
FROM
    produtos;

-- ============================================
-- 4. CRIAR/VERIFICAR TABELA pedidos
-- ============================================
CREATE TABLE
    IF NOT EXISTS pedidos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        numero_pedido VARCHAR(20),
        id_cliente INT,
        total DECIMAL(10, 2) NOT NULL,
        status ENUM (
            'pendente',
            'em_preparo',
            'pronto',
            'entregue',
            'cancelado'
        ) DEFAULT 'pendente',
        prioridade ENUM ('baixa', 'media', 'alta') DEFAULT 'media',
        metodo_pagamento VARCHAR(50),
        numero_mesa INT,
        observacoes TEXT,
        data_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_cliente) REFERENCES usuarios (id),
        INDEX idx_numero_pedido (numero_pedido),
        INDEX idx_cliente (id_cliente),
        INDEX idx_status (status),
        INDEX idx_prioridade (prioridade),
        INDEX idx_data (data_pedido)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Adicionar colunas faltantes na tabela pedidos
ALTER TABLE pedidos
ADD COLUMN IF NOT EXISTS numero_pedido VARCHAR(20) AFTER id;

ALTER TABLE pedidos
ADD COLUMN IF NOT EXISTS prioridade ENUM ('baixa', 'media', 'alta') DEFAULT 'media' AFTER status;

ALTER TABLE pedidos
ADD COLUMN IF NOT EXISTS observacoes TEXT AFTER metodo_pagamento;

-- Adicionar índices se não existirem
ALTER TABLE pedidos ADD INDEX IF NOT EXISTS idx_numero_pedido (numero_pedido);

ALTER TABLE pedidos ADD INDEX IF NOT EXISTS idx_status (status);

ALTER TABLE pedidos ADD INDEX IF NOT EXISTS idx_prioridade (prioridade);

ALTER TABLE pedidos ADD INDEX IF NOT EXISTS idx_data (data_pedido);

-- Verificar estrutura
SHOW COLUMNS
FROM
    pedidos;

-- ============================================
-- 5. CRIAR TABELA itens_pedido (IMPORTANTE!)
-- ============================================
CREATE TABLE
    IF NOT EXISTS itens_pedido (
        id INT PRIMARY KEY AUTO_INCREMENT,
        id_pedido INT NOT NULL,
        id_produto INT NOT NULL,
        quantidade INT NOT NULL DEFAULT 1,
        preco_unitario DECIMAL(10, 2) NOT NULL,
        FOREIGN KEY (id_pedido) REFERENCES pedidos (id) ON DELETE CASCADE,
        FOREIGN KEY (id_produto) REFERENCES produtos (id),
        INDEX idx_pedido (id_pedido),
        INDEX idx_produto (id_produto)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verificar estrutura
SHOW COLUMNS
FROM
    itens_pedido;

-- ============================================
-- 6. CRIAR/VERIFICAR TABELA carrinho
-- ============================================
CREATE TABLE
    IF NOT EXISTS carrinho (
        id INT PRIMARY KEY AUTO_INCREMENT,
        id_cliente INT NOT NULL,
        id_produto INT NOT NULL,
        quantidade INT DEFAULT 1,
        data_adicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_cliente) REFERENCES usuarios (id) ON DELETE CASCADE,
        FOREIGN KEY (id_produto) REFERENCES produtos (id) ON DELETE CASCADE,
        INDEX idx_cliente (id_cliente),
        INDEX idx_produto (id_produto),
        UNIQUE KEY unique_cliente_produto (id_cliente, id_produto)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verificar estrutura
SHOW COLUMNS
FROM
    carrinho;

-- ============================================
-- 7. CRIAR TABELA fila_impressao
-- ============================================
CREATE TABLE
    IF NOT EXISTS fila_impressao (
        id INT PRIMARY KEY AUTO_INCREMENT,
        id_pedido INT NOT NULL,
        status ENUM ('pendente', 'impresso') DEFAULT 'pendente',
        data_adicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_impressao TIMESTAMP NULL,
        FOREIGN KEY (id_pedido) REFERENCES pedidos (id) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_pedido (id_pedido),
        INDEX idx_data (data_adicao)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verificar estrutura
SHOW COLUMNS
FROM
    fila_impressao;

-- ============================================
-- 8. CRIAR TABELA system_logs
-- ============================================
CREATE TABLE
    IF NOT EXISTS system_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tipo VARCHAR(50),
        mensagem TEXT,
        id_usuario INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        data_log TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios (id),
        INDEX idx_tipo (tipo),
        INDEX idx_data (data_log),
        INDEX idx_usuario (id_usuario)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Verificar estrutura
SHOW COLUMNS
FROM
    system_logs;

-- ============================================
-- 9. CRIAR TABELA funcionarios (OPCIONAL)
-- ============================================
CREATE TABLE
    IF NOT EXISTS funcionarios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(100) NOT NULL,
        cpf VARCHAR(14) UNIQUE,
        telefone VARCHAR(20),
        email VARCHAR(100),
        cargo VARCHAR(50),
        salario DECIMAL(10, 2),
        data_admissao DATE,
        status ENUM ('ativo', 'inativo') DEFAULT 'ativo',
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cpf (cpf),
        INDEX idx_status (status)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================
-- 10. CRIAR TABELA produtos_especiais (OPCIONAL)
-- ============================================
CREATE TABLE
    IF NOT EXISTS produtos_especiais (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        preco DECIMAL(10, 2) NOT NULL,
        data_inicio DATE,
        data_fim DATE,
        imagem VARCHAR(255),
        ativo TINYINT (1) DEFAULT 1,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ativo (ativo),
        INDEX idx_periodo (data_inicio, data_fim)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================
-- 11. CRIAR VIEW clientes (RECOMENDADO)
-- ============================================
-- Deletar VIEW se já existir
DROP VIEW IF EXISTS clientes;

-- Criar VIEW atualizada
CREATE VIEW
    clientes AS
SELECT
    id,
    nome,
    email,
    telefone,
    endereco,
    data_cadastro
FROM
    usuarios
WHERE
    tipo = 'cliente';

-- Testar VIEW
SELECT
    *
FROM
    clientes
LIMIT
    5;

-- ============================================
-- 12. CRIAR USUÁRIO DONO (SE NÃO EXISTIR)
-- ============================================
-- Verificar se já existe usuário dono
SELECT
    *
FROM
    usuarios
WHERE
    tipo = 'dono';

-- Se não existir, criar usuário dono
-- Senha padrão: "admin123" (hash bcrypt)
INSERT IGNORE INTO usuarios (nome, email, senha, tipo)
VALUES
    (
        'Administrador',
        'admin@burgerhouse.com',
        '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1oOqVWvhHKGcfCRV2OYJ9XbpMwEhR3u',
        'dono'
    );

-- Verificar se foi criado
SELECT
    id,
    nome,
    email,
    tipo
FROM
    usuarios
WHERE
    tipo = 'dono';

-- ============================================
-- 13. INSERIR PRODUTOS DE EXEMPLO (OPCIONAL)
-- ============================================
-- Deletar produtos de exemplo antigos (CUIDADO!)
-- DELETE FROM produtos WHERE nome LIKE '%Teste%';
-- Inserir produtos de exemplo
INSERT IGNORE INTO produtos (nome, descricao, preco, categoria, disponivel)
VALUES
    (
        'X-Burger',
        'Hambúrguer tradicional com queijo, alface e tomate',
        15.90,
        'Hambúrguer',
        1
    ),
    (
        'X-Bacon',
        'Hambúrguer com bacon crocante e queijo cheddar',
        18.90,
        'Hambúrguer',
        1
    ),
    (
        'X-Egg',
        'Hambúrguer com ovo frito e queijo',
        17.90,
        'Hambúrguer',
        1
    ),
    (
        'X-Salada',
        'Hambúrguer completo com salada',
        19.90,
        'Hambúrguer',
        1
    ),
    (
        'X-Tudo',
        'Hambúrguer com tudo que você imaginar',
        24.90,
        'Hambúrguer',
        1
    ),
    (
        'Coca-Cola Lata',
        'Refrigerante 350ml',
        5.00,
        'Bebida',
        1
    ),
    (
        'Guaraná Lata',
        'Refrigerante 350ml',
        5.00,
        'Bebida',
        1
    ),
    (
        'Suco Natural',
        'Suco de laranja natural 300ml',
        8.00,
        'Bebida',
        1
    ),
    (
        'Batata Frita',
        'Porção de batata frita crocante',
        12.00,
        'Acompanhamento',
        1
    ),
    (
        'Onion Rings',
        'Anéis de cebola empanados',
        14.00,
        'Acompanhamento',
        1
    );

-- Verificar produtos inseridos
SELECT
    id,
    nome,
    preco,
    categoria
FROM
    produtos
ORDER BY
    categoria,
    nome;

-- ============================================
-- 14. ESTATÍSTICAS E VERIFICAÇÕES
-- ============================================
-- Contar registros em cada tabela
SELECT
    'usuarios' as tabela,
    COUNT(*) as total
FROM
    usuarios
UNION ALL
SELECT
    'produtos',
    COUNT(*)
FROM
    produtos
UNION ALL
SELECT
    'pedidos',
    COUNT(*)
FROM
    pedidos
UNION ALL
SELECT
    'itens_pedido',
    COUNT(*)
FROM
    itens_pedido
UNION ALL
SELECT
    'carrinho',
    COUNT(*)
FROM
    carrinho
UNION ALL
SELECT
    'fila_impressao',
    COUNT(*)
FROM
    fila_impressao
UNION ALL
SELECT
    'system_logs',
    COUNT(*)
FROM
    system_logs;

-- Verificar estrutura de todas as tabelas importantes
SHOW TABLES;

SHOW COLUMNS
FROM
    usuarios;

SHOW COLUMNS
FROM
    produtos;

SHOW COLUMNS
FROM
    pedidos;

SHOW COLUMNS
FROM
    itens_pedido;

SHOW COLUMNS
FROM
    carrinho;

-- Verificar índices
SHOW INDEX
FROM
    pedidos;

SHOW INDEX
FROM
    itens_pedido;

SHOW INDEX
FROM
    produtos;

-- Verificar foreign keys
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    TABLE_SCHEMA = 'burger_house'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ============================================
-- 15. LIMPEZA E OTIMIZAÇÃO
-- ============================================
-- Limpar logs antigos (mais de 90 dias)
DELETE FROM system_logs
WHERE
    data_log < DATE_SUB (NOW (), INTERVAL 90 DAY);

-- Otimizar tabelas
OPTIMIZE TABLE usuarios;

OPTIMIZE TABLE produtos;

OPTIMIZE TABLE pedidos;

OPTIMIZE TABLE itens_pedido;

OPTIMIZE TABLE carrinho;

-- Analisar tabelas
ANALYZE TABLE usuarios;

ANALYZE TABLE produtos;

ANALYZE TABLE pedidos;

ANALYZE TABLE itens_pedido;

-- ============================================
-- 16. BACKUP BÁSICO (OPCIONAL)
-- ============================================
-- Para fazer backup via linha de comando:
-- mysqldump -u root -p burger_house > backup_burger_house_$(date +%Y%m%d).sql
-- Para restaurar backup:
-- mysql -u root -p burger_house < backup_burger_house_YYYYMMDD.sql
-- ============================================
-- 17. CONSULTAS ÚTEIS PARA DEBUG
-- ============================================
-- Ver últimos pedidos
SELECT
    p.id,
    p.numero_pedido,
    u.nome as cliente,
    p.total,
    p.status,
    p.prioridade,
    p.data_pedido
FROM
    pedidos p
    LEFT JOIN usuarios u ON p.id_cliente = u.id
ORDER BY
    p.data_pedido DESC
LIMIT
    10;

-- Ver itens de um pedido específico
SELECT
    pr.nome,
    ip.quantidade,
    ip.preco_unitario,
    (ip.quantidade * ip.preco_unitario) as subtotal
FROM
    itens_pedido ip
    JOIN produtos pr ON ip.id_produto = pr.id
WHERE
    ip.id_pedido = 1;

-- Altere o ID do pedido
-- Ver produtos mais vendidos
SELECT
    pr.nome,
    SUM(ip.quantidade) as total_vendido,
    COUNT(DISTINCT ip.id_pedido) as num_pedidos
FROM
    itens_pedido ip
    JOIN produtos pr ON ip.id_produto = pr.id
GROUP BY
    ip.id_produto
ORDER BY
    total_vendido DESC
LIMIT
    10;

-- Ver faturamento por dia
SELECT
    DATE (data_pedido) as data,
    COUNT(*) as total_pedidos,
    SUM(total) as faturamento
FROM
    pedidos
WHERE
    status != 'cancelado'
GROUP BY
    DATE (data_pedido)
ORDER BY
    data DESC
LIMIT
    30;

-- Ver pedidos por status
SELECT
    status,
    COUNT(*) as total,
    SUM(total) as valor_total
FROM
    pedidos
GROUP BY
    status
ORDER BY
    total DESC;

-- ============================================
-- 18. TESTES DE INTEGRIDADE
-- ============================================
-- Verificar pedidos sem itens
SELECT
    p.id,
    p.numero_pedido
FROM
    pedidos p
    LEFT JOIN itens_pedido ip ON p.id = ip.id_pedido
WHERE
    ip.id IS NULL;

-- Verificar itens órfãos (sem pedido)
SELECT
    ip.id,
    ip.id_pedido
FROM
    itens_pedido ip
    LEFT JOIN pedidos p ON ip.id_pedido = p.id
WHERE
    p.id IS NULL;

-- Verificar usuários duplicados por email
SELECT
    email,
    COUNT(*) as total
FROM
    usuarios
GROUP BY
    email
HAVING
    COUNT(*) > 1;

-- Verificar produtos sem categoria
SELECT
    id,
    nome
FROM
    produtos
WHERE
    categoria IS NULL
    OR categoria = '';

-- ============================================
-- 19. PERMISSÕES (SE NECESSÁRIO)
-- ============================================
-- Criar usuário específico para a aplicação (opcional)
-- CREATE USER 'burgerhouse'@'localhost' IDENTIFIED BY 'senha_forte_aqui';
-- Conceder permissões
-- GRANT SELECT, INSERT, UPDATE, DELETE ON burger_house.* TO 'burgerhouse'@'localhost';
-- Aplicar mudanças
-- FLUSH PRIVILEGES;
-- ============================================
-- 20. CHECKLIST FINAL
-- ============================================
-- ✅ Verificar se todas as tabelas foram criadas
SHOW TABLES;

-- ✅ Verificar se todas as colunas estão corretas
SHOW COLUMNS
FROM
    pedidos;

-- ✅ Verificar se a VIEW clientes funciona
SELECT
    COUNT(*)
FROM
    clientes;

-- ✅ Verificar se existe usuário dono
SELECT
    COUNT(*)
FROM
    usuarios
WHERE
    tipo = 'dono';

-- ✅ Verificar se existem produtos
SELECT
    COUNT(*)
FROM
    produtos;

-- ============================================
-- SCRIPTS EXECUTADOS COM SUCESSO!
-- ============================================
-- Se chegou até aqui sem erros, o banco está pronto!
-- Agora você pode fazer upload dos arquivos PHP.
-- Próximos passos:
-- 1. Fazer backup do banco de dados
-- 2. Testar o login no sistema
-- 3. Adicionar produtos reais
-- 4. Fazer pedidos de teste
-- 5. Verificar se tudo funciona
-- ✅ BOA SORTE!
-- ============================================
-- FIM DOS SCRIPTS SQL
-- ============================================