-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 28, 2026 at 02:49 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ong_bd`
--

-- --------------------------------------------------------

--
-- Table structure for table `centros_acopio`
--

CREATE TABLE `centros_acopio` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `es_sede_principal` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `centros_acopio`
--

INSERT INTO `centros_acopio` (`id`, `nombre`, `direccion`, `telefono`, `es_sede_principal`, `activo`) VALUES
(1, 'Sede Central Montalbán', 'Caracas', NULL, 1, 1),
(2, 'Centro Catia La Mar', 'Catia La Mar', '04121231234', 0, 1),
(3, 'Centro de Acopio Petare', 'Caracas', '', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `detalles_movimiento`
--

CREATE TABLE `detalles_movimiento` (
  `id` int(11) NOT NULL,
  `movimiento_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detalles_movimiento`
--

INSERT INTO `detalles_movimiento` (`id`, `movimiento_id`, `insumo_id`, `cantidad`) VALUES
(1, 1, 1, 3.00),
(2, 2, 2, 2.00),
(3, 2, 1, 3.00),
(4, 3, 3, 10.00),
(5, 3, 1, 5.00),
(6, 4, 2, 2.00),
(7, 4, 1, 1.00),
(8, 4, 3, 1.00),
(9, 5, 1, 7.00),
(10, 6, 1, 5.00),
(11, 7, 4, 20.00),
(12, 7, 5, 10.00),
(13, 8, 4, 10.00),
(14, 9, 2, 3.00),
(15, 10, 2, 3.00),
(16, 11, 1, 1.00),
(17, 12, 6, 30.00);

-- --------------------------------------------------------

--
-- Table structure for table `detalle_solicitudes`
--

CREATE TABLE `detalle_solicitudes` (
  `id` int(11) NOT NULL,
  `solicitud_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL COMMENT 'ID del insumo solicitado',
  `cantidad` decimal(10,2) NOT NULL COMMENT 'Cantidad solicitada (soporta decimales ej. 5.50 kg)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detalle_solicitudes`
--

INSERT INTO `detalle_solicitudes` (`id`, `solicitud_id`, `insumo_id`, `cantidad`) VALUES
(1, 1, 1, 11.00),
(2, 2, 1, 7.00);

-- --------------------------------------------------------

--
-- Table structure for table `insumos`
--

CREATE TABLE `insumos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `unidad_medida` varchar(30) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insumos`
--

