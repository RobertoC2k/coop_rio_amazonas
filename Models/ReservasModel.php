<?php
/**
 * Modelo de Reservas (solicitud de turno).
 * Una reserva es la solicitud que hace un cliente para usar un vehículo
 * durante un periodo (f_inicio a f_fin). El personal la aprueba o la rechaza indicando el motivo.
 * Estados: 0 = Pendiente, 1 = Aprobada, 2 = Rechazada.
 */
class ReservasModel extends Query
{
    public function __construct()
    {
        parent::__construct();
    }

    /* =========================================================
     * REGLAS DE NEGOCIO
     * ========================================================= */

    /**
     * Registra la solicitud de reserva aplicando las reglas del negocio.
     * Recibe los datos del formulario y devuelve la respuesta para la vista
     * ('msg', 'icono' y, si se registró, 'id_reserva').
     */
    public function solicitar(array $d)
    {
        // 1. Campos obligatorios
        if (
            empty($d['id_cli']) || empty($d['id_veh']) || empty($d['cantidad'])
            || empty($d['precios']) || empty($d['fecha']) || empty($d['hora'])
        ) {
            return array('msg' => 'Todo los campos con * son requeridos', 'icono' => 'warning');
        }

        // 2. Cantidad positiva y tarifa válida (1 = hora, 2 = día, 3 = mes)
        if (!is_numeric($d['cantidad']) || $d['cantidad'] <= 0 || !in_array($d['precios'], array('1', '2', '3'))) {
            return array('msg' => 'Cantidad o tipo de tarifa no válidos', 'icono' => 'warning');
        }

        // 3. No se puede reservar en una fecha que ya pasó
        $fecha = $d['fecha'] . ' ' . $d['hora'];
        if (strtotime($fecha) === false || strtotime($fecha) < time()) {
            return array('msg' => 'La fecha de inicio debe ser posterior a la fecha actual', 'icono' => 'warning');
        }

        // 4. El vehículo debe existir y estar activo
        $vehiculo = $this->getVehiculo($d['id_veh']);
        if (empty($vehiculo) || $vehiculo['estado'] == 0) {
            return array('msg' => 'El vehículo no existe', 'icono' => 'error');
        }

        // 5. Calcular precio unitario y fecha de fin según la tarifa elegida
        $tarifa = $this->calcularTarifa($vehiculo, $d['precios'], $d['cantidad'], $fecha);

        // 6. El vehículo no debe estar ocupado en esas fechas
        $ocupado = $this->buscarCruceDeFechas($tarifa['inicio'], $tarifa['fin'], $d['id_veh']);
        if (!empty($ocupado)) {
            return array('msg' => 'El vehículo está ocupado: ' . $ocupado['desde'] . ' hasta ' . $ocupado['hasta'], 'icono' => 'error');
        }

        // 7. Guardar (la reserva nace en estado 0 = Pendiente)
        $id = $this->registrarReserva(
            $tarifa['inicio'], $tarifa['fin'], $d['cantidad'], $d['precios'], $tarifa['monto'],
            date('Y-m-d H:i:s'), $d['observacion'], $d['id_veh'], $d['id_cli']
        );
        if ($id > 0) {
            return array('msg' => 'Reserva solicitada', 'icono' => 'success', 'id_reserva' => $id);
        }
        return array('msg' => 'Error al solicitar reserva', 'icono' => 'error');
    }

    /**
     * Tarifa: 1 = por hora, 2 = por día, 3 = por mes.
     * Devuelve el precio unitario (monto) y las fechas de inicio y fin.
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
     * Se revisan las reservas pendientes o aprobadas del vehículo (las rechazadas
     * liberan el horario) y los servicios que siguen en curso.
     * Devuelve el primer cruce encontrado o false si el vehículo está libre.
     */
    public function buscarCruceDeFechas($desde, $hasta, $id_veh)
    {
        $sql = "SELECT f_inicio AS desde, f_fin AS hasta FROM reservas
                WHERE id_vehiculo = ? AND estado != 2 AND f_inicio < ? AND f_fin > ?";
        $reserva = $this->select($sql, array($id_veh, $hasta, $desde));
        if (!empty($reserva)) {
            return $reserva;
        }

        $sql = "SELECT fecha_inicio AS desde, fecha_fin AS hasta FROM servicios
                WHERE id_vehiculo = ? AND estado = 1
                AND fecha_inicio < ? AND fecha_fin > ?";
        return $this->select($sql, array($id_veh, $hasta, $desde));
    }

