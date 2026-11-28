-- =====================================================================
-- Migración 05: cada servicio sabe de qué reserva viene
-- Base de datos: coop_rio_amazonas
-- Ejecutar una sola vez, después de 04_abonos.sql
--
-- Al aprobar una reserva se crea su servicio (ReservasModel::aprobar).
-- Hasta ahora el enlace era solo el texto "Reserva #N." en la observación,
-- que es frágil. Esta columna lo guarda como llave foránea.
-- Es NULL en los servicios registrados a mano (no vienen de una reserva).
-- =====================================================================

ALTER TABLE `servicios`
  ADD COLUMN `id_reserva` int(11) DEFAULT NULL AFTER `id_vehiculo`,
  ADD KEY `id_reserva` (`id_reserva`),
  ADD CONSTRAINT `servicios_ibfk_3` FOREIGN KEY (`id_reserva`) REFERENCES `reservas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Completar el enlace de los servicios ya creados a partir de la observación.
-- 'Reserva #1.%' no confunde la reserva 1 con la 10, porque exige el punto después del número.
UPDATE servicios s
INNER JOIN reservas r ON s.observacion LIKE CONCAT('Reserva #', r.id, '.%')
SET s.id_reserva = r.id
WHERE s.id_reserva IS NULL;
