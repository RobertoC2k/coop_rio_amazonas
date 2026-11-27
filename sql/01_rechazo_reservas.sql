-- =====================================================================
-- Migración 01: rechazo de reservas con motivo
-- Base de datos: coop_rio_amazonas
-- Ejecutar una sola vez (phpMyAdmin > pestaña SQL, o consola de MariaDB).
--
-- Estados de reservas después de esta migración:
--   0 = Pendiente, 1 = Aprobada, 2 = Rechazada
-- =====================================================================

ALTER TABLE `reservas`
  ADD COLUMN `motivo_rechazo` VARCHAR(255) NULL DEFAULT NULL AFTER `estado`;
