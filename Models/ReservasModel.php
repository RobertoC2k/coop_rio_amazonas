<?php
/**
 * Modelo de Reservas (solicitud de turno).
 * Una reserva es la solicitud que hace un cliente para usar un vehículo
 * durante un periodo (f_inicio a f_fin) en un punto de trabajo. El personal la aprueba
 * (se avisa al cliente y al dueño del vehículo) o la rechaza indicando el motivo.
 * Estados: 0 = Pendiente, 1 = Aprobada, 2 = Rechazada.
 */
require_once "Models/ReglasTransporte.php";

class ReservasModel extends ReglasTransporte
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
            return array('msg' => 'Todos los campos con * son requeridos', 'icono' => 'warning');
        }
        // El vehículo debe saber a dónde ir: la dirección del trabajo es obligatoria
        if (strlen(trim($d['direccion_trabajo'])) < 5) {
            return array('msg' => 'Indica la dirección o punto del trabajo', 'icono' => 'warning');
        }

        // 2. Cantidad entera dentro del máximo y tarifa válida (1 = hora, 2 = día, 3 = mes)
        $error = $this->validarCantidad($d['cantidad'], $d['precios']);
        if ($error != null) {
            return array('msg' => $error, 'icono' => 'warning');
        }

        // 3. No se puede reservar en una fecha que ya pasó
        $fecha = $d['fecha'] . ' ' . $d['hora'];
        if (strtotime($fecha) === false || strtotime($fecha) < time()) {
            return array('msg' => 'La fecha de inicio debe ser posterior a la fecha actual', 'icono' => 'warning');
        }

        // 4. El cliente debe seguir activo: si el personal lo dio de baja mientras tenía
        //    la sesión abierta, ya no puede solicitar reservas
        if (empty($this->getClienteActivo($d['id_cli']))) {
            return array('msg' => 'Tu cuenta está dada de baja. Comunícate con la cooperativa', 'icono' => 'error');
        }

        // 4b. El vehículo debe existir y estar activo
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
            date('Y-m-d H:i:s'), $d['observacion'], $d['direccion_trabajo'], $d['id_veh'], $d['id_cli']
        );
        if ($id > 0) {
            return array('msg' => 'Reserva solicitada', 'icono' => 'success', 'id_reserva' => $id);
        }
        return array('msg' => 'Error al solicitar reserva', 'icono' => 'error');
    }

    // validarCantidad(), calcularTarifa() y buscarCruceDeFechas() se heredan de
    // ReglasTransporte (Models/ReglasTransporte.php): son las mismas reglas para reservas y servicios.

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
            $titulo = $esPropia ? $reserva['nombre'] : 'RESERVADO';
            if ($reserva['estado'] == 2) {
                $titulo = 'RECHAZADA: ' . $reserva['motivo_rechazo'];
            }
            $colores = array(0 => '#dc3545', 1 => '#198754', 2 => '#6c757d');
            // Privacidad: se arma un evento nuevo solo con lo que el calendario necesita.
            // Antes se enviaba la fila completa (nombre, observación, dirección, id del
            // cliente...) y, aunque se veía "RESERVADO", esos datos viajaban al navegador.
            $evento = array(
                'title' => $titulo,
                'start' => $reserva['f_inicio'],
                'end'   => $reserva['f_fin'],
                'color' => $colores[$reserva['estado']],
            );
            if ($esPropia) {
                $evento['id'] = $reserva['id'];   // el id solo se entrega al dueño o al personal
            }
            $eventos[] = $evento;
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
     * Aprueba la reserva (estado 1) y avisa:
     *  - al cliente: correo de confirmación con el evento de calendario (.ics);
     *  - al dueño del vehículo: correo con los datos del trabajo y el evento de calendario.
     * El WhatsApp al dueño se envía desde la pantalla de la reserva (botón), con el enlace
     * que arma enlaceWhatsapp(). Si un correo falla, la reserva queda aprobada igual.
     */
    public function aprobar($idReserva)
    {
        // 1. Solo se aprueba una reserva que sigue pendiente
        $pendiente = $this->getReserva($idReserva);
        if (empty($pendiente) || $pendiente['estado'] != 0) {
            return array('msg' => 'La reserva no existe o ya fue respondida', 'icono' => 'warning');
        }
        // 1b. El vehículo y el cliente deben seguir activos (pudieron darse de baja
        //     después de que se hizo la solicitud). En ese caso se debe rechazar.
        $vehiculo = $this->getVehiculo($pendiente['id_vehiculo']);
        if (empty($vehiculo) || $vehiculo['estado'] == 0) {
            return array('msg' => 'El vehículo fue dado de baja: rechaza la reserva indicando el motivo', 'icono' => 'warning');
        }
        if (empty($this->getClienteActivo($pendiente['id_cliente']))) {
            return array('msg' => 'El cliente fue dado de baja: rechaza la reserva indicando el motivo', 'icono' => 'warning');
        }
        // 2. Cambiar el estado
        if ($this->actualizarEstado(1, $idReserva) != 1) {
            return array('msg' => 'Error al aprobar', 'icono' => 'error');
        }
        // 3. Notificar por correo y armar el resumen para el usuario
        $r = $this->getDetalleReserva($idReserva);
        $avisos = array();
        if (!empty($r['correo'])) {
            $avisos[] = $this->notificarCliente($r) ? 'correo al cliente enviado' : 'no se pudo enviar el correo al cliente';
        }
        if (!empty($r['prop_correo'])) {
            $avisos[] = $this->notificarPropietario($r) ? 'correo al dueño enviado' : 'no se pudo enviar el correo al dueño';
        } else {
            $avisos[] = 'el vehículo no tiene dueño asignado';
        }
        return array(
            'msg' => 'Solicitud aprobada (' . implode(', ', $avisos) . ')',
            'icono' => 'success',
            'whatsapp' => $this->enlaceWhatsapp($r)
        );
    }

    // Correo de confirmación al cliente, con el evento para su calendario
    private function notificarCliente($r)
    {
        $empresa = $this->getEmpresa();
        $cuerpo = '<p>Hola ' . htmlspecialchars($r['nombre']) . ',</p>'
            . '<p>Tu reserva fue <b>aprobada</b>. Estos son los datos:</p>'
            . $this->tablaDetalle($r, false)
            . '<p>Adjuntamos el evento para que lo agregues a tu calendario.</p>';
        $ics = $this->generarIcs($r, 'Reserva ' . $r['tipo'] . ' ' . $r['placa'] . ' - ' . $empresa['nombre']);
        return Correo::enviar($r['correo'], $r['nombre'], 'Reserva aprobada', $cuerpo,
            $empresa['nombre'], $empresa['correo'], $empresa['nombre'],
            array(array('contenido' => $ics, 'nombre' => 'reserva-' . $r['id'] . '.ics', 'tipo' => 'text/calendar; charset=utf-8; method=PUBLISH')));
    }

    // Correo al dueño del vehículo con los datos del trabajo y el evento de calendario
    private function notificarPropietario($r)
    {
        $empresa = $this->getEmpresa();
        $cuerpo = '<p>Hola ' . htmlspecialchars($r['prop_nombre']) . ',</p>'
            . '<p>Se aprobó una reserva de tu vehículo <b>' . htmlspecialchars($r['placa']) . '</b>:</p>'
            . $this->tablaDetalle($r, true)
            . '<p>Adjuntamos el evento para tu calendario.</p>';
        $ics = $this->generarIcs($r, 'Trabajo ' . $r['placa'] . ' - ' . $r['nombre']);
        return Correo::enviar($r['prop_correo'], $r['prop_nombre'], 'Nueva reserva aprobada - ' . $r['placa'], $cuerpo,
            $empresa['nombre'], $empresa['correo'], $empresa['nombre'],
            array(array('contenido' => $ics, 'nombre' => 'reserva-' . $r['id'] . '.ics', 'tipo' => 'text/calendar; charset=utf-8; method=PUBLISH')));
    }

    // Tabla HTML con el detalle de la reserva. Al dueño se le incluyen los datos de contacto del cliente.
    private function tablaDetalle($r, $paraPropietario)
    {
        $filas = array(
            'Vehículo'  => $r['tipo'] . ' ' . $r['marca'] . ' - ' . $r['placa'],
            'Fecha'     => date('d/m/Y', strtotime($r['f_inicio'])),
            'Hora'      => date('H:i', strtotime($r['f_inicio'])),
            'Duración'  => $this->textoDuracion($r['cantidad'], $r['tipo_precio']) . ' (hasta ' . date('d/m/Y H:i', strtotime($r['f_fin'])) . ')',
            'Punto de trabajo' => $r['direccion_trabajo'],
            'Detalle'   => $r['observacion']
        );
        if ($paraPropietario) {
            $filas['Cliente'] = $r['nombre'] . ' - Tel: ' . $r['codphone'] . ' ' . $r['telefono'];
        }
        $html = '<table cellpadding="6" style="border-collapse:collapse">';
        foreach ($filas as $titulo => $valor) {
            $html .= '<tr><td style="border:1px solid #ddd"><b>' . $titulo . '</b></td>'
                . '<td style="border:1px solid #ddd">' . nl2br(htmlspecialchars((string) $valor)) . '</td></tr>';
        }
        return $html . '</table>';
    }

    // "3 días", "1 hora", "2 meses"
    public function textoDuracion($cantidad, $tipoPrecio)
    {
        $unidades = array(1 => array('hora', 'horas'), 2 => array('día', 'días'), 3 => array('mes', 'meses'));
        $u = $unidades[$tipoPrecio];
        return $cantidad . ' ' . ($cantidad == 1 ? $u[0] : $u[1]);
    }

    /**
     * Evento de calendario en formato iCalendar (.ics), compatible con Google Calendar,
     * Outlook y el calendario del celular. Las horas se convierten a UTC (sufijo Z).
     * Incluye un recordatorio 1 hora antes.
     */
    public function generarIcs($r, $titulo)
    {
        $utc = function ($fecha) {
            $d = new DateTime($fecha, new DateTimeZone(date_default_timezone_get()));
            return $d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        };
        $descripcion = 'Vehículo: ' . $r['tipo'] . ' ' . $r['marca'] . ' - ' . $r['placa'] . "\n"
            . 'Duración: ' . $this->textoDuracion($r['cantidad'], $r['tipo_precio']) . "\n"
            . 'Cliente: ' . $r['nombre'] . ' - Tel: ' . $r['codphone'] . ' ' . $r['telefono'] . "\n"
            . 'Detalle: ' . $r['observacion'];
        $lineas = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Cooperativa Río Amazonas//Reservas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:reserva-' . $r['id'] . '@coop-rio-amazonas',
            'DTSTAMP:' . $utc('now'),
            'DTSTART:' . $utc($r['f_inicio']),
            'DTEND:' . $utc($r['f_fin']),
            'SUMMARY:' . $this->textoIcs($titulo),
            'DESCRIPTION:' . $this->textoIcs($descripcion),
            'LOCATION:' . $this->textoIcs((string) $r['direccion_trabajo']),
            'STATUS:CONFIRMED',
            'BEGIN:VALARM',
            'TRIGGER:-PT1H',
            'ACTION:DISPLAY',
            'DESCRIPTION:Recordatorio de reserva',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR'
        );
        // El formato exige líneas de máximo 75 bytes: las largas continúan con un espacio
        $salida = '';
        foreach ($lineas as $linea) {
            while (strlen($linea) > 75) {
                $parte = mb_strcut($linea, 0, 75, 'UTF-8');
                $salida .= $parte . "\r\n ";
                $linea = substr($linea, strlen($parte));
            }
            $salida .= $linea . "\r\n";
        }
        return $salida;
    }

    // Escapa los caracteres especiales del formato iCalendar
    private function textoIcs($texto)
    {
        return str_replace(array('\\', ';', ',', "\r\n", "\n"), array('\\\\', '\;', '\,', '\n', '\n'), $texto);
    }

    /**
     * Enlace "click to chat" de WhatsApp al celular del dueño, con el mensaje ya escrito.
     * Devuelve '' si el vehículo no tiene dueño o el dueño no tiene celular.
     */
    public function enlaceWhatsapp($r)
    {
        if (empty($r['prop_celular'])) {
            return '';
        }
        $numero = $this->numeroInternacional($r['prop_celular']);
        $mensaje = "*Reserva aprobada*\n"
            . 'Vehículo: ' . $r['tipo'] . ' ' . $r['marca'] . ' - ' . $r['placa'] . "\n"
            . 'Fecha: ' . date('d/m/Y', strtotime($r['f_inicio'])) . "\n"
            . 'Hora: ' . date('H:i', strtotime($r['f_inicio'])) . "\n"
            . 'Duración: ' . $this->textoDuracion($r['cantidad'], $r['tipo_precio'])
            . ' (hasta ' . date('d/m/Y H:i', strtotime($r['f_fin'])) . ")\n"
            . 'Punto de trabajo: ' . $r['direccion_trabajo'] . "\n"
            . 'Cliente: ' . $r['nombre'] . ' - Tel: ' . trim($r['codphone'] . ' ' . $r['telefono']) . "\n"
            . 'Detalle: ' . $r['observacion'];
        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje);
    }

    // WhatsApp necesita el número con código de país (Ecuador 593) y sin el 0 inicial
    private function numeroInternacional($celular)
    {
        $numero = preg_replace('/\D/', '', $celular);
        if (strlen($numero) == 10 && $numero[0] == '0') {
            return '593' . substr($numero, 1);      // 0987654321 -> 593987654321
        }
        if (strlen($numero) == 9) {
            return '593' . $numero;                 // 987654321  -> 593987654321
        }
        return $numero;                             // ya viene con código de país
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

    public function getClienteActivo($id)
    {
        return $this->select("SELECT id FROM clientes WHERE id = ? AND estado = 1", array($id));
    }

    public function getVehiculo($id)
    {
        $sql = "SELECT v.*, m.marca, t.tipo FROM vehiculos v INNER JOIN marcas m ON v.id_marca = m.id INNER JOIN tipos t ON v.id_tipo = t.id WHERE v.id = ?";
        return $this->select($sql, array($id));
    }

    public function registrarReserva($fecha, $fecha_devolucion, $cantidad, $tipo, $monto, $fecha_reserva, $observacion, $direccion, $id_veh, $id_cli)
    {
        $sql = "INSERT INTO reservas(f_inicio, f_fin, cantidad, tipo_precio, monto, f_reserva, observacion, direccion_trabajo, id_vehiculo, id_cliente) VALUES (?,?,?,?,?,?,?,?,?,?)";
        $datos = array($fecha, $fecha_devolucion, $cantidad, $tipo, $monto, $fecha_reserva, $observacion, $direccion, $id_veh, $id_cli);
        $data = $this->insertar($sql, $datos);
        return ($data > 0) ? $data : 0;
    }

    // Todas las reservas pendientes (estado 0), de la más reciente a la más antigua.
    // Antes tenía LIMIT 5: con más de 5 pendientes, las demás no aparecían en ningún lado.
    // La campana muestra el total y lista las 5 primeras (Templates/header.php).
    public function getNuevasReservas()
    {
        $sql = "SELECT r.id, r.f_reserva, c.nombre FROM reservas r INNER JOIN clientes c ON r.id_cliente = c.id WHERE r.estado = 0 ORDER BY r.id DESC";
        return $this->selectAll($sql);
    }

    public function getReserva($id)
    {
        $sql = "SELECT r.*, c.nombre, c.correo FROM reservas r INNER JOIN clientes c ON r.id_cliente = c.id WHERE r.id = ?";
        return $this->select($sql, array($id));
    }

    // Reserva con los datos del cliente, del vehículo y de su dueño (para la pantalla y los avisos)
    public function getDetalleReserva($id)
    {
        $sql = "SELECT r.*, c.nombre, c.correo, c.codphone, c.telefono,
                       v.placa, v.modelo, v.foto, t.tipo, m.marca,
                       p.nombre AS prop_nombre, p.correo AS prop_correo, p.celular AS prop_celular
                FROM reservas r
                INNER JOIN clientes c ON r.id_cliente = c.id
                INNER JOIN vehiculos v ON r.id_vehiculo = v.id
                INNER JOIN tipos t ON v.id_tipo = t.id
                INNER JOIN marcas m ON v.id_marca = m.id
                LEFT JOIN propietarios p ON v.id_propietario = p.id
                WHERE r.id = ?";
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
