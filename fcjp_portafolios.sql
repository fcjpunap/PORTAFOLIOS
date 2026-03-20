-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 20, 2026 at 06:06 PM
-- Server version: 11.8.6-MariaDB-0+deb13u1 from Debian
-- PHP Version: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tu_base_de_datos`
--

-- --------------------------------------------------------

--
-- Table structure for table `actividades_fichas`
--

CREATE TABLE `actividades_fichas` (
  `id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `titulo_caso` varchar(200) NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` datetime NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_limite` datetime DEFAULT NULL,
  `estructura_campos` text DEFAULT NULL,
  `tipo_trabajo` varchar(20) DEFAULT 'Grupal',
  `max_integrantes` int(11) DEFAULT 5,
  `secciones_json` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `actividades_fichas`
--

INSERT INTO `actividades_fichas` (`id`, `curso_id`, `titulo_caso`, `fecha_inicio`, `fecha_cierre`, `descripcion`, `fecha_limite`, `estructura_campos`, `tipo_trabajo`, `max_integrantes`, `secciones_json`) VALUES
(10, 1, 'Semana 1: Delito de Rebelión (Art. 346)', '2026-03-10 08:00:00', '2026-03-20 23:59:00', 'Análisis del caso Asonada en el sur', '2026-03-20 23:59:00', NULL, 'Grupal', 5, '[{\"titulo\":\"Factum\",\"guia\":\"Hechos relevantes\"},{\"titulo\":\"Tipicidad\",\"guia\":\"Tipo penal\"},{\"titulo\":\"Dogmatica\",\"guia\":\"Análisis de la teoría del delito\"},{\"titulo\":\"Fallo\",\"guia\":\"Conclusión\"}]'),
(11, 1, 'Semana 2: Sedición y Motín', '2026-03-15 08:00:00', '2026-03-27 23:59:00', 'Caso hipotético: Toma de instalaciones estratégicas', '2026-03-27 23:59:00', '[{\"titulo\":\"Factum\",\"guia\":\"Redacte un resumen cronológico de los hechos.\"},{\"titulo\":\"Juicio de Tipicidad\",\"guia\":\"Indique y sustente el tipo penal aplicable.\"},{\"titulo\":\"Análisis Dogmático\",\"guia\":\"Analice la antijuridicidad y culpabilidad.\"},{\"titulo\":\"Fallo o Conclusión\",\"guia\":\"Emita su conclusión final del caso.\"}]', 'Grupal', 5, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `anexos`
--

CREATE TABLE `anexos` (
  `id` int(11) NOT NULL,
  `envio_id` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `tipo_archivo` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cursos`
--

CREATE TABLE `cursos` (
  `id` int(11) NOT NULL,
  `nombre_curso` varchar(150) NOT NULL,
  `docente_id` int(11) NOT NULL,
  `semestre` varchar(10) NOT NULL,
  `nombre` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cursos`
--

INSERT INTO `cursos` (`id`, `nombre_curso`, `docente_id`, `semestre`, `nombre`) VALUES
(1, 'Derecho Penal Especial III (Grupo B) - 2026 -1', 1, '2026-1', 'Derecho Penal Especial III (Grupo B)');

-- --------------------------------------------------------

--
-- Table structure for table `envios_fichas`
--

CREATE TABLE `envios_fichas` (
  `id` int(11) NOT NULL,
  `actividad_id` int(11) NOT NULL,
  `lider_id` int(11) NOT NULL,
  `factum` text NOT NULL,
  `tipicidad` text NOT NULL,
  `dogmatica` text NOT NULL,
  `jurisprudencia` text NOT NULL,
  `fallo` text NOT NULL,
  `fecha_envio` timestamp NULL DEFAULT current_timestamp(),
  `estado` varchar(50) DEFAULT 'Pendiente',
  `calificacion` decimal(5,2) DEFAULT NULL,
  `respuestas_json` text DEFAULT NULL,
  `retroalimentacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `envios_fichas`
--

INSERT INTO `envios_fichas` (`id`, `actividad_id`, `lider_id`, `factum`, `tipicidad`, `dogmatica`, `jurisprudencia`, `fallo`, `fecha_envio`, `estado`, `calificacion`, `respuestas_json`, `retroalimentacion`) VALUES
(4, 10, 2, 'El día 15 de marzo, un grupo de ciudadanos organizados tomó control de las vías principales...', 'El comportamiento se subsume en el tipo penal de Rebelión (Art. 346 CP)...', 'Existe coautoría funcional. Según la teoría del dominio del hecho (Roxin)...', 'Se aplica el criterio vinculante de la Casación N° 123-2022/Puno...', 'Culpables como coautores no ejecutivos', '2026-03-18 08:12:13', 'Revisado', 18.00, NULL, NULL),
(5, 11, 4, 'Hechos del segundo caso...', 'Tipicidad...', 'Dogmática...', 'Jurisprudencia...', 'Absolución por atipicidad', '2026-03-18 08:12:13', 'Revisado', 16.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `envio_integrantes`
--

CREATE TABLE `envio_integrantes` (
  `envio_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `envio_integrantes`
--

INSERT INTO `envio_integrantes` (`envio_id`, `estudiante_id`) VALUES
(4, 2),
(4, 3),
(5, 3),
(5, 4);

-- --------------------------------------------------------

--
-- Table structure for table `matriculas`
--

CREATE TABLE `matriculas` (
  `id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `estudiante_id` int(11) NOT NULL,
  `fecha_matricula` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `matriculas`
--

INSERT INTO `matriculas` (`id`, `curso_id`, `estudiante_id`, `fecha_matricula`) VALUES
(35, 1, 64, '2026-03-20 04:27:16'),
(36, 1, 65, '2026-03-20 04:27:17'),
(37, 1, 66, '2026-03-20 04:27:17'),
(38, 1, 67, '2026-03-20 04:27:17'),
(39, 1, 68, '2026-03-20 04:27:18'),
(40, 1, 69, '2026-03-20 04:27:18'),
(41, 1, 70, '2026-03-20 04:27:19'),
(42, 1, 71, '2026-03-20 04:27:19'),
(43, 1, 72, '2026-03-20 04:27:19'),
(44, 1, 73, '2026-03-20 04:27:20'),
(45, 1, 74, '2026-03-20 04:27:20'),
(46, 1, 75, '2026-03-20 04:27:20'),
(47, 1, 76, '2026-03-20 04:27:21'),
(48, 1, 77, '2026-03-20 04:27:21'),
(49, 1, 78, '2026-03-20 04:27:21'),
(50, 1, 79, '2026-03-20 04:27:22'),
(51, 1, 80, '2026-03-20 04:27:22'),
(52, 1, 81, '2026-03-20 04:27:23'),
(53, 1, 82, '2026-03-20 04:27:23'),
(54, 1, 83, '2026-03-20 04:27:23'),
(55, 1, 84, '2026-03-20 04:27:24'),
(56, 1, 85, '2026-03-20 04:27:24'),
(57, 1, 86, '2026-03-20 04:27:24'),
(58, 1, 87, '2026-03-20 04:27:25'),
(59, 1, 88, '2026-03-20 04:27:25'),
(60, 1, 89, '2026-03-20 04:27:25'),
(61, 1, 90, '2026-03-20 04:27:26'),
(62, 1, 91, '2026-03-20 04:27:26'),
(63, 1, 92, '2026-03-20 04:27:27'),
(64, 1, 93, '2026-03-20 04:27:27'),
(65, 1, 94, '2026-03-20 04:27:27'),
(66, 1, 95, '2026-03-20 04:27:28'),
(67, 1, 96, '2026-03-20 04:27:28'),
(68, 1, 97, '2026-03-20 04:27:28');

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `rol` enum('admin','docente','estudiante') NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `codigo_estudiante` varchar(20) DEFAULT NULL,
  `ciclo` varchar(10) DEFAULT NULL,
  `semestre` varchar(10) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_exp` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `rol`, `nombres`, `apellidos`, `email`, `password`, `codigo_estudiante`, `ciclo`, `semestre`, `estado`, `created_at`, `reset_token`, `reset_token_exp`) VALUES
(1, 'docente', 'Usuario 1', 'Anónimo', 'user1@example.com', '$2y$12$WoTdrL0pfrRpOUl7w9WAm.oSSp9ipld89b7piJW4D0k376I9qxcvm', NULL, NULL, NULL, 1, '2026-03-18 07:46:02', '97325539e8eed6814878be556b6c3033672c17ebb2297c5c280ad944572c7caf', '2026-03-19 04:46:37'),
(2, 'estudiante', 'Usuario 2', 'Anónimo', 'user2@example.com', '$2y$12$ZbOKThkfAYufUGLP02MFSOIyMJE1xTzhSIi0BzBrZzrrVA.QMQtt.', '123456', NULL, NULL, 1, '2026-03-18 08:00:27', NULL, NULL),
(3, 'estudiante', 'Usuario 3', 'Anónimo', 'user3@example.com', '$2y$12$53N6BjNFGOEbY44Z76Ku7uuaxRhQaLx6VMJ/3DXqnmYbc/2YL/WQa', '123457', NULL, NULL, 1, '2026-03-18 08:00:27', NULL, NULL),
(4, 'estudiante', 'Usuario 4', 'Anónimo', 'user4@example.com', '$2y$12$53N6BjNFGOEbY44Z76Ku7uuaxRhQaLx6VMJ/3DXqnmYbc/2YL/WQa', '123458', NULL, NULL, 1, '2026-03-18 08:00:27', NULL, NULL),
(29, 'admin', 'Usuario 29', 'Anónimo', 'user29@example.com', '$2y$12$fiHaBj3rHcp6zVWhq49M7eadS9fFVliFB6y3edipE03ObCLSYiGjm', '', NULL, NULL, 1, '2026-03-18 07:46:02', NULL, NULL),
(64, 'estudiante', 'Usuario 64', 'Anónimo', 'user64@example.com', '$2y$12$kCkQzoaKe96DwY.5afxcT.zHsdpyHJDekt/HEKUYv4Xv68dXuWO3q', '215822', NULL, NULL, 1, '2026-03-20 04:27:16', NULL, NULL),
(65, 'estudiante', 'Usuario 65', 'Anónimo', 'user65@example.com', '$2y$12$I/PtPfsFZz5LJaibj3Ge7ubnsteTMR.9WGuIlPjcKUB5jbej4H.w2', '216486', NULL, NULL, 1, '2026-03-20 04:27:17', NULL, NULL),
(66, 'estudiante', 'Usuario 66', 'Anónimo', 'user66@example.com', '$2y$12$FDfvRXCFU7LYaDtCWTuUIu8XBGuBSdMFjD.TX7rKdfXdNqy0EJgm2', '213944', NULL, NULL, 1, '2026-03-20 04:27:17', NULL, NULL),
(67, 'estudiante', 'Usuario 67', 'Anónimo', 'user67@example.com', '$2y$12$h/2g6RXlrYNtU6SQh0uHS.8WscC/2q0w6Dp4DcpNv6wnQJJqp3rJK', '216269', NULL, NULL, 1, '2026-03-20 04:27:17', NULL, NULL),
(68, 'estudiante', 'Usuario 68', 'Anónimo', 'user68@example.com', '$2y$12$BFLCjzLwEcqLbe1SJRn84e/7ybTeh0BxBJnuN2.N7241lKBgvEVE.', '215657', NULL, NULL, 1, '2026-03-20 04:27:18', NULL, NULL),
(69, 'estudiante', 'Usuario 69', 'Anónimo', 'user69@example.com', '$2y$12$SV8wIryp4yswHUm19q9CuegJQBA9VEwErwLD167p.MwntePULU.dK', '215677', NULL, NULL, 1, '2026-03-20 04:27:18', NULL, NULL),
(70, 'estudiante', 'Usuario 70', 'Anónimo', 'user70@example.com', '$2y$12$qaJhCdezYfZU./m0SRyVO.twjDd6AhMnjnjSBV11MVJJWwQGVA.va', '215903', NULL, NULL, 1, '2026-03-20 04:27:19', NULL, NULL),
(71, 'estudiante', 'Usuario 71', 'Anónimo', 'user71@example.com', '$2y$12$tEz0p1bwMirOeXSYNwgVhesoRz1InvH3R0E7E.UU54iD6XTmes13q', '216148', NULL, NULL, 1, '2026-03-20 04:27:19', NULL, NULL),
(72, 'estudiante', 'Usuario 72', 'Anónimo', 'user72@example.com', '$2y$12$uWNYjCM5TIIaq1kbLUTjau9PB5hoswElMoumGvq6/hfQVu0gtPwx2', '215678', NULL, NULL, 1, '2026-03-20 04:27:19', NULL, NULL),
(73, 'estudiante', 'Usuario 73', 'Anónimo', 'user73@example.com', '$2y$12$AxAllWpftcFIN/dl/wn4e.TKlntJZCldvICxcDDIcrG3lSWyEru4G', '216397', NULL, NULL, 1, '2026-03-20 04:27:20', NULL, NULL),
(74, 'estudiante', 'Usuario 74', 'Anónimo', 'user74@example.com', '$2y$12$hMxgCAWg97.ULVXLLa76HOsFQl9ZPTGE/DtTi4rJth0BGI8lLh/M2', '215665', NULL, NULL, 1, '2026-03-20 04:27:20', NULL, NULL),
(75, 'estudiante', 'Usuario 75', 'Anónimo', 'user75@example.com', '$2y$12$wWS7krV1wxesLe7xwULPN.TWiWKs.NguSdjgMm3mwuK66Kvitidmy', '215650', NULL, NULL, 1, '2026-03-20 04:27:20', NULL, NULL),
(76, 'estudiante', 'Usuario 76', 'Anónimo', 'user76@example.com', '$2y$12$efkrRrhaOn5A4thYHHhYveZaz8oY5U5zAG2ECHU/F6eRrrzJBBkKW', '215451', NULL, NULL, 1, '2026-03-20 04:27:21', NULL, NULL),
(77, 'estudiante', 'Usuario 77', 'Anónimo', 'user77@example.com', '$2y$12$1j9NI2thtIpdCH87uBE26u1dRdxJXzHj8EwB9ZNA9kY6XJCP6eb8q', '215699', NULL, NULL, 1, '2026-03-20 04:27:21', NULL, NULL),
(78, 'estudiante', 'Usuario 78', 'Anónimo', 'user78@example.com', '$2y$12$OjTz1ORWR5HtBZTOcWcW0.zTaW/m3.YS7kmxSLIupW5rtfSKXGzpW', '215561', NULL, NULL, 1, '2026-03-20 04:27:21', NULL, NULL),
(79, 'estudiante', 'Usuario 79', 'Anónimo', 'user79@example.com', '$2y$12$ADBhzcq0vmIP0UVbrbNcxO/U3fiXQtI/0GeqhJlB6Mf6wGR0QWA4O', '215522', NULL, NULL, 1, '2026-03-20 04:27:22', NULL, NULL),
(80, 'estudiante', 'Usuario 80', 'Anónimo', 'user80@example.com', '$2y$12$GiDx.zNk6zx3KMVPI6H6Lup/43/n/W/RiwBcC13cOADnob6.Q7sm.', '216353', NULL, NULL, 1, '2026-03-20 04:27:22', NULL, NULL),
(81, 'estudiante', 'Usuario 81', 'Anónimo', 'user81@example.com', '$2y$12$NGtkJvAR79OKtdUjhs7Rh.DLfF2q.QUpOsreJLEvMMCcPzuNJUHfa', '215454', NULL, NULL, 1, '2026-03-20 04:27:23', NULL, NULL),
(82, 'estudiante', 'Usuario 82', 'Anónimo', 'user82@example.com', '$2y$12$/UPzPSUWESnXCJKBQqoROOSd2.XAmhmwL33N9.AYWSOkVIHGDmBIu', '216538', NULL, NULL, 1, '2026-03-20 04:27:23', NULL, NULL),
(83, 'estudiante', 'Usuario 83', 'Anónimo', 'user83@example.com', '$2y$12$DNxu.Zzhw10rC8cBm7GCi.1MhcJ9d7NL65P30R1o4eLrfQFb0gLNW', '216515', NULL, NULL, 1, '2026-03-20 04:27:23', NULL, NULL),
(84, 'estudiante', 'Usuario 84', 'Anónimo', 'user84@example.com', '$2y$12$XvaXk9PoCWviOkz2MCRz4.xLwlv4O8sbYmcvlRmOK8t4FjLRgKk4W', '216362', NULL, NULL, 1, '2026-03-20 04:27:24', NULL, NULL),
(85, 'estudiante', 'Usuario 85', 'Anónimo', 'user85@example.com', '$2y$12$8OiPZ0F3iwbPXN.NiowohOoMr5HN9T8ltyr.E0nhuE7Tpbl0jtNvu', '215703', NULL, NULL, 1, '2026-03-20 04:27:24', NULL, NULL),
(86, 'estudiante', 'Usuario 86', 'Anónimo', 'user86@example.com', '$2y$12$eSv5g2gJWCIOzPbDckwCRuQakPLCuLb6PfGGhHSMIlMoS0QRirHT.', '215545', NULL, NULL, 1, '2026-03-20 04:27:24', NULL, NULL),
(87, 'estudiante', 'Usuario 87', 'Anónimo', 'user87@example.com', '$2y$12$iyiA3q0SsfZ6rKggLeFH9eWcjSeyqj/sQL2UHdC5o/9853I5lIKiW', '215710', NULL, NULL, 1, '2026-03-20 04:27:25', NULL, NULL),
(88, 'estudiante', 'Usuario 88', 'Anónimo', 'user88@example.com', '$2y$12$.0WLAxCTczSbn5cG1XvC/entyRIPMZwJJmJur6XplWrZX6KiY.VKy', '215418', NULL, NULL, 1, '2026-03-20 04:27:25', NULL, NULL),
(89, 'estudiante', 'Usuario 89', 'Anónimo', 'user89@example.com', '$2y$12$hP3Sb/qL7JVkIksLarF/CevvLSvQ5NlzMV0zWogBDh8.nmzTb45d6', '215785', NULL, NULL, 1, '2026-03-20 04:27:25', NULL, NULL),
(90, 'estudiante', 'Usuario 90', 'Anónimo', 'user90@example.com', '$2y$12$r5C0wDSSyDUtirCyOLQ0xuGwegxHbBBKHIZSMcTb/Sx4BUksL/vMq', '931793', NULL, NULL, 1, '2026-03-20 04:27:26', NULL, NULL),
(91, 'estudiante', 'Usuario 91', 'Anónimo', 'user91@example.com', '$2y$12$2cxbQLouxB.06XzrvTSF9O7GpM7qfcBMQwrk4Zoqz1.le5kIsNtvm', '215525', NULL, NULL, 1, '2026-03-20 04:27:26', NULL, NULL),
(92, 'estudiante', 'Usuario 92', 'Anónimo', 'user92@example.com', '$2y$12$wxrMLqs1LKPkL6984BdaKeECrSQp1YMaDpvyIH/YVUZBkKmzhxSTe', '215478', NULL, NULL, 1, '2026-03-20 04:27:27', NULL, NULL),
(93, 'estudiante', 'Usuario 93', 'Anónimo', 'user93@example.com', '$2y$12$0XjBK4C19jufK8p4WIHugeevpl6WWgccVUQe4QUKT4LnsphYsWNUu', '216672', NULL, NULL, 1, '2026-03-20 04:27:27', NULL, NULL),
(94, 'estudiante', 'Usuario 94', 'Anónimo', 'user94@example.com', '$2y$12$pkouke9ewk/T1nyQU6Y4lOZNUSFjDmGPsw5exsNbtclvzWDUsBsIm', '216116', NULL, NULL, 1, '2026-03-20 04:27:27', NULL, NULL),
(95, 'estudiante', 'Usuario 95', 'Anónimo', 'user95@example.com', '$2y$12$1Pj/.FUYY4ZoBH/nBHhxj.6dl7U0tTIh7F5Z8IN.vY6Mw5yssSmlu', '215810', NULL, NULL, 1, '2026-03-20 04:27:28', NULL, NULL),
(96, 'estudiante', 'Usuario 96', 'Anónimo', 'user96@example.com', '$2y$12$DKwgnUHnfaxxP3fF29R63uSUiyDsGTfqgU3AeSPu3gPmF2jqn/un.', '215452', NULL, NULL, 1, '2026-03-20 04:27:28', NULL, NULL),
(97, 'estudiante', 'Usuario 97', 'Anónimo', 'user97@example.com', '$2y$12$toDZ4m7HIDrFxugwdkflne93yFEYgDdFC6Pm0CSL9SpozYhXM89M2', '214558', NULL, NULL, 1, '2026-03-20 04:27:28', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `actividades_fichas`
--
ALTER TABLE `actividades_fichas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `curso_id` (`curso_id`);

--
-- Indexes for table `anexos`
--
ALTER TABLE `anexos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `envio_id` (`envio_id`);

--
-- Indexes for table `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `docente_id` (`docente_id`);

--
-- Indexes for table `envios_fichas`
--
ALTER TABLE `envios_fichas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actividad_id` (`actividad_id`),
  ADD KEY `lider_id` (`lider_id`);

--
-- Indexes for table `envio_integrantes`
--
ALTER TABLE `envio_integrantes`
  ADD PRIMARY KEY (`envio_id`,`estudiante_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indexes for table `matriculas`
--
ALTER TABLE `matriculas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `curso_id` (`curso_id`),
  ADD KEY `estudiante_id` (`estudiante_id`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `actividades_fichas`
--
ALTER TABLE `actividades_fichas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `anexos`
--
ALTER TABLE `anexos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `envios_fichas`
--
ALTER TABLE `envios_fichas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `matriculas`
--
ALTER TABLE `matriculas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `actividades_fichas`
--
ALTER TABLE `actividades_fichas`
  ADD CONSTRAINT `actividades_fichas_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`);

--
-- Constraints for table `anexos`
--
ALTER TABLE `anexos`
  ADD CONSTRAINT `anexos_ibfk_1` FOREIGN KEY (`envio_id`) REFERENCES `envios_fichas` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cursos`
--
ALTER TABLE `cursos`
  ADD CONSTRAINT `cursos_ibfk_1` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `envios_fichas`
--
ALTER TABLE `envios_fichas`
  ADD CONSTRAINT `envios_fichas_ibfk_1` FOREIGN KEY (`actividad_id`) REFERENCES `actividades_fichas` (`id`),
  ADD CONSTRAINT `envios_fichas_ibfk_2` FOREIGN KEY (`lider_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `envio_integrantes`
--
ALTER TABLE `envio_integrantes`
  ADD CONSTRAINT `envio_integrantes_ibfk_1` FOREIGN KEY (`envio_id`) REFERENCES `envios_fichas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `envio_integrantes_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `matriculas`
--
ALTER TABLE `matriculas`
  ADD CONSTRAINT `matriculas_ibfk_1` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `matriculas_ibfk_2` FOREIGN KEY (`estudiante_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
