-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-07-2025 a las 16:40:19
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `nexus_quiz`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `ID` int(11) NOT NULL COMMENT 'Identificador del empleado',
  `NOMBRE` varchar(255) NOT NULL COMMENT 'Nombre completo del empleado. Campo tipo Text. Solo debe permitir letras con o sin tilde y espacios. No se admiten caracteres especiales ni numeros.',
  `EMAIL` varchar(255) NOT NULL COMMENT 'Correo electronico del empleado. Campo tipo TextIEmail. solo permite una estructura de correo',
  `SEXO` char(1) NOT NULL COMMENT 'campo de tipo radio button. M para masculino. F para femenino',
  `AREA_ID` int(11) NOT NULL COMMENT 'Area de la empresa a la que pertenece el empleado. Campo de tipo select',
  `BOLETIN` int(11) NOT NULL COMMENT '1 para recibir boletin. 0 para no recibir boletin. Campo de tipo checkbox',
  `DESCRIPCION` text NOT NULL COMMENT 'Se describe la experiencia del usuario'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`ID`, `NOMBRE`, `EMAIL`, `SEXO`, `AREA_ID`, `BOLETIN`, `DESCRIPCION`) VALUES
(6, 'Andres Felipe Orozco Morales', 'aforozco23@gmail.com', 'M', 21, 1, 'Ambiente laboral magnifico');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `area_id` (`AREA_ID`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identificador del empleado', AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD CONSTRAINT `areas_area_id_empleados` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
