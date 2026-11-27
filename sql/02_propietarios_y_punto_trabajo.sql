-- =====================================================================
-- Migración 02: dueños de vehículos y punto de trabajo de la reserva
-- Base de datos: coop_rio_amazonas
-- Ejecutar una sola vez, después de 01_rechazo_reservas.sql
-- =====================================================================

-- 1. Dueños (socios) de los vehículos. Reciben la notificación cuando
--    se aprueba una reserva de su vehículo (correo + WhatsApp).
CREATE TABLE `propietarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cedula` varchar(13) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `celular` varchar(20) NOT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Cada vehículo pertenece a un dueño. Es NULL para los vehículos que ya
--    existían: se asigna al editarlos. Si se borrara un dueño, el vehículo
--    queda sin dueño (SET NULL) en lugar de borrarse.
ALTER TABLE `vehiculos`
  ADD COLUMN `id_propietario` int(11) NULL DEFAULT NULL AFTER `id_marca`,
  ADD CONSTRAINT `vehiculos_ibfk_3` FOREIGN KEY (`id_propietario`)
      REFERENCES `propietarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 3. Dirección o punto del trabajo, para que el vehículo llegue al cliente.
--    NULL en las reservas anteriores; obligatorio en las nuevas (lo valida el modelo).
ALTER TABLE `reservas`
  ADD COLUMN `direccion_trabajo` varchar(255) NULL DEFAULT NULL AFTER `observacion`;
