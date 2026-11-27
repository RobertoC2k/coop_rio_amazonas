<?php
/**
 * Reglas de negocio compartidas por Reservas y Servicios.
 * Antes estas tres funciones estaban copiadas en ReservasModel y ServiciosModel:
 * un cambio en una (por ejemplo, el máximo de horas) había que repetirlo en la otra.
 * Ahora ambos modelos heredan de esta clase:
 *
 *   Query (acceso a la base de datos)
 *     └── ReglasTransporte (este archivo)
 *           ├── ReservasModel
 *           └── ServiciosModel
 *
 * Tarifas: 1 = por hora, 2 = por día, 3 = por mes.
 */
class ReglasTransporte extends Query
{
    // Cantidad máxima por tarifa: 720 horas (30 días), 365 días (1 año), 12 meses (1 año)
    const MAXIMOS = array('1' => 720, '2' => 365, '3' => 12);

    /**
     * La cantidad debe ser un número ENTERO mayor que cero (la columna cantidad es INT)
     * y no pasar del máximo de su tarifa. Con decimales (1.5) la fecha de fin
     * salía mal, y un número enorme provocaba un error fatal.
     * Devuelve el mensaje de error, o null si la cantidad es válida.
     */
    public function validarCantidad($cantidad, $tipoPrecio)
    {
        $tipoPrecio = (string) $tipoPrecio;
        if (!isset(self::MAXIMOS[$tipoPrecio])) {
            return 'Tipo de tarifa no válido';
        }
        // ctype_digit: solo dígitos 0-9, sin punto, coma, signo ni letras
        if (!ctype_digit((string) $cantidad) || (int) $cantidad < 1) {
            return 'La cantidad debe ser un número entero mayor que cero';
        }
        if ((int) $cantidad > self::MAXIMOS[$tipoPrecio]) {
            return 'La cantidad máxima para esta tarifa es ' . self::MAXIMOS[$tipoPrecio];
        }
        return null;
    }

    /**
     * Devuelve el precio unitario (monto) según la tarifa y las fechas de inicio y fin
     * (fin = inicio + cantidad de horas, días o meses).
     */
    public function calcularTarifa($vehiculo, $tipoPrecio, $cantidad, $fecha)
    {
        if ($tipoPrecio == 1) {
            $suma = '+' . $cantidad . ' hours';
            $monto = $vehiculo['precio_hora'];
        } else if ($tipoPrecio == 2) {
            $suma = '+' . $cantidad . ' days';
            $monto = $vehiculo['precio_dia'];
        } else {
            $suma = '+' . $cantidad . ' month';
            $monto = $vehiculo['precio_mes'];
        }
        $inicio = date("Y-m-d H:i:s", strtotime($fecha));
        $fin = date("Y-m-d H:i:s", strtotime($fecha . ' ' . $suma));
        return array('monto' => $monto, 'inicio' => $inicio, 'fin' => $fin);
    }

    /**
     * Dos periodos se cruzan si uno empieza antes de que el otro termine
     * y termina después de que el otro empiece.
     * Se revisan:
     *  - las reservas pendientes o aprobadas del vehículo (las rechazadas liberan el horario),
     *  - los servicios que siguen en curso (estado 1).
     * $idClienteExcluido: al registrar un SERVICIO se ignoran las reservas de ese mismo
     * cliente, porque el servicio es justamente la concreción de su reserva.
     * En una RESERVA nueva no se excluye a nadie (null).
     * Devuelve el primer cruce encontrado o false si el vehículo está libre.
     */
    public function buscarCruceDeFechas($desde, $hasta, $id_veh, $idClienteExcluido = null)
    {
        $sql = "SELECT f_inicio AS desde, f_fin AS hasta FROM reservas
                WHERE id_vehiculo = ? AND estado != 2 AND f_inicio < ? AND f_fin > ?";
        $datos = array($id_veh, $hasta, $desde);
        if ($idClienteExcluido !== null) {
            $sql .= " AND id_cliente != ?";
            $datos[] = $idClienteExcluido;
        }
        $reserva = $this->select($sql, $datos);
        if (!empty($reserva)) {
            return $reserva;
        }

        $sql = "SELECT fecha_inicio AS desde, fecha_fin AS hasta FROM servicios
                WHERE id_vehiculo = ? AND estado = 1
                AND fecha_inicio < ? AND fecha_fin > ?";
        return $this->select($sql, array($id_veh, $hasta, $desde));
    }
}
