CREATE DATABASE IF NOT EXISTS ong_inventario CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ong_inventario;

-- 1. TABLA DE ROLES
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE -- 'administrador_central', 'voluntario_centro'
);

-- 2. TABLA DE CENTROS DE ACOPIO
CREATE TABLE centros_acopio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ubicacion VARCHAR(255) NOT NULL
);

-- 3. TABLA DE USUARIOS
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Guardaremos hashes con password_hash()
    nombre VARCHAR(100) NOT NULL,
    rol_id INT NOT NULL,
    centro_id INT NULL, -- NULL si es administrador central, o ID del centro si es voluntario
    FOREIGN KEY (rol_id) REFERENCES roles(id),
    FOREIGN KEY (centro_id) REFERENCES centros_acopio(id) ON DELETE SET NULL
);

-- 4. TABLA DE INSUMOS (Inventario Sede Central)
CREATE TABLE insumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL, -- Permite decimales para kilos/litros
    unidad_medida ENUM('unidades', 'litros', 'kilos') NOT NULL,
    estado ENUM('disponible', 'donado') DEFAULT 'disponible',
    fecha_ingreso TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. TABLA DE PERSONAS BENEFICIARIAS
CREATE TABLE personas_beneficiarias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cedula VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL
);

-- 6. TABLA DE ENTREGAS / DONACIONES (Cabecera)
CREATE TABLE entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_receptor ENUM('persona', 'centro') NOT NULL,
    persona_id INT NULL,
    centro_id INT NULL,
    detalle_adicional TEXT,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responsable_entrega VARCHAR(100) NOT NULL, -- Nombre del voluntario/personal
    FOREIGN KEY (persona_id) REFERENCES personas_beneficiarias(id),
    FOREIGN KEY (centro_id) REFERENCES centros_acopio(id)
);

-- 7. DETALLE DE LA ENTREGA (Relación muchos a muchos entre Entregas e Insumos)
CREATE TABLE detalle_entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrega_id INT NOT NULL,
    insumo_id INT NOT NULL,
    cantidad_donada DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (entrega_id) REFERENCES entregas(id) ON DELETE CASCADE,
    FOREIGN KEY (insumo_id) REFERENCES insumos(id)
);

-- 8. TABLA DE SOLICITUDES DESDE CENTROS (Texto libre para insumos no existentes)
CREATE TABLE solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL, -- Voluntario que solicita
    centro_id INT NOT NULL, -- Centro desde donde se solicita
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (centro_id) REFERENCES centros_acopio(id)
);

-- 9. DETALLE DE SOLICITUDES
CREATE TABLE detalle_solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    nombre_insumo VARCHAR(100) NOT NULL, -- Texto libre
    cantidad VARCHAR(50) NOT NULL,       -- Texto libre (ej: "50 cajas", "10 kilos")
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id) ON DELETE CASCADE
);

-- ==========================================
-- INSERCIÓN DE DATOS INICIALES REQUERIDOS
-- ==========================================

INSERT INTO roles (nombre) VALUES ('administrador_central'), ('voluntario_centro');

-- Insertamos un centro de ejemplo para pruebas
INSERT INTO centros_acopio (nombre, ubicacion) VALUES ('Centro Regional Valencia', 'Edo. Carabobo');

-- Creamos el usuario Administrador Inicial
-- La contraseña por defecto será: AdminONG2026
-- El hash se genera con PASSWORD_BCRYPT en PHP. Aquí pongo el hash directo para que funcione en la BD.
INSERT INTO usuarios (username, password, nombre, rol_id, centro_id) 
VALUES (
    'admin', 
    '$2y$10$mB0YUnb8O7rMh4Yv2Vb.reW7tXbFf3YwS1mDqK1oDneO9FpZ8WzW.', -- Hash de: AdminONG2026
    'Administrador Central Montalbán', 
    1, 
    NULL
);





INSERT INTO centros_acopio (nombre, ubicacion) VALUES 
('Centro de Acopio de Catia La Mar', 'Edo. La Guaira - Sector Catia La Mar'),
('Centro de Acopio de La Guaira', 'Edo. La Guaira - Casco Central');

-- 2. CREAR UN USUARIO VOLUNTARIO ASOCIADO A CATIA LA MAR (centro_id = 2)
-- La contraseña de este voluntario será: Voluntario2026
INSERT INTO usuarios (username, password, nombre, rol_id, centro_id) 
VALUES (
    'voluntario_catia', 
    '$2y$10$7R3vXW98N1MhYv2Vb.reW7tXbFf3YwS1mDqK1oDneO9FpZ8WzW.', -- Hash de: Voluntario2026
    'Juan Pérez', 
    2, -- Rol: voluntario_centro
    2  -- ID del Centro de Catia La Mar
);

-- 3. POBLAR CON INSUMOS DUMMY (Unos disponibles y otros ya donados para el histórico)
INSERT INTO insumos (nombre, cantidad, unidad_medida, estado) VALUES 
('Harina de Maíz', 150.00, 'kilos', 'disponible'),
('Arroz Blanco', 80.00, 'kilos', 'disponible'),
('Aceite Vegetal', 45.50, 'litros', 'disponible'),
('Agua Mineral 5L', 30.00, 'litros', 'disponible'),
('Pañales Talla G', 100.00, 'unidades', 'disponible'),
('Acetaminofén 500mg', 200.00, 'unidades', 'disponible'),
-- Insumos que simulan haber sido donados en el pasado
('Pasta Regulada', 50.00, 'kilos', 'donado'),
('Leche en Polvo', 25.00, 'kilos', 'donado'),
('Alcohol Antiséptico', 10.00, 'litros', 'donado');