    /**
     * Prepara las reservas para el calendario.
     * Regla de privacidad: el administrador (tipo 1) ve el nombre de todos los clientes;
     * un cliente solo ve su nombre en sus propias reservas, las demás aparecen como "RESERVADO".
     * Las rechazadas solo las ven el personal y el cliente dueño de la reserva (en gris).
     * Color: rojo = pendiente, verde = aprobada, gris = rechazada.
     */
    public function getEventosCalendario($id_vehiculo, $tipoUsuario, $idUsuario)
    {
        $eventos = array();
        foreach ($this->getReservas($id_vehiculo) as $reserva) {
            $esPropia = ($tipoUsuario == 1 || $reserva['id_cliente'] == $idUsuario);
            if ($reserva['estado'] == 2 && !$esPropia) {
                continue;   // una reserva rechazada de otro cliente no ocupa el horario
            }
            $reserva['title'] = $esPropia ? $reserva['nombre'] : 'RESERVADO';
            if ($reserva['estado'] == 2) {
                $reserva['title'] = 'RECHAZADA: ' . $reserva['motivo_rechazo'];
            }
            $reserva['start'] = $reserva['f_inicio'];
            $reserva['end'] = $reserva['f_fin'];
            $colores = array(0 => '#dc3545', 1 => '#198754', 2 => '#6c757d');
            $reserva['color'] = $colores[$reserva['estado']];
            $eventos[] = $reserva;
        }
        return $eventos;
    }

    /**
     * Motivos de rechazo disponibles. "Otro" obliga a escribir el detalle.
     * La vista arma la lista desplegable con este mismo arreglo.
     */
    public function motivosRechazo()
    {
        return array('No realizó el pago', 'El cliente solicitó cancelar', 'Vehículo no disponible', 'Otro');
    }

    /**
     * Rechaza una reserva pendiente (estado 2), guarda el motivo y avisa al cliente por correo.
     */
    public function rechazar($idReserva, $motivo, $detalle)
    {
        // 1. Solo se responde una reserva que sigue pendiente
        $reserva = $this->getReserva($idReserva);
        if (empty($reserva) || $reserva['estado'] != 0) {
            return array('msg' => 'La reserva no existe o ya fue respondida', 'icono' => 'warning');
        }
        // 2. El motivo debe ser de la lista; "Otro" exige detalle
        if (!in_array($motivo, $this->motivosRechazo())) {
            return array('msg' => 'Selecciona un motivo de rechazo', 'icono' => 'warning');
        }
        if ($motivo == 'Otro' && strlen(trim($detalle)) < 5) {
            return array('msg' => 'Describe el motivo del rechazo (mínimo 5 caracteres)', 'icono' => 'warning');
        }
        // 3. Texto final del motivo: el de la lista, más el detalle si se escribió
        $texto = ($motivo == 'Otro') ? $detalle : $motivo;
        if ($motivo != 'Otro' && trim($detalle) != '') {
            $texto .= ' - ' . $detalle;
        }
        $texto = substr($texto, 0, 255);
        // 4. Guardar
        if ($this->guardarRechazo($idReserva, $texto) != 1) {
            return array('msg' => 'Error al rechazar', 'icono' => 'error');
        }
        // 5. Avisar al cliente (si tiene correo)
        if (empty($reserva['correo'])) {
            return array('msg' => 'Reserva rechazada', 'icono' => 'success');
        }
        $empresa = $this->getEmpresa();
        $cuerpo = '<p>Hola ' . htmlspecialchars($reserva['nombre']) . ',</p>'
            . '<p>Lamentamos informarte que tu solicitud de reserva del <b>' . $reserva['f_inicio']
            . '</b> al <b>' . $reserva['f_fin'] . '</b> fue <b>rechazada</b>.</p>'
            . '<p><b>Motivo:</b> ' . htmlspecialchars($texto) . '</p>'
            . '<p>Si tienes dudas, responde a este correo.</p>';
        $enviado = Correo::enviar($reserva['correo'], $reserva['nombre'], 'Reserva rechazada', $cuerpo,
            $empresa['nombre'], $empresa['correo'], $empresa['nombre']);
        return $enviado
            ? array('msg' => 'Reserva rechazada y cliente notificado', 'icono' => 'success')
            : array('msg' => 'Reserva rechazada (no se pudo enviar el correo al cliente)', 'icono' => 'success');
    }

