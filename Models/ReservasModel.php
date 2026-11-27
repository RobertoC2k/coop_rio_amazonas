<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

/**
 * Modelo de Reservas (solicitud de turno).
 * Una reserva es la solicitud que hace un cliente para usar un vehículo
 * durante un periodo (f_inicio a f_fin). El administrador la aprueba.
 * Estados: 0 = Pendiente, 1 = Aprobada.
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
     * Se revisan las reservas del vehículo y los servicios que siguen en curso.
     * Devuelve el primer cruce encontrado o false si el vehículo está libre.
     */
    public function buscarCruceDeFechas($desde, $hasta, $id_veh)
    {
        $sql = "SELECT f_inicio AS desde, f_fin AS hasta FROM reservas
                WHERE id_vehiculo = ? AND f_inicio < ? AND f_fin > ?";
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
     * Color: rojo = pendiente, verde = aprobada.
     */
    public function getEventosCalendario($id_vehiculo, $tipoUsuario, $idUsuario)
    {
        $data = $this->getReservas($id_vehiculo);
        for ($i = 0; $i < count($data); $i++) {
            if ($tipoUsuario == 1 || $data[$i]['id_cliente'] == $idUsuario) {
                $data[$i]['title'] = $data[$i]['nombre'];
            } else {
                $data[$i]['title'] = 'RESERVADO';
            }
            $data[$i]['start'] = $data[$i]['f_inicio'];
            $data[$i]['end'] = $data[$i]['f_fin'];
            $data[$i]['color'] = ($data[$i]['estado'] == 0) ? '#dc3545' : '#198754';
        }
        return $data;
    }

    /**
     * Aprueba la reserva (estado 1) y, si el cliente tiene correo, le avisa.
     */
    public function aprobar($idReserva)
    {
        if (empty($idReserva)) {
            return array('msg' => 'Reserva no válida', 'icono' => 'warning');
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
        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host       = HOST_SMTP;
            $mail->SMTPAuth   = true;
            $mail->Username   = USER_SMTP;
            $mail->Password   = CLAVE_SMTP;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = PUERTO_SMTP;
            $mail->setFrom($empresa['correo'], $empresa['nombre']);
            $mail->addAddress($reserva['correo'], $reserva['nombre']);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'Solicitud de reserva';
            $mail->Body    = 'Confirmamos que tu solicitud de reserva del ' . $reserva['f_inicio']
                . ' al ' . $reserva['f_fin'] . ' ha sido aprobada.';
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
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

    public function getEmpresa()
    {
        $sql = "SELECT * FROM configuracion";
        return $this->select($sql);
    }
}
