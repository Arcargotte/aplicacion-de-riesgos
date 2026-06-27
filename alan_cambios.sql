-- Crear tabla de víctimas adaptada al esquema actual
CREATE TABLE IF NOT EXISTS `victimas` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `centro_id` int(11) NOT NULL,
    `usuario_id` int(11) NOT NULL, -- Voluntario o admin que registra
    `nombre_apellido` varchar(150),
    `cedula` varchar(20) DEFAULT NULL, -- Se cambia a varchar(20) y DEFAULT NULL para ser compatible/coherente con la tabla personas
    `edad_aproximada` int(11) NOT NULL,
    `estado_logistico` enum('Sin Atender', 'Atendido', 'Despachado', 'Fallecido') NOT NULL DEFAULT 'Sin Atender',
    `gravedad_triaje` enum('Leve', 'Moderado', 'Crítico') NOT NULL,
    `sintoma_principal` varchar(255) NOT NULL,
    `red_o_apoyo` enum('Sí', 'No') NOT NULL DEFAULT 'No', -- Corregido typo 'red_of_apoyo' a 'red_o_apoyo'
    `observaciones` text DEFAULT NULL,
    `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `centro_id` (`centro_id`),
    KEY `usuario_id` (`usuario_id`),
    CONSTRAINT `victimas_ibfk_1` FOREIGN KEY (`centro_id`) REFERENCES `centros_acopio` (`id`) ON DELETE CASCADE,
    CONSTRAINT `victimas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;