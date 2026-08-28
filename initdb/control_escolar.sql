
CREATE DATABASE IF NOT EXISTS control_escolar
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE control_escolar;

-- --------------------------------------------------------
-- TABLA GENERACIONES
-- --------------------------------------------------------

CREATE TABLE `generaciones` (
  `idGeneracion` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_generacion` varchar(50) NOT NULL,
  `fecha_inicio` year NOT NULL,
  `fecha_fin` year NOT NULL,
  PRIMARY KEY (`idGeneracion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `generaciones` (`idGeneracion`, `nombre_generacion`, `fecha_inicio`, `fecha_fin`) VALUES
(1, '2023-2026', 2023, 2026),
(2, '2024-2027', 2024, 2027),
(3, '2025-2028', 2025, 2028);

-- --------------------------------------------------------
-- TABLA ESTADO
-- --------------------------------------------------------

CREATE TABLE `estado` (
  `idEstado` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`idEstado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `estado` (`idEstado`, `descripcion`) VALUES
(1, 'Registrado'),
(2, 'Baja'),
(3, 'Preregistrado'),
(4, 'Egresado');

-- --------------------------------------------------------
-- TABLA GRUPO
-- --------------------------------------------------------

CREATE TABLE `grupo` (
  `idGrupo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_grupo` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`idGrupo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `grupo` (`nombre_grupo`) VALUES
('A'),
('B'),
('C');

-- --------------------------------------------------------
-- TABLA PLANTELES
-- --------------------------------------------------------

CREATE TABLE `planteles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `planteles` (`id`, `clave`, `nombre`) VALUES
(1, 'B23', 'Plantel B23'),
(2, 'B27', 'Plantel B27');

-- --------------------------------------------------------
-- TABLA CONFIG_SISTEMA
-- --------------------------------------------------------

CREATE TABLE `config_sistema` (
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `config_sistema` (`clave`, `valor`) VALUES
('ultima_sincronizacion_semestres', '');

-- --------------------------------------------------------
-- TABLA ALUMNO
-- idAlumno = numero de cuenta (no autoincremental)
-- --------------------------------------------------------

CREATE TABLE `alumno` (
  `idAlumno` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `apellido1` varchar(100) DEFAULT NULL,
  `apellido2` varchar(100) DEFAULT NULL,
  `semestre` int(11) DEFAULT NULL,
  `idGrupo` int(11) DEFAULT NULL,
  `idplantel` int(11) DEFAULT NULL,
  `idEstado` int(11) DEFAULT NULL,
  `idGeneracion` int(11) DEFAULT NULL,
  `ultima_actualizacion` date DEFAULT NULL,
  PRIMARY KEY (`idAlumno`),
  KEY `idGrupo` (`idGrupo`),
  KEY `idplantel` (`idplantel`),
  KEY `idEstado` (`idEstado`),
  KEY `idGeneracion` (`idGeneracion`),
  CONSTRAINT `alumno_ibfk_1`
    FOREIGN KEY (`idGrupo`) REFERENCES `grupo` (`idGrupo`),
  CONSTRAINT `alumno_ibfk_2`
    FOREIGN KEY (`idplantel`) REFERENCES `planteles` (`id`),
  CONSTRAINT `alumno_ibfk_3`
    FOREIGN KEY (`idEstado`) REFERENCES `estado` (`idEstado`),
  CONSTRAINT `alumno_ibfk_4`
    FOREIGN KEY (`idGeneracion`) REFERENCES `generaciones` (`idGeneracion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- TABLA ASISTENCIAS
-- ON DELETE RESTRICT: conserva historial al eliminar alumno
-- --------------------------------------------------------

CREATE TABLE `asistencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('Asistencia','Falta') DEFAULT 'Asistencia',
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asistencia` (`alumno_id`, `fecha`),
  CONSTRAINT `asistencias_ibfk_1`
    FOREIGN KEY (`alumno_id`) REFERENCES `alumno` (`idAlumno`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- TABLA ASISTENCIAS_PENDIENTES (QR sin registro completo)
-- --------------------------------------------------------

CREATE TABLE `asistencias_pendientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `num_cuenta` int(11) NOT NULL,
  `idplantel` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` time NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pendiente_dia` (`num_cuenta`, `idplantel`, `fecha`),
  KEY `idplantel` (`idplantel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- TABLA USUARIOS
-- --------------------------------------------------------

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `idplantel` int(11) DEFAULT NULL,
  `rol` enum('admin','user') DEFAULT 'admin',
  PRIMARY KEY (`id`),
  KEY `idplantel` (`idplantel`),
  CONSTRAINT `usuarios_ibfk_1`
    FOREIGN KEY (`idplantel`) REFERENCES `planteles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `usuarios` (`usuario`, `password`, `idplantel`, `rol`) VALUES
('b23', '1234', 1, 'admin'),
('b27', '1234', 2, 'admin');

COMMIT;


-- --------------------------------------------------------
-- seleccion de horario 
-- --------------------------------------------------------


CREATE USER IF NOT EXISTS 'appuser'@'%' IDENTIFIED BY '54473c73023774aaa60671f8b979dee8';
GRANT ALL PRIVILEGES ON control_escolar.* TO 'appuser'@'%';
FLUSH PRIVILEGES;
