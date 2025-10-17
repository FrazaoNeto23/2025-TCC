-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Tempo de geração: 17/10/2025 às 20:07
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `burger_house`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `carrinho`
--

CREATE TABLE `carrinho` (
  `id` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_produto` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `tipo_produto` enum('normal','especial') DEFAULT 'normal',
  `data_adicao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `carrinho`
--

INSERT INTO `carrinho` (`id`, `id_cliente`, `id_produto`, `quantidade`, `tipo_produto`, `data_adicao`) VALUES
(7, 14, 16, 1, 'normal', '2025-10-10 19:46:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `funcionarios`
--

CREATE TABLE `funcionarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `numero_pedido` varchar(20) DEFAULT NULL,
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
  `observacoes` text DEFAULT NULL,
  `status_pagamento` varchar(50) DEFAULT 'Aguardando'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `pedidos`
--

INSERT INTO `pedidos` (`id`, `numero_pedido`, `id_cliente`, `numero_mesa`, `id_produto`, `tipo_produto`, `quantidade`, `total`, `data`, `data_pedido`, `status`, `metodo_pagamento`, `observacoes`, `status_pagamento`) VALUES
(14, NULL, 12, 1, 13, 'normal', 2, 47.00, '2025-10-17 17:50:04', '2025-10-17 17:50:04', 'Pendente', 'dinheiro', NULL, 'Pago'),
(15, NULL, 12, 1, 14, 'normal', 2, 51.80, '2025-10-17 17:50:04', '2025-10-17 17:50:04', 'Pendente', 'dinheiro', NULL, 'Pago'),
(16, NULL, 12, 1, 15, 'normal', 2, 55.80, '2025-10-17 17:50:04', '2025-10-17 17:50:04', 'Pendente', 'dinheiro', NULL, 'Pago'),
(17, NULL, 12, 1, 17, 'normal', 3, 38.70, '2025-10-17 17:50:04', '2025-10-17 17:50:04', 'Pendente', 'dinheiro', NULL, 'Pago'),
(18, NULL, 12, 1, 16, 'normal', 8, 44.00, '2025-10-17 17:50:04', '2025-10-17 17:50:04', 'Pendente', 'dinheiro', NULL, 'Pago');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos_backup_2025_10_17`
--

CREATE TABLE `pedidos_backup_2025_10_17` (
  `id` int(11) NOT NULL,
  `numero_pedido` varchar(20) DEFAULT NULL,
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
  `status_pagamento` varchar(50) DEFAULT 'Aguardando'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `descricao`, `preco`, `imagem`) VALUES
(13, 'X-Burguer', 'Pão brioche, carne bovina 120g, queijo cheddar.', 23.50, '1757425983_Cheesburguer.jpg'),
(14, 'X-Salada', 'Pão brioche, carne bovina 150g, alface fatiada, tomate, cebola e queijo cheddar.', 25.90, '1757426044_x-salada.png'),
(15, 'X-Bacon', 'Pão brioche, carne bovina 120g, queijo cheddar e fatias de bacon artesanal.', 27.90, '1757426136_Bacon.jpg'),
(16, 'Coca Lata', 'Coca bem gelada, pode trazer de acompanhamento um copo com gelo e limão (opcional)', 5.50, '1760124844_james-yarema-wQFmDhrvVSs-unsplash.jpg'),
(17, 'Batata Frita', 'Porção de batata frita crocante', 12.90, '1760124987_download.jpg');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos_especiais`
--

CREATE TABLE `produtos_especiais` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `produtos_especiais`
--

INSERT INTO `produtos_especiais` (`id`, `nome`, `descricao`, `preco`, `imagem`) VALUES
(1, 'Burger Premium Especial', 'Pão artesanal, carne angus 200g, queijo gorgonzola, cebola caramelizada e molho especial da casa', 45.90, '1760468299_burger especial.jpeg'),
(2, 'Combo Executivo', 'X-Bacon + Batata Frita + Refrigerante + Sobremesa', 39.90, '1760468307_combo especial.jpeg'),
(3, 'Milk Shake Premium', 'Milk shake de chocolate belga com sorvete artesanal', 18.90, '1760468316_milk shake.jpeg');

-- --------------------------------------------------------

--
-- Estrutura para tabela `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `nivel` varchar(20) DEFAULT 'INFO',
  `status` varchar(20) NOT NULL,
  `mensagem` text DEFAULT NULL,
  `dados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dados`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `system_logs`
--

INSERT INTO `system_logs` (`id`, `tipo`, `nivel`, `status`, `mensagem`, `dados`, `created_at`) VALUES
(1, 'sistema', 'INFO', 'info', 'Sistema de reset de pedidos configurado', NULL, '2025-10-17 18:02:59'),
(2, 'reset_diario', 'INFO', 'sucesso', 'Reset automático executado', '{\"cliente_id\":12,\"pedidos_removidos\":0,\"backup_table\":\"pedidos_backup_2025_10_17\",\"timestamp\":1760724219}', '2025-10-17 18:03:39'),
(3, 'configuracao', 'INFO', 'sucesso', 'Colunas observacoes e numero_pedido verificadas/criadas', NULL, '2025-10-17 18:06:40');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('dono','cliente') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`) VALUES
(4, 'João', 'dono@burger.com', '$2y$10$5eGH1FwVNpDbDzSs2HssMehdYNlZqqCh4VFrDFRdMkz58BgLbrhXe', 'dono'),
(5, 'Donizete', 'cliente@burger.com', '$2y$10$Y6YJPBMF2YBqwaRsY1Z4s.ASdC5NekkyzwQIS9AI.55YkyZ9L9HaO', 'cliente'),
(12, 'Rafa', 'teste@teste.com', '$2y$10$AMCQAroGQAzJpWeMCtSGHeP9ucs.sOGo2Yq1szHDRDHbuAhe41OzS', 'cliente'),
(13, 'João', 'neto@dono.com', '$2y$10$lQOOf/mEhd2sUnckNHL6u.WqeLdBjzEfa8c.F3MWYrQRFk4MStbYi', 'dono'),
(14, 'Rafael', 'rmrpaisdoai@fmauis.cnm', '$2y$10$SOyiG.4lCGzYVL.in9uY/.O6dpTxH/22W574XcYLDeESmnESekbSC', 'cliente');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `carrinho`
--
ALTER TABLE `carrinho`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_produto` (`id_produto`),
  ADD KEY `idx_carrinho_cliente` (`id_cliente`);

--
-- Índices de tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_produto` (`id_produto`),
  ADD KEY `idx_pedidos_cliente` (`id_cliente`),
  ADD KEY `idx_pedidos_status` (`status`),
  ADD KEY `idx_pedidos_pagamento` (`status_pagamento`),
  ADD KEY `idx_pedidos_mesa` (`numero_mesa`),
  ADD KEY `idx_pedidos_tipo` (`tipo_produto`),
  ADD KEY `idx_numero_pedido` (`numero_pedido`),
  ADD KEY `idx_pedidos_cliente_status` (`id_cliente`,`status`),
  ADD KEY `idx_pedidos_data` (`data`);

--
-- Índices de tabela `pedidos_backup_2025_10_17`
--
ALTER TABLE `pedidos_backup_2025_10_17`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_produto` (`id_produto`),
  ADD KEY `idx_pedidos_cliente` (`id_cliente`),
  ADD KEY `idx_pedidos_status` (`status`),
  ADD KEY `idx_pedidos_pagamento` (`status_pagamento`),
  ADD KEY `idx_pedidos_mesa` (`numero_mesa`),
  ADD KEY `idx_pedidos_tipo` (`tipo_produto`),
  ADD KEY `idx_numero_pedido` (`numero_pedido`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `produtos_especiais`
--
ALTER TABLE `produtos_especiais`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_data` (`created_at`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `carrinho`
--
ALTER TABLE `carrinho`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de tabela `pedidos_backup_2025_10_17`
--
ALTER TABLE `pedidos_backup_2025_10_17`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `produtos_especiais`
--
ALTER TABLE `produtos_especiais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `carrinho`
--
ALTER TABLE `carrinho`
  ADD CONSTRAINT `carrinho_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carrinho_ibfk_2` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_produto`) REFERENCES `produtos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
