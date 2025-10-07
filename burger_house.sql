-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Tempo de geração: 07/10/2025 às 21:27
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
-- Estrutura para tabela `funcionarios`
--

CREATE TABLE `funcionarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_produto` int(11) NOT NULL,
  `quantidade` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `data` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Pendente',
  `metodo_pagamento` varchar(50) DEFAULT NULL,
  `status_pagamento` varchar(50) DEFAULT 'Aguardando'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `descricao`, `preco`, `imagem`) VALUES
(13, 'X-Burguer', 'PÃ£o brioche, carne bovina 120g, queijo cheddar.', 23.50, '1757425983_Cheesburguer.jpg'),
(14, 'X-Salada', 'PÃ£o brioche, carne bovina 150g, alface fatiada, tomate, cebola e queijo cheddar.', 25.90, '1757426044_x-salada.png'),
(15, 'X-Bacon', 'PÃ£o brioche, carne bovina 120g, queijo cheddar e fatias de bacon artesanal.', 27.90, '1757426136_Bacon.jpg'),
(16, 'Coca Lata', 'Coca bem gelada, pode trazer de acompanhamentos um copo com gelo e limÃ£o (opcional)', 6.50, ''),
(17, 'Coca Lata', 'dfsdfsdf', 8.99, '');

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
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`) VALUES
(4, 'joao', 'dono@burger.com', '$2y$10$5eGH1FwVNpDbDzSs2HssMehdYNlZqqCh4VFrDFRdMkz58BgLbrhXe', 'dono'),
(5, 'Donizete', 'asdf@asdf.com', '$2y$10$Y6YJPBMF2YBqwaRsY1Z4s.ASdC5NekkyzwQIS9AI.55YkyZ9L9HaO', 'cliente'),
(12, 'Rafa', 'teste@teste.com', '$2y$10$AMCQAroGQAzJpWeMCtSGHeP9ucs.sOGo2Yq1szHDRDHbuAhe41OzS', 'cliente'),
(13, 'Joao', 'neto@dono.com', '$2y$10$lQOOf/mEhd2sUnckNHL6u.WqeLdBjzEfa8c.F3MWYrQRFk4MStbYi', 'dono');

--
-- Índices para tabelas despejadas
--

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
  ADD KEY `idx_pedidos_pagamento` (`status_pagamento`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT de tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restrições para tabelas despejadas
--

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
