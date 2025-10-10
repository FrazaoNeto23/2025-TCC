CREATE TABLE
  `funcionarios` (
    `id` int (11) NOT NULL,
    `nome` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `senha` varchar(255) NOT NULL,
    `foto` varchar(255) DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = latin1 COLLATE = latin1_swedish_ci;

CREATE TABLE
  `pedidos` (
    `id` int (11) NOT NULL,
    `id_cliente` int (11) NOT NULL,
    `id_produto` int (11) NOT NULL,
    `quantidade` int (11) NOT NULL,
    `total` decimal(10, 2) NOT NULL,
    `data` timestamp NOT NULL DEFAULT current_timestamp(),
    `data_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
    `status` varchar(50) DEFAULT 'Pendente',
    `metodo_pagamento` varchar(50) DEFAULT NULL,
    `status_pagamento` varchar(50) DEFAULT 'Aguardando'
  ) ENGINE = InnoDB DEFAULT CHARSET = latin1 COLLATE = latin1_swedish_ci;

CREATE TABLE
  `produtos` (
    `id` int (11) NOT NULL,
    `nome` varchar(100) NOT NULL,
    `descricao` text DEFAULT NULL,
    `preco` decimal(10, 2) NOT NULL,
    `imagem` varchar(255) DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = latin1 COLLATE = latin1_swedish_ci;

INSERT INTO
  `produtos` (`id`, `nome`, `descricao`, `preco`, `imagem`)
VALUES
  (
    13,
    'X-Burguer',
    'PÃ£o brioche, carne bovina 120g, queijo cheddar.',
    23.50,
    '1757425983_Cheesburguer.jpg'
  ),
  (
    14,
    'X-Salada',
    'PÃ£o brioche, carne bovina 150g, alface fatiada, tomate, cebola e queijo cheddar.',
    25.90,
    '1757426044_x-salada.png'
  ),
  (
    15,
    'X-Bacon',
    'PÃ£o brioche, carne bovina 120g, queijo cheddar e fatias de bacon artesanal.',
    27.90,
    '1757426136_Bacon.jpg'
  ),
  (
    16,
    'Coca Lata',
    'Coca bem gelada, pode trazer de acompanhamentos um copo com gelo e limÃ£o (opcional)',
    6.50,
    ''
  ),
  (17, 'Coca Lata', 'dfsdfsdf', 8.99, '');

CREATE TABLE
  `usuarios` (
    `id` int (11) NOT NULL,
    `nome` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `senha` varchar(255) NOT NULL,
    `tipo` enum ('dono', 'cliente') NOT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = latin1 COLLATE = latin1_swedish_ci;

INSERT INTO
  `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`)
VALUES
  (
    4,
    'joao',
    'dono@burger.com',
    '$2y$10$5eGH1FwVNpDbDzSs2HssMehdYNlZqqCh4VFrDFRdMkz58BgLbrhXe',
    'dono'
  ),
  (
    5,
    'Donizete',
    'asdf@asdf.com',
    '$2y$10$Y6YJPBMF2YBqwaRsY1Z4s.ASdC5NekkyzwQIS9AI.55YkyZ9L9HaO',
    'cliente'
  ),
  (
    12,
    'Rafa',
    'teste@teste.com',
    '$2y$10$AMCQAroGQAzJpWeMCtSGHeP9ucs.sOGo2Yq1szHDRDHbuAhe41OzS',
    'cliente'
  ),
  (
    13,
    'Joao',
    'neto@dono.com',
    '$2y$10$lQOOf/mEhd2sUnckNHL6u.WqeLdBjzEfa8c.F3MWYrQRFk4MStbYi',
    'dono'
  );

ALTER TABLE `funcionarios` ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `pedidos` ADD PRIMARY KEY (`id`),
ADD KEY `id_produto` (`id_produto`),
ADD KEY `idx_pedidos_cliente` (`id_cliente`),
ADD KEY `idx_pedidos_status` (`status`),
ADD KEY `idx_pedidos_pagamento` (`status_pagamento`);

ALTER TABLE `produtos` ADD PRIMARY KEY (`id`);

ALTER TABLE `usuarios` ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `funcionarios` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 4;

ALTER TABLE `pedidos` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 111;

ALTER TABLE `produtos` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 18;

ALTER TABLE `usuarios` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 14;

ALTER TABLE `pedidos` ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id`),
ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`),
COMMIT


/* ATUALIZAÇÕES */
--------------------------------------------------------------------
-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS `burger_house` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `burger_house`;

CREATE TABLE `funcionarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('dono','cliente') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`) VALUES
(4, 'joao', 'dono@burger.com', '$2y$10$5eGH1FwVNpDbDzSs2HssMehdYNlZqqCh4VFrDFRdMkz58BgLbrhXe', 'dono'),
(5, 'Donizete', 'cliente@burger.com', '$2y$10$Y6YJPBMF2YBqwaRsY1Z4s.ASdC5NekkyzwQIS9AI.55YkyZ9L9HaO', 'cliente'),
(12, 'Rafa', 'teste@teste.com', '$2y$10$AMCQAroGQAzJpWeMCtSGHeP9ucs.sOGo2Yq1szHDRDHbuAhe41OzS', 'cliente'),
(13, 'Joao', 'neto@dono.com', '$2y$10$lQOOf/mEhd2sUnckNHL6u.WqeLdBjzEfa8c.F3MWYrQRFk4MStbYi', 'dono');

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `produtos` (`id`, `nome`, `descricao`, `preco`, `imagem`) VALUES
(13, 'X-Burguer', 'Pão brioche, carne bovina 120g, queijo cheddar.', 23.50, '1757425983_Cheesburguer.jpg'),
(14, 'X-Salada', 'Pão brioche, carne bovina 150g, alface fatiada, tomate, cebola e queijo cheddar.', 25.90, '1757426044_x-salada.png'),
(15, 'X-Bacon', 'Pão brioche, carne bovina 120g, queijo cheddar e fatias de bacon artesanal.', 27.90, '1757426136_Bacon.jpg'),
(16, 'Coca Lata', 'Coca bem gelada, pode trazer de acompanhamento um copo com gelo e limão (opcional)', 6.50, ''),
(17, 'Batata Frita', 'Porção de batata frita crocante', 12.90, '');

CREATE TABLE `produtos_especiais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `produtos_especiais` (`id`, `nome`, `descricao`, `preco`, `imagem`) VALUES
(1, 'Burger Premium Especial', 'Pão artesanal, carne angus 200g, queijo gorgonzola, cebola caramelizada e molho especial da casa', 45.90, ''),
(2, 'Combo Executivo', 'X-Bacon + Batata Frita + Refrigerante + Sobremesa', 39.90, ''),
(3, 'Milk Shake Premium', 'Milk shake de chocolate belga com sorvete artesanal', 18.90, '');

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `numero_mesa` int(11) DEFAULT NULL,
  `id_produto` int(11) NOT NULL,
  `tipo_produto` enum('normal','especial') DEFAULT 'normal',
  `quantidade` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `data` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Pendente',
  `metodo_pagamento` varchar(50) DEFAULT NULL,
  `status_pagamento` varchar(50) DEFAULT 'Aguardando',
  PRIMARY KEY (`id`),
  KEY `id_produto` (`id_produto`),
  KEY `idx_pedidos_cliente` (`id_cliente`),
  KEY `idx_pedidos_status` (`status`),
  KEY `idx_pedidos_pagamento` (`status_pagamento`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `carrinho` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_produto` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `tipo_produto` enum('normal','especial') DEFAULT 'normal',
  `data_adicao` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_produto` (`id_produto`),
  CONSTRAINT `carrinho_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `carrinho_ibfk_2` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE INDEX idx_pedidos_mesa ON pedidos(numero_mesa);

CREATE INDEX idx_carrinho_cliente ON carrinho(id_cliente);

CREATE INDEX idx_pedidos_tipo ON pedidos(tipo_produto);

ALTER TABLE `funcionarios` AUTO_INCREMENT=1;
ALTER TABLE `usuarios` AUTO_INCREMENT=14;
ALTER TABLE `produtos` AUTO_INCREMENT=18;
ALTER TABLE `produtos_especiais` AUTO_INCREMENT=4;
ALTER TABLE `pedidos` AUTO_INCREMENT=1;
ALTER TABLE `carrinho` AUTO_INCREMENT=1;

DELIMITER $$
CREATE TRIGGER limpar_carrinho_apos_pedido
AFTER INSERT ON pedidos
FOR EACH ROW
BEGIN

  NULL;
END$$
DELIMITER ;

CREATE OR REPLACE VIEW vw_pedidos_completos AS
SELECT 
  p.id,
  p.numero_mesa,
  u.nome AS cliente_nome,
  u.email AS cliente_email,
  CASE 
    WHEN p.tipo_produto = 'normal' THEN prod.nome
    WHEN p.tipo_produto = 'especial' THEN pe.nome
  END AS produto_nome,
  CASE 
    WHEN p.tipo_produto = 'normal' THEN prod.preco
    WHEN p.tipo_produto = 'especial' THEN pe.preco
  END AS produto_preco,
  p.quantidade,
  p.total,
  p.status,
  p.status_pagamento,
  p.metodo_pagamento,
  p.tipo_produto,
  p.data
FROM pedidos p
JOIN usuarios u ON p.id_cliente = u.id
LEFT JOIN produtos prod ON p.id_produto = prod.id AND p.tipo_produto = 'normal'
LEFT JOIN produtos_especiais pe ON p.id_produto = pe.id AND p.tipo_produto = 'especial';

CREATE OR REPLACE VIEW vw_carrinho_completo AS
SELECT 
  c.id,
  u.nome AS cliente_nome,
  u.email AS cliente_email,
  CASE 
    WHEN c.tipo_produto = 'normal' THEN prod.nome
    WHEN c.tipo_produto = 'especial' THEN pe.nome
  END AS produto_nome,
  CASE 
    WHEN c.tipo_produto = 'normal' THEN prod.preco
    WHEN c.tipo_produto = 'especial' THEN pe.preco
  END AS produto_preco,
  CASE 
    WHEN c.tipo_produto = 'normal' THEN prod.imagem
    WHEN c.tipo_produto = 'especial' THEN pe.imagem
  END AS produto_imagem,
  c.quantidade,
  c.tipo_produto,
  c.data_adicao,
  CASE 
    WHEN c.tipo_produto = 'normal' THEN prod.preco * c.quantidade
    WHEN c.tipo_produto = 'especial' THEN pe.preco * c.quantidade
  END AS subtotal
FROM carrinho c
JOIN usuarios u ON c.id_cliente = u.id
LEFT JOIN produtos prod ON c.id_produto = prod.id AND c.tipo_produto = 'normal'
LEFT JOIN produtos_especiais pe ON c.id_produto = pe.id AND c.tipo_produto = 'especial';

DELIMITER $$
CREATE PROCEDURE limpar_pedidos_antigos()
BEGIN
  DELETE FROM pedidos 
  WHERE status = 'Entregue' 
  AND data < DATE_SUB(NOW(), INTERVAL 30 DAY);
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE estatisticas_dia()
BEGIN
  SELECT 
    COUNT(*) as total_pedidos,
    SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'Em preparo' THEN 1 ELSE 0 END) as em_preparo,
    SUM(CASE WHEN status = 'Entregando' THEN 1 ELSE 0 END) as entregando,
    SUM(CASE WHEN status = 'Entregue' THEN 1 ELSE 0 END) as entregues,
    SUM(total) as faturamento_total,
    COUNT(DISTINCT id_cliente) as clientes_atendidos,
    COUNT(DISTINCT numero_mesa) as mesas_ocupadas
  FROM pedidos
  WHERE DATE(data) = CURDATE();
END$$
DELIMITER ;

INSERT INTO `funcionarios` (`nome`, `email`, `senha`, `foto`) VALUES
('Carlos Silva', 'funcionario@burger.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL);

COMMIT;

SHOW TABLES;

DESCRIBE usuarios;
DESCRIBE funcionarios;
DESCRIBE produtos;
DESCRIBE produtos_especiais;
DESCRIBE pedidos;
DESCRIBE carrinho;

SELECT 'Banco de dados BURGER HOUSE criado com sucesso!' AS Status;