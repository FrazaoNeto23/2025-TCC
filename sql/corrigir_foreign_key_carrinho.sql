-- ============================================
-- CORREÇÃO: Erro de Foreign Key no Carrinho
-- ============================================
-- Erro: Cannot add or update a child row: a foreign key constraint fails
-- Data: 31/10/2025
-- ============================================
-- PASSO 1: Verificar produtos que não existem no carrinho
SELECT
    c.id as id_carrinho,
    c.id_produto,
    c.id_cliente,
    c.quantidade,
    p.id as produto_existe
FROM
    carrinho c
    LEFT JOIN produtos p ON c.id_produto = p.id
WHERE
    p.id IS NULL;

-- Se a query acima retornar resultados, existem dados inconsistentes!
-- ============================================
-- SOLUÇÃO 1: LIMPAR DADOS INCONSISTENTES
-- ============================================
-- Deletar itens do carrinho que referenciam produtos inexistentes
DELETE c
FROM
    carrinho c
    LEFT JOIN produtos p ON c.id_produto = p.id
WHERE
    p.id IS NULL;

-- Verificar se foi limpo
SELECT
    COUNT(*) as itens_no_carrinho
FROM
    carrinho;

-- ============================================
-- SOLUÇÃO 2: RECRIAR A TABELA CARRINHO
-- ============================================
-- Fazer backup dos dados válidos
CREATE TEMPORARY TABLE carrinho_backup AS
SELECT
    c.*
FROM
    carrinho c
    INNER JOIN produtos p ON c.id_produto = p.id
    INNER JOIN usuarios u ON c.id_cliente = u.id;

-- Dropar a tabela original
DROP TABLE IF EXISTS carrinho;

-- Recriar a tabela com constraints corretas
CREATE TABLE
    carrinho (
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

-- Restaurar dados válidos
INSERT INTO
    carrinho (
        id,
        id_cliente,
        id_produto,
        quantidade,
        data_adicao
    )
SELECT
    id,
    id_cliente,
    id_produto,
    quantidade,
    data_adicao
FROM
    carrinho_backup;

-- Dropar backup temporário
DROP TEMPORARY TABLE IF EXISTS carrinho_backup;

-- Verificar se funcionou
SELECT
    COUNT(*) as total
FROM
    carrinho;

-- ============================================
-- SOLUÇÃO 3: VERIFICAÇÕES DE INTEGRIDADE
-- ============================================
-- Verificar se todos os produtos existem
SELECT
    p.id,
    p.nome,
    p.disponivel
FROM
    produtos p
ORDER BY
    p.id;

-- Verificar se todos os usuários existem
SELECT
    u.id,
    u.nome,
    u.tipo
FROM
    usuarios u
WHERE
    u.tipo = 'cliente'
ORDER BY
    u.id;

-- Verificar foreign keys atuais
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
    AND TABLE_NAME = 'carrinho'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ============================================
-- SOLUÇÃO 4: DESABILITAR TEMPORARIAMENTE (NÃO RECOMENDADO)
-- ============================================
-- ATENÇÃO: Use isso apenas para debug temporário!
-- Não use em produção!
-- Desabilitar verificação de foreign keys
-- SET FOREIGN_KEY_CHECKS=0;
-- Fazer operações necessárias
-- ...
-- Reabilitar verificação
-- SET FOREIGN_KEY_CHECKS=1;
-- ============================================
-- PREVENÇÃO: Verificar antes de inserir
-- ============================================
-- Exemplo de query segura para adicionar ao carrinho:
-- 
-- INSERT INTO carrinho (id_cliente, id_produto, quantidade)
-- SELECT ?, ?, ?
-- FROM produtos
-- WHERE id = ? AND disponivel = 1
-- LIMIT 1;
-- ============================================
-- TESTES APÓS CORREÇÃO
-- ============================================
-- Teste 1: Tentar adicionar produto existente
-- (Substitua os valores conforme necessário)
INSERT INTO
    carrinho (id_cliente, id_produto, quantidade)
VALUES
    (1, 1, 1) ON DUPLICATE KEY
UPDATE quantidade = quantidade + 1;

-- Teste 2: Verificar se foi adicionado
SELECT
    c.id,
    c.id_cliente,
    u.nome as cliente,
    c.id_produto,
    p.nome as produto,
    c.quantidade
FROM
    carrinho c
    JOIN usuarios u ON c.id_cliente = u.id
    JOIN produtos p ON c.id_produto = p.id;

-- Teste 3: Deletar item de teste
DELETE FROM carrinho
WHERE
    id_cliente = 1
    AND id_produto = 1;

-- ============================================
-- QUERIES ÚTEIS PARA DEBUG
-- ============================================
-- Ver todos os produtos disponíveis
SELECT
    id,
    nome,
    preco,
    disponivel
FROM
    produtos
ORDER BY
    id;

-- Ver todos os clientes
SELECT
    id,
    nome,
    email
FROM
    usuarios
WHERE
    tipo = 'cliente'
ORDER BY
    id;

-- Ver status do carrinho
SELECT
    c.id as carrinho_id,
    u.nome as cliente,
    p.nome as produto,
    c.quantidade,
    c.data_adicao
FROM
    carrinho c
    LEFT JOIN usuarios u ON c.id_cliente = u.id
    LEFT JOIN produtos p ON c.id_produto = p.id
ORDER BY
    c.data_adicao DESC;

-- ============================================
-- RESULTADO ESPERADO
-- ============================================
-- ✅ Carrinho limpo de dados inconsistentes
-- ✅ Foreign keys funcionando corretamente
-- ✅ Pode adicionar produtos ao carrinho sem erro
SELECT
    'CORREÇÃO APLICADA COM SUCESSO!' as status;

-- ============================================
-- FIM DO SCRIPT
-- ============================================