INSERT INTO `insumos` (`id`, `nombre`, `unidad_medida`, `descripcion`) VALUES
(1, 'Harina', 'kilogramos', NULL),
(2, 'Gasas', 'unidades', NULL),
(3, 'Papas', 'unidades', NULL),
(4, 'Pasta Larga', 'kilogramos', NULL),
(5, 'Arroz', 'kilogramos', NULL),
(6, 'Harina de Batata', 'kilogramos', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventario`
--

CREATE TABLE `inventario` (
  `id` int(11) NOT NULL,
  `centro_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventario`
--

INSERT INTO `inventario` (`id`, `centro_id`, `insumo_id`, `cantidad`, `ultima_actualizacion`) VALUES
(1, 1, 1, 2.00, '2026-06-27 20:04:45'),
(2, 1, 2, 0.00, '2026-06-27 19:51:36'),
(3, 1, 3, 9.00, '2026-06-27 17:55:29'),
(5, 2, 2, 2.00, '2026-06-27 17:55:29'),
(6, 2, 1, 3.00, '2026-06-27 18:37:53'),
(7, 2, 3, 1.00, '2026-06-27 17:55:29'),
(8, 2, 4, 10.00, '2026-06-27 19:08:05'),
(9, 2, 5, 10.00, '2026-06-27 18:42:02'),
(10, 1, 4, 10.00, '2026-06-27 19:08:05'),
(11, 1, 6, 30.00, '2026-06-27 22:10:38');

-- --------------------------------------------------------

--
-- Table structure for table `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','traslado') NOT NULL,
  `centro_origen_id` int(11) DEFAULT NULL,
  `centro_destino_id` int(11) DEFAULT NULL,
  `persona_id` int(11) DEFAULT NULL,
  `organizacion_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `detalles` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movimientos`
--

INSERT INTO `movimientos` (`id`, `tipo_movimiento`, `centro_origen_id`, `centro_destino_id`, `persona_id`, `organizacion_id`, `usuario_id`, `fecha`, `detalles`) VALUES
(1, 'salida', 1, NULL, 1, NULL, 1, '2026-06-27 17:23:59', 'Se despacha por x razón'),
(2, 'salida', 1, NULL, NULL, 1, 1, '2026-06-27 17:28:45', 'Lo requieren con urgencia'),
(3, 'entrada', NULL, 1, 2, NULL, 1, '2026-06-27 17:29:51', 'Todo en orden'),
(4, 'traslado', 1, 2, NULL, NULL, 1, '2026-06-27 17:55:29', 'Lo necesitan'),
(5, '', 1, 2, NULL, NULL, 1, '2026-06-27 18:36:00', 'Donación / Traslado por aprobación de Solicitud #2'),
(6, 'salida', 2, NULL, 3, NULL, 2, '2026-06-27 18:37:53', 'El afectado requería estas harinas para su familia.'),
(7, 'entrada', NULL, 2, 2, NULL, 2, '2026-06-27 18:42:02', 'Caja de insumos.'),
(8, 'traslado', 2, 1, NULL, NULL, 2, '2026-06-27 19:08:05', 'Devolucion'),
(9, 'salida', 1, NULL, 4, NULL, 1, '2026-06-27 19:50:36', 'donacion'),
(10, 'salida', 1, NULL, 4, NULL, 1, '2026-06-27 19:51:36', 'd'),
(11, 'salida', 1, NULL, 5, NULL, 1, '2026-06-27 20:04:45', 'fill'),
(12, 'entrada', NULL, 1, NULL, 2, 1, '2026-06-27 22:10:38', 'Insumos entregados');

-- --------------------------------------------------------

--
-- Table structure for table `organizaciones`
--

CREATE TABLE `organizaciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` enum('ONG','Empresa','Fundacion','Gobierno','Otro') DEFAULT 'Otro',
  `telefono` varchar(40) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizaciones`
--

INSERT INTO `organizaciones` (`id`, `nombre`, `tipo`, `telefono`, `direccion`) VALUES
(1, 'ONG Caracas', 'ONG', '04123412123', 'Desconocida'),
(2, 'Samaritan´s Purse', 'ONG', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `personas`
--

CREATE TABLE `personas` (
  `id` int(11) NOT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `apellido` varchar(120) NOT NULL,
  `telefono` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personas`
--

INSERT INTO `personas` (`id`, `cedula`, `nombre`, `apellido`, `telefono`) VALUES
(1, '28148442', 'David', 'Díaz', '04127340311'),
(2, NULL, 'Juan Pérez', '', NULL),
(3, '23445665', 'Alan', 'Perez', '04127334562'),
(4, 'Desconocido', 'Desconocido', 'Desconocido', 'Desconocido'),
(5, 'X-XXXXXXXXX', 'Desconocido', '', 'XXXX-XXXXXXX');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'administrador_central'),
(2, 'administrador_centro'),
(3, 'voluntario');

-- --------------------------------------------------------

--
-- Table structure for table `solicitudes`
--

CREATE TABLE `solicitudes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Voluntario que hace la solicitud',
  `centro_id` int(11) NOT NULL COMMENT 'Centro de acopio que necesita los insumos',
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `motivo_rechazo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `solicitudes`
--

