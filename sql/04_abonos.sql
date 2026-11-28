-- =====================================================================
-- Migración 04: historial de abonos de los servicios
-- Base de datos: coop_rio_amazonas
-- Ejecutar una sola vez, después de 03_servicios_de_reservas_aprobadas.sql
--
-- Antes, servicios.abono era un solo número que se escribía al registrar el
-- servicio y no se podía cambiar. Ahora cada pago del cliente es una fila de
-- la tabla abonos (cuánto, cuándo y qué usuario lo cobró).
-- servicios.abono se mantiene y guarda la SUMA de los abonos del servicio,
-- para que el listado, el dashboard y el PDF sigan funcionando igual.
-- =====================================================================

CREATE TABLE `abonos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `monto` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `id_servicio` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,   -- quien lo cobró; NULL en los abonos migrados
  PRIMARY KEY (`id`),
  KEY `id_servicio` (`id_servicio`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `abonos_ibfk_1` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `abonos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Los abonos que ya existían pasan al historial como un primer pago,
-- con la fecha de inicio del servicio (no se sabe cuándo se cobraron).
INSERT INTO abonos (monto, fecha, id_servicio, id_usuario)
SELECT abono, fecha_inicio, id, NULL FROM servicios WHERE abono > 0;
