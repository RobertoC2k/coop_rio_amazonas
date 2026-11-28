-- =====================================================================
-- Migración 06: la identificación del cliente se llama `cedula`, no `dni`
-- Base de datos: coop_rio_amazonas
-- Ejecutar una sola vez, después de 05_reserva_en_servicios.sql
-- =====================================================================

-- En Ecuador el documento es la cédula (10 dígitos). Solo cambia el nombre
-- de la columna: el tipo y los datos se conservan. Queda igual que en
-- `propietarios`.`cedula` (sin tilde).
ALTER TABLE `clientes`
  CHANGE COLUMN `dni` `cedula` varchar(10) DEFAULT NULL;
