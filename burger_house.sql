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