INSERT INTO `solicitudes` (`id`, `usuario_id`, `centro_id`, `fecha_hora`, `estado`, `motivo_rechazo`) VALUES
(1, 2, 2, '2026-06-27 14:29:30', 'rechazado', 'Solo podemos darte 10 kilos de Harina.'),
(2, 2, 2, '2026-06-27 14:35:43', 'aprobado', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `rol_id` int(11) NOT NULL,
  `centro_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `rol_id`, `centro_id`, `nombre`, `usuario`, `clave`, `activo`) VALUES
(1, 1, 1, 'Administrador General', 'admin', '$2y$10$FcYGq1Z5TzYN6tMJ2HROL.nQnrxRqUuM4MhTpP3A2LkJwnb88JpX6', 1),
(2, 2, 2, 'Alan Argotte', 'aargotte', '$2y$10$xGnSYgSisg59C.VbHIPmBuYS1XXXNrlbzGFyFvsmwzzfQSqiBofc6', 1),
(4, 1, 1, 'David Diaz', 'ddiaz', '$2y$10$MEC1XMkLR9sWSWcuT0vcxuqhNNvkuBh6wjdcBVQKSTgb6rlnEaFve', 1);

-- --------------------------------------------------------

--
-- Table structure for table `victimas`
--

CREATE TABLE `victimas` (
  `id` int(11) NOT NULL,
  `centro_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `nombre_apellido` varchar(150) DEFAULT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `edad_aproximada` int(11) NOT NULL,
  `estado_logistico` enum('Sin Atender','Atendido','Despachado','Fallecido') NOT NULL DEFAULT 'Sin Atender',
  `gravedad_triaje` enum('Leve','Moderado','Crítico') NOT NULL,
  `sintoma_principal` varchar(255) NOT NULL,
  `red_o_apoyo` enum('Sí','No') NOT NULL DEFAULT 'No',
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `victimas`
--

INSERT INTO `victimas` (`id`, `centro_id`, `usuario_id`, `nombre_apellido`, `cedula`, `edad_aproximada`, `estado_logistico`, `gravedad_triaje`, `sintoma_principal`, `red_o_apoyo`, `observaciones`, `fecha_registro`) VALUES
(6, 1, 1, 'Desconocido', 'X-XXXXXXXXX', 23, 'Atendido', 'Leve', 'Fractura', 'Sí', NULL, '2026-06-27 20:19:41'),
(7, 1, 1, 'Desconocido', 'X-XXXXXXXXX', 23, 'Sin Atender', 'Leve', 'Fractura', 'Sí', 'test', '2026-06-27 20:29:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `centros_acopio`
--
ALTER TABLE `centros_acopio`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `detalles_movimiento`
--
ALTER TABLE `detalles_movimiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movimiento_id` (`movimiento_id`),
  ADD KEY `insumo_id` (`insumo_id`);

--
-- Indexes for table `detalle_solicitudes`
--
ALTER TABLE `detalle_solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detalle_solicitud` (`solicitud_id`),
  ADD KEY `fk_detalle_insumo` (`insumo_id`);

--
-- Indexes for table `insumos`
--
ALTER TABLE `insumos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `centro_id` (`centro_id`,`insumo_id`),
  ADD KEY `insumo_id` (`insumo_id`);

--
-- Indexes for table `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `centro_origen_id` (`centro_origen_id`),
  ADD KEY `centro_destino_id` (`centro_destino_id`),
  ADD KEY `persona_id` (`persona_id`),
  ADD KEY `organizacion_id` (`organizacion_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indexes for table `organizaciones`
--
ALTER TABLE `organizaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `personas`
--
ALTER TABLE `personas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_solicitud_usuario` (`usuario_id`),
  ADD KEY `fk_solicitud_centro` (`centro_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `rol_id` (`rol_id`),
  ADD KEY `centro_id` (`centro_id`);

--
-- Indexes for table `victimas`
--
ALTER TABLE `victimas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `centro_id` (`centro_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `centros_acopio`
--
ALTER TABLE `centros_acopio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `detalles_movimiento`
--
ALTER TABLE `detalles_movimiento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `detalle_solicitudes`
--
ALTER TABLE `detalle_solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `organizaciones`
--
ALTER TABLE `organizaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `personas`
--
ALTER TABLE `personas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `victimas`
--
ALTER TABLE `victimas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detalles_movimiento`
--
ALTER TABLE `detalles_movimiento`
  ADD CONSTRAINT `detalles_movimiento_ibfk_1` FOREIGN KEY (`movimiento_id`) REFERENCES `movimientos` (`id`),
  ADD CONSTRAINT `detalles_movimiento_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`);

--
-- Constraints for table `detalle_solicitudes`
--
ALTER TABLE `detalle_solicitudes`
  ADD CONSTRAINT `fk_detalle_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`),
  ADD CONSTRAINT `fk_detalle_solicitud` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `inventario_ibfk_1` FOREIGN KEY (`centro_id`) REFERENCES `centros_acopio` (`id`),
  ADD CONSTRAINT `inventario_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`);

--
-- Constraints for table `movimientos`
--
ALTER TABLE `movimientos`
  ADD CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`centro_origen_id`) REFERENCES `centros_acopio` (`id`),
  ADD CONSTRAINT `movimientos_ibfk_2` FOREIGN KEY (`centro_destino_id`) REFERENCES `centros_acopio` (`id`),
  ADD CONSTRAINT `movimientos_ibfk_3` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`),
  ADD CONSTRAINT `movimientos_ibfk_4` FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones` (`id`),
  ADD CONSTRAINT `movimientos_ibfk_5` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `fk_solicitud_centro` FOREIGN KEY (`centro_id`) REFERENCES `centros_acopio` (`id`),
  ADD CONSTRAINT `fk_solicitud_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`centro_id`) REFERENCES `centros_acopio` (`id`);

--
-- Constraints for table `victimas`
--
ALTER TABLE `victimas`
  ADD CONSTRAINT `victimas_ibfk_1` FOREIGN KEY (`centro_id`) REFERENCES `centros_acopio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `victimas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
