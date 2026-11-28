-- =====================================================================
-- Migración 03: servicios para las reservas aprobadas antes del cambio
-- Base de datos: coop_rio_amazonas
-- Ejecutar después de 02_propietarios_y_punto_trabajo.sql
--
-- Desde este cambio, aprobar una reserva crea su servicio automáticamente
-- (ReservasModel::aprobar). Las reservas aprobadas ANTES no tienen servicio,
-- por eso no aparecían en el dashboard de ingresos ni en el reporte PDF.
-- Este script les crea el servicio con los mismos datos y abono 0.
--  - Si la reserva ya terminó (f_fin pasada), el servicio queda Finalizado (0).
--  - Si aún no termina, queda En curso (1).
-- No cambia la estructura de ninguna tabla. Se puede ejecutar más de una vez:
-- omite las reservas que ya tienen su servicio ("Reserva #id." en la observación).
-- =====================================================================

INSERT INTO servicios (cantidad, tipo_precio, monto, abono, fecha_inicio, fecha_fin,
                       observacion, estado, id_cliente, id_vehiculo)
SELECT r.cantidad, r.tipo_precio, r.monto, 0, r.f_inicio, r.f_fin,
       TRIM(CONCAT('Reserva #', r.id, '. ', IFNULL(r.observacion, ''),
            IF(r.direccion_trabajo IS NULL OR r.direccion_trabajo = '', '',
               CONCAT(' Punto de trabajo: ', r.direccion_trabajo)))),
       IF(r.f_fin < NOW(), 0, 1),
       r.id_cliente, r.id_vehiculo
FROM reservas r
WHERE r.estado = 1
  AND NOT EXISTS (SELECT 1 FROM servicios s
                  WHERE s.observacion LIKE CONCAT('Reserva #', r.id, '.%'));