    /**
     * Aprueba la reserva (estado 1) y, si el cliente tiene correo, le avisa.
     */
    public function aprobar($idReserva)
    {
        // Solo se aprueba una reserva que sigue pendiente
        $pendiente = $this->getReserva($idReserva);
        if (empty($pendiente) || $pendiente['estado'] != 0) {
            return array('msg' => 'La reserva no existe o ya fue respondida', 'icono' => 'warning');
        }
        if ($this->actualizarEstado(1, $idReserva) != 1) {
            return array('msg' => 'Error al aprobar', 'icono' => 'error');
        }
        $reserva = $this->getReserva($idReserva);
        if (empty($reserva['correo'])) {
            return array('msg' => 'Solicitud aprobada', 'icono' => 'success');
        }
        if ($this->notificarAprobacion($reserva)) {
            return array('msg' => 'Solicitud aprobada y correo enviado', 'icono' => 'success');
        }
        // La reserva SÍ quedó aprobada; solo falló el aviso por correo.
        return array('msg' => 'Solicitud aprobada (no se pudo enviar el correo al cliente)', 'icono' => 'success');
    }

    // Envía el correo de confirmación al cliente. Devuelve true si se envió.
    private function notificarAprobacion($reserva)
    {
        $empresa = $this->getEmpresa();
        $cuerpo = 'Confirmamos que tu solicitud de reserva del ' . $reserva['f_inicio']
            . ' al ' . $reserva['f_fin'] . ' ha sido aprobada.';
        // Las respuestas del cliente llegan al correo de la cooperativa
        return Correo::enviar($reserva['correo'], $reserva['nombre'], 'Solicitud de reserva', $cuerpo,
            $empresa['nombre'], $empresa['correo'], $empresa['nombre']);
    }

    /* =========================================================
     * ACCESO A DATOS
     * ========================================================= */

    public function getReservas($id_vehiculo)
    {
        if (empty($id_vehiculo)) {
            $sql = "SELECT r.*, c.nombre FROM reservas r INNER JOIN clientes c ON r.id_cliente = c.id";
            return $this->selectAll($sql);
        }
        $sql = "SELECT r.*, c.nombre FROM reservas r INNER JOIN clientes c ON r.id_cliente = c.id WHERE r.id_vehiculo = ?";
        return $this->selectAll($sql, array($id_vehiculo));
    }

    public function getVehiculos()
    {
        $sql = "SELECT v.*, m.marca, t.tipo FROM vehiculos v INNER JOIN marcas m ON v.id_marca = m.id INNER JOIN tipos t ON v.id_tipo = t.id WHERE v.estado != 0";
        return $this->selectAll($sql);
    }

    public function getVehiculo($id)
    {
        $sql = "SELECT v.*, m.marca, t.tipo FROM vehiculos v INNER JOIN marcas m ON v.id_marca = m.id INNER JOIN tipos t ON v.id_tipo = t.id WHERE v.id = ?";
        return $this->select($sql, array($id));
    }

    public function registrarReserva($fecha, $fecha_devolucion, $cantidad, $tipo, $monto, $fecha_reserva, $observacion, $id_veh, $id_cli)
    {
        $sql = "INSERT INTO reservas(f_inicio, f_fin, cantidad, tipo_precio, monto, f_reserva, observacion, id_vehiculo, id_cliente) VALUES (?,?,?,?,?,?,?,?,?)";
        $datos = array($fecha, $fecha_devolucion, $cantidad, $tipo, $monto, $fecha_reserva, $observacion, $id_veh, $id_cli);
        $data = $this->insertar($sql, $datos);
        return ($data > 0) ? $data : 0;
    }

    public function getNuevasReservas()
    {
        $sql = "SELECT r.id, r.f_reserva, c.nombre FROM reservas r INNER JOIN clientes c ON r.id_cliente = c.id WHERE r.estado = 0 ORDER BY r.id DESC LIMIT 5";
        return $this->selectAll($sql);
    }

    public function getReserva($id)
    {
        $sql = "SELECT r.*, c.nombre, c.correo FROM reservas r INNER JOIN clientes c ON r.id_cliente = c.id WHERE r.id = ?";
        return $this->select($sql, array($id));
    }


    public function actualizarEstado($estado, $id)
    {
        $sql = "UPDATE reservas SET estado = ? WHERE id = ?";
        return $this->save($sql, array($estado, $id));
    }

    // Estado 2 = Rechazada, con su motivo
    public function guardarRechazo($id, $motivo)
    {
        $sql = "UPDATE reservas SET estado = 2, motivo_rechazo = ? WHERE id = ?";
        return $this->save($sql, array($motivo, $id));
    }

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }
}
