/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.15-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: datasis
-- ------------------------------------------------------
-- Server version	10.11.15-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `indicadores_marketing`
--

DROP TABLE IF EXISTS `indicadores_marketing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `indicadores_marketing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `periodo` date NOT NULL,
  `seguidores_total` int(11) DEFAULT 0,
  `nuevos_seguidores` int(11) DEFAULT 0,
  `alcance` int(11) DEFAULT 0,
  `interacciones` int(11) DEFAULT 0,
  `videos` longtext DEFAULT NULL CHECK (json_valid(`videos`)),
  `campanas` longtext DEFAULT NULL CHECK (json_valid(`campanas`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_periodo` (`periodo`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `indicadores_marketing`
--

LOCK TABLES `indicadores_marketing` WRITE;
/*!40000 ALTER TABLE `indicadores_marketing` DISABLE KEYS */;
INSERT INTO `indicadores_marketing` VALUES
(1,'2026-01-01',3782,200,58344,598,'[{\"id\":\"8e007a9f-f7d5-4e1b-9c32-bd6f03101340\",\"periodo\":\"2026-01-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Video Promo probioticos ZAKI\",\"cantidad\":1,\"fecha\":\"2026-01-28\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-18T13:56:23.131075+00:00\",\"updated_at\":\"2026-03-18T13:56:22.933+00:00\"},{\"id\":\"e7b54775-2120-470e-a174-d9c368b03880\",\"periodo\":\"2026-01-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Promo niuvit\",\"cantidad\":1,\"fecha\":\"2026-01-25\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-18T14:48:30.058272+00:00\",\"updated_at\":\"2026-03-18T14:48:28.763+00:00\"}]','[{\"id\":\"e65815cf-cb8a-4a79-bb40-6ed6a8a3d9b8\",\"periodo\":\"2026-01-01\",\"nombre\":\"Trafico | Vacantes asesor | Aragua - Carabobo - M\\u00e9rida - Caracas | IG\",\"plataforma\":\"Instagram\",\"fecha_inicio\":\"2026-01-20\",\"fecha_fin\":\"2026-01-25\",\"importe_gastado\":0,\"alcance_campana\":0,\"impresiones_campana\":0,\"clics\":855,\"ctr\":null,\"conversiones\":266,\"notas\":\"Campa\\u00f1a realizada para vacantes de asesores en diferentes zonas de venezuela\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-18T15:03:04.774393+00:00\",\"objetivo\":\"trafico\",\"presupuesto\":15.88,\"alcance\":28424,\"updated_at\":\"2026-03-18T15:03:04.774393+00:00\"},{\"id\":\"6964986f-b1bb-4cdf-8746-9fedf55fac6e\",\"periodo\":\"2026-01-01\",\"nombre\":\"Trafico | Vacantes asesor | Carabobo - M\\u00e9rida\",\"plataforma\":\"Instagram\",\"fecha_inicio\":\"2026-01-26\",\"fecha_fin\":\"2026-01-30\",\"importe_gastado\":0,\"alcance_campana\":0,\"impresiones_campana\":0,\"clics\":747,\"ctr\":null,\"conversiones\":93,\"notas\":\"Campa\\u00f1a realizada para la recoleccion de CV para vacantes de asesores\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-18T15:07:01.50295+00:00\",\"objetivo\":\"trafico\",\"presupuesto\":19.19,\"alcance\":37852,\"updated_at\":\"2026-03-18T15:07:01.50295+00:00\"}]','2026-05-23 00:31:04'),
(2,'2026-02-01',3991,209,25876,174,'[{\"id\":\"5fe4485e-0735-421d-abb2-1a979c35c313\",\"periodo\":\"2026-02-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Sorteo Lister fenix\",\"cantidad\":1,\"fecha\":\"2026-02-25\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-18T13:48:15.431243+00:00\",\"updated_at\":\"2026-03-18T13:48:15.762+00:00\"},{\"id\":\"57059c76-3846-4758-9cbb-af7b9a4d93a3\",\"periodo\":\"2026-02-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Video 20% de descuento KMPLUS en productos seleccionados\",\"cantidad\":1,\"fecha\":\"2026-02-27\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-18T14:50:49.050439+00:00\",\"updated_at\":\"2026-03-18T14:50:48.39+00:00\"}]','[{\"id\":\"f077987e-49b2-4a93-a374-c1efde3a60b8\",\"periodo\":\"2026-02-01\",\"nombre\":\" Trafico | Chofer y asesor Merida | VE\",\"plataforma\":\"Instagram\",\"fecha_inicio\":\"2026-02-20\",\"fecha_fin\":\"2026-02-23\",\"importe_gastado\":0,\"alcance_campana\":0,\"impresiones_campana\":0,\"clics\":1108,\"ctr\":null,\"conversiones\":365,\"notas\":\"Recoleccion de vacantes para Transportista y asesor en MERIDA \",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-18T15:10:17.580431+00:00\",\"objetivo\":\"trafico\",\"presupuesto\":15.22,\"alcance\":24627,\"updated_at\":\"2026-03-18T15:10:17.580431+00:00\"}]','2026-05-23 00:31:04'),
(3,'2026-03-01',4091,82,78131,1065,'[{\"id\":\"04daa3ed-87d1-45bf-8bba-174cfe9f2808\",\"periodo\":\"2026-03-01\",\"red_social\":\"instagram\",\"etiqueta\":\"Super jueves  Joskar\",\"cantidad\":1,\"fecha\":\"2026-03-12\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-16T18:09:34.640961+00:00\",\"updated_at\":\"2026-03-16T18:09:32.972+00:00\"},{\"id\":\"e8e92abf-2104-4138-a340-070d68e0f9fe\",\"periodo\":\"2026-03-01\",\"red_social\":\"instagram\",\"etiqueta\":\"Entrega de moto Yaracuy\",\"cantidad\":1,\"fecha\":\"2026-03-17\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-16T18:10:07.372808+00:00\",\"updated_at\":\"2026-03-16T18:10:06.165+00:00\"},{\"id\":\"740a131b-a45c-446e-b417-5deba6076af3\",\"periodo\":\"2026-03-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Ganadores del sorteo Super Jueves Joskar\",\"cantidad\":1,\"fecha\":\"2026-03-20\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-20T14:13:32.191527+00:00\",\"updated_at\":\"2026-03-20T14:13:31.046+00:00\"},{\"id\":\"dbcd2002-add0-41f8-8a1d-63f4fe22573a\",\"periodo\":\"2026-03-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Video entrevista dia de la mujer\",\"cantidad\":1,\"fecha\":\"2026-03-08\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-23T15:45:39.413249+00:00\",\"updated_at\":\"2026-03-23T15:45:41.572+00:00\"},{\"id\":\"414659e8-b64d-4d92-993b-ec3451a21a0b\",\"periodo\":\"2026-03-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Video resumen de jornada aniversario Expendio de medicinas Dr. Jos\\u00e9 Gregorio\",\"cantidad\":1,\"fecha\":\"2026-03-23\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-23T20:41:32.214809+00:00\",\"updated_at\":\"2026-03-23T20:41:31.418+00:00\"},{\"id\":\"9c72e1d9-89f6-415f-bc4d-9368a815b4b1\",\"periodo\":\"2026-03-01\",\"red_social\":\"[\\\"instagram\\\",\\\"whatsapp\\\"]\",\"etiqueta\":\"Promoci\\u00f3n del Equipa tu hogar video 1\",\"cantidad\":1,\"fecha\":\"2026-03-25\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-25T22:05:33.809026+00:00\",\"updated_at\":\"2026-03-25T22:05:33.207+00:00\"},{\"id\":\"51647854-f2a2-44ae-9b9e-5b9e0474cca6\",\"periodo\":\"2026-03-01\",\"red_social\":\"[\\\"instagram\\\"]\",\"etiqueta\":\"Video recordatorio de lister fenix\",\"cantidad\":1,\"fecha\":\"2026-03-18\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-27T15:12:06.410889+00:00\",\"updated_at\":\"2026-03-27T15:12:06.04+00:00\"},{\"id\":\"b56255ce-ef2d-463b-bcb2-b56f32fa6a5c\",\"periodo\":\"2026-03-01\",\"red_social\":\"[\\\"whatsapp\\\"]\",\"etiqueta\":\"Video informativo del centro de transferencias IVENTAS\",\"cantidad\":1,\"fecha\":\"2026-03-31\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-31T18:29:45.935033+00:00\",\"updated_at\":\"2026-03-31T18:29:46.458+00:00\"}]','[{\"id\":\"f080b572-89cd-49be-af4b-542b66418deb\",\"periodo\":\"2026-03-01\",\"nombre\":\"Equipa tu hogar | VIDEO\",\"plataforma\":\"Instagram\",\"fecha_inicio\":\"2026-03-26\",\"fecha_fin\":\"2026-03-30\",\"importe_gastado\":0,\"alcance_campana\":0,\"impresiones_campana\":0,\"clics\":64,\"ctr\":null,\"conversiones\":11285,\"notas\":\"Obtuvimos 30000 reproducciones llegando a nuevas audiencia y fortaleciendo la presencia digital de la marca a nivel nacional\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-30T13:45:37.397313+00:00\",\"objetivo\":\"engagement\",\"presupuesto\":18.72,\"alcance\":37070,\"updated_at\":\"2026-03-30T13:45:37.397313+00:00\"},{\"id\":\"75205a67-7903-4a7f-a322-8ee4efe8648f\",\"periodo\":\"2026-03-01\",\"nombre\":\"Equipa tu hogar | POST \",\"plataforma\":\"Instagram\",\"fecha_inicio\":\"2026-03-24\",\"fecha_fin\":\"2026-03-30\",\"importe_gastado\":0,\"alcance_campana\":0,\"impresiones_campana\":0,\"clics\":429,\"ctr\":null,\"conversiones\":409,\"notas\":\"Se logro concretar visitas de publico nuevo al perfil de instagram usando como gancho la publicacion de equipa tu hogar\",\"ingresado_por\":\"social\",\"created_at\":\"2026-03-30T13:47:46.890044+00:00\",\"objetivo\":\"trafico\",\"presupuesto\":20.13,\"alcance\":22995,\"updated_at\":\"2026-03-30T13:47:46.890044+00:00\"}]','2026-05-23 00:31:04'),
(4,'2026-04-01',0,0,0,0,'[]','[]','2026-05-23 00:31:04');
/*!40000 ALTER TABLE `indicadores_marketing` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-22 20:33:56
