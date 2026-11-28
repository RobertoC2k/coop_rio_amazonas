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
     * Total a pagar = cantidad x precio unitario, redondeado a centavos.
     * round(..., 2): la computadora guarda los decimales de forma aproximada
     * (3 x 1.15 = 3.4499999999...); sin redondear, las comparaciones fallaban.
     */
    public function calcularTotal($cantidad, $monto)
    {
        return round((int) $cantidad * (float) $monto, 2);
    }

    // Etiqueta de la tarifa para pantallas y PDF (antes se calculaba en verify.php)
    public function textoTipoPrecio($tipo)
    {
        if ($tipo == 1) {
            return 'HORAS: ';
        } else if ($tipo == 2) {
            return 'DIAS: ';
        }
        return 'MESES: ';
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
     * Lo que falta pagar: (cantidad x precio unitario) - lo abonado.
     * Antes devolvía 0 si el servicio estaba finalizado, aunque el cliente no hubiera
     * pagado todo. Ahora un servicio solo se finaliza con el saldo en 0 (ver
     * ServiciosModel::finalizar), así que el cálculo real sirve para todos los casos.
     */
    public function calcularSaldoPendiente($servicio)
    {
        return round($this->calcularTotal($servicio['cantidad'], $servicio['monto']) - $servicio['abono'], 2);
    }

    /**
     * Resumen del servicio creado al aprobar una reserva (pantalla reservas/verify).
     * Devuelve null si la reserva todavía no tiene servicio (pendiente o rechazada).
     */
    public function resumenServicioDeReserva($idReserva)
    {
        $servicio = $this->select("SELECT * FROM servicios WHERE id_reserva = ?", array($idReserva));
        if (empty($servicio)) {
            return null;
        }
        return array(
            'id'           => $servicio['id'],
            'estado'       => $servicio['estado'],
            'texto_estado' => $this->textoEstado($servicio['estado']),
            'total'        => $this->calcularTotal($servicio['cantidad'], $servicio['monto']),
            'abonado'      => (float) $servicio['abono'],
            'pendiente'    => $this->calcularSaldoPendiente($servicio),
        );
    }

    public function textoEstado($estado)
    {
        return ($estado == 1) ? 'En curso' : 'Finalizado';
    }

    /**
     * Datos listos para el comprobante PDF de un servicio (Views/servicios/pdf-comprobante.php).
     * Lo usan Servicios::pdfServicio (verlo en el navegador) y ReservasModel::aprobar
     * (adjuntarlo al correo del cliente). Devuelve null si el servicio no existe.
     */
    public function datosComprobante(int $id)
    {
        $servicio = $this->getServicio($id);
        if (empty($servicio)) {
            return null;
        }
        return array(
            'empresa'      => $this->getEmpresa(),
            'servicio'     => $servicio,
            'texto_estado' => $this->textoEstado($servicio['estado']),
            'texto_tipo'   => $this->textoTipoPrecio($servicio['tipo_precio']),
            'pendiente'    => $this->calcularSaldoPendiente($servicio),
            'abonos'       => $this->getAbonos($servicio['id']),
        );
    }

    // Historial de pagos de un servicio, del más antiguo al más reciente, con quién lo cobró
    public function getAbonos($idServicio)
    {
        $sql = "SELECT a.id, a.monto, a.fecha, IFNULL(u.nombre, '-') AS usuario
                FROM abonos a LEFT JOIN usuarios u ON u.id = a.id_usuario
                WHERE a.id_servicio = ? ORDER BY a.fecha, a.id";
        return $this->selectAll($sql, array($idServicio));
    }

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }

    public function getServicio(int $id)
    {
        $sql = "SELECT s.*, c.dni, c.nombre, c.telefono, c.direccion, v.placa, v.modelo, t.tipo FROM servicios s
                INNER JOIN clientes c ON c.id = s.id_cliente
                INNER JOIN vehiculos v ON v.id = s.id_vehiculo
                INNER JOIN tipos t ON t.id = v.id_tipo
                WHERE s.id = ?";
        return $this->select($sql, array($id));
    }

    /**
     * Guarda un servicio y devuelve su id (0 si falló).
     * Lo usan ServiciosModel::registrar (servicio a mano) y ReservasModel::aprobar
     * (al aprobar una reserva se crea su servicio, así cuenta en el dashboard y el reporte).
     * $idReserva: la reserva de origen; null si el servicio se registró a mano.
     */
    public function registrarServicio($cantidad, $precios, $monto, $abono, $inicio, $fin, $observacion, $id_cli, $id_veh, $idReserva = null)
    {
        $sql = "INSERT INTO servicios(cantidad, tipo_precio, monto, abono, fecha_inicio, fecha_fin, observacion, id_cliente, id_vehiculo, id_reserva) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $datos = array($cantidad, $precios, $monto, $abono, $inicio, $fin, $observacion, $id_cli, $id_veh, $idReserva);
        return $this->insertar($sql, $datos);
